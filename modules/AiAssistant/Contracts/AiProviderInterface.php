<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Contracts;

use Modules\AiAssistant\DTOs\ChatRequest;
use Modules\AiAssistant\DTOs\CompletionResult;
use Modules\AiAssistant\DTOs\ModelOption;
use Modules\AiAssistant\DTOs\ProviderDefinition;
use Modules\AiAssistant\Exceptions\AiModelListingException;
use Modules\AiAssistant\Exceptions\AiProviderException;

/**
 * Strategy contract implemented by every AI provider adapter.
 *
 * Adapters are stateless: credentials and base URLs are supplied per call so
 * they can be resolved from settings, env config or request context.
 */
interface AiProviderInterface
{
    public function definition(): ProviderDefinition;

    /**
     * Retrieve the live model catalog from the provider.
     *
     * Providers are the single source of truth: models are only exposed once
     * the provider is reachable (valid credentials / base URL). There is no
     * hardcoded fallback list by design.
     *
     * @return list<ModelOption>
     *
     * @throws AiModelListingException
     */
    public function listModels(?string $apiKey, ?string $baseUrl = null): array;

    /**
     * Execute a normalized chat completion.
     *
     * @throws AiProviderException
     */
    public function chat(ChatRequest $request, string $apiKey, string $model, ?string $baseUrl = null): CompletionResult;
}
