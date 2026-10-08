<?php

declare(strict_types=1);

namespace App\Services\Capabilities;

use App\Contracts\Sectionable;
use App\Contracts\Services\LocalizationServiceInterface;
use App\Core\Content\Contracts\EditorialWorkflowInterface;
use App\Core\Content\Contracts\EntityMutationHandlerInterface;
use App\Core\Content\Contracts\EntityRelationMutationHandlerInterface;
use App\Core\Content\Contracts\EntityRelationPayloadHandlerInterface;
use App\Core\Content\Contracts\EntitySchemaRegistryInterface;
use App\Core\Content\Contracts\TranslatableEntity;
use App\Core\Content\EntitySchema;
use App\Core\Search\SearchIndexer;
use App\Core\Sections\SectionSyncService;
use App\Models\Market;
use App\Models\User;
use App\Services\Cms\PageCacheService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Full CRUD over every entity a module registered.
 *
 * The modules already declare their entities (type, model, semantic field roles,
 * permission, URL prefix) in the {@see EntitySchemaRegistryInterface}; this
 * service turns that declaration into a working API, so an agent can manage
 * posts, pages, products, categories, galleries and forms — today and for any
 * module installed later — without a tool per entity.
 *
 * It saves through Eloquent, so every rule of the backoffice still applies:
 * search reindexing, the content-audit workflow (review/approval observers) and
 * the same permissions.
 */
