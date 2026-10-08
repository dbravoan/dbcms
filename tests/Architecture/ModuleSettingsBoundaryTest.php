<?php

declare(strict_types=1);

arch('core settings HTTP adapters have no storage or module implementation knowledge')->expect('App\\Http\\Controllers\\Admin\\ModuleSettings')->not->toUse([
    'App\\Models\\CmsModule', 'Illuminate\\Support\\Facades\\DB', 'Modules\\Maintenance\\Http\\Controllers\\AdminMaintenanceController',
]);
