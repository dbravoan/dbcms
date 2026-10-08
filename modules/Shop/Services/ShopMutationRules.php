<?php

declare(strict_types=1);

namespace Modules\Shop\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

final class ShopMutationRules
{
    /** @return array<string,mixed> */
    public static function forEntity(bool $product, ?Model $entity = null): array
    {
        $rules = [
            'translations' => 'sometimes|array', 'translations.*' => 'array',
            'translations.*.name' => 'nullable|string|max:255', 'translations.*.title' => 'nullable|string|max:255',
            'translations.*.slug' => 'nullable|string|max:255', 'translations.*.description' => 'nullable|string|max:65535',
            'is_active' => 'sometimes|boolean', 'market_ids' => 'sometimes|array',
            'market_ids.*' => 'integer|distinct|exists:markets,id',
        ];
        if (! $product) {
            return $rules + [
                'parent_id' => 'nullable|integer|exists:product_categories,id', 'icon' => 'nullable|string|max:50',
                'image' => 'nullable|string|max:2048', 'order' => 'sometimes|integer',
            ];
        }

        return $rules + [
            'sku' => ['sometimes', 'required', 'string', 'max:64', Rule::unique('products', 'sku')->ignore($entity?->getKey())],
            'price' => 'sometimes|required|numeric|min:0', 'compare_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0', 'stock_quantity' => 'sometimes|required|integer|min:0',
            'track_stock' => 'sometimes|boolean', 'is_featured' => 'sometimes|boolean', 'is_digital' => 'sometimes|boolean',
            'category_id' => 'nullable|integer|exists:product_categories,id', 'featured_image' => 'nullable|string|max:2048',
            'gallery_images' => 'nullable|array', 'gallery_images.*' => 'string|max:2048', 'specs_json' => 'nullable|array',
            'market_prices' => 'sometimes|array', 'market_prices.*' => 'nullable|numeric|min:0',
            'translations.*.summary' => 'nullable|string|max:65535', 'translations.*.meta_title' => 'nullable|string|max:255',
            'translations.*.meta_description' => 'nullable|string|max:65535', 'translations.*.schema_type' => 'nullable|string|max:100',
            'sections_json' => 'nullable',
        ];
    }
}
