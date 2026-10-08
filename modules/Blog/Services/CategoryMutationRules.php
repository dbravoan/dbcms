<?php

declare(strict_types=1);

namespace Modules\Blog\Services;

final class CategoryMutationRules
{
    /** @return array<string,mixed> */
    public static function all(): array
    {
        return [
            'parent_id' => 'bail|nullable|integer|min:1|exists:categories,id',
            'is_active' => 'sometimes|boolean',
            'translations' => 'sometimes|array', 'translations.*' => 'array',
            'translations.*.name' => 'nullable|string|max:255',
            'translations.*.slug' => 'nullable|string|max:255',
            'translations.*.description' => 'nullable|string|max:65535',
            'market_ids' => 'sometimes|array',
            'market_ids.*' => 'bail|integer|distinct|exists:markets,id',
        ];
    }
}
