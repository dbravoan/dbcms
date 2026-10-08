<?php

declare(strict_types=1);

namespace Modules\Analytics;

use App\Core\Modules\BaseModuleServiceProvider;
use Modules\Analytics\Services\AnalyticsManagerService;
use Modules\Analytics\Services\Ga4CspContributor;

class AnalyticsModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $identifier = 'analytics';

    protected string $name = 'Analytics & GDPR Consent';

    protected string $version = '1.0.0';

    protected bool $isPageable = false;

    public function register(): void
    {
        $this->app->singleton(AnalyticsManagerService::class);
        $this->app->singleton(Ga4CspContributor::class);
        $this->app->tag(Ga4CspContributor::class, 'cms.csp.connect');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/Resources/views', 'analytics');
    }
}
