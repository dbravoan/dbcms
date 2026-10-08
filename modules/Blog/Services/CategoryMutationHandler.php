<?php

declare(strict_types=1);

namespace Modules\Blog\Services;

use App\Core\Content\Contracts\EntityMutationHandlerInterface;
use App\Core\Traits\NormalizesCategoryTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\CategoryTranslation;

final class CategoryMutationHandler implements EntityMutationHandlerInterface
{
    use NormalizesCategoryTranslations;

    public function prepare(Model $entity, array $args, bool $creating): array
    {
        $attributes = (array) ($args['attributes'] ?? []);
        $input = array_replace($attributes, array_intersect_key($args, array_flip(['translations', 'market_ids'])));
        Validator::make($input, CategoryMutationRules::all())->validate();
        if (isset($attributes['parent_id'])) {
            $parent = Category::find((int) $attributes['parent_id']);
            $visited = [];
            while ($parent !== null) {
                if ($parent->getKey() === $entity->getKey() || isset($visited[$parent->getKey()])) {
                    throw ValidationException::withMessages(['parent_id' => 'A category cannot contain itself or its descendants.']);
                }
                $visited[$parent->getKey()] = true;
                $parent = $parent->parent;
            }
        }
        if ($creating) {
            $attributes += ['is_active' => false];
        }
        $translations = (array) ($args['translations'] ?? []);
        $inputTranslations = $translations;
        if (! $creating && $entity instanceof Category && array_key_exists('parent_id', $attributes) && $translations === []) {
            $translations = $entity->translations()->get()->mapWithKeys(static fn (CategoryTranslation $row): array => [$row->locale => $row->only(['name', 'slug', 'description'])])->all();
        }
        if (! $creating && $entity instanceof Category) {
            foreach ($translations as $locale => $values) {
                $existing = $entity->translations()->where('locale', $locale)->first();
                if (is_array($values) && $existing !== null) {
                    $translations[$locale] = $values + $existing->only(['name', 'slug', 'description']);
                } elseif (is_array($values) && empty($values['name'])) {
                    throw ValidationException::withMessages(["translations.{$locale}.name" => 'A new translation needs a category name.']);
                }
            }
        }
        if ($creating || $translations !== []) {
            $translations = $this->normalizeCategoryTranslations(['translations' => $translations]);
        }
        foreach ($translations as $locale => &$values) {
            $base = $values['slug'];
            $parentId = array_key_exists('parent_id', $attributes) ? $attributes['parent_id'] : $entity->getAttribute('parent_id');
            $siblings = Category::query()->where('parent_id', $parentId);
            if ($entity->exists) {
                $siblings->whereKeyNot($entity->getKey());
            }
            $suffix = 2;
            while (CategoryTranslation::query()->whereIn('category_id', $siblings->select('id'))->where('locale', $locale)->where('slug', $values['slug'])->exists()) {
                if (! $creating || ! empty($inputTranslations[$locale]['slug'])) {
                    throw ValidationException::withMessages(["translations.{$locale}.slug" => 'A sibling category already uses this slug.']);
                }
                $values['slug'] = $base.'-'.$suffix++;
            }
        }
        unset($values);

        return array_replace($args, ['attributes' => $attributes, 'translations' => $translations]);
    }
}
