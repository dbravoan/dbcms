<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Services\IntegrationGuideServiceInterface;
use App\Core\Integrations\IntegrationGuideRegistry;
use App\Services\Integrations\IntegrationGuideService;
use Illuminate\Support\ServiceProvider;

final class IntegrationGuidesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(IntegrationGuideRegistry::class);
        $this->app->bind(IntegrationGuideServiceInterface::class, IntegrationGuideService::class);
    }
}
