<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Providers;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Modules\AiAssistant\Contracts\AiProviderInterface;
use Modules\AiAssistant\DTOs\ChatRequest;
use Modules\AiAssistant\DTOs\CompletionResult;
use Modules\AiAssistant\DTOs\ModelOption;
use Modules\AiAssistant\DTOs\ProviderDefinition;
use Modules\AiAssistant\Enums\AiEndpointFamily;
use Modules\AiAssistant\Exceptions\AiModelListingException;
use Modules\AiAssistant\Exceptions\AiProviderException;

final class AnthropicProvider implements AiProviderInterface
{
    private const DEFAULT_BASE_URL = 'https://api.anthropic.com/v1';

    private const API_VERSION = '2023-06-01';

    public function __construct(private readonly HttpFactory $http) {}

    public function definition(): ProviderDefinition
    {
        return new ProviderDefinition(
            id: 'anthropic',
            label: 'Anthropic Claude',
            description: 'Excelencia literaria, matices sutiles y la menor tendencia a respuestas cliché del mercado.',
            icon: 'fa-solid fa-pen-nib',
            badge: 'Claude Sonnet / Haiku',
            badgeColor: 'bg-amber-500/20 text-amber-300 border-amber-500/40',
            docUrl: 'https://console.anthropic.com/',
            defaultBaseUrl: self::DEFAULT_BASE_URL,
        );
    }

    public function listModels(?string $apiKey, ?string $baseUrl = null): array
    {
        $response = $this->headers((string) $apiKey)
            ->timeout(20)
            ->get(rtrim((string) ($baseUrl ?: self::DEFAULT_BASE_URL), '/').'/models');

        if (! $response->successful()) {
            throw AiModelListingException::requestFailed('anthropic', $response->status());
        }

        $models = [];
        foreach ((array) $response->json('data', []) as $row) {
            if (! is_array($row) || empty($row['id'])) {
                continue;
            }

            $id = (string) $row['id'];
            $models[] = new ModelOption(
                id: $id,
                label: (string) ($row['display_name'] ?? ucwords(str_replace('-', ' ', $id))),
                family: AiEndpointFamily::Messages,
            );
        }

        return $models;
    }

    public function chat(ChatRequest $request, string $apiKey, string $model, ?string $baseUrl = null): CompletionResult
    {
        $response = $this->headers($apiKey)
            ->timeout(60)
            ->post(rtrim((string) ($baseUrl ?: self::DEFAULT_BASE_URL), '/').'/messages', [
                'model' => $model,
                'system' => $request->systemPrompt,
                'max_tokens' => 4096,
                'temperature' => $request->temperature,
                'messages' => [['role' => 'user', 'content' => $request->userPrompt]],
            ]);

        if (! $response->successful()) {
            throw AiProviderException::requestFailed('anthropic', $response->status(), $response->body());
        }

        return new CompletionResult(
            content: (string) ($response->json('content.0.text') ?? '{}'),
            provider: 'anthropic',
            model: $model,
            promptTokens: (int) ($response->json('usage.input_tokens') ?? 0),
            completionTokens: (int) ($response->json('usage.output_tokens') ?? 0),
        );
    }

    private function headers(string $apiKey): PendingRequest
    {
        return $this->http->acceptJson()->withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => self::API_VERSION,
            'content-type' => 'application/json',
        ]);
    }
}
