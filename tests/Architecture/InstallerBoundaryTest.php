<?php

declare(strict_types=1);

arch('installer HTTP adapters do not contain persistence cryptography or PDO')->expect('App\\Http\\Controllers\\Install')->not->toUse([
    'PDO', 'App\\Models\\User', 'App\\Models\\Role', 'Illuminate\\Support\\Facades\\DB', 'Illuminate\\Support\\Facades\\File', 'Illuminate\\Support\\Facades\\Artisan', 'Illuminate\\Support\\Facades\\Hash',
]);
