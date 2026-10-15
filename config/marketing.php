<?php

declare(strict_types=1);

return [
    'installation_id' => env('CMS_INSTALLATION_ID') ?: substr(hash('sha256', (string) env('APP_KEY', '')), 0, 32),
    'artifact_root' => env('MARKETING_ARTIFACT_ROOT'),
    'max_artifact_bytes' => (int) env('MARKETING_MAX_ARTIFACT_BYTES', 536870912),
    'ffmpeg_binary' => env('MARKETING_FFMPEG_BINARY', 'ffmpeg'),
    'ffprobe_binary' => env('MARKETING_FFPROBE_BINARY', 'ffprobe'),
    'font_path' => env('MARKETING_FONT_PATH', '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf'),
    'generation_concurrency' => (int) env('MARKETING_GENERATION_CONCURRENCY', 2),
];
