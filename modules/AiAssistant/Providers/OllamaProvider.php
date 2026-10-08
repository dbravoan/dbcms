<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Providers;

use Modules\AiAssistant\DTOs\ProviderDefinition;

/**
 * Ollama local runtime (OpenAI-compatible `/v1` endpoint).
 *
 * Ollama runs on the host machine. From the Sail container it is reached
 * through `host.docker.internal` (see the `extra_hosts` entry in
 * docker-compose.yml), so the default base URL already points there.
 *
 * No API key is required. The model catalog is exposed only when the endpoint
 * is actually reachable: there is no hardcoded fallback list.
 */
final class OllamaProvider extends AbstractOpenAiCompatibleProvider
{
    private const DEFAULT_BASE_URL = 'http://host.docker.internal:11434/v1';

    public function definition(): ProviderDefinition
    {
        return new ProviderDefinition(
            id: 'ollama',
            label: 'Ollama (Local)',
            description: 'Ejecuta modelos open-source (Llama, Qwen, Mistral...) en tu propia máquina o servidor remoto.',
            icon: 'fa-solid fa-server',
            badge: 'Local · Sin coste',
            badgeColor: 'bg-teal-500/20 text-teal-300 border-teal-500/40',
            docUrl: 'https://ollama.com/',
            defaultBaseUrl: self::DEFAULT_BASE_URL,
            requiresApiKey: false,
            modalities: ['text'],
        );
    }

    protected function baseUrl(?string $baseUrl): string
    {
        return rtrim((string) ($baseUrl ?: self::DEFAULT_BASE_URL), '/');
    }

    /**
     * Local inference on CPU can take minutes for long completions.
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
     * Bound generation so a small local model cannot ramble for minutes.
     */
    protected function maxOutputTokens(): int
    {
        return 2048;
    }
}