class EntityCrudService
{
    public function __construct(
        private readonly EntitySchemaRegistryInterface $schemas,
        private readonly LocalizationServiceInterface $localization,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function types(): array
    {
        $types = [];

        foreach ($this->schemas->all() as $schema) {
            $types[] = $this->describe($schema);
        }

        return $types;
    }

    /**
     * @return array<string, mixed>
     */
    public function describe(EntitySchema $schema): array
    {
        return [
            'type' => $schema->type,
            'label' => $schema->label,
            'model' => class_basename($schema->model),
            'permission' => $schema->permission ?? 'access:admin',
            'sectionable' => $schema->sectionable,
            'searchable' => $schema->searchable,
            'translatable' => is_subclass_of($schema->model, TranslatableEntity::class),
            'roles' => $schema->fieldRoles(),
            'attributes' => $this->fillableAttributes($schema),
            'translation_attributes' => array_values($this->translationAttributes($schema)),
            'description' => $schema->description,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function listing(string $type, array $filters = []): array
    {
        $schema = $this->schemaFor($type);
        $model = $this->newModel($schema);

        $perPage = max(1, min(100, (int) ($filters['per_page'] ?? 20)));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $locale = $this->stringOrNull($filters['locale'] ?? null);
        $search = $this->stringOrNull($filters['search'] ?? null);
        $status = $this->stringOrNull($filters['status'] ?? null);

        /** @var Builder<Model> $query */
        $query = $model->newQuery();

        if ($model instanceof TranslatableEntity) {
            $query->with('translations');
        }

        if ($locale !== null && $model instanceof TranslatableEntity) {
            $query->whereHas('translations', static fn (Builder $q): Builder => $q->where('locale', $locale));
        }

        if ($search !== null && $model instanceof TranslatableEntity) {
            $needle = '%'.$search.'%';
            $columns = array_values($this->translationAttributes($schema));

            $query->whereHas('translations', static function (Builder $q) use ($needle, $columns): void {
                $q->where(function (Builder $inner) use ($needle, $columns): void {
                    foreach ($columns as $column) {
                        $inner->orWhere($column, 'like', $needle);
                    }
                });
            });
        }

        if ($status !== null) {
            $this->applyStatusFilter($query, $status);
        }

        $column = $this->listableColumn($model, $filters['order_by'] ?? null);
        $direction = strtolower((string) ($filters['order'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        $total = (clone $query)->count();

        /** @var Collection<int, Model> $rows */
        $rows = $query
            ->orderBy($column, $direction)
            ->forPage($page, $perPage)
            ->get();

        return [
            'type' => $type,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'items' => $rows->map(fn (Model $entity): array => $this->payload($schema, $entity))->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function find(string $type, int|string $id): array
    {
        $schema = $this->schemaFor($type);

        return $this->payload($schema, $this->resolve($schema, $id));
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function create(string $type, array $args, ?User $actor = null): array
    {
        $schema = $this->schemaFor($type);

        return DB::transaction(function () use ($schema, $args, $actor): array {
            $entity = $this->newModel($schema);
            $args = $this->prepareMutation($schema, $entity, $args, true);
            $fillable = $this->fillableAttributes($schema);

            if (in_array('user_id', $fillable, true) && ! $actor instanceof User) {
                throw ValidationException::withMessages([
                    'type' => "Creating a [{$schema->type}] needs an author: use a user-backed token (a personal access token), not a machine client.",
                ]);
            }

            $defaults = array_map(static fn (mixed $value): mixed => $value instanceof \Closure ? $value() : $value, $schema->creationDefaults);
            $attributes = $this->arrayValue($args['attributes'] ?? []) + $defaults;
            if (array_key_exists('publish', $args)) {
                $attributes['publish'] = $args['publish'];
            }
            foreach (['is_published', 'is_active'] as $flag) {
                if (in_array($flag, $fillable, true) && ! array_key_exists($flag, $attributes)) {
                    $attributes[$flag] = false;
                }
            }
            if (in_array('slug', $fillable, true) && empty($attributes['slug'])) {
                $attributes['slug'] = Str::slug((string) ($attributes['name'] ?? $attributes['title'] ?? $schema->type)).'-'.Str::lower(Str::random(8));
            }
            $attributes = $this->prepareAttributes($schema, $attributes, $actor, creating: true);

            $entity->fill($attributes);
            $entity->save();

            $this->syncTranslations($schema, $entity, $this->arrayValue($args['translations'] ?? []), creating: true);
            $this->syncRelations($schema, $entity, $args);
            $entity->refresh();
            $editor = $actor ?? Auth::user();
            if ($editor instanceof User) {
                app(EditorialWorkflowInterface::class)->completeMutation($entity, $editor, 0, is_string($args['audit_action'] ?? null) ? $args['audit_action'] : null, is_string($args['audit_notes'] ?? null) ? $args['audit_notes'] : null);
            }

            return $this->payload($schema, $entity);
        });
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function update(string $type, int|string $id, array $args): array
    {
        $schema = $this->schemaFor($type);

        return DB::transaction(function () use ($schema, $id, $args): array {
            $entity = $this->resolve($schema, $id, lock: true);
            $previousRevisionId = Auth::user() instanceof User ? app(EditorialWorkflowInterface::class)->latestRevisionId($entity) : 0;

            $input = $this->arrayValue($args['attributes'] ?? []);
            if (array_key_exists('publish', $args)) {
                $input['publish'] = $args['publish'];
            }
            $attributes = $this->prepareAttributes($schema, $input, null, creating: false);

            $flags = $entity->getAttributes();
            $published = (bool) ($flags['is_published'] ?? $flags['is_active'] ?? false);
            $actor = Auth::user();
            $audit = app(EditorialWorkflowInterface::class);
            $publishing = ($attributes['is_published'] ?? $attributes['is_active'] ?? false) === true;
            if ($audit->isEnabled() && $published && (! $publishing || $audit->requiresAudit($entity, $actor))) {
                abort_unless($actor instanceof User, 403);
                $snapshots = app(ContentSnapshotService::class);
                $live = $snapshots->capture($entity);

                return Model::withoutEvents(function () use ($schema, $entity, $attributes, $args, $actor, $audit, $snapshots, $live): array {
                    $previous = $audit->pendingDraft($entity);
                    $visibilityIntent = null;
                    if ($previous !== null) {
                        abort_unless(($previous->snapshot['_live_hash'] ?? null) === $snapshots->hash($live), 409, 'The live content changed after this draft was created.');
                        $snapshots->restore($entity, $previous->snapshot);
                        $visibilityIntent = $previous->snapshot['_visibility_intent'] ?? null;
                    }
                    $args = $this->prepareMutation($schema, $entity, $args, false);
                    $input = $this->arrayValue($args['attributes'] ?? []);
                    if (array_key_exists('publish', $args)) {
                        $input['publish'] = $args['publish'];
                    }
                    $attributes = $this->prepareAttributes($schema, $input, null, creating: false);
                    $entity->fill($attributes)->save();
                    $this->syncTranslations($schema, $entity, $this->arrayValue($args['translations'] ?? []), creating: false);
                    $this->syncRelations($schema, $entity, $args);
                    $entity->refresh();
                    $pending = $snapshots->capture($entity);
                    foreach (['is_published', 'is_active'] as $flag) {
                        if (array_key_exists($flag, $attributes)) {
                            $visibilityIntent = ['field' => $flag, 'value' => $attributes[$flag]];
                            break;
                        }
                    }
                    if ($visibilityIntent !== null) {
                        $pending['_visibility_intent'] = $visibilityIntent;
                    }
                    $draft = $this->payload($schema, $entity);
                    $record = $audit->stageDraft($entity, $actor, $pending, $snapshots->hash($live), ($args['audit_action'] ?? null) === 'submit_for_review' || ($audit->requiresAudit($entity, $actor) && ($args['audit_action'] ?? null) !== 'draft'), is_string($args['audit_notes'] ?? null) ? $args['audit_notes'] : null);
                    $snapshots->restore($entity, $live);
                    $draft['status'] = 'draft';
                    $draft['live_preserved'] = true;
                    $draft['pending_revision_id'] = $record->id;

                    return $draft;
                });
            }
            if ($audit->isEnabled() && $publishing && $audit->pendingDraft($entity) !== null) {
                abort_unless($actor instanceof User, 403);
                $audit->approve($entity, $actor, publish: true);
            }

            $args = $this->prepareMutation($schema, $entity, $args, false);
            $input = $this->arrayValue($args['attributes'] ?? []);
            if (array_key_exists('publish', $args)) {
                $input['publish'] = $args['publish'];
            }
            $attributes = $this->prepareAttributes($schema, $input, null, creating: false);

            if ($attributes !== []) {
                $entity->fill($attributes);
                $entity->save();
            }

            $this->syncTranslations($schema, $entity, $this->arrayValue($args['translations'] ?? []), creating: false);
            $this->syncRelations($schema, $entity, $args);
            $entity->refresh();
            $editor = $actor ?? Auth::user();
            if ($editor instanceof User) {
                app(EditorialWorkflowInterface::class)->completeMutation($entity, $editor, $previousRevisionId, is_string($args['audit_action'] ?? null) ? $args['audit_action'] : null, is_string($args['audit_notes'] ?? null) ? $args['audit_notes'] : null);
            }

            return $this->payload($schema, $entity);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function delete(string $type, int|string $id): array
    {
        $schema = $this->schemaFor($type);

        return DB::transaction(function () use ($schema, $id): array {
            $entity = $this->resolve($schema, $id, lock: true);
            $snapshot = $this->payload($schema, $entity);
            $actor = Auth::user();
            if ($actor instanceof User) {
                app(EditorialWorkflowInterface::class)->recordDeletion($entity, $actor);
            }

            $entity->delete();

            return [
                'deleted' => true,
                'type' => $schema->type,
                'id' => $snapshot['id'],
                'label' => $snapshot['label'] ?? null,
            ];
        });
    }

    /**
     * Resolves the model instance of an entity type (used by the editorial and
     * block actions, which work on any sectionable entity).
     */
    public function resolveEntity(string $type, int|string $id): Model
    {
        return $this->resolve($this->schemaFor($type), $id);
    }

    public function schemaFor(string $type): EntitySchema
    {
        $schema = $this->schemas->get($type);

        if ($schema === null) {
            throw ValidationException::withMessages([
                'type' => "Unknown entity type [{$type}]. Available: ".implode(', ', $this->schemas->types()).'.',
            ]);
        }

        return $schema;
    }

    private function resolve(EntitySchema $schema, int|string $id, bool $lock = false): Model
    {
        $model = $this->newModel($schema);

        if ($model instanceof TranslatableEntity) {
            $model->load('translations');
        }

        $query = $model->newQuery()->whereKey($id);
        if ($lock) {
            $query->lockForUpdate();
        }
        $found = $query->first();

        if (! $found instanceof Model) {
            throw ValidationException::withMessages([
                'id' => "No [{$schema->type}] found with id [{$id}].",
            ]);
        }

        if ($found instanceof TranslatableEntity) {
            $found->load('translations');
        }

        return $found;
    }

    private function newModel(EntitySchema $schema): Model
    {
        /** @var class-string<Model> $class */
        $class = $schema->model;

        return new $class;
    }

    /**
     * @param  array<string,mixed>  $args
     * @return array<string,mixed>
     */
    private function prepareMutation(EntitySchema $schema, Model $entity, array $args, bool $creating): array
    {
        if ($schema->mutationHandler === null) {
            return $args;
        }
        $handler = app($schema->mutationHandler);
        if (! $handler instanceof EntityMutationHandlerInterface) {
            throw new \LogicException('Entity mutation handlers must implement the shared contract.');
        }

        return $handler->prepare($entity, $args, $creating);
    }

    /**
     * Model attributes an agent may write (the model's own fillable list).
     *
     * @return list<string>
     */
    private function fillableAttributes(EntitySchema $schema): array
    {
        return array_values(array_filter(
            $this->newModel($schema)->getFillable(),
            static fn (mixed $attribute): bool => is_string($attribute)
                && $attribute !== 'id',
        ));
    }

    /**
     * Semantic role → translation column, read from the declared form inputs
     * (`translations[{locale}][title]` → `title`).
     *
     * @return array<string, string>
     */
    private function translationAttributes(EntitySchema $schema): array
    {
        $attributes = [];

        foreach ($schema->fields as $role => $field) {
            $input = (string) ($field['input'] ?? '');

            if (preg_match('/translations\[\{locale\}\]\[([a-z0-9_]+)\]/i', $input, $matches) === 1) {
                $attributes[$role] = $matches[1];
            }
        }

        if ($schema->hasField(EntitySchema::ROLE_SLUG) && ! in_array('slug', $attributes, true)) {
            $attributes[EntitySchema::ROLE_SLUG] = 'slug';
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function prepareAttributes(EntitySchema $schema, array $attributes, ?User $actor, bool $creating): array
    {
        $allowed = $this->fillableAttributes($schema);
        $prepared = [];

        foreach ($attributes as $key => $value) {
            if (in_array($key, $allowed, true)) {
                if (in_array($key, ['is_published', 'is_active', 'is_featured'], true)) {
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    if ($value === null) {
                        throw ValidationException::withMessages([$key => 'Use a boolean value.']);
                    }
                }
                $prepared[$key] = $value;
            }
        }

        // `publish` is a convenience flag mapped onto whichever publishing
        // columns the entity actually uses.
        if (array_key_exists('publish', $attributes)) {
            $publish = filter_var($attributes['publish'], FILTER_VALIDATE_BOOLEAN);

            if (in_array('is_published', $allowed, true)) {
                $prepared['is_published'] = $publish;

                if ($publish && in_array('published_at', $allowed, true)) {
                    $model = $this->newModel($schema);
                    $prepared['published_at'] = $attributes['published_at'] ?? now();
                }
            }

            if (in_array('is_active', $allowed, true)) {
                $prepared['is_active'] = $publish;
            }

            if (in_array('is_featured', $allowed, true) && array_key_exists('is_featured', $attributes)) {
                $prepared['is_featured'] = filter_var($attributes['is_featured'], FILTER_VALIDATE_BOOLEAN);
            }
        }

        // Authorship: the acting agent replaces the backoffice session user.
        if ($creating && $actor instanceof User && in_array('user_id', $allowed, true)) {
            $prepared['user_id'] = $actor->getKey();
        } elseif (! $creating) {
            unset($prepared['user_id']);
        }

        return $prepared;
    }

    /** @param array<string,mixed> $args */
    private function syncRelations(EntitySchema $schema, Model $entity, array $args): void
    {
        $handler = $schema->mutationHandler !== null ? app($schema->mutationHandler) : null;
        if ($handler instanceof EntityRelationMutationHandlerInterface) {
            $handler->syncRelations($entity, $args);
        }
        if (! $handler instanceof EntityRelationMutationHandlerInterface && array_key_exists('market_ids', $args) && method_exists($entity, 'markets')) {
            $ids = array_values(array_unique(array_map('intval', $this->arrayValue($args['market_ids']))));
            if (Market::query()->whereIn('id', $ids)->count() !== count($ids)) {
                throw ValidationException::withMessages(['market_ids' => 'Select existing markets.']);
            }
            $relation = $entity->markets();
            if ($relation instanceof BelongsToMany) {
                $relation->sync($ids);
            }
        }
        if ($entity instanceof Sectionable && array_key_exists('sections_json', $args)) {
            $json = $args['sections_json'];
            if (is_array($json)) {
                $json = json_encode($json, JSON_THROW_ON_ERROR);
            }
            if (! is_string($json) || ! is_array(json_decode($json, true))) {
                throw ValidationException::withMessages(['sections_json' => 'Sections must be a JSON object or array.']);
            }
            $attributes = $entity->getAttributes();
            $published = (bool) ($attributes['is_published'] ?? $attributes['is_active'] ?? false);
            app(SectionSyncService::class)->sync($entity, $json, $published ? 'published' : 'draft');
        }
        app(SectionSyncService::class)->publish($entity);
        if (array_key_exists('market_ids', $args) || array_key_exists('market_prices', $args)) {
            $entity->touchQuietly();
            $table = $entity->getTable();
            DB::afterCommit(static function () use ($table, $schema, $entity): void {
                app(PageCacheService::class)->invalidateType($table);
                app(SearchIndexer::class)->indexEntity($schema->type, $entity->getKey());
            });
        }
    }

    /**
     * @param  array<string, mixed>  $translations  locale => attributes
     */
    private function syncTranslations(EntitySchema $schema, Model $entity, array $translations, bool $creating): void
    {
        if ($translations === [] || ! $entity instanceof TranslatableEntity) {
            return;
        }

        $columns = $this->translationAttributes($schema);
        $allowed = array_values($columns);

        foreach ($translations as $locale => $values) {
            if (! is_string($locale) || $locale === '' || ! is_array($values)) {
                continue;
            }

            /** @var Model|null $translation */
            $translation = $entity->translations()->where('locale', $locale)->first();

            $meaningful = array_intersect_key($values, array_flip(array_diff($allowed, ['schema_type'])));
            if ($translation === null && array_filter($meaningful, static fn (mixed $value): bool => is_scalar($value) && trim((string) $value) !== '') === []) {
                continue;
            }

            if ($translation === null) {
                if (! $creating) {
                    $translation = $entity->translations()->getRelated()->newInstance();
                    $translation->setAttribute('locale', $locale);
                } else {
                    /** @var Model $translation */
                    $translation = $entity->translations()->getRelated()->newInstance();
                    $translation->setAttribute('locale', $locale);
                }
            }

            $translationFillable = $translation->getFillable();

            foreach ($values as $key => $value) {
                if ($key === 'slug' || in_array($key, $allowed, true)) {
                    if (in_array($key, $translationFillable, true)) {
                        $translation->setAttribute($key, $value);
                    }
                }
            }

            // A readable slug is required by every routing surface.
            $slug = (string) $translation->getAttribute('slug');

            if ($slug === '') {
                $title = (string) ($translation->getAttribute('title')
                    ?? $translation->getAttribute('name')
                    ?? '');

                $base = Str::slug($title) ?: $schema->type;
                $translation->setAttribute('slug', $schema->uniqueSlugs ? $this->uniqueSlug($translation, $base) : $base);
            } elseif (! $creating && $schema->uniqueSlugs) {
                $translation->setAttribute('slug', $this->uniqueSlug($translation, $slug));
            }

            // Columns the table requires (NOT NULL without default) must be
            // satisfied even when the agent does not send them: an empty body
            // is valid content, a failed insert is not.
            foreach ($this->requiredStringColumns($translation) as $column) {
                if ($translation->getAttribute($column) === null) {
                    $translation->setAttribute($column, '');
                }
            }

            $entity->translations()->save($translation);
        }
    }

    /**
     * NOT NULL text columns of a translation table, resolved once per table.
     *
     * @return list<string>
     */
    private function requiredStringColumns(Model $translation): array
    {
        static $cache = [];

        $table = $translation->getTable();

        if (isset($cache[$table])) {
            return $cache[$table];
        }

        $columns = [];

        try {
            foreach (Schema::getColumns($table) as $column) {
                $type = (string) ($column['type_name'] ?? '');
                $isText = str_contains($type, 'char') || str_contains($type, 'text');

                if ($isText && ($column['nullable'] ?? true) === false && ($column['default'] ?? null) === null) {
                    $columns[] = (string) $column['name'];
                }
            }
        } catch (\Throwable) {
            $columns = [];
        }

        return $cache[$table] = $columns;
    }

    private function uniqueSlug(Model $translation, string $slug): string
    {
        $base = $slug !== '' ? $slug : 'item';
        $candidate = $base;
        $attempt = 1;

        while ($this->slugExists($translation, $candidate) && $attempt < 25) {
            $candidate = $base.'-'.(++$attempt);
        }

        return $candidate;
    }

    private function slugExists(Model $translation, string $slug): bool
    {
        $query = $translation->newQuery()
            ->where('slug', $slug)
            ->where('locale', $translation->getAttribute('locale'));

        $key = $translation->getKey();

        if ($key !== null) {
            $query->whereKeyNot($key);
        }

        return $query->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(EntitySchema $schema, Model $entity): array
    {
        $attributes = [];

        foreach ($this->fillableAttributes($schema) as $attribute) {
            $value = $entity->getAttribute($attribute);

            if ($value !== null) {
                $attributes[$attribute] = $value;
            }
        }

        $translations = [];

        if ($entity instanceof TranslatableEntity) {
            $entity->loadMissing('translations');

            /** @var Collection<int, Model> $rows */
            $rows = $entity->getRelation('translations');

            foreach ($rows as $translation) {
                $locale = (string) $translation->getAttribute('locale');
                $values = [];

                foreach ($translation->getFillable() as $attribute) {
                    $value = $translation->getAttribute($attribute);

                    if ($value !== null && $value !== '') {
                        $values[$attribute] = $value;
                    }
                }

                $translations[$locale] = $values;
            }
        }

        $urls = $this->urlsFor($schema, $translations);
        $handler = $schema->mutationHandler !== null ? app($schema->mutationHandler) : null;
        $relations = $handler instanceof EntityRelationPayloadHandlerInterface ? $handler->relationPayload($entity) : [];

        return [
            'id' => $entity->getKey(),
            'type' => $schema->type,
            'label' => $schema->label,
            'status' => $this->statusOf($entity),
            'attributes' => $attributes,
            'translations' => $translations,
            'relations' => $relations,
            'url' => $urls[(string) app()->getLocale()] ?? (reset($urls) ?: null),
            'urls' => $urls,
            'updated_at' => $entity->getAttribute('updated_at'),
        ];
    }

    private function statusOf(Model $entity): string
    {
        $attributes = $entity->getAttributes();
        foreach (['is_published', 'is_active'] as $flag) {
            if (array_key_exists($flag, $attributes)) {
                return filter_var($attributes[$flag], FILTER_VALIDATE_BOOLEAN) ? 'published' : 'draft';
            }
        }

        return 'active';
    }

    /**
     * Canonical URL of the entity in each locale it is translated into.
     *
     * @param  array<string, array<string, mixed>>  $translations
     * @return array<string, string>
     */
    private function urlsFor(EntitySchema $schema, array $translations): array
    {
        if ($schema->urlPrefix === null) {
            return [];
        }

        $urls = [];

        foreach ($translations as $locale => $values) {
            $slug = (string) ($values['slug'] ?? '');

            if ($slug === '') {
                continue;
            }

            $urls[(string) $locale] = $schema->urlPrefix === ''
                ? $this->localization->url('/'.$slug, null, $locale)
                : $this->localization->frontUrl($schema->urlPrefix, $slug, $locale);
        }

        return $urls;
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function applyStatusFilter(Builder $query, string $status): void
    {
        $published = in_array(strtolower($status), ['published', 'active'], true);

        foreach (['is_published', 'is_active'] as $flag) {
            if ($this->newModelClassHasColumn($query, $flag)) {
                $query->where($flag, $published);

                return;
            }
        }
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function newModelClassHasColumn(Builder $query, string $column): bool
    {
        $model = $query->getModel();
        $table = $model->getTable();

        return $model->getConnection()->getSchemaBuilder()->hasColumn($table, $column);
    }

    private function listableColumn(Model $model, mixed $requested): string
    {
        $allowed = ['id', 'created_at', 'updated_at', 'position', 'order', 'sort_order'];

        if (is_string($requested) && in_array($requested, array_merge($allowed, $model->getFillable()), true)) {
            return $requested;
        }

        return 'id';
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array<string, mixed>
     */
    private function arrayValue(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
