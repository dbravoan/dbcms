<?php

declare(strict_types=1);

use Modules\Shop\Models\Product;
use Modules\Shop\Models\ProductCategory;

return [
    'shop_categories' => [
        'permission' => 'manage:shop',
        'module' => 'shop',
        'model' => ProductCategory::class,
        'value' => ['id'],
        'allowed_values' => ['id', 'translation.slug'],
        'label' => ['translation.name'],
        'search' => ['translation.name', 'translation.slug'],
        'filters' => ['is_active' => true],
        'market_scoped' => true,
        'order' => 'order',
        'icon' => 'fa-solid fa-layer-group',
        'badge' => 'CATEGORY',
    ],
    'shop_products' => [
        'permission' => 'manage:shop',
        'module' => 'shop',
        'model' => Product::class,
        'value' => ['id'],
        'label' => ['translation.name'],
        'search' => ['translation.name', 'translation.slug'],
        'scope' => 'active',
        'market_scoped' => true,
        'order' => 'id',
        'icon' => 'fa-solid fa-bag-shopping',
        'badge' => 'PRODUCT',
    ],
];
