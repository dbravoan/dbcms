<?php

declare(strict_types=1);

use Modules\Blog\Models\Category as BlogCategory;
use Modules\Blog\Models\Post;

return [
    'blog_categories' => [
        'permission' => 'manage:posts',
        'module' => 'blog',
        'model' => BlogCategory::class,
        'value' => ['id'],
        'allowed_values' => ['id', 'translation.slug'],
        'label' => ['translation.name'],
        'search' => ['translation.name', 'translation.slug'],
        'filters' => ['is_active' => true],
        'market_scoped' => true,
        'order' => 'id',
        'icon' => 'fa-solid fa-tags',
        'badge' => 'CATEGORY',
    ],
    'blog_posts' => [
        'permission' => 'manage:posts',
        'module' => 'blog',
        'model' => Post::class,
        'value' => ['id'],
        'label' => ['translation.title'],
        'search' => ['translation.title', 'translation.slug'],
        'scope' => 'published',
        'market_scoped' => true,
        'order' => 'id',
        'icon' => 'fa-solid fa-newspaper',
        'badge' => 'POST',
    ],
];
