<?php

declare(strict_types=1);

return [
    'queue' => [
        'requires_managed_updates' => true,
        'update_owner_modules' => ['creative-studio', 'social-publishing'],
        'driver' => env('MARKETING_QUEUE_DRIVER', 'redis'),
        'connection' => 'marketing',
        'queue' => 'default',
        'retry_after' => (int) env('MARKETING_QUEUE_RETRY_AFTER', 1500),
        'block_for' => null,
        'after_commit' => true,
    ],

    'redis' => [
        'url' => env('MARKETING_REDIS_URL', env('REDIS_URL')),
        'host' => env('REDIS_HOST', 'redis'),
        'username' => env('REDIS_USERNAME'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('MARKETING_REDIS_DB', '0'),
        'options' => ['prefix' => 'cms_'.(env('CMS_INSTALLATION_ID') ?: substr(hash('sha256', (string) env('APP_KEY', '')), 0, 32)).'_marketing_'],
    ],

    'installation_id' => env('CMS_INSTALLATION_ID') ?: substr(hash('sha256', (string) env('APP_KEY', '')), 0, 32),
    'artifact_root' => env('MARKETING_ARTIFACT_ROOT'),
    'max_artifact_bytes' => (int) env('MARKETING_MAX_ARTIFACT_BYTES', 536870912),
    'ffmpeg_binary' => env('MARKETING_FFMPEG_BINARY', 'ffmpeg'),
    'ffprobe_binary' => env('MARKETING_FFPROBE_BINARY', 'ffprobe'),
    'font_path' => env('MARKETING_FONT_PATH', '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf'),
    'generation_concurrency' => (int) env('MARKETING_GENERATION_CONCURRENCY', 2),
];
