<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Providers;

use Illuminate\Http\Client\Factory as HttpFactory;
use Modules\AiAssistant\Contracts\AiProviderInterface;
use Modules\AiAssistant\DTOs\ChatRequest;
use Modules\AiAssistant\DTOs\CompletionResult;
use Modules\AiAssistant\DTOs\ModelOption;
use Modules\AiAssistant\Enums\AiEndpointFamily;
use Modules\AiAssistant\Exceptions\AiModelListingException;
use Modules\AiAssistant\Exceptions\AiProviderException;

/**
 * Base adapter for any OpenAI-compatible HTTP API (`/models` + `/chat/completions`).
 */
abstract class AbstractOpenAiCompatibleProvider implements AiProviderInterface
{
    public function __construct(protected readonly HttpFactory $http) {}

    public function listModels(?string $apiKey, ?string $baseUrl = null): array
    {
        $endpoint = $this->baseUrl($baseUrl).'/models';

        $request = $this->http->withOptions(['allow_redirects' => false])->timeout($this->listTimeout())->acceptJson();
        if (! empty($apiKey)) {
            $request = $request->withToken($apiKey);
        }

        $response = $request->get($endpoint);

        if (! $response->successful()) {
            throw AiModelListingException::requestFailed($this->definition()->id, $response->status());
        }

        $rows = (array) $response->json('data', []);

        return array_values(array_filter(array_map(function (mixed $row): ?ModelOption {
            if (! is_array($row) || empty($row['id'])) {
                return null;
            }

            $id = (string) $row['id'];

            return new ModelOption(
                id: $id,
                label: $this->labelFromId($id),
                family: AiEndpointFamily::fromModelId($id),
            );
        }, $rows)));
    }

    public function chat(ChatRequest $request, string $apiKey, string $model, ?string $baseUrl = null): CompletionResult
    {
        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $request->systemPrompt],
                ['role' => 'user', 'content' => $request->userPrompt],
            ],
            'temperature' => $request->temperature,
        ];

        if ($this->maxOutputTokens() !== null) {
            $payload['max_tokens'] = $this->maxOutputTokens();
        }

        if ($request->jsonMode) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $http = $this->http->withOptions(['allow_redirects' => false])->timeout($this->chatTimeout())->acceptJson();
        if (! empty($apiKey)) {
            $http = $http->withToken($apiKey);
        }

        $response = $http->post($this->baseUrl($baseUrl).'/chat/completions', $payload);

        if (! $response->successful()) {
            throw AiProviderException::requestFailed($this->definition()->id, $response->status(), $response->body());
        }

        return new CompletionResult(
            content: (string) ($response->json('choices.0.message.content') ?? '{}'),
            provider: $this->definition()->id,
            model: $model,
            promptTokens: (int) ($response->json('usage.prompt_tokens') ?? 0),
            completionTokens: (int) ($response->json('usage.completion_tokens') ?? 0),
        );
    }

    protected function baseUrl(?string $baseUrl): string
    {
        $resolved = $baseUrl ?: $this->definition()->defaultBaseUrl;

        return rtrim((string) $resolved, '/');
    }

    /**
     * Request timeout (seconds) for chat completions.
     *
     * Local/self-hosted runtimes (Ollama, vLLM, LMStudio) can be much slower
     * than cloud APIs, so subclasses may extend this.
     */
    protected function chatTimeout(): int
    {
        return 60;
    }

    /**
     * Request timeout (seconds) for the model listing endpoint.
     */
    protected function listTimeout(): int
    {
        return 20;
    }

    /**
     * Maximum output tokens. Cloud APIs stop on their own; local runtimes can
     * ramble until the context window is exhausted, so subclasses may cap it.
     */
    protected function maxOutputTokens(): ?int
    {
        return null;
    }

    protected function labelFromId(string $id): string
    {
        return ucwords(str_replace(['-', '_', '/'], ' ', $id));
    }
}
