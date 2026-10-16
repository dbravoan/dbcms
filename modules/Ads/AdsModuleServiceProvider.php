<?php

declare(strict_types=1);

namespace Modules\Ads;

use App\Core\Modules\BaseModuleServiceProvider;
use Modules\Ads\Services\AdsCookieDisclosure;
use Modules\Ads\Services\AdSenseManagerService;

class AdsModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $identifier = 'ads';

    protected string $name = 'Google AdSense & Ad Placements';

    protected string $version = '1.0.0';

    protected bool $isPageable = false;

    public function register(): void
    {
        $this->app->singleton(AdsCookieDisclosure::class);
        $this->app->tag(AdsCookieDisclosure::class, 'cms.cookie-disclosures');
        $this->app->singleton(AdSenseManagerService::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/Resources/views', 'ads');
    }
}
