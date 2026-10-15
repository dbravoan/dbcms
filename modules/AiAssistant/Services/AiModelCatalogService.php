<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Services;

use Illuminate\Support\Facades\Cache;
use Modules\AiAssistant\Contracts\AiModelCatalogInterface;
use Modules\AiAssistant\Contracts\AiProviderRegistryInterface;
use Modules\AiAssistant\DTOs\ModelOption;

/**
 * Caches live provider catalogs and declared catalogs for visual providers.
 * Declared model support never implies a verified account connection.
 */
final class AiModelCatalogService implements AiModelCatalogInterface
{
    private const CACHE_PREFIX = 'ai_assistant:models:';

    private const CACHE_TTL_SECONDS = 43200; // 12 hours

    private const EMPTY_TTL_SECONDS = 300; // 5 minutes: avoid hammering an unreachable endpoint

    public function __construct(
        private readonly AiProviderRegistryInterface $registry,
        private readonly AiProviderSettings $settings,
    ) {}

    /**
     * @return list<ModelOption>
     */
    public function models(string $provider, bool $forceRefresh = false): array
    {
        if ($forceRefresh) {
            $this->forget($provider);
        }

        $cached = Cache::get($this->cacheKey($provider));
        if (is_array($cached)) {
            return array_values(array_map(static fn (array $row): ModelOption => ModelOption::fromArray($row), $cached));
        }

        return $this->fetch($provider);
    }

    /**
     * @return list<ModelOption>
     */
    public function refresh(string $provider): array
    {
        $this->forget($provider);

        return $this->fetch($provider);
    }

    public function forget(string $provider): void
    {
        Cache::forget($this->cacheKey($provider));
        Cache::forget($this->cacheKey($provider).':connection');
    }

    /** @return array{state: string, verified: bool} */
    public function connectionState(string $provider): array
    {
        if (! $this->settings->isConfigured($provider)) {
            return ['state' => 'unconfigured', 'verified' => false];
        }
        $state = Cache::get($this->cacheKey($provider).':connection', 'configured');

        return ['state' => is_string($state) ? $state : 'configured', 'verified' => $state === 'verified'];
    }

    /**
     * Providers with listing APIs return live account catalogs. Visual adapters
     * without listing APIs return documented support with configured status.
     * Unconfigured or unreachable providers return no models.
     *
     * @return list<ModelOption>
     */
    private function fetch(string $provider): array
    {
        $adapter = $this->registry->resolve($provider);
        $definition = $adapter->definition();
        $apiKey = $this->settings->apiKey($provider);

        // Cloud provider without credentials -> not configured.
        if ($definition->requiresApiKey && empty($apiKey)) {
            return [];
        }

        // Local/self-hosted provider -> only query once an endpoint is saved.
        if (! $definition->requiresApiKey && $this->settings->baseUrl($provider) === null) {
            return [];
        }

        try {
            $models = $adapter->listModels($apiKey, $this->settings->baseUrl($provider));
        } catch (\Throwable) {
            // Unreachable endpoint or rejected credentials -> expose nothing,
            // and remember the miss briefly so page loads stay fast.
            Cache::put($this->cacheKey($provider).':connection', 'unavailable', now()->addSeconds(self::EMPTY_TTL_SECONDS));

            return $this->rememberEmpty($provider);
        }

        if ($models === []) {
            Cache::put($this->cacheKey($provider).':connection', 'unavailable', now()->addSeconds(self::EMPTY_TTL_SECONDS));

            return $this->rememberEmpty($provider);
        }

        Cache::put($this->cacheKey($provider).':connection', $definition->supportsModelListing ? 'verified' : 'configured', now()->addSeconds(self::CACHE_TTL_SECONDS));

        Cache::put(
            $this->cacheKey($provider),
            array_map(static fn (ModelOption $model): array => $model->toArray(), $models),
            now()->addSeconds(self::CACHE_TTL_SECONDS)
        );

        return $models;
    }

    /**
     * Cache a short-lived empty result and return it.
     *
     * @return list<ModelOption>
     */
    private function rememberEmpty(string $provider): array
    {
        Cache::put($this->cacheKey($provider), [], now()->addSeconds(self::EMPTY_TTL_SECONDS));

        return [];
    }

    private function cacheKey(string $provider): string
    {
        return self::CACHE_PREFIX.$provider.':'.hash('sha256', (string) $this->settings->apiKey($provider).'|'.(string) $this->settings->baseUrl($provider));
    }
}
