<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Providers;

use Illuminate\Http\Client\Factory as HttpFactory;
use Modules\AiAssistant\Contracts\AiProviderInterface;
use Modules\AiAssistant\DTOs\ChatRequest;
use Modules\AiAssistant\DTOs\CompletionResult;
use Modules\AiAssistant\DTOs\ModelOption;
use Modules\AiAssistant\DTOs\ProviderDefinition;
use Modules\AiAssistant\Enums\AiEndpointFamily;
use Modules\AiAssistant\Exceptions\AiModelListingException;
use Modules\AiAssistant\Exceptions\AiProviderException;

/**
 * OpenCode gateway adapter.
 *
 * A single API key unlocks both catalogs: Zen (pay-as-you-go) and Go
 * (subscription). Models are merged and each one keeps the base URL it
 * belongs to. The HTTP contract is derived from the model family because
 * Zen/Go expose chat, Anthropic, OpenAI-Responses and Google shapes.
 */
final class OpenCodeProvider implements AiProviderInterface
{
    private const ZEN_BASE_URL = 'https://opencode.ai/zen/v1';

    private const GO_BASE_URL = 'https://opencode.ai/zen/go/v1';

    private const ANTHROPIC_VERSION = '2023-06-01';

    public function __construct(private readonly HttpFactory $http) {}

    public function definition(): ProviderDefinition
    {
        return new ProviderDefinition(
            id: 'opencode',
            label: 'OpenCode (Zen + Go)',
            description: 'Gateway curado de OpenCode: DeepSeek, GLM, Kimi, Qwen, MiniMax, Claude, GPT y más con una sola API key.',
            icon: 'fa-solid fa-bolt-lightning',
            badge: 'Zen + Go',
            badgeColor: 'bg-orange-500/20 text-orange-300 border-orange-500/40',
            docUrl: 'https://opencode.ai/docs/zen/',
            defaultBaseUrl: self::ZEN_BASE_URL,
            sourceBaseUrls: [
                'zen' => self::ZEN_BASE_URL,
                'go' => self::GO_BASE_URL,
            ],
        );
    }

    public function listModels(?string $apiKey, ?string $baseUrl = null): array
    {
        $sources = ['zen' => self::ZEN_BASE_URL, 'go' => self::GO_BASE_URL];
        $models = [];
        $seen = [];

        foreach ($sources as $source => $url) {
            try {
                $ids = $this->fetchModelIds((string) $apiKey, $url);
            } catch (\Throwable) {
                // Go may not be subscribed for a given key: skip it gracefully.
                continue;
            }

            foreach ($ids as $id) {
                $family = AiEndpointFamily::fromModelId($id);
                if (isset($seen[$id]) || ! $family->isConversational()) {
                    continue;
                }

                $seen[$id] = true;
                $models[] = new ModelOption(
                    id: $id,
                    label: $this->labelFromId($id),
                    source: $source,
                    family: $family,
                    experimental: $family === AiEndpointFamily::Google,
                );
            }
        }

        if ($models === []) {
            throw AiModelListingException::requestFailed('opencode', 0);
        }

        return $models;
    }

    public function chat(ChatRequest $request, string $apiKey, string $model, ?string $baseUrl = null): CompletionResult
    {
        $base = rtrim((string) ($baseUrl ?: self::ZEN_BASE_URL), '/');

        return match (AiEndpointFamily::fromModelId($model)) {
            AiEndpointFamily::ChatCompletions => $this->chatCompletions($request, $apiKey, $base, $model),
            AiEndpointFamily::Messages => $this->chatMessages($request, $apiKey, $base, $model),
            AiEndpointFamily::Responses => $this->chatResponses($request, $apiKey, $base, $model),
            AiEndpointFamily::Google => $this->chatGoogle($request, $apiKey, $base, $model),
            AiEndpointFamily::SystemOne => throw new AiProviderException("El modelo [{$model}] no es conversacional (SystemOne)."),
        };
    }

    /**
     * @return list<string>
     */
    private function fetchModelIds(string $apiKey, string $baseUrl): array
    {
        $request = $this->http->timeout(20)->acceptJson();
        if ($apiKey !== '') {
            $request = $request->withToken($apiKey);
        }

        $response = $request->get(rtrim($baseUrl, '/').'/models');

        if (! $response->successful()) {
            throw AiModelListingException::requestFailed('opencode', $response->status());
        }

        return array_values(array_filter(array_map(
            static fn (mixed $row): ?string => is_array($row) && ! empty($row['id']) ? (string) $row['id'] : null,
            (array) $response->json('data', [])
        )));
    }

