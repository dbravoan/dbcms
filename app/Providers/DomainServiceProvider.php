<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Services\GeoServiceInterface;
use App\Contracts\Services\ProjectDeploymentAdoptionServiceInterface;
use App\Contracts\Services\ProjectInitializationServiceInterface;
use App\Contracts\Services\SeoServiceInterface;
use App\Contracts\Services\SourceRevisionResolverInterface;
use App\Contracts\Services\UserAuthServiceInterface;
use App\Services\Auth\UserAuthService;
use App\Services\Geo\LlmsTxtGeneratorService;
use App\Services\Projects\GitSourceRevisionResolver;
use App\Services\Projects\ProjectDeploymentAdoptionService;
use App\Services\Projects\ProjectInitializationService;
use App\Services\Seo\SitemapService;
use Illuminate\Filesystem\Filesystem;
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
        $this->app->bind(SourceRevisionResolverInterface::class, GitSourceRevisionResolver::class);
        $this->app->bind(ProjectDeploymentAdoptionServiceInterface::class, ProjectDeploymentAdoptionService::class);
        $this->app->bind(ProjectInitializationServiceInterface::class, fn ($app) => new ProjectInitializationService(
            $app->make(Filesystem::class),
            $app->make(SourceRevisionResolverInterface::class),
            $app->basePath(),
            $app->make(ProjectDeploymentAdoptionServiceInterface::class),
        ));
    }

    public function boot(): void
    {
        //
    }
}
