<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Providers;

use Modules\AiAssistant\Contracts\AiImageProviderInterface;
use Modules\AiAssistant\DTOs\ImageGenerationRequest;
use Modules\AiAssistant\DTOs\ImageGenerationResult;
use Modules\AiAssistant\DTOs\ProviderDefinition;
use Modules\AiAssistant\Exceptions\AiProviderException;

final class OpenAiProvider extends AbstractOpenAiCompatibleProvider implements AiImageProviderInterface
{
    public function definition(): ProviderDefinition
    {
        return new ProviderDefinition(
            id: 'openai',
            label: 'OpenAI',
            description: 'Modelos conversacionales y generación visual GPT Image.',
            icon: 'fa-solid fa-cube',
            badge: 'Texto & GPT Image',
            badgeColor: 'bg-cyan-500/20 text-cyan-300 border-cyan-500/40',
            docUrl: 'https://platform.openai.com/api-keys',
            defaultBaseUrl: 'https://api.openai.com/v1',
            modalities: ['text', 'image'],
        );
    }

    public function generateImage(
        ImageGenerationRequest $request,
        string $apiKey,
        string $model,
        ?string $baseUrl = null
    ): ImageGenerationResult {
        if ($apiKey === '' || (! str_starts_with($model, 'gpt-image-') && $model !== 'chatgpt-image-latest')) {
            throw new AiProviderException('Configure an API key and select a GPT Image model.');
        }
        if ($request->referenceImageUrl !== null) {
            throw new AiProviderException('Image references require the image-editing adapter.');
        }
        $endpoint = rtrim((string) ($baseUrl ?: 'https://api.openai.com/v1'), '/').'/images/generations';

        $response = $this->http->withOptions(['allow_redirects' => false])->timeout(90)->withToken($apiKey)->acceptJson()->post($endpoint, [
            'model' => $model,
            'prompt' => $request->prompt,
            'n' => 1,
            'size' => match ($request->aspectRatio) {
                '1:1' => '1024x1024', '9:16', '3:4' => '1024x1536', default => '1536x1024'
            },
            'quality' => 'auto',
            'output_format' => 'png',
        ]);

        if (! $response->successful()) {
            throw AiProviderException::requestFailed('openai', $response->status(), $response->body());
        }

        $encoded = (string) $response->json('data.0.b64_json', '');
        $binary = base64_decode($encoded, true);
        if ($encoded === '' || $binary === false || $binary === '') {
            throw new AiProviderException('OpenAI completed without a generated image.');
        }

        return new ImageGenerationResult(
            url: 'data:image/png;base64,'.$encoded,
            provider: 'openai',
            model: $model,
            binaryData: $binary,
            mimeType: 'image/png',
            prompt: $request->prompt,
            metadata: ['usage' => (array) $response->json('usage', []), 'trace_id' => (string) $response->header('x-request-id')],
        );
    }
}
