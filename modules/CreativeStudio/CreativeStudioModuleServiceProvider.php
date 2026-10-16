<?php

declare(strict_types=1);

namespace Modules\CreativeStudio;

use App\Core\Capabilities\CapabilityRegistry;
use App\Core\Integrations\IntegrationGuideRegistry;
use App\Core\Modules\BaseModuleServiceProvider;
use App\Events\ModuleAvailabilityChanged;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Modules\CreativeStudio\Console\RecoverCreativeTasksCommand;
use Modules\CreativeStudio\Contracts\Services\CreativeStudioServiceInterface;
use Modules\CreativeStudio\Services\AiAssistantCreativeGateway;
use Modules\CreativeStudio\Services\CreativeCapabilityRegistrar;
use Modules\CreativeStudio\Services\CreativeStudioDomainService;
use Modules\Marketing\Contracts\CreativeGenerationGatewayInterface;
use Modules\Marketing\Contracts\CreativePackageProviderInterface;

final class CreativeStudioModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $identifier = 'creative-studio';

    protected string $name = 'CreativeStudio';

    protected string $version = '1.0.0';

    protected bool $isPageable = false;

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config/creative_studio.php', 'creative_studio');
        $this->app->bind(CreativeStudioServiceInterface::class, CreativeStudioDomainService::class);
        $this->app->bind(CreativePackageProviderInterface::class, CreativeStudioDomainService::class);
        $this->app->bind(CreativeGenerationGatewayInterface::class, AiAssistantCreativeGateway::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/Resources/views', 'creative-studio');
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        parent::boot();
        Route::prefix('api/v1/creative-studio')->name('api.creative-studio.')->middleware(['api', 'module.enabled:creative-studio'])->group(__DIR__.'/Routes/api.php');
        $this->app->make(CreativeCapabilityRegistrar::class)->register($this->app->make(CapabilityRegistry::class));
        if ($this->app->runningInConsole()) {
            $this->commands([RecoverCreativeTasksCommand::class]);
        }
        $this->app->make(Dispatcher::class)->listen(ModuleAvailabilityChanged::class, function (ModuleAvailabilityChanged $event): void {
            if (in_array($event->module, ['creative-studio', 'ai_assistant'], true) && ! $event->available && Schema::hasTable('creative_generation_tasks')) {
                $this->app->make(CreativeStudioDomainService::class)->pauseUnstarted();
            }
        });
        $this->app->afterResolving(Schedule::class, static function (Schedule $schedule): void {
            $schedule->command('creative-studio:recover')->everyMinute()->name('creative-recover-'.(string) config('cms.installation_id'))->createMutexNameUsing('framework/schedule-creative-recover-'.hash('sha256', (string) config('cms.installation_id')))->withoutOverlapping()->onOneServer();
        });
        $registry = $this->app->make(IntegrationGuideRegistry::class);
        $registry->register('creative-studio', require __DIR__.'/Resources/guides/providers.php');
    }

    public function getAdminNavigation(): array
    {
        return [['title' => 'CreativeStudio', 'route' => 'admin.creative-studio.index', 'icon' => 'palette', 'permission' => 'manage:creative-studio']];
    }
}
