<?php

declare(strict_types=1);

arch('maintenance settings application contract is independent of HTTP response types')->expect([
    'Modules\\Maintenance\\Contracts\\MaintenanceSettingsAdministrationInterface',
    'Modules\\Maintenance\\Services\\MaintenanceSettingsAdministrationService',
])->not->toUse(['Illuminate\\Http', 'Symfony\\Component\\HttpFoundation']);
