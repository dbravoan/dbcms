<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Providers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Modules\AiAssistant\Contracts\AiProviderInterface;
use Modules\AiAssistant\Contracts\AiVideoProviderInterface;
use Modules\AiAssistant\DTOs\ChatRequest;
use Modules\AiAssistant\DTOs\CompletionResult;
use Modules\AiAssistant\DTOs\ModelOption;
use Modules\AiAssistant\DTOs\ProviderDefinition;
use Modules\AiAssistant\DTOs\VideoGenerationRequest;
use Modules\AiAssistant\DTOs\VideoGenerationResult;
use Modules\AiAssistant\Enums\AiEndpointFamily;
use Modules\AiAssistant\Exceptions\AiProviderException;
use Modules\AiAssistant\Exceptions\AiProviderPendingException;

final class HiggsfieldProvider implements AiProviderInterface, AiVideoProviderInterface
{
    private const MODEL = 'bytedance/seedance-2.0/text-to-video';

    public function __construct(private readonly HttpFactory $http) {}

    public function definition(): ProviderDefinition
    {
        return new ProviderDefinition(id: 'higgsfield', label: 'Higgsfield', description: 'Generación de vídeo asíncrona. Catálogo documentado; acceso de cuenta pendiente de verificar.', defaultBaseUrl: 'https://api.higgsfield.ai', supportsModelListing: false, modalities: ['video']);
    }

    public function listModels(?string $apiKey, ?string $baseUrl = null): array
    {
        return empty($apiKey) ? [] : [new ModelOption(self::MODEL, 'Seedance 2.0 · Text to video', family: AiEndpointFamily::ChatCompletions), new ModelOption('bytedance/seedance-2.0/image-to-video', 'Seedance 2.0 · Image to video', family: AiEndpointFamily::ChatCompletions)];
    }

    public function chat(ChatRequest $request, string $apiKey, string $model, ?string $baseUrl = null): CompletionResult
    {
        throw new AiProviderException('Higgsfield does not support text completions.');
    }

    public function generateVideo(VideoGenerationRequest $request, string $apiKey, string $model, ?string $baseUrl = null): VideoGenerationResult
    {
        if ($apiKey === '' || ! in_array($model, [self::MODEL, 'bytedance/seedance-2.0/image-to-video'], true)) {
            throw new AiProviderException('Configure a Higgsfield key and a supported, documented video model.');
        }
        if ($request->referenceImageUrl !== null && $model === self::MODEL) {
            throw new AiProviderException('The selected text-to-video model does not accept an image reference; select an image-to-video adapter.');
        }
        if ($model !== self::MODEL && $request->referenceImageUrl === null) {
            throw new AiProviderException('The selected image-to-video model requires an image reference.');
        }
        if ($request->durationSeconds < 4 || $request->durationSeconds > 15) {
            throw new AiProviderException('Seedance 2.0 duration must be between 4 and 15 seconds.');
        }
        $base = rtrim($baseUrl ?: (string) $this->definition()->defaultBaseUrl, '/');
        $http = $this->http->withOptions(['allow_redirects' => false])->timeout(30)->withHeaders(['Authorization' => 'Key '.$apiKey])->acceptJson();
        $requestId = (string) ($request->extraParameters['provider_request_id'] ?? '');
        if ($requestId === '') {
            $payload = [
                'prompt' => $request->prompt, 'duration' => $request->durationSeconds,
                'resolution' => '720p', 'aspect_ratio' => $request->aspectRatio, 'generate_audio' => false,
            ];
            if ($model !== self::MODEL) {
                unset($payload['aspect_ratio']);
                $payload['image_url'] = $request->referenceImageUrl;
            }
            $response = $http->post($base.'/'.$model, $payload);
            if (! $response->successful()) {
                throw AiProviderException::requestFailed('higgsfield', $response->status(), '');
            }
            $requestId = (string) $response->json('request_id', '');
            if ($requestId === '') {
                throw new AiProviderException('Higgsfield did not return a request identifier.');
            }
        }
        $deadline = microtime(true) + 35;
        for ($attempt = 0; $attempt < 15 && microtime(true) < $deadline; $attempt++) {
            try {
                $response = $http->get($base.'/requests/'.rawurlencode($requestId).'/status');
            } catch (ConnectionException) {
                throw new AiProviderPendingException('higgsfield', $requestId);
            }
            if (! $response->successful()) {
                if ($response->status() < 500 && ! in_array($response->status(), [408, 429], true)) {
                    throw AiProviderException::requestFailed('higgsfield', $response->status(), '');
                }
                throw new AiProviderPendingException('higgsfield', $requestId);
            }
            $status = strtolower((string) $response->json('status', ''));
            if (in_array($status, ['failed', 'nsfw', 'canceled', 'cancelled'], true)) {
                throw new AiProviderException('Higgsfield generation ended with status '.$status.'.');
            }
            if ($status === 'completed') {
                $url = (string) $response->json('video.url', '');
                if ($url === '') {
                    throw new AiProviderException('Higgsfield completed without a video.');
                }

                return new VideoGenerationResult(url: $url, provider: 'higgsfield', model: $model, durationSeconds: $request->durationSeconds, metadata: ['request_id' => $requestId]);
            }
            if (! app()->environment('testing')) {
                usleep(2000000);
            }
        }
        throw new AiProviderPendingException('higgsfield', $requestId);
    }
}
