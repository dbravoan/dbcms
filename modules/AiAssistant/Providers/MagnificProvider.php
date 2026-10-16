<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Providers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Sleep;
use Modules\AiAssistant\Contracts\AiImageProviderInterface;
use Modules\AiAssistant\Contracts\AiProviderInterface;
use Modules\AiAssistant\Contracts\AiVideoProviderInterface;
use Modules\AiAssistant\DTOs\ChatRequest;
use Modules\AiAssistant\DTOs\CompletionResult;
use Modules\AiAssistant\DTOs\ImageGenerationRequest;
use Modules\AiAssistant\DTOs\ImageGenerationResult;
use Modules\AiAssistant\DTOs\ModelOption;
use Modules\AiAssistant\DTOs\ProviderDefinition;
use Modules\AiAssistant\DTOs\VideoGenerationRequest;
use Modules\AiAssistant\DTOs\VideoGenerationResult;
use Modules\AiAssistant\Exceptions\AiProviderException;
use Modules\AiAssistant\Exceptions\AiProviderPendingException;

final class MagnificProvider implements AiImageProviderInterface, AiProviderInterface, AiVideoProviderInterface
{
    private const IMAGE_MODELS = ['realism', 'fluid', 'zen', 'flexible', 'super_real', 'editorial_portraits'];

    public function __construct(private readonly HttpFactory $http) {}

    public function definition(): ProviderDefinition
    {
        return new ProviderDefinition(id: 'magnific', label: 'Magnific', description: 'Mystic y LTX 2.0 Pro. Catálogo documentado; acceso de cuenta pendiente de verificar.', defaultBaseUrl: 'https://api.magnific.com', supportsModelListing: false, modalities: ['image', 'video'], docUrl: 'https://docs.magnific.com/llms.txt');
    }

    public function listModels(?string $apiKey, ?string $baseUrl = null): array
    {
        if (empty($apiKey)) {
            return [];
        }

        return [...array_map(static fn (string $model): ModelOption => new ModelOption($model, 'Mystic · '.$model), self::IMAGE_MODELS), new ModelOption('ltx-2-pro', 'LTX 2.0 Pro · Text to video')];
    }

    public function chat(ChatRequest $request, string $apiKey, string $model, ?string $baseUrl = null): CompletionResult
    {
        throw new AiProviderException('Magnific does not support text completions.');
    }

    public function generateImage(ImageGenerationRequest $request, string $apiKey, string $model, ?string $baseUrl = null): ImageGenerationResult
    {
        if (! in_array($model, self::IMAGE_MODELS, true)) {
            throw new AiProviderException('Select a supported Mystic image model.');
        }
        if ($request->referenceImageUrl !== null) {
            throw new AiProviderException('Mystic requires a base64 structure reference; URL references are unsupported by this adapter.');
        }
        $ratios = ['1:1' => 'square_1_1', '16:9' => 'widescreen_16_9', '9:16' => 'social_story_9_16', '4:3' => 'classic_4_3', '3:4' => 'traditional_3_4'];
        if (! isset($ratios[$request->aspectRatio])) {
            throw new AiProviderException('Unsupported Mystic aspect ratio.');
        }
        $result = $this->submitAndPoll('/v1/ai/mystic', ['prompt' => $request->prompt, 'model' => $model, 'aspect_ratio' => $ratios[$request->aspectRatio], 'resolution' => '2k', 'filter_nsfw' => true], $apiKey, $baseUrl, $request->extraParameters);

        return new ImageGenerationResult(url: $result['url'], provider: 'magnific', model: $model, prompt: $request->prompt, metadata: ['request_id' => $result['request_id']]);
    }

    public function upscaleImage(string $imageUrl, string $apiKey, array $options = [], ?string $baseUrl = null): ImageGenerationResult
    {
        throw new AiProviderException('Creative upscaling requires its dedicated documented adapter; it is not exposed by the image-generation contract.');
    }

    public function generateVideo(VideoGenerationRequest $request, string $apiKey, string $model, ?string $baseUrl = null): VideoGenerationResult
    {
        if ($model !== 'ltx-2-pro' || ! in_array($request->durationSeconds, [6, 8, 10], true) || $request->referenceImageUrl !== null) {
            throw new AiProviderException('LTX 2.0 Pro text-to-video requires duration 6, 8 or 10 and does not accept an image reference.');
        }
        $result = $this->submitAndPoll('/v1/ai/text-to-video/ltx-2-pro', ['prompt' => $request->prompt, 'duration' => $request->durationSeconds, 'resolution' => '1080p', 'fps' => 25, 'generate_audio' => false], $apiKey, $baseUrl, $request->extraParameters);

        return new VideoGenerationResult(url: $result['url'], provider: 'magnific', model: $model, durationSeconds: $request->durationSeconds, metadata: ['request_id' => $result['request_id']]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $options
     * @return array{url: string, request_id: string}
     */
    private function submitAndPoll(string $path, array $payload, string $apiKey, ?string $baseUrl, array $options): array
    {
        if ($apiKey === '') {
            throw new AiProviderException('Magnific is not configured.');
        }
        $url = rtrim($baseUrl ?: (string) $this->definition()->defaultBaseUrl, '/').$path;
        $http = $this->http->withOptions(['allow_redirects' => false])->timeout(30)->withHeaders(['x-magnific-api-key' => $apiKey])->acceptJson();
        $requestId = (string) ($options['provider_request_id'] ?? '');
        if ($requestId === '') {
            $response = $http->post($url, $payload);
            if (! $response->successful()) {
                throw AiProviderException::requestFailed('magnific', $response->status(), '');
            }
            $requestId = (string) $response->json('data.task_id', '');
            if ($requestId === '') {
                throw new AiProviderException('Magnific did not return a task identifier.');
            }
        }
        $deadline = microtime(true) + 35;
        for ($attempt = 0; $attempt < 15 && microtime(true) < $deadline; $attempt++) {
            try {
                $response = $http->get($url.'/'.rawurlencode($requestId));
            } catch (ConnectionException) {
                throw new AiProviderPendingException('magnific', $requestId);
            }
            if (! $response->successful()) {
                if ($response->status() < 500 && ! in_array($response->status(), [408, 429], true)) {
                    throw AiProviderException::requestFailed('magnific', $response->status(), '');
                }
                throw new AiProviderPendingException('magnific', $requestId);
            }
            $status = strtoupper((string) $response->json('data.status', ''));
            if (in_array($status, ['FAILED', 'ERROR', 'CANCELLED', 'CANCELED'], true)) {
                throw new AiProviderException('Magnific generation ended with status '.$status.'.');
            }
            if ($status === 'COMPLETED') {
                $generated = (array) $response->json('data.generated', []);
                $output = $generated[0] ?? null;
                if (! is_string($output) || filter_var($output, FILTER_VALIDATE_URL) === false) {
                    throw new AiProviderException('Magnific completed without a generated file.');
                }

                return ['url' => $output, 'request_id' => $requestId];
            }
            Sleep::for(2)->seconds();
        }
        throw new AiProviderPendingException('magnific', $requestId);
    }
}
