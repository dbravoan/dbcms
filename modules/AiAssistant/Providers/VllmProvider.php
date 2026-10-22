<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Providers;

use Modules\AiAssistant\DTOs\ProviderDefinition;

/**
 * vLLM high-throughput inference engine (OpenAI-compatible `/v1` endpoint).
 *
 * Supports local (host.docker.internal:8000/v1) and remote GPU cluster endpoints
 * (e.g. AWS, RunPod, Lambda Labs, on-prem Kubernetes).
 *
 * Allows optional API key / bearer token for authenticated remote proxies.
 */
final class VllmProvider extends AbstractOpenAiCompatibleProvider
{
    private const DEFAULT_BASE_URL = 'http://host.docker.internal:8000/v1';

    public function definition(): ProviderDefinition
    {
        return new ProviderDefinition(
            id: 'vllm',
            label: 'vLLM (Local / Remoto)',
            description: 'Servidor de inferencia de ultra-alto rendimiento con PagedAttention. Conéctate a tus GPUs locales o clústeres remotos.',
            icon: 'fa-solid fa-microchip',
            badge: 'GPU Cluster · Local / Remoto',
            badgeColor: 'bg-cyan-500/20 text-cyan-300 border-cyan-500/40',
            docUrl: 'https://docs.vllm.ai/',
            defaultBaseUrl: self::DEFAULT_BASE_URL,
            requiresApiKey: false,
            modalities: ['text'],
        );
    }

    protected function baseUrl(?string $baseUrl): string
    {
        return rtrim((string) ($baseUrl ?: self::DEFAULT_BASE_URL), '/');
    }

    protected function chatTimeout(): int
    {
        return 300;
    }

    protected function listTimeout(): int
    {
        return 5;
    }

    protected function maxOutputTokens(): ?int
    {
        return null; // Let vLLM use its model context limit
    }
}
