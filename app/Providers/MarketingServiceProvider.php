<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Services\CampaignAttributionGatewayInterface;
use App\Contracts\Services\CreativeGenerationGatewayInterface;
use App\Contracts\Services\CreativePackageProviderInterface;
use App\Contracts\Services\MarketingArtifactStoreInterface;
use App\Services\Marketing\MarketingArtifactStore;
use App\Services\Marketing\UnavailableCampaignAttributionGateway;
use App\Services\Marketing\UnavailableCreativeGenerationGateway;
use App\Services\Marketing\UnavailableCreativePackageProvider;
use Illuminate\Support\ServiceProvider;

final class MarketingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MarketingArtifactStoreInterface::class, MarketingArtifactStore::class);
        $this->app->singleton(CreativePackageProviderInterface::class, UnavailableCreativePackageProvider::class);
        $this->app->singleton(CreativeGenerationGatewayInterface::class, UnavailableCreativeGenerationGateway::class);
        $this->app->singleton(CampaignAttributionGatewayInterface::class, UnavailableCampaignAttributionGateway::class);
        config(['cache.stores.marketing' => [
            'driver' => config('cache.default') === 'array' ? 'array' : 'redis',
            'connection' => 'marketing',
            'lock_connection' => 'marketing',
            'prefix' => 'private_marketing_',
        ]]);
    }
}
