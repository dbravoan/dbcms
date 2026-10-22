<?php

declare(strict_types=1);

namespace App\Providers;

use App\Core\Filters\Contracts\FilterableModule;
use App\Core\Filters\FilterAlternateUrls;
use App\Core\Filters\FilterFacets;
use App\Core\Filters\FilterModuleRegistry;
use App\Core\Filters\FilterPanelSkin;
use App\Core\Filters\FilterPathParser;
use App\Core\Filters\FilterPathResolver;
use App\Core\Filters\FilterSeoResolver;
use App\Core\Filters\FilterUrlBuilder;
use Illuminate\Support\ServiceProvider;

/**
 * Bootstraps the friendly filter URL framework.
 *
 * The module registry is a singleton shared by every module (blog, shop, …);
 * they register their {@see FilterableModule}
 * implementation in their service provider's `boot()`.
 */
final class FilterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FilterModuleRegistry::class);
        $this->app->singleton(FilterPathParser::class);
        $this->app->singleton(FilterUrlBuilder::class);
        $this->app->singleton(FilterSeoResolver::class);
        $this->app->singleton(FilterAlternateUrls::class);
        $this->app->singleton(FilterFacets::class);
        $this->app->singleton(FilterPathResolver::class);
        $this->app->singleton(FilterPanelSkin::class);
    }
}
