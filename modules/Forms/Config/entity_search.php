<?php

declare(strict_types=1);

use Modules\Forms\Models\CustomForm;

return [
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
];
