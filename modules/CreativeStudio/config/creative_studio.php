<?php

declare(strict_types=1);

return [
    'installation_budget' => 0, 'currency' => 'USD', 'concurrency' => 2,
    'prices' => ['text' => 0, 'image' => 0, 'video' => 0],
    'font' => config('marketing.font_path', '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf'), 'ffmpeg' => config('marketing.ffmpeg_binary', '/usr/bin/ffmpeg'), 'ffprobe' => config('marketing.ffprobe_binary', '/usr/bin/ffprobe'),
    'fonts' => ['default' => config('marketing.font_path', '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf')],
    'templates' => ['square' => ['version' => 1, 'width' => 1080, 'height' => 1080, 'safe_margin' => 80], 'portrait' => ['version' => 1, 'width' => 1080, 'height' => 1350, 'safe_margin' => 80], 'video' => ['version' => 1, 'width' => 1080, 'height' => 1920, 'duration' => 30, 'safe_margin' => 100]],
];
