<?php

declare(strict_types=1);

namespace Modules\ContentAudit\Services;

use App\Core\Content\Contracts\EntitySchemaRegistryInterface;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\ContentAudit\Enums\AuditAction;

final class AuditEntityPresentation
{
    public function slug(Model $entity): string
    {
        foreach (app(EntitySchemaRegistryInterface::class)->all() as $schema) {
            if ($entity instanceof $schema->model) {
                return Str::plural($schema->type);
            }
        }

        return Str::plural(Str::snake(class_basename($entity)));
    }

    public function find(string $type, int|string $id): ?Model
    {
        $alias = strtolower($type);
        foreach (app(EntitySchemaRegistryInterface::class)->all() as $schema) {
            if (! in_array($alias, [$schema->type, Str::plural($schema->type), strtolower($schema->model)], true)) {
                continue;
            }
            /** @var class-string<Model> $class */
            $class = $schema->model;
            $model = new $class;
            $query = $model->newQuery();
            if (method_exists($model, 'translations')) {
                $query->with('translations');
            }

            return $query->find($id);
        }

        return null;
    }

    /** @return array{title:string,slug:string|null,is_published:bool,translations:Collection<int,array{locale:string,title:string,slug:string,summary:string,content:string,meta_title:string,meta_description:string}>} */
    public function describe(Model $entity): array
    {
        $translations = collect($this->translationAttributes($entity))->values();

        $attributes = $entity->getAttributes();
        $locale = app()->getLocale();
        $primary = $translations->firstWhere('locale', $locale) ?? $translations->first();

        return [
            'title' => (string) ($primary['title'] ?? ''),
            'slug' => $primary['slug'] ?? null,
            'is_published' => (bool) ($attributes['is_published'] ?? $attributes['is_active'] ?? false),
            'translations' => $translations,
        ];
    }

    /**
     * Normalize an entity's translations from raw attributes (strict-mode safe).
     *
     * @return array<string, array{locale: string, title: string, slug: string, summary: string, content: string, meta_title: string, meta_description: string}>
     */
    protected function translationAttributes(Model $entity): array
    {
        if (! method_exists($entity, 'translations')) {
            return [];
        }

        $relation = $entity->relationLoaded('translations')
            ? $entity->getRelationValue('translations')
            : $entity->translations()->get();

        if (! $relation instanceof Collection) {
            return [];
        }

        $translations = [];
        foreach ($relation as $translation) {
            if (! $translation instanceof Model) {
                continue;
            }

            $attributes = $translation->getAttributes();
            $locale = (string) ($attributes['locale'] ?? 'es');

            $translations[$locale] = [
                'locale' => $locale,
                'title' => (string) ($attributes['title'] ?? $attributes['name'] ?? ''),
                'slug' => (string) ($attributes['slug'] ?? ''),
                'summary' => (string) ($attributes['summary'] ?? $attributes['description'] ?? ''),
                'content' => (string) ($attributes['content'] ?? ''),
                'meta_title' => (string) ($attributes['meta_title'] ?? ''),
                'meta_description' => (string) ($attributes['meta_description'] ?? ''),
            ];
        }

        return $translations;
    }

    public function permission(Model $entity): string
    {
        foreach (app(EntitySchemaRegistryInterface::class)->all() as $schema) {
            if ($entity instanceof $schema->model) {
                return $schema->permission ?? 'access:admin';
            }
        }

        return 'access:admin';
    }

    public function title(Model $entity): string
    {
        $translations = $this->translationAttributes($entity);

        if ($translations !== []) {
            $translation = $translations['es'] ?? reset($translations);
            if ($translation['title'] !== '') {
                return $translation['title'];
            }
        }

        $attributes = $entity->getAttributes();

        return (string) ($attributes['title'] ?? $attributes['name'] ?? "Entidad #{$entity->getKey()}");
    }

    public function actionSummary(string $action, ?User $user, string $title): string
    {
        $userName = $user ? $user->name : 'Sistema';

        return match ($action) {
            AuditAction::CREATED->value => "{$userName} creó el contenido inicial '{$title}'.",
            AuditAction::UPDATED->value => "{$userName} modificó el contenido de '{$title}'.",
            AuditAction::SUBMITTED->value => "{$userName} envió '{$title}' a la cola de auditoría de calidad.",
            AuditAction::APPROVED->value => "{$userName} aprobó y autorizó la publicación de '{$title}'.",
            AuditAction::REJECTED->value => "{$userName} rechazó '{$title}' solicitando subsanaciones.",
            AuditAction::PUBLISHED->value => "{$userName} publicó directamente '{$title}'.",
            AuditAction::UNPUBLISHED->value => "{$userName} despublicó '{$title}'.",
            AuditAction::DELETED->value => "{$userName} eliminó el contenido '{$title}'. Su última versión queda archivada.",
            AuditAction::ROLLBACK->value => "{$userName} revirtió '{$title}' a una versión histórica.",
            default => "{$userName} ejecutó la acción [{$action}] sobre '{$title}'.",
        };
    }
}
