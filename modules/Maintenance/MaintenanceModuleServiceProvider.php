<?php

declare(strict_types=1);

namespace Modules\Maintenance;

use App\Core\Capabilities\CapabilityRegistry;
use App\Core\Modules\BaseModuleServiceProvider;
use App\Core\Modules\Contracts\MaintenanceModeInterface;
use App\Core\Modules\ModuleSettingsExtensionRegistry;
use App\DTOs\Modules\ModuleSettingsUpdateDTO;
use App\Services\Capabilities\CmsActionService;
use Modules\Maintenance\Capabilities\MaintenanceActionCatalog;
use Modules\Maintenance\Contracts\MaintenanceSettingsAdministrationInterface;
use Modules\Maintenance\Services\MaintenanceService;
use Modules\Maintenance\Services\MaintenanceSettingsAdministrationService;

class MaintenanceModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $identifier = 'maintenance';

    protected string $name = 'Mantenimiento & Construcción';

    protected string $version = '1.0.0';

    protected bool $isPageable = false;

    public function register(): void
    {
        $this->app->singleton(MaintenanceService::class);
        $this->app->alias(MaintenanceService::class, MaintenanceModeInterface::class);
        $this->app->bind(MaintenanceSettingsAdministrationInterface::class, MaintenanceSettingsAdministrationService::class);
    }

    public function boot(): void
    {
        $this->app->make(ModuleSettingsExtensionRegistry::class)->register('maintenance',
            fn (ModuleSettingsUpdateDTO $settings) => $this->app->make(MaintenanceSettingsAdministrationInterface::class)->update($settings),
            fn (): array => $this->app->make(MaintenanceSettingsAdministrationInterface::class)->view(),
        );
        $this->app->booted(function (): void {
            MaintenanceActionCatalog::register(
                $this->app->make(CapabilityRegistry::class),
                $this->app->make(CmsActionService::class),
            );
        });
        parent::boot();

        $this->loadViewsFrom(__DIR__.'/Resources/views', 'maintenance');
    }

    public function hasDedicatedPanel(): bool
    {
        return true;
    }

    public function getDedicatedPanelUrl(): ?string
    {
        return route('admin.modules.settings', ['identifier' => 'maintenance']);
    }

    public function getAdminNavigation(): array
    {
        return [
            [
                'title' => 'Mantenimiento & Construcción',
                'route' => 'admin.modules.settings',
                'params' => ['identifier' => 'maintenance'],
                'url' => route('admin.modules.settings', ['identifier' => 'maintenance']),
                'icon' => 'wrench',
                'permission' => 'manage:modules',
            ],
        ];
    }
}
