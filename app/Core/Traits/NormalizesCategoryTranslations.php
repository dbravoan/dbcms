<?php

declare(strict_types=1);

namespace App\Core\Traits;

use App\Contracts\Services\LocalizationServiceInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Normalizes category translation input from the canonical
 * `translations[locale][...]` payload.
 *
 * Shared by Blog and Shop category controllers to keep them cohesive without
 * coupling to each other.
 */
trait NormalizesCategoryTranslations
{
    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, array{name: string, slug: string, description: string|null}>
     */
    protected function normalizeCategoryTranslations(array $validated): array
    {
        $translations = [];
        $input = (array) ($validated['translations'] ?? []);

        foreach ($input as $locale => $data) {
            if (! is_array($data) || empty($data['name'])) {
                continue;
            }

            $name = (string) $data['name'];
            $translations[(string) $locale] = [
                'name' => $name,
                'slug' => ! empty($data['slug']) ? Str::slug((string) $data['slug']) : Str::slug($name),
                'description' => ! empty($data['description']) ? (string) $data['description'] : null,
            ];
        }

        if ($translations === []) {
            $defaultLocale = app(LocalizationServiceInterface::class)->defaultLocale();

            $translations[$defaultLocale] = [
                'name' => 'Nueva categoría',
                'slug' => 'nueva-categoria',
                'description' => null,
            ];
        }

        return $translations;
    }

    /**
     * @param  class-string<Model>  $translationClass
     * @param  array<string, array{name: string, slug: string, description: string|null}>  $translations
     */
    protected function syncCategoryTranslations(Model $category, array $translations, string $translationClass): void
    {
        foreach ($translations as $locale => $data) {
            $translationClass::updateOrCreate(
                ['category_id' => $category->getKey(), 'locale' => $locale],
                $data
            );
        }
    }
}
