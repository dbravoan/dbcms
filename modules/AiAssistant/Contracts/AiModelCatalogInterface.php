<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Contracts;

use Modules\AiAssistant\DTOs\ModelOption;

/**
 * Cache-aware access to each provider's live model catalog.
 */
interface AiModelCatalogInterface
{
    /**
     * @return list<ModelOption>
     */
    public function models(string $provider, bool $forceRefresh = false): array;

    /**
     * Force a remote refresh and return the fresh catalog.
     *
     * @return list<ModelOption>
     */
    public function refresh(string $provider): array;

    public function forget(string $provider): void;
}
