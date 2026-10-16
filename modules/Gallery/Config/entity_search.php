<?php

declare(strict_types=1);

use Modules\Gallery\Models\Gallery;

return [
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
];
