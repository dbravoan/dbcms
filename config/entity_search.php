<?php

declare(strict_types=1);

use App\Models\Language;
use App\Models\Market;
use App\Models\Role;
use App\Models\User;
use Modules\Blog\Models\Category as BlogCategory;
use Modules\Blog\Models\Post;
use Modules\Forms\Models\CustomForm;
use Modules\Gallery\Models\Gallery;
use Modules\Pages\Models\Page;
use Modules\Shop\Models\Product;
use Modules\Shop\Models\ProductCategory;

/**
 * Whitelist registry for the reusable AJAX searchable select.
 *
 * Never accept raw table names from the browser: only the keys declared here
 * can be queried. Add a source to make every `<x-admin.search-select>` able
 * to search it system-wide.
 *
 * Keys per source:
 *  - model          Eloquent model class.
 *  - value          Candidate attribute paths for the submitted value (first non-empty wins).
 *  - label          Candidate attribute paths for the visible label.
 *  - search         Attribute paths matched against the query (supports `translation.<field>`).
 *  - allowed_values Extra value paths a caller may request via `value-key` (security allow-list).
 *  - filters        Column => value conditions.
 *  - scope          Optional Eloquent scope name (e.g. `published`, `active`).
 *  - order          Order column (defaults to primary key).
 *  - icon / badge   UI metadata returned with every option.
 */
return [
    'per_page' => 20,
    'max_per_page' => 50,

    'sources' => [
        'markets' => [
            'permission' => 'access:admin',
            'model' => Market::class,
            'value' => ['code'],
            'allowed_values' => ['code', 'id'],
            'label' => ['name'],
            'search' => ['name', 'code'],
            'filters' => ['is_active' => true],
            'order' => 'name',
            'icon' => 'fa-solid fa-globe',
            'badge' => 'MARKET',
        ],

        'languages' => [
            'permission' => 'access:admin',
            'model' => Language::class,
            'value' => ['code'],
            'label' => ['name'],
            'search' => ['name', 'code', 'native_name'],
            'filters' => ['is_active' => true],
            'order' => 'name',
            'icon' => 'fa-solid fa-language',
            'badge' => 'LANG',
        ],

        'users' => [
            'permission' => 'manage:users',
            'model' => User::class,
            'value' => ['id'],
            'label' => ['name'],
            'search' => ['name', 'email'],
            'order' => 'name',
            'icon' => 'fa-solid fa-user',
            'badge' => 'USER',
        ],

        'roles' => [
            'permission' => 'manage:users',
            'model' => Role::class,
            'value' => ['id'],
            'label' => ['name'],
            'search' => ['name', 'slug'],
            'order' => 'name',
            'icon' => 'fa-solid fa-user-shield',
            'badge' => 'ROLE',
        ],

        'pages' => [
            'permission' => 'manage:pages',
            'module' => 'pages',
            'model' => Page::class,
            'value' => ['id'],
            'label' => ['translation.title'],
            'search' => ['translation.title', 'translation.slug'],
            'market_scoped' => true,
            'order' => 'id',
            'icon' => 'fa-solid fa-file-lines',
            'badge' => 'PAGE',
        ],

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

        'gallery_albums' => [
            'permission' => 'manage:galleries',
            'module' => 'gallery',
            'model' => Gallery::class,
            'value' => ['id'],
            'allowed_values' => ['id', 'translation.slug'],
            'label' => ['translation.title', 'title'],
            'search' => ['translation.title', 'title'],
            'filters' => ['is_active' => true],
            'market_scoped' => true,
            'order' => 'id',
            'icon' => 'fa-solid fa-images',
            'badge' => 'GALLERY',
        ],

        'forms' => [
            'permission' => 'manage:forms',
            'module' => 'forms',
            'model' => CustomForm::class,
            'value' => ['id'],
            'allowed_values' => ['id', 'slug'],
            'label' => ['translation.name', 'slug'],
            'search' => ['translation.name', 'slug'],
            'market_scoped' => true,
            'order' => 'slug',
            'icon' => 'fa-solid fa-clipboard-list',
            'badge' => 'FORM',
        ],
    ],
];
