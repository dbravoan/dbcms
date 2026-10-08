<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Contracts;

interface AiConfigBridgeInterface
{
    public function getActiveProvider(?string $modality = null): string;

    public function getApiKeyForProvider(string $provider): ?string;

    public function getModelForProvider(string $provider): string;

    public function hasApiKeyForProvider(string $provider): bool;

    public function isConfigured(?string $provider = null): bool;

    /**
     * Get matrix of modalities with their available status, active provider and supported adapters.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getModalitiesMatrix(): array;

    /**
     * Get pre-registered curated models for a given provider.
     *
     * @return list<array{id: string, name: string, description: string, source: ?string, family: string, experimental: bool}>
     */
    public function getRegisteredModels(string $provider): array;

    /**
     * Get the live (cached) model catalog for a provider.
     *
     * @return list<array{id: string, name: string, description: string, source: ?string, family: string, experimental: bool}>
     */
    public function listModels(string $provider, bool $forceRefresh = false): array;

    /**
     * Force a remote refresh of the provider model catalog.
     *
     * @return list<array{id: string, name: string, description: string, source: ?string, family: string, experimental: bool}>
     */
    public function refreshModels(string $provider): array;

    /**
     * Get complete catalog of providers with their specific API schemas and models.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getProvidersCatalog(): array;

    /**
     * Save configuration for a single provider.
     *
     * @param  array<string, mixed>  $data
     */
    public function saveProviderConfig(string $provider, array $data): bool;

    /**
     * Set the active/default provider for the AI assistant (optionally per modality).
     */
    public function setDefaultProvider(string $provider, string $modality = 'text'): bool;

    /**
     * Registered provider identifiers (single source of truth for validation).
     *
     * @return list<string>
     */
    public function getProviderIds(): array;

    public function supportsProvider(string $provider): bool;

    /**
     * Executes AI completion with telemetry and intelligent mock fallback for testing.
     *
     * @param  array<string, mixed>  $options
     * @return array{content: string, provider: string, model: string, prompt_tokens: int, completion_tokens: int, latency_ms: int}
     */
    public function generateCompletion(string $systemPrompt, string $userPrompt, ?string $provider = null, array $options = []): array;
}
