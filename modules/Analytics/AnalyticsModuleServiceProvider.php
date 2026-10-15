<?php

declare(strict_types=1);

namespace Modules\Analytics;

use App\Contracts\Services\CampaignAttributionGatewayInterface;
use App\Core\Modules\BaseModuleServiceProvider;
use App\Core\Modules\ModuleSettingsExtensionRegistry;
use Modules\Analytics\Contracts\Ga4SettingsNormalizerInterface;
use Modules\Analytics\Services\AnalyticsManagerService;
use Modules\Analytics\Services\Ga4CspContributor;
use Modules\Analytics\Services\Ga4ReportGateway;
use Modules\Analytics\Services\Ga4SettingsNormalizer;

class AnalyticsModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $identifier = 'analytics';

    protected string $name = 'Analytics & GDPR Consent';

    protected string $version = '1.0.0';

    protected bool $isPageable = false;

    public function register(): void
    {
        $this->app->singleton(AnalyticsManagerService::class);
        $this->app->bind(Ga4SettingsNormalizerInterface::class, Ga4SettingsNormalizer::class);
        $this->app->bind(CampaignAttributionGatewayInterface::class, Ga4ReportGateway::class);
        $this->app->singleton(Ga4CspContributor::class);
        $this->app->tag(Ga4CspContributor::class, 'cms.csp.connect');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/Resources/views', 'analytics');
        $this->app->make(ModuleSettingsExtensionRegistry::class)->registerNormalizer('analytics',
            fn (array $settings): array => $this->app->make(Ga4SettingsNormalizerInterface::class)->normalize($settings),
        );
    }
}
