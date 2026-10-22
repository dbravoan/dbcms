<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Providers;

use Modules\AiAssistant\DTOs\ProviderDefinition;

/**
 * Local / self-hosted OpenAI-compatible endpoints (vLLM, LMStudio, proxies).
 */
final class CustomOpenAiProvider extends AbstractOpenAiCompatibleProvider
{
    private const DEFAULT_BASE_URL = 'http://localhost:8000/v1';

    public function definition(): ProviderDefinition
    {
        return new ProviderDefinition(
            id: 'custom',
            label: 'Compatible OpenAI (vLLM / LMStudio)',
            description: 'Conecta endpoints locales compatibles con OpenAI (vLLM, LMStudio o proxies) sin enviar datos a la nube.',
            icon: 'fa-solid fa-server',
            badge: 'Self-Hosted',
            badgeColor: 'bg-purple-500/20 text-purple-300 border-purple-500/40',
            docUrl: 'https://platform.openai.com/docs/api-reference',
            defaultBaseUrl: self::DEFAULT_BASE_URL,
            requiresApiKey: false,
        );
    }

    protected function baseUrl(?string $baseUrl): string
    {
        return rtrim((string) ($baseUrl ?: self::DEFAULT_BASE_URL), '/');
    }

    /**
     * Self-hosted inference can take minutes for long completions.
     */
    protected function chatTimeout(): int
    {
        return 600;
    }

    /**
     * Keep settings pages responsive when the local endpoint is down.
     */
    protected function listTimeout(): int
    {
        return 5;
    }

    /**
     * Bound generation so a self-hosted model cannot ramble for minutes.
     */
    protected function maxOutputTokens(): int
    {
        return 2048;
    }
}
