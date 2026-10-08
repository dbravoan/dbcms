<?php

declare(strict_types=1);

arch('core maintenance consumers depend on a shared contract rather than the module implementation')->expect([
    'App\\Services\\Capabilities\\CmsActionService', 'App\\Http\\Middleware\\CheckMaintenanceMode', 'App\\Console\\Commands\\CmsMaintenanceCommand', 'App\\Services\\Admin\\AdminNavigationService',
])->not->toUse('Modules\\Maintenance');
