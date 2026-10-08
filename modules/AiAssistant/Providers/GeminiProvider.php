<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Providers;

use Illuminate\Http\Client\Factory as HttpFactory;
use Modules\AiAssistant\Contracts\AiImageProviderInterface;
use Modules\AiAssistant\Contracts\AiProviderInterface;
use Modules\AiAssistant\DTOs\ChatRequest;
use Modules\AiAssistant\DTOs\CompletionResult;
use Modules\AiAssistant\DTOs\ImageGenerationRequest;
use Modules\AiAssistant\DTOs\ImageGenerationResult;
use Modules\AiAssistant\DTOs\ModelOption;
use Modules\AiAssistant\DTOs\ProviderDefinition;
use Modules\AiAssistant\Enums\AiEndpointFamily;
use Modules\AiAssistant\Exceptions\AiModelListingException;
use Modules\AiAssistant\Exceptions\AiProviderException;

final class GeminiProvider implements AiImageProviderInterface, AiProviderInterface
{
    private const DEFAULT_BASE_URL = 'https://generativelanguage.googleapis.com/v1beta';

    public function __construct(private readonly HttpFactory $http) {}

    public function definition(): ProviderDefinition
    {
        return new ProviderDefinition(
            id: 'gemini',
            label: 'Google Gemini',
            description: 'Modelos multimodales de Google AI Studio con ventana de contexto expansiva y generación de imágenes.',
            icon: 'fa-brands fa-google',
            badge: 'Recomendado',
            badgeColor: 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40',
            docUrl: 'https://aistudio.google.com/',
            defaultBaseUrl: self::DEFAULT_BASE_URL,
            modalities: ['text', 'image'],
        );
    }

    public function listModels(?string $apiKey, ?string $baseUrl = null): array
    {
        $response = $this->http->withOptions(['allow_redirects' => false])->timeout(20)->acceptJson()->withHeaders(['x-goog-api-key' => (string) $apiKey])
            ->get(rtrim((string) ($baseUrl ?: self::DEFAULT_BASE_URL), '/').'/models');

        if (! $response->successful()) {
            throw AiModelListingException::requestFailed('gemini', $response->status());
        }

        $rows = (array) $response->json('models', []);
        $models = [];

        foreach ($rows as $row) {
            if (! is_array($row) || empty($row['name'])) {
                continue;
            }

            $methods = (array) ($row['supportedGenerationMethods'] ?? []);
            if ($methods !== [] && ! in_array('generateContent', $methods, true)) {
                continue;
            }

            $id = str_replace('models/', '', (string) $row['name']);
            $models[] = new ModelOption(
                id: $id,
                label: (string) ($row['displayName'] ?? ucwords(str_replace('-', ' ', $id))),
                family: AiEndpointFamily::fromModelId($id),
            );
        }

        return $models;
    }

    public function chat(ChatRequest $request, string $apiKey, string $model, ?string $baseUrl = null): CompletionResult
    {
        $endpoint = rtrim((string) ($baseUrl ?: self::DEFAULT_BASE_URL), '/')."/models/{$model}:generateContent";

        $response = $this->http->withOptions(['allow_redirects' => false])->timeout(60)->acceptJson()->withHeaders(['x-goog-api-key' => $apiKey])->post($endpoint, [
            'system_instruction' => ['parts' => [['text' => $request->systemPrompt]]],
            'contents' => [['role' => 'user', 'parts' => [['text' => $request->userPrompt]]]],
            'generationConfig' => [
                'temperature' => $request->temperature,
                'responseMimeType' => $request->jsonMode ? 'application/json' : 'text/plain',
            ],
        ]);

        if (! $response->successful()) {
            throw AiProviderException::requestFailed('gemini', $response->status(), $response->body());
        }

        return new CompletionResult(
            content: (string) ($response->json('candidates.0.content.parts.0.text') ?? '{}'),
            provider: 'gemini',
            model: $model,
            promptTokens: (int) ($response->json('usageMetadata.promptTokenCount') ?? 0),
            completionTokens: (int) ($response->json('usageMetadata.candidatesTokenCount') ?? 0),
        );
    }

    public function generateImage(
        ImageGenerationRequest $request,
        string $apiKey,
        string $model,
        ?string $baseUrl = null
    ): ImageGenerationResult {
        if ($apiKey === '' || ! str_starts_with($model, 'gemini-') || (! str_contains($model, 'image') && ! str_contains($model, 'nano-banana'))) {
            throw new AiProviderException('Configure a key and select a supported native Gemini image model.');
        }
        if ($request->referenceImageUrl !== null) {
            throw new AiProviderException('Image references require the image-editing adapter.');
        }
        $endpoint = rtrim((string) ($baseUrl ?: self::DEFAULT_BASE_URL), '/').'/models/'.rawurlencode($model).':generateContent';
        $response = $this->http->withOptions(['allow_redirects' => false])->timeout(90)->withHeaders(['x-goog-api-key' => $apiKey])->acceptJson()->post($endpoint, [
            'contents' => [['parts' => [['text' => $request->prompt]]]],
            'generationConfig' => ['responseModalities' => ['TEXT', 'IMAGE'], 'imageConfig' => ['aspectRatio' => $request->aspectRatio]],
        ]);
        if (! $response->successful()) {
            throw AiProviderException::requestFailed('gemini', $response->status(), '');
        }
        foreach ((array) $response->json('candidates.0.content.parts', []) as $part) {
            $inline = is_array($part) ? ($part['inlineData'] ?? $part['inline_data'] ?? null) : null;
            if (! is_array($inline) || ! is_string($inline['data'] ?? null)) {
                continue;
            }
            $mime = (string) ($inline['mimeType'] ?? $inline['mime_type'] ?? '');
            $binary = base64_decode($inline['data'], true);
            if (! in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true) || $binary === false || $binary === '') {
                continue;
            }

            return new ImageGenerationResult(url: 'data:'.$mime.';base64,'.$inline['data'], provider: 'gemini', model: $model, binaryData: $binary, mimeType: $mime, prompt: $request->prompt);
        }
        throw new AiProviderException('Gemini completed without a generated image.');
    }

    public function upscaleImage(
        string $imageUrl,
        string $apiKey,
        array $options = [],
        ?string $baseUrl = null
    ): ImageGenerationResult {
        throw new AiProviderException('Gemini does not expose an upscaling operation through this adapter.');
    }
}
