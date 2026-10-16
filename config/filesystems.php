<?php

declare(strict_types=1);

return [
    'disks' => [
        'uploads' => [
            'driver' => 'local',
            'root' => public_path('uploads'),
            'url' => env('APP_URL').'/uploads',
            'visibility' => 'public',
            'throw' => true,
        ],
    ],
];
