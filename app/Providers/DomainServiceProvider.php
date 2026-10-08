<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Services\GeoServiceInterface;
use App\Contracts\Services\SeoServiceInterface;
use App\Contracts\Services\UserAuthServiceInterface;
use App\Services\Auth\UserAuthService;
use App\Services\Geo\LlmsTxtGeneratorService;
use App\Services\Seo\SitemapService;
use Illuminate\Support\ServiceProvider;

class DomainServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        UserAuthServiceInterface::class => UserAuthService::class,
        GeoServiceInterface::class => LlmsTxtGeneratorService::class,
        SeoServiceInterface::class => SitemapService::class,
    ];

    public function register(): void
    {
        // Bindings registered via $bindings property
    }

    public function boot(): void
    {
        //
    }
}
