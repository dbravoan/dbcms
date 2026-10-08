<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Services;

use App\Core\Content\EntitySchema;
use App\Models\Language;
use App\Models\Market;
use App\Models\User;
use App\Services\Capabilities\EntityCrudService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\AiAssistant\DTOs\MultimodalOrchestrationRequest;

final class AiDraftPersistenceService
{
    public function __construct(private readonly EntityCrudService $entities) {}

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function createElement(array $args, ?User $actor): array
    {
        $fields = (array) $args['fields'];
        $type = (string) $args['type'];
        $title = (string) ($fields['title'] ?? '');
        $fields['slug'] = Str::slug((string) ($fields['slug'] ?? $title));
        $fields['content'] = $fields['body'] ?? $fields['content'] ?? '';
        $request = new MultimodalOrchestrationRequest(topic: $title, entityType: $type, market: (string) ($args['market'] ?? 'es'), locale: (string) ($args['locale'] ?? 'es'), options: ['actor_id' => $actor?->getKey()]);
        /** @var list<array<string, mixed>> $blocks */
        $blocks = array_values(array_filter((array) ($args['sections'] ?? []), 'is_array'));
        $id = $this->create($type, $request, $fields, $blocks);
        $schema = $this->entities->schemaFor($type);

        return ['type' => $type, 'id' => $id, 'edit_url' => $schema->editView !== null && Route::has($schema->editView) ? route($schema->editView, $id) : null, 'sections' => count((array) ($args['sections'] ?? [])), 'message' => 'Borrador creado. Revísalo antes de publicar.'];
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  list<array<string, mixed>>  $blocks
     */
    public function create(string $type, MultimodalOrchestrationRequest $request, array $values, array $blocks): int
    {
        $actor = User::query()->find($request->options['actor_id'] ?? null);
        $schema = $this->entities->schemaFor($type);
        if (! $actor instanceof User || ! $actor->hasPermission('use:ai') || ! $actor->hasPermission($schema->permission ?? 'access:admin')) {
            throw new AuthorizationException('Draft creation requires an authorized CMS user.');
        }
        $market = Market::query()->where('code', $request->market)->first();
        if ($market === null || ! Language::query()->where('code', $request->locale)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['market' => 'Select an existing market and active language.']);
        }
        $description = $this->entities->describe($schema);
        if (! in_array('is_published', $description['attributes'], true) && ! in_array('is_active', $description['attributes'], true)) {
            throw ValidationException::withMessages(['entity_type' => 'This entity does not support publication drafts.']);
        }
        $translation = [];
        $attributes = [];
        foreach ($schema->fields as $role => $field) {
            if (preg_match('/translations\[\{locale\}\]\[([a-z0-9_]+)\]/i', $field['input'], $match) === 1) {
                $source = $role === EntitySchema::ROLE_BODY ? 'content' : $role;
                if (array_key_exists($source, $values)) {
                    $translation[$match[1]] = $values[$source];
                    if (in_array($match[1], $description['attributes'], true)) {
                        $attributes[$match[1]] = $values[$source];
                    }
                }
            } elseif (array_key_exists($role, $values) && in_array($field['input'], $description['attributes'], true)) {
                $attributes[$field['input']] = $values[$role];
            }
        }
        $translation['slug'] = $values['slug'];
        if (in_array('slug', $description['attributes'], true)) {
            $attributes['slug'] = $values['slug'];
        }

        $normalized = array_map(static fn (array $block): array => isset($block['data']) ? ['type' => $block['type']] + (array) $block['data'] : $block, $blocks);
        $payload = $this->entities->create($type, [
            'attributes' => ['publish' => false, 'featured_image' => $values['featured_image'] ?? null, 'category_id' => $request->targetCategoryId] + $attributes,
            'translations' => [$request->locale => $translation],
            'market_ids' => [$market->getKey()],
            'sections_json' => json_encode([$request->locale => $normalized], JSON_THROW_ON_ERROR),
        ], $actor);

        return (int) $payload['id'];
    }

    public function attachFeaturedImage(string $type, int $id, string $url, ?int $actorId): void
    {
        $actor = User::query()->find($actorId);
        $schema = $this->entities->schemaFor($type);
        if (! $actor instanceof User || ! $actor->hasPermission('use:ai') || ! $actor->hasPermission('manage:media') || ! $actor->hasPermission($schema->permission ?? 'access:admin')) {
            throw new AuthorizationException('Media attachment requires an authorized CMS user.');
        }
        $entity = $this->entities->resolveEntity($type, $id);
        $publicationField = in_array('is_published', $entity->getFillable(), true) ? 'is_published' : 'is_active';
        if ((bool) $entity->getAttribute($publicationField)) {
            throw new AuthorizationException('Attach media to a draft; changing published content requires confirmation.');
        }
        if (in_array('featured_image', $entity->getFillable(), true)) {
            $this->entities->update($type, $id, ['attributes' => ['featured_image' => $url]]);
        }
    }
}
