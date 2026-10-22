<?php

declare(strict_types=1);

use App\Core\Modules\ModuleManager;
use App\Core\Modules\NullMaintenanceModeService;
use Modules\Maintenance\Services\MaintenanceService;
use Symfony\Component\Process\Process;
use Tests\Support\DirectoryFixture;
use Tests\Support\MaintenanceComposition;

uses(MaintenanceComposition::class);

test('maintenance availability follows the module and does not keep an old lock effective after deactivation', function (): void {
    $modules = app(ModuleManager::class);
    $maintenance = app(MaintenanceService::class);
    $maintenance->enable();
    $modules->deactivate('maintenance');
    try {
        expect($maintenance->isAvailable())->toBeFalse()->and($maintenance->isActive())->toBeFalse();
        $this->artisan('cms:maintenance enable')->assertExitCode(1);
    } finally {
        $modules->activate('maintenance');
        $maintenance->disable();
    }
});

test('null maintenance reads inactive state and rejects fake successful mutation', function (): void {
    $service = new NullMaintenanceModeService;
    expect($service->isAvailable())->toBeFalse()->and($service->isActive())->toBeFalse()->and($service->getSettings())->toBe([]);
    expect(fn () => $service->enable())->toThrow(LogicException::class);
});

test('a fresh process boots generic CMS actions when the maintenance module is physically absent', function (): void {
    DirectoryFixture::hide(base_path('modules/Maintenance'), storage_path('app/private/maintenance-architecture-hidden'), function (): void {
        $program = 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); $mode = $app->make(App\\Core\\Modules\\Contracts\\MaintenanceModeInterface::class); $actions = $app->make(App\\Services\\Capabilities\\PlatformAdministrationActions::class); echo json_encode(["available" => $mode->isAvailable(), "generic_actions" => $actions instanceof App\\Services\\Capabilities\\PlatformAdministrationActions]);';
        $process = new Process([PHP_BINARY, '-r', $program], base_path());
        $process->mustRun();
        expect(json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR))->toBe(['available' => false, 'generic_actions' => true]);
    });
});
