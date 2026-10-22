<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Services;

use App\Contracts\Services\ModuleSettingsServiceInterface;
use App\Core\Capabilities\ExecutionContext;
use Modules\AiAssistant\Contracts\AiConfigBridgeInterface;
use Modules\AiAssistant\Contracts\AiModelCatalogInterface;
use Modules\AiAssistant\Contracts\AiProviderRegistryInterface;
use Modules\AiAssistant\DTOs\ChatRequest;
use Modules\AiAssistant\DTOs\ModelOption;
use Modules\AiAssistant\DTOs\ProviderDefinition;
use Modules\AiAssistant\Exceptions\AiProviderException;

/**
 * Backoffice-facing facade that resolves providers, credentials and the
 * live model catalog. All provider-specific behavior lives in the adapters.
 */
class AiConfigBridge implements AiConfigBridgeInterface
{
    public function __construct(
        protected readonly AiProviderRegistryInterface $registry,
        protected readonly AiModelCatalogInterface $catalog,
        protected readonly AiProviderSettings $settings,
        protected readonly ModuleSettingsServiceInterface $settingsService,
    ) {}

    public function getActiveProvider(?string $modality = null): string
    {
        return $this->settings->activeProvider($modality ?: 'text');
    }

    public function getApiKeyForProvider(string $provider): ?string
    {
        return $this->settings->apiKey($provider);
    }

    public function getModelForProvider(string $provider): string
    {
        $stored = $this->settings->modelForModality($provider, 'text') ?? $this->settings->model($provider);
        if ($stored !== null) {
            return $stored;
        }

        // No stored model: fall back to the first live model, if any.
        // Unconfigured providers expose no models, so this stays empty.
        $models = $this->catalog->models($provider);

        return $models[0]->id ?? '';
    }

    public function hasApiKeyForProvider(string $provider): bool
    {
        return $this->settings->hasApiKey($provider);
    }

    public function isConfigured(?string $provider = null): bool
    {
        $target = $provider ?: $this->getActiveProvider();
        $definition = $this->registry->resolve($target)->definition();

        // Local/self-hosted providers are configured once an endpoint is saved.
        if (! $definition->requiresApiKey) {
            return $this->settings->baseUrl($target) !== null;
        }

        return $this->hasApiKeyForProvider($target);
    }

    /**
     * @return list<array{id: string, name: string, description: string, source: ?string, family: string, experimental: bool}>
     */
    public function getRegisteredModels(string $provider): array
    {
        return array_map(static function (ModelOption $model): array {
            $origin = $model->source ? strtoupper($model->source) : null;
            $description = $origin !== null ? "Gateway OpenCode · {$origin}" : '';

            if ($model->experimental) {
                $description = trim($description.' (experimental)');
            }

            return [
                'id' => $model->id,
                'name' => $model->label,
                'description' => $description,
                'source' => $model->source,
                'family' => $model->family->value,
                'experimental' => $model->experimental,
            ];
        }, $this->catalog->models($provider));
    }

    /**
     * @return list<array{id: string, name: string, description: string, source: ?string, family: string, experimental: bool}>
     */
    public function listModels(string $provider, bool $forceRefresh = false): array
    {
        $models = $forceRefresh
            ? $this->catalog->refresh($provider)
            : $this->catalog->models($provider);

        return array_map(static fn (ModelOption $model): array => $model->toArray() + [
            'name' => $model->label,
            'description' => '',
        ], $models);
    }

