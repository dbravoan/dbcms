<?php

declare(strict_types=1);

namespace Modules\Marketing;

use App\Core\Modules\BaseModuleServiceProvider;
use Modules\Marketing\Contracts\CampaignAttributionGatewayInterface;
use Modules\Marketing\Contracts\CreativeGenerationGatewayInterface;
use Modules\Marketing\Contracts\CreativePackageProviderInterface;
use Modules\Marketing\Contracts\MarketingArtifactStoreInterface;
use Modules\Marketing\Services\MarketingArtifactStore;
use Modules\Marketing\Services\UnavailableCampaignAttributionGateway;
use Modules\Marketing\Services\UnavailableCreativeGenerationGateway;
use Modules\Marketing\Services\UnavailableCreativePackageProvider;

final class MarketingModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $identifier = 'marketing';

    protected string $name = 'Marketing infrastructure';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/Config/marketing.php', 'marketing');
        config([
            'queue.connections.marketing' => config('marketing.queue'),
            'database.redis.marketing' => config('marketing.redis'),
        ]);
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
