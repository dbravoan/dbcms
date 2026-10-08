<?php

declare(strict_types=1);

namespace App\Core\Translations;

use App\Models\Language;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Single source of truth for persisting localized entity translations.
 *
 * Consolidates the near-identical "for each active locale upsert a
 * *Translation row" logic previously duplicated across the Pages, Blog, Shop
 * and Gallery backoffice controllers and domain services.
 */
class EntityTranslationSyncService
{
    /**
     * Upsert localized translations for an entity.
     *
     * @param  Model  $entity  The owning entity (Page, Post, Product, Gallery…).
     * @param  class-string<Model>  $translationModel  e.g. PageTranslation::class.
     * @param  string  $foreignKey  Translation column pointing back at the entity (e.g. 'page_id').
     * @param  array<string, array<string, mixed>>  $translations  locale => column values.
     * @param  string  $titleKey  Column that must be non-empty for the row to be persisted.
     */
    public function sync(
        Model $entity,
        string $translationModel,
        string $foreignKey,
        array $translations,
        string $titleKey = 'title'
    ): void {
        foreach ($translations as $locale => $fields) {
            if (! is_array($fields)) {
                continue;
            }

            $title = trim((string) ($fields[$titleKey] ?? ''));
            if ($title === '') {
                continue;
            }

            if (array_key_exists('slug', $fields)) {
                $slug = trim((string) ($fields['slug'] ?? ''));
                $fields['slug'] = Str::slug($slug !== '' ? $slug : $title);
            }

            $translationModel::updateOrCreate(
                [$foreignKey => $entity->getKey(), 'locale' => (string) $locale],
                $fields
            );
        }
    }

    /**
     * Active locale codes, ordered by id.
     *
     * @return list<string>
     */
    public function activeLocales(): array
    {
        return array_values(
            Language::query()
                ->where('is_active', true)
                ->orderBy('id')
                ->pluck('code')
                ->map(static fn ($code): string => (string) $code)
                ->all()
        );
    }
}