    public function refreshModels(string $provider): array
    {
        return $this->listModels($provider, true);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getProvidersCatalog(): array
    {
        $active = $this->getActiveProvider();
        $catalog = [];

        foreach ($this->registry->definitions() as $id => $definition) {
            $catalog[$id] = $this->providerView($definition, $active);
        }

        return $catalog;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveProviderConfig(string $provider, array $data): bool
    {
        $this->registry->resolve($provider);
        $this->catalog->forget($provider);
        $this->settings->save($provider, $data);
        $this->catalog->forget($provider);

        return true;
    }

    public function setDefaultProvider(string $provider, string $modality = 'text'): bool
    {
        if (! $this->registry->has($provider) || ! $this->registry->resolve($provider)->definition()->supportsModality($modality)) {
            return false;
        }

        $this->settings->setActiveProvider($provider, $modality);

        return true;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getModalitiesMatrix(): array
    {
        $catalog = $this->getProvidersCatalog();
        $matrix = [];

        foreach (['text', 'image', 'video'] as $modality) {
            $matching = [];
            $hasConfigured = false;
            $active = $this->settings->activeProvider($modality);

            foreach ($this->registry->definitionsForModality($modality) as $id => $def) {
                if (isset($catalog[$id])) {
                    $item = $catalog[$id];
                    $item['is_active_for_modality'] = ($active === $id);
                    $matching[] = $item;
                    if ($item['is_configured']) {
                        $hasConfigured = true;
                    }
                }
            }

            $matrix[$modality] = [
                'available' => $hasConfigured,
                'active_provider' => $active,
                'providers' => $matching,
            ];
        }

        return $matrix;
    }

    /**
     * @return list<string>
     */
    public function getProviderIds(): array
    {
        return $this->registry->ids();
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{content: string, provider: string, model: string, prompt_tokens: int, completion_tokens: int, latency_ms: int}
     */
    public function generateCompletion(string $systemPrompt, string $userPrompt, ?string $provider = null, array $options = []): array
    {
        $startTime = microtime(true);
        $context = app(ExecutionContext::class);
        $action = AiActionRunner::MCP_TOOL_ACTIONS[$context->capabilityName ?? ''] ?? '';
        if ($context->actor !== null && in_array($action, ['block', 'draft', 'recommend_blocks', 'compose_blocks', 'proofread', 'humanize', 'seo', 'page_element', 'research', 'orchestrate_multimodal'], true)) {
            $selectedProvider = trim((string) ($context->arguments['text_provider'] ?? $context->arguments['provider'] ?? ''));
            $selectedModel = trim((string) ($context->arguments['text_model'] ?? $context->arguments['model'] ?? ''));
            if ($provider === null && $selectedProvider !== '') {
                $provider = $selectedProvider;
            }
            if (! isset($options['model']) && $selectedModel !== '' && ($provider === null || $selectedProvider === '' || $provider === $selectedProvider)) {
                $options['model'] = $selectedModel;
            }
        }
        $targetProvider = $provider ?: $this->getActiveProvider();
        $adapter = $this->registry->resolve($targetProvider);
        $model = (string) ($options['model'] ?? $this->getModelForProvider($targetProvider));
        $apiKey = (string) ($this->getApiKeyForProvider($targetProvider) ?? '');

        if (! $adapter->definition()->supportsModality('text')) {
            throw new AiProviderException('Provider does not support text generation.');
        }
        if (! $this->isConfigured($targetProvider)) {
            throw new AiProviderException('AI provider is not configured.');
        }
        if ($model === '') {
            throw new AiProviderException('Select a configured model before generating content.');
        }
        $request = new ChatRequest(
            systemPrompt: $systemPrompt,
            userPrompt: $userPrompt,
            temperature: (float) ($options['temperature'] ?? $this->settingsService->get('ai_assistant', 'default_temperature', 0.7)),
            jsonMode: (bool) ($options['json'] ?? true),
        );

        $completion = $adapter->chat($request, $apiKey, $model, $this->resolveBaseUrl($targetProvider, $model));
        $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

        return [
            'content' => $completion->content,
            'provider' => $completion->provider,
            'model' => $completion->model,
            'prompt_tokens' => $completion->promptTokens,
            'completion_tokens' => $completion->completionTokens,
            'latency_ms' => $latencyMs,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function providerView(ProviderDefinition $definition, string $activeProvider): array
    {
        $hasApiKey = $this->hasApiKeyForProvider($definition->id);
        $hasBaseUrl = $this->settings->baseUrl($definition->id) !== null;
        $isConfigured = $this->isConfigured($definition->id);
        $models = $this->getRegisteredModels($definition->id);
        $connection = $this->catalog instanceof AiModelCatalogService ? $this->catalog->connectionState($definition->id) : ['state' => $isConfigured ? 'configured' : 'unconfigured', 'verified' => false];

        return [
            'id' => $definition->id,
            'name' => $definition->label,
            'badge' => $definition->badge,
            'badge_color' => $definition->badgeColor,
            'description' => $definition->description,
            'icon' => $definition->icon,
            'doc_url' => $definition->docUrl,
            'api_key' => $hasApiKey ? AiProviderSettings::MASKED_KEY : '',
            'credential_fields' => $definition->id === 'higgsfield' ? ['api_key_id', 'api_key_secret'] : ['api_key'],
            'has_api_key_id' => $definition->id === 'higgsfield' && $this->settings->higgsfieldCredentials()['id'] !== null,
            'has_api_key_secret' => $definition->id === 'higgsfield' && $this->settings->higgsfieldCredentials()['secret'] !== null,
            'has_key' => $isConfigured,
            'is_configured' => $isConfigured,
            'current_model' => $this->getModelForProvider($definition->id),
            'image_model' => $this->settings->modelForModality($definition->id, 'image'),
            'video_model' => $this->settings->modelForModality($definition->id, 'video'),
            'base_url' => $this->settings->baseUrl($definition->id) ?? '',
            'default_base_url' => $definition->defaultBaseUrl ?? '',
            'is_active' => $activeProvider === $definition->id,
            'registered_models' => $models,
            'connection_state' => $connection['state'],
            'connection_verified' => $connection['verified'],
            'catalog_source' => $definition->supportsModelListing ? 'live' : 'documented',
            'requires_api_key' => $definition->requiresApiKey,
            'supports_model_listing' => $definition->supportsModelListing,
            'modalities' => $definition->modalities,
        ];
    }

    private function resolveBaseUrl(string $provider, string $model): ?string
    {
        $definition = $this->registry->resolve($provider)->definition();

        if ($definition->sourceBaseUrls !== []) {
            foreach ($this->catalog->models($provider) as $option) {
                if ($option->id === $model && $option->source !== null && isset($definition->sourceBaseUrls[$option->source])) {
                    return $definition->sourceBaseUrls[$option->source];
                }
            }
        }

        return $this->settings->baseUrl($provider);
    }
}
