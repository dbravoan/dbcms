<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Services;

use InvalidArgumentException;
use Modules\AiAssistant\Contracts\AiProviderInterface;
use Modules\AiAssistant\Contracts\AiProviderRegistryInterface;
use Modules\AiAssistant\DTOs\ProviderDefinition;

final class AiProviderRegistry implements AiProviderRegistryInterface
{
    /**
     * @var array<string, AiProviderInterface>
     */
    private array $providers = [];

    /**
     * @param  iterable<AiProviderInterface>  $providers
     */
    public function __construct(iterable $providers)
    {
        foreach ($providers as $provider) {
            $this->providers[$provider->definition()->id] = $provider;
        }
    }

    /**
     * @return list<string>
     */
    public function ids(): array
    {
        return array_keys($this->providers);
    }

    public function has(string $provider): bool
    {
        return isset($this->providers[$provider]);
    }

    public function resolve(string $provider): AiProviderInterface
    {
        if (! $this->has($provider)) {
            throw new InvalidArgumentException("Unsupported AI provider [{$provider}].");
        }

        return $this->providers[$provider];
    }

    /**
     * @return array<string, ProviderDefinition>
     */
    public function definitions(): array
    {
        $definitions = [];

        foreach ($this->providers as $id => $provider) {
            $definitions[$id] = $provider->definition();
        }

        return $definitions;
    }

    /**
     * @return list<string>
     */
    public function idsForModality(string $modality): array
    {
        return array_keys($this->forModality($modality));
    }

    /**
     * @return array<string, AiProviderInterface>
     */
    public function forModality(string $modality): array
    {
        $filtered = [];

        foreach ($this->providers as $id => $provider) {
            if ($provider->definition()->supportsModality($modality)) {
                $filtered[$id] = $provider;
            }
        }

        return $filtered;
    }

    /**
     * @return array<string, ProviderDefinition>
     */
    public function definitionsForModality(string $modality): array
    {
        $definitions = [];

        foreach ($this->forModality($modality) as $id => $provider) {
            $definitions[$id] = $provider->definition();
        }

        return $definitions;
    }
}
