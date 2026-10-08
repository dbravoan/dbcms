<?php

declare(strict_types=1);

use App\Core\Modules\ModuleManager;
use App\Models\CmsModule;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\AiAssistant\Contracts\AiConfigBridgeInterface;
use Modules\AiAssistant\Contracts\AiProviderRegistryInterface;
use Modules\AiAssistant\DTOs\ChatRequest;
use Modules\AiAssistant\DTOs\ImageGenerationRequest;
use Modules\AiAssistant\DTOs\VideoGenerationRequest;
use Modules\AiAssistant\Providers\HiggsfieldProvider;
use Modules\AiAssistant\Providers\MagnificProvider;
use Modules\AiAssistant\Providers\VllmProvider;

uses(RefreshDatabase::class);

const VLLM_BASE = 'http://host.docker.internal:8000/v1';

beforeEach(function (): void {
    Http::preventStrayRequests();
    $role = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
    $user = User::factory()->create(['email' => 'admin@db-cms.io']);
    $user->roles()->syncWithoutDetaching([$role->id]);

    app(ModuleManager::class)->discoverModules();
    CmsModule::updateOrCreate(['identifier' => 'ai_assistant'], ['is_active' => true, 'is_installed' => true]);

    Cache::forget('ai_assistant:models:vllm');
    Cache::forget('ai_assistant:models:magnific');
    Cache::forget('ai_assistant:models:higgsfield');
});

test('vllm provider is registered and has proper definition', function (): void {
    $bridge = app(AiConfigBridgeInterface::class);

    expect($bridge->getProviderIds())->toContain('vllm');

    $catalog = $bridge->getProvidersCatalog();
    expect($catalog['vllm']['name'])->toBe('vLLM (Local / Remoto)')
        ->and($catalog['vllm']['requires_api_key'])->toBeFalse()
        ->and($catalog['vllm']['default_base_url'])->toContain('8000');
});

test('vllm lists models dynamically from endpoint', function (): void {
    Http::fake([
        'host.docker.internal:8000/v1/models' => Http::response([
            'object' => 'list',
            'data' => [
                ['id' => 'mistralai/Mistral-7B-Instruct-v0.3', 'object' => 'model'],
                ['id' => 'meta-llama/Llama-3.1-8B-Instruct', 'object' => 'model'],
            ],
        ]),
    ]);

    /** @var VllmProvider $vllm */
    $vllm = app(AiProviderRegistryInterface::class)->resolve('vllm');
    $models = $vllm->listModels(null, VLLM_BASE);

    expect($models)->toHaveCount(2)
        ->and($models[0]->id)->toBe('mistralai/Mistral-7B-Instruct-v0.3')
        ->and($models[1]->id)->toBe('meta-llama/Llama-3.1-8B-Instruct');
});

test('vllm executes chat completion', function (): void {
    Http::fake([
        'host.docker.internal:8000/v1/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => 'Respuesta desde cluster vLLM.',
                    ],
                ],
            ],
            'usage' => [
                'prompt_tokens' => 12,
                'completion_tokens' => 8,
            ],
        ]),
    ]);

    /** @var VllmProvider $vllm */
    $vllm = app(AiProviderRegistryInterface::class)->resolve('vllm');
    $result = $vllm->chat(
        new ChatRequest('Eres un asistente útil.', 'Hola mundo', 0.7),
        '',
        'meta-llama/Llama-3.1-8B-Instruct',
        VLLM_BASE
    );

    expect($result->content)->toBe('Respuesta desde cluster vLLM.')
        ->and($result->provider)->toBe('vllm');
});

test('magnific provider generates image and video with mock response', function (): void {
    Http::fake([
        'api.magnific.com/v1/ai/mystic' => Http::response(['data' => ['task_id' => 'image-task']]),
        'api.magnific.com/v1/ai/mystic/image-task' => Http::response(['data' => ['status' => 'COMPLETED', 'generated' => ['https://cdn.magnific.ai/creations/sample_hero.webp']]]),
        'api.magnific.com/v1/ai/text-to-video/ltx-2-pro' => Http::response(['data' => ['task_id' => 'video-task']]),
        'api.magnific.com/v1/ai/text-to-video/ltx-2-pro/video-task' => Http::response(['data' => ['status' => 'COMPLETED', 'generated' => ['https://cdn.magnific.ai/creations/sample_clip.mp4']]]),
    ]);

    /** @var MagnificProvider $magnific */
    $magnific = app(AiProviderRegistryInterface::class)->resolve('magnific');

    $imageResult = $magnific->generateImage(
        new ImageGenerationRequest('Futuristic hypercar in studio lighting'),
        'fake-magnific-key',
        'realism'
    );

    expect($imageResult->url)->toBe('https://cdn.magnific.ai/creations/sample_hero.webp')
        ->and($imageResult->provider)->toBe('magnific');

    $videoResult = $magnific->generateVideo(
        new VideoGenerationRequest('Camera orbit around hypercar', durationSeconds: 6),
        'fake-magnific-key',
        'ltx-2-pro'
    );

    expect($videoResult->url)->toBe('https://cdn.magnific.ai/creations/sample_clip.mp4')
        ->and($videoResult->provider)->toBe('magnific');
});

test('higgsfield provider generates video with DoP motion', function (): void {
    Http::fake([
        'api.higgsfield.ai/bytedance/seedance-2.0/text-to-video' => Http::response(['request_id' => 'video-task'], 202),
        'api.higgsfield.ai/requests/video-task/status' => Http::response(['status' => 'completed', 'video' => ['url' => 'https://cdn.higgsfield.ai/renders/cinematic_teaser.mp4']]),
    ]);

    /** @var HiggsfieldProvider $higgsfield */
    $higgsfield = app(AiProviderRegistryInterface::class)->resolve('higgsfield');

    $videoResult = $higgsfield->generateVideo(
        new VideoGenerationRequest(
            prompt: 'Slow dolly into cybernetic laboratory',
            cameraMotion: 'cinematic_push',
            durationSeconds: 6
        ),
        'fake-higgsfield-key',
        'bytedance/seedance-2.0/text-to-video'
    );

    expect($videoResult->url)->toBe('https://cdn.higgsfield.ai/renders/cinematic_teaser.mp4')
        ->and($videoResult->provider)->toBe('higgsfield');
});

test('registry and bridge resolve providers by modality', function (): void {
    /** @var AiProviderRegistryInterface $registry */
    $registry = app(AiProviderRegistryInterface::class);

    $textProviders = $registry->idsForModality('text');
    expect($textProviders)->toContain('gemini', 'openai', 'vllm', 'ollama');

    $imageProviders = $registry->idsForModality('image');
    expect($imageProviders)->toContain('magnific', 'gemini', 'openai');

    $videoProviders = $registry->idsForModality('video');
    expect($videoProviders)->toContain('higgsfield', 'magnific');

    /** @var AiConfigBridgeInterface $bridge */
    $bridge = app(AiConfigBridgeInterface::class);
    $matrix = $bridge->getModalitiesMatrix();

    expect($matrix)->toHaveKeys(['text', 'image', 'video'])
        ->and($matrix['text']['active_provider'])->toBe('gemini')
        ->and($matrix['image']['active_provider'])->toBe('openai')
        ->and($matrix['video']['active_provider'])->toBe('higgsfield');
});
