<?php

declare(strict_types=1);

namespace Modules\SocialPublishing;

use App\Core\Capabilities\CapabilityRegistry;
use App\Core\Integrations\IntegrationGuideRegistry;
use App\Core\Modules\BaseModuleServiceProvider;
use App\Events\ModuleAvailabilityChanged;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Modules\SocialPublishing\Capabilities\SocialCapabilities;
use Modules\SocialPublishing\Console\DispatchSocial;
use Modules\SocialPublishing\Contracts\DestinationValidatorInterface;
use Modules\SocialPublishing\Contracts\PublicationServiceInterface;
use Modules\SocialPublishing\Http\Controllers\AssetGetController;
use Modules\SocialPublishing\Services\DeliveryWorker;
use Modules\SocialPublishing\Services\DestinationValidator;
use Modules\SocialPublishing\Services\PublicationService;
use Modules\SocialPublishing\Services\SocialGuides;

class SocialPublishingModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $identifier = 'social-publishing';

    protected string $name = 'SocialPublishing';

    protected string $version = '1.0.0';

    protected bool $isPageable = false;

    public function register(): void
    {
        $this->app->bind(PublicationServiceInterface::class, PublicationService::class);
        $this->app->bind(DestinationValidatorInterface::class, DestinationValidator::class);
        $this->mergeConfigFrom(__DIR__.'/config/social_publishing.php', 'social_publishing');
    }

    public function boot(): void
    {
        parent::boot();

        $this->loadViewsFrom(__DIR__.'/Resources/views', 'social-publishing');
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $timezone = (string) $this->getSetting('timezone', config('app.timezone', 'UTC'));
        config(['social_publishing.timezone' => in_array($timezone, \DateTimeZone::listIdentifiers(), true) ? $timezone : 'UTC']);
        $this->app->make(IntegrationGuideRegistry::class)->register('social-publishing', $this->app->make(SocialGuides::class)->all());
        $this->app->make(SocialCapabilities::class)->register($this->app->make(CapabilityRegistry::class));
        Route::prefix('api/v1/social-publishing')->middleware(['api', 'module.enabled:social-publishing'])->group(__DIR__.'/Routes/api.php');
        Route::get('/social-publishing/assets/{delivery}/{index}', AssetGetController::class)
            ->whereNumber('index')->middleware(['web', 'signed', 'module.enabled:social-publishing'])->name('social-publishing.asset');
        Event::listen(ModuleAvailabilityChanged::class, function (ModuleAvailabilityChanged $event): void {
            if ($event->module === 'social-publishing' && ! $event->available && Schema::hasTable('sp_deliveries')) {
                $this->app->make(DeliveryWorker::class)->pause();
            }
        });
        $this->commands([DispatchSocial::class]);
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $id = (string) config('marketing.installation_id');
            $schedule->command('social-publishing:dispatch')->everyMinute()->name('social-publishing:dispatch:'.$id)->createMutexNameUsing('social-publishing:dispatch:'.$id)->withoutOverlapping(5)->onOneServer();
            $schedule->command('social-publishing:dispatch --metrics')->hourly()->name('social-publishing:metrics:'.$id)->createMutexNameUsing('social-publishing:metrics:'.$id)->withoutOverlapping(15)->onOneServer();
        });

    }

    public function getAdminNavigation(): array
    {
        return [
            [
                'title' => 'SocialPublishing',
                'route' => 'admin.social-publishing.index',
                'icon' => 'calendar-days',
                'permission' => 'read:social-publishing',
            ],
        ];
    }
}
