<?php

declare(strict_types=1);

namespace Modules\Shop\Services;

use App\Core\Content\Contracts\EntityMutationHandlerInterface;
use App\Core\Traits\NormalizesCategoryTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Shop\Models\ProductCategory;

final class ShopCategoryMutationHandler implements EntityMutationHandlerInterface
{
    use NormalizesCategoryTranslations;

    public function prepare(Model $entity, array $args, bool $creating): array
    {
        $attributes = (array) ($args['attributes'] ?? []);
        $input = array_replace($attributes, array_intersect_key($args, array_flip(['translations', 'market_ids'])));
        Validator::make($input, ShopMutationRules::forEntity(false, $entity))->validate();
        if (isset($attributes['parent_id'])) {
            $parent = ProductCategory::find((int) $attributes['parent_id']);
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
            $attributes += ['icon' => '🏷️', 'order' => 0, 'is_active' => false];
        }
        $translations = $args['translations'] ?? [];
        if (! $creating && $entity instanceof ProductCategory) {
            foreach ((array) $translations as $locale => $values) {
                $existing = $entity->translations()->where('locale', $locale)->first();
                if (is_array($values) && $existing !== null) {
                    $translations[$locale] = $values + $existing->only(['name', 'slug', 'description']);
                }
            }
        }
        if ($creating || $translations !== []) {
            $translations = $this->normalizeCategoryTranslations(['translations' => $translations]);
        }

        return array_replace($args, ['attributes' => $attributes, 'translations' => $translations]);
    }
}
