<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Contracts;

use Modules\AiAssistant\DTOs\ProviderDefinition;

/**
 * Resolves AI provider adapters by identifier.
 */
interface AiProviderRegistryInterface
{
    /**
     * @return list<string>
     */
    public function ids(): array;

    public function has(string $provider): bool;

    public function resolve(string $provider): AiProviderInterface;

    /**
     * @return array<string, ProviderDefinition>
     */
    public function definitions(): array;

    /**
     * @return list<string>
     */
    public function idsForModality(string $modality): array;

    /**
     * @return array<string, AiProviderInterface>
     */
    public function forModality(string $modality): array;

    /**
     * @return array<string, ProviderDefinition>
     */
    public function definitionsForModality(string $modality): array;
}