    private function chatCompletions(ChatRequest $request, string $apiKey, string $base, string $model): CompletionResult
    {
        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $request->systemPrompt],
                ['role' => 'user', 'content' => $request->userPrompt],
            ],
            'temperature' => $request->temperature,
        ];

        if ($request->jsonMode) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $response = $this->http->withToken($apiKey)->timeout(60)->acceptJson()
            ->post($base.'/chat/completions', $payload);

        if (! $response->successful()) {
            throw AiProviderException::requestFailed('opencode', $response->status(), $response->body());
        }

        return new CompletionResult(
            content: (string) ($response->json('choices.0.message.content') ?? '{}'),
            provider: 'opencode',
            model: $model,
            promptTokens: (int) ($response->json('usage.prompt_tokens') ?? 0),
            completionTokens: (int) ($response->json('usage.completion_tokens') ?? 0),
        );
    }

    private function chatMessages(ChatRequest $request, string $apiKey, string $base, string $model): CompletionResult
    {
        $response = $this->http->timeout(60)
            ->withHeaders([
                'Authorization' => 'Bearer '.$apiKey,
                'x-api-key' => $apiKey,
                'anthropic-version' => self::ANTHROPIC_VERSION,
                'content-type' => 'application/json',
            ])
            ->post($base.'/messages', [
                'model' => $model,
                'system' => $request->systemPrompt,
                'max_tokens' => 4096,
                'temperature' => $request->temperature,
                'messages' => [['role' => 'user', 'content' => $request->userPrompt]],
            ]);

        if (! $response->successful()) {
            throw AiProviderException::requestFailed('opencode', $response->status(), $response->body());
        }

        return new CompletionResult(
            content: (string) ($response->json('content.0.text') ?? '{}'),
            provider: 'opencode',
            model: $model,
            promptTokens: (int) ($response->json('usage.input_tokens') ?? 0),
            completionTokens: (int) ($response->json('usage.output_tokens') ?? 0),
        );
    }

    private function chatResponses(ChatRequest $request, string $apiKey, string $base, string $model): CompletionResult
    {
        $response = $this->http->withToken($apiKey)->timeout(60)->acceptJson()
            ->post($base.'/responses', [
                'model' => $model,
                'input' => [
                    ['role' => 'system', 'content' => $request->systemPrompt],
                    ['role' => 'user', 'content' => $request->userPrompt],
                ],
                'temperature' => $request->temperature,
            ]);

        if (! $response->successful()) {
            throw AiProviderException::requestFailed('opencode', $response->status(), $response->body());
        }

        return new CompletionResult(
            content: $this->extractResponsesText($response->json()),
            provider: 'opencode',
            model: $model,
            promptTokens: (int) ($response->json('usage.input_tokens') ?? 0),
            completionTokens: (int) ($response->json('usage.output_tokens') ?? 0),
        );
    }

    private function chatGoogle(ChatRequest $request, string $apiKey, string $base, string $model): CompletionResult
    {
        $response = $this->http->withToken($apiKey)->timeout(60)->acceptJson()
            ->post($base."/models/{$model}:generateContent", [
                'system_instruction' => ['parts' => [['text' => $request->systemPrompt]]],
                'contents' => [['role' => 'user', 'parts' => [['text' => $request->userPrompt]]]],
                'generationConfig' => [
                    'temperature' => $request->temperature,
                    'responseMimeType' => $request->jsonMode ? 'application/json' : 'text/plain',
                ],
            ]);

        if (! $response->successful()) {
            throw AiProviderException::requestFailed('opencode', $response->status(), $response->body());
        }

        return new CompletionResult(
            content: (string) ($response->json('candidates.0.content.parts.0.text') ?? '{}'),
            provider: 'opencode',
            model: $model,
            promptTokens: (int) ($response->json('usageMetadata.promptTokenCount') ?? 0),
            completionTokens: (int) ($response->json('usageMetadata.candidatesTokenCount') ?? 0),
        );
    }

    private function extractResponsesText(mixed $payload): string
    {
        if (! is_array($payload)) {
            return '{}';
        }

        $text = '';
        foreach ((array) ($payload['output'] ?? []) as $item) {
            foreach ((array) ($item['content'] ?? []) as $part) {
                if (($part['type'] ?? '') === 'output_text') {
                    $text .= (string) ($part['text'] ?? '');
                }
            }
        }

        if ($text === '' && isset($payload['output_text'])) {
            $text = (string) $payload['output_text'];
        }

        return $text !== '' ? $text : '{}';
    }

    private function labelFromId(string $id): string
    {
        return ucwords(str_replace(['-', '.', '_'], ' ', $id));
    }
}
