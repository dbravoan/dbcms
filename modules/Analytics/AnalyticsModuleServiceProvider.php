<?php

declare(strict_types=1);

namespace Modules\Analytics;

use App\Core\Modules\BaseModuleServiceProvider;
use App\Core\Modules\ModuleSettingsExtensionRegistry;
use Illuminate\View\View;
use Modules\Analytics\Contracts\Ga4SettingsNormalizerInterface;
use Modules\Analytics\Services\AnalyticsCookieDisclosure;
use Modules\Analytics\Services\AnalyticsManagerService;
use Modules\Analytics\Services\Ga4CspContributor;
use Modules\Analytics\Services\Ga4ReportGateway;
use Modules\Analytics\Services\Ga4SettingsNormalizer;
use Modules\Marketing\Contracts\CampaignAttributionGatewayInterface;

class AnalyticsModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $identifier = 'analytics';

    protected string $name = 'Analytics & GDPR Consent';

    protected string $version = '1.0.0';

    protected bool $isPageable = false;

    public function register(): void
    {
        $this->app->singleton(AnalyticsCookieDisclosure::class);
        $this->app->tag(AnalyticsCookieDisclosure::class, 'cms.cookie-disclosures');
        $this->app->singleton(AnalyticsManagerService::class);
        $this->app->bind(Ga4SettingsNormalizerInterface::class, Ga4SettingsNormalizer::class);
        $this->app->bind(CampaignAttributionGatewayInterface::class, Ga4ReportGateway::class);
        $this->app->singleton(Ga4CspContributor::class);
        $this->app->tag(Ga4CspContributor::class, 'cms.csp.connect');
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\View::composer('themes::components.analytics', function (View $view): void {
            $view->with('analyticsScript', $this->app->make(AnalyticsManagerService::class)->renderGa4Script());
        });
        $this->loadViewsFrom(__DIR__.'/Resources/views', 'analytics');
        $this->app->make(ModuleSettingsExtensionRegistry::class)->registerNormalizer('analytics',
            fn (array $settings): array => $this->app->make(Ga4SettingsNormalizerInterface::class)->normalize($settings),
        );
    }
}
