<?php

declare(strict_types=1);

namespace Tests\Feature\AiAssistant;

use App\Models\Language;
use App\Models\Market;
use App\Models\Role;
use App\Models\User;
use App\Services\Media\MediaManagerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Modules\AiAssistant\Contracts\AiConfigBridgeInterface;
use Modules\AiAssistant\Contracts\AiMultimodalOrchestratorInterface;
use Modules\AiAssistant\DTOs\MultimodalOrchestrationRequest;
use Modules\AiAssistant\DTOs\MultimodalOrchestrationResult;
use Modules\AiAssistant\Exceptions\AiProviderPendingException;
use Modules\AiAssistant\Services\AiActionRunner;
use Modules\AiAssistant\Services\AiResearchService;
use Modules\AiAssistant\Services\BlockIntelligenceService;
use Modules\Blog\Models\Post;
use Tests\Support\AiBlogComposition;
use Tests\Support\AiCompletionFixture;

uses(RefreshDatabase::class, AiBlogComposition::class);

beforeEach(function (): void {
    Sleep::fake();
    Http::preventStrayRequests();
    $role = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
    $user = User::factory()->create(['email' => 'admin@db-cms.io']);
    $user->roles()->syncWithoutDetaching([$role->id]);

    Market::firstOrCreate(
        ['code' => 'es'],
        ['name' => 'España', 'currency' => 'EUR', 'default_locale' => 'es', 'is_default' => true, 'is_active' => true]
    );

    config([
        'services.magnific.key' => 'test-magnific-key',
    ]);

    app(AiConfigBridgeInterface::class)->saveProviderConfig('higgsfield', ['api_key_id' => 'fixture-id', 'api_key_secret' => 'fixture-secret']);

    Cache::forget('ai_assistant:models:vllm');
    Cache::forget('ai_assistant:models:magnific');
    Cache::forget('ai_assistant:models:higgsfield');
});

test('block intelligence service detects block types from natural language intent', function (): void {
    $service = app(BlockIntelligenceService::class);

    expect($service->detectBlockTypeFromIntent('crea un bloque de ROI para calcular el ahorro energético'))->toBe('roi')
        ->and($service->detectBlockTypeFromIntent('añade un hero con imagen impactante y título principal'))->toBe('hero')
        ->and($service->detectBlockTypeFromIntent('inserta un vídeo cinemático con dron'))->toBe('video')
        ->and($service->detectBlockTypeFromIntent('muestra una cuadrícula de características y beneficios'))->toBe('features')
        ->and($service->detectBlockTypeFromIntent('añade preguntas frecuentes con respuestas desplegables'))->toBe('faq')
        ->and($service->detectBlockTypeFromIntent('tabla comparativa de precios y planes'))->toBe('pricing')
        ->and($service->detectBlockTypeFromIntent('slider de testimonios de clientes satisfechos'))->toBe('testimonials');
});

test('ai research service searches web and synthesizes grounded topic context', function (): void {
    Http::fake([
        'html.duckduckgo.com/*' => Http::response(
            '<html><body><a class="result__snippet" href="https://laravel.com">Laravel es un framework web PHP para artesanos.</a><a class="result__snippet" href="https://php.net">PHP 8.3 aporta mejoras de rendimiento.</a></body></html>',
            200
        ),
    ]);

    $researchService = app(AiResearchService::class);
    $result = $researchService->researchTopic('Novedades Laravel 11', 'es', 'es');

    expect($result)->toBeArray()
        ->and($result['topic'])->toBe('Novedades Laravel 11')
        ->and($result['claims'])->toBeArray()
        ->and($result['context'])->toBeString()
        ->and(count($result['claims']))->toBeGreaterThanOrEqual(1);
});

test('multimodal orchestrator generates image and saves it to media manager', function (): void {
    Http::fake([
        'api.magnific.com/v1/ai/mystic' => Http::response(['data' => ['task_id' => 'visual-task']]),
        'api.magnific.com/v1/ai/mystic/visual-task' => Http::response(['data' => ['status' => 'COMPLETED', 'generated' => ['https://cdn.magnific.ai/generated/render_999.png']]], 200),
        'cdn.magnific.ai/*' => Http::response('fake-png-binary-content', 200, ['Content-Type' => 'image/png']),
    ]);

    $mediaMock = \Mockery::mock(MediaManagerService::class);
    $mediaMock->shouldReceive('importFromUrl')
        ->once()
        ->with('https://cdn.magnific.ai/generated/render_999.png', 'Imagen para: Arquitectura Moderna', 'Arquitectura Moderna en la costa al atardecer')
        ->andReturn([
            'id' => 42,
            'url' => '/storage/media/ai_generated_arquitectura.webp',
            'file_name' => 'ai_generated_arquitectura.webp',
            'dimensions' => [1920, 1080],
        ]);

    app()->instance(MediaManagerService::class, $mediaMock);
    $orchestrator = app(AiMultimodalOrchestratorInterface::class);

    $result = $orchestrator->generateImage(
        prompt: 'Arquitectura Moderna en la costa al atardecer',
        title: 'Arquitectura Moderna',
        options: ['provider' => 'magnific', 'aspect_ratio' => '16:9']
    );

    expect($result)->toBeArray()
        ->and($result['url'])->toBe('/storage/media/ai_generated_arquitectura.webp')
        ->and($result['provider'])->toBe('magnific');
});

test('multimodal orchestrator generates video and saves it to media manager', function (): void {
    Http::fake([
        'api.higgsfield.ai/bytedance/seedance-2.0/text-to-video' => Http::response(['request_id' => 'video-task'], 202),
        'api.higgsfield.ai/requests/video-task/status' => Http::response(['status' => 'completed', 'video' => ['url' => 'https://cdn.higgsfield.ai/output/clip_888.mp4']], 200),
        'cdn.higgsfield.ai/*' => Http::response('fake-mp4-binary-content', 200, ['Content-Type' => 'video/mp4']),
    ]);

    $mediaMock = \Mockery::mock(MediaManagerService::class);
    $mediaMock->shouldReceive('importFromUrl')
        ->once()
        ->with('https://cdn.higgsfield.ai/output/clip_888.mp4', 'Vídeo Teaser: Toma Dron Costa', 'Toma con dron en travelling frontal sobre la costa')
        ->andReturn([
            'id' => 99,
            'url' => '/storage/media/clip_888.mp4',
            'file_name' => 'clip_888.mp4',
            'duration' => 5,
        ]);

    app()->instance(MediaManagerService::class, $mediaMock);
    $orchestrator = app(AiMultimodalOrchestratorInterface::class);

    $result = $orchestrator->generateVideo(
        prompt: 'Toma con dron en travelling frontal sobre la costa',
        title: 'Toma Dron Costa',
        options: ['provider' => 'higgsfield', 'duration' => 8]
    );

    expect($result)->toBeArray()
        ->and($result['url'])->toBe('/storage/media/clip_888.mp4')
        ->and($result['provider'])->toBe('higgsfield')
        ->and($result['duration_seconds'])->toBe(8);
});

test('multimodal orchestrator orchestrates full post with research and blocks', function (): void {
    AiCompletionFixture::configure();
    Http::fake([
        'html.duckduckgo.com/*' => Http::response(
            '<html><body><a class="result__snippet" href="https://example.com">Laravel es el framework PHP más popular.</a></body></html>',
            200
        ),
        'api.magnific.com/v1/ai/mystic' => Http::response(['data' => ['task_id' => 'visual-task']]),
        'api.magnific.com/v1/ai/mystic/visual-task' => Http::response(['data' => ['status' => 'COMPLETED', 'generated' => ['https://cdn.magnific.ai/generated/hero_post.png']]], 200),
    ]);

    $mediaMock = \Mockery::mock(MediaManagerService::class);
    $mediaMock->shouldReceive('importFromUrl')
        ->andReturn([
            'id' => 101,
            'url' => '/storage/media/hero_post.webp',
            'file_name' => 'hero_post.webp',
            'dimensions' => [1920, 1080],
        ]);

    app()->instance(MediaManagerService::class, $mediaMock);
    $orchestrator = app(AiMultimodalOrchestratorInterface::class);

    $req = new MultimodalOrchestrationRequest(
        topic: 'Investiga las ventajas de Laravel 11 para microservicios',
        entityType: 'post',
        market: 'es',
        locale: 'es',
        generateImage: true,
        imageProvider: 'magnific',
        generateVideo: false
    );

    $res = $orchestrator->orchestratePost($req);

    expect($res)->toBeInstanceOf(MultimodalOrchestrationResult::class)
        ->and($res->entityType)->toBe('post')
        ->and($res->title)->not()->toBeEmpty()
        ->and($res->summary)->not()->toBeEmpty()
        ->and($res->content)->not()->toBeEmpty()
        ->and($res->featuredImageUrl)->toBe('/storage/media/hero_post.webp')
        ->and($res->sections)->toBeArray()
        ->and(count($res->sections))->toBeGreaterThanOrEqual(1)
        ->and($res->sources)->toBeArray();
});

test('multimodal orchestrator orchestrates modular page with interactive blocks', function (): void {
    AiCompletionFixture::configure();
    Http::fake([
        'api.magnific.com/v1/ai/mystic' => Http::response(['data' => ['task_id' => 'visual-task']]),
        'api.magnific.com/v1/ai/mystic/visual-task' => Http::response(['data' => ['status' => 'COMPLETED', 'generated' => ['https://cdn.magnific.ai/generated/landing_hero.png']]], 200),
    ]);

    $mediaMock = \Mockery::mock(MediaManagerService::class);
    $mediaMock->shouldReceive('importFromUrl')
        ->andReturn([
            'id' => 102,
            'url' => '/storage/media/landing_hero.webp',
            'file_name' => 'landing_hero.webp',
            'dimensions' => [1920, 1080],
        ]);

    app()->instance(MediaManagerService::class, $mediaMock);
    $orchestrator = app(AiMultimodalOrchestratorInterface::class);

    $req = new MultimodalOrchestrationRequest(
        topic: 'Landing de servicios de ciberseguridad con calculadora de ROI y testimonios',
        entityType: 'page',
        market: 'es',
        locale: 'es',
        generateImage: true,
        imageProvider: 'magnific',
        generateVideo: false
    );

    $res = $orchestrator->orchestratePage($req);

    expect($res)->toBeInstanceOf(MultimodalOrchestrationResult::class)
        ->and($res->entityType)->toBe('page')
        ->and($res->title)->not()->toBeEmpty()
        ->and($res->sections)->toBeArray()
        ->and(count($res->sections))->toBeGreaterThanOrEqual(2);

    $blockTypes = array_column($res->sections, 'type');
    expect($blockTypes)->toContain('hero');
});

test('ai action runner dispatches multimodal actions seamlessly', function (): void {
    AiCompletionFixture::configure();
    Http::fake([
        'html.duckduckgo.com/*' => Http::response(
            '<html><body><a class="result__snippet" href="https://example.com">Información verificada de IA.</a></body></html>',
            200
        ),
    ]);

    $runner = app(AiActionRunner::class);

    // Test research action
    $researchRes = $runner->run('research', [
        'topic' => 'Model Context Protocol en Laravel',
        'locale' => 'es',
        'market' => 'es',
    ]);

    expect($researchRes)->toBeArray()
        ->and($researchRes['topic'])->toBe('Model Context Protocol en Laravel')
        ->and($researchRes['claims'])->toBeArray();

    // Test auto block detection with ROI intent
    $blockRes = $runner->run('block', [
        'block_type' => 'auto',
        'prompt' => 'Crea un bloque de ROI para estimar el ahorro con energía solar',
        'locale' => 'es',
    ]);

    expect($blockRes)->toBeArray()
        ->and($blockRes['type'])->toBe('roi');
});

test('composite generation resumes its pending stage without repeating text media or drafts', function (): void {
    Language::firstOrCreate(['code' => 'es'], ['name' => 'Español', 'native_name' => 'Español', 'is_active' => true]);
    $actorId = User::firstOrFail()->getKey();
    $complete = false;
    Http::fake(function ($request) use (&$complete) {
        if ($request->method() === 'POST') {
            return Http::response(['request_id' => 'composite-checkpoint'], 202);
        }

        return Http::response($complete ? ['status' => 'completed', 'video' => ['url' => 'https://cdn.example.com/resumed.mp4']] : ['status' => 'queued']);
    });
    $bridge = \Mockery::mock(AiConfigBridgeInterface::class);
    $bridge->shouldReceive('generateCompletion')->once()->andReturn(['content' => json_encode(['title' => 'Checkpoint', 'summary' => 'A preserved summary', 'content' => 'A preserved article'])]);
    app()->instance(AiConfigBridgeInterface::class, $bridge);
    $media = \Mockery::mock(MediaManagerService::class);
    $media->shouldReceive('importFromUrl')->once()->andReturn(['id' => 912, 'url' => '/storage/resumed.mp4']);
    app()->instance(MediaManagerService::class, $media);
    $service = app(AiMultimodalOrchestratorInterface::class);
    $initial = new MultimodalOrchestrationRequest(topic: 'Checkpoint', generateImage: false, generateVideo: true, persistDraft: true, videoProvider: 'higgsfield', options: ['actor_id' => $actorId]);
    expect(fn () => $service->orchestratePost($initial))->toThrow(AiProviderPendingException::class);
    expect(Post::count())->toBe(0);
    $complete = true;
    $resume = new MultimodalOrchestrationRequest(topic: 'Checkpoint', generateImage: false, generateVideo: true, persistDraft: true, videoProvider: 'higgsfield', options: ['actor_id' => $actorId, 'provider_request_id' => 'composite-checkpoint']);
    $result = $service->orchestratePost($resume);
    expect($result->content)->toBe('A preserved article')->and($result->videoUrl)->toBe('/storage/resumed.mp4');
    $completedAgain = $service->orchestratePost($resume);
    expect($completedAgain->toArray())->toBe($result->toArray());
    expect(Post::count())->toBe(1)->and($result->entityId)->not->toBeNull();
    expect(Http::recorded(fn ($request) => $request->method() === 'POST'))->toHaveCount(1);
});

test('an old image checkpoint follows the pending video stage without resubmitting either provider', function (): void {
    Language::firstOrCreate(['code' => 'es'], ['name' => 'Español', 'native_name' => 'Español', 'is_active' => true]);
    $actorId = User::firstOrFail()->getKey();
    $phase = 0;
    Http::fake(function ($request) use (&$phase) {
        if (str_contains($request->url(), 'api.magnific.com')) {
            return $request->method() === 'POST'
                ? Http::response(['data' => ['task_id' => 'checkpoint-image']])
                : Http::response(['data' => $phase === 0 ? ['status' => 'CREATED'] : ['status' => 'COMPLETED', 'generated' => ['https://cdn.example.com/image.png']]]);
        }

        return $request->method() === 'POST'
            ? Http::response(['request_id' => 'checkpoint-video'], 202)
            : Http::response($phase < 2 ? ['status' => 'queued'] : ['status' => 'completed', 'video' => ['url' => 'https://cdn.example.com/video.mp4']]);
    });
    $bridge = \Mockery::mock(AiConfigBridgeInterface::class);
    $bridge->shouldReceive('generateCompletion')->once()->andReturn(['content' => json_encode(['title' => 'Two stages', 'summary' => 'Summary', 'content' => 'Preserved article'])]);
    app()->instance(AiConfigBridgeInterface::class, $bridge);
    $media = \Mockery::mock(MediaManagerService::class);
    $media->shouldReceive('importFromUrl')->twice()->andReturnUsing(fn ($url) => ['id' => str_ends_with($url, '.png') ? 111 : 112, 'url' => str_ends_with($url, '.png') ? '/storage/image.png' : '/storage/video.mp4']);
    app()->instance(MediaManagerService::class, $media);
    $service = app(AiMultimodalOrchestratorInterface::class);
    $initial = new MultimodalOrchestrationRequest(topic: 'Two stages', generateImage: true, generateVideo: true, persistDraft: true, imageProvider: 'magnific', videoProvider: 'higgsfield', options: ['actor_id' => $actorId]);
    expect(fn () => $service->orchestratePost($initial))->toThrow(AiProviderPendingException::class);
    $resumeOld = new MultimodalOrchestrationRequest(topic: 'Two stages', generateImage: true, generateVideo: true, persistDraft: true, imageProvider: 'magnific', videoProvider: 'higgsfield', options: ['actor_id' => $actorId, 'provider_request_id' => 'checkpoint-image']);
    $phase = 1;
    expect(fn () => $service->orchestratePost($resumeOld))->toThrow(AiProviderPendingException::class);
    $phase = 2;
    $result = $service->orchestratePost($resumeOld);
    expect($result->featuredImageUrl)->toBe('/storage/image.png')->and($result->videoUrl)->toBe('/storage/video.mp4');
    expect($service->orchestratePost($resumeOld)->entityId)->toBe($result->entityId);
    $resumeNew = new MultimodalOrchestrationRequest(topic: 'Two stages', generateImage: true, generateVideo: true, persistDraft: true, imageProvider: 'magnific', videoProvider: 'higgsfield', options: ['actor_id' => $actorId, 'provider_request_id' => 'checkpoint-video']);
    expect($service->orchestratePost($resumeNew)->entityId)->toBe($result->entityId);
    expect(Post::count())->toBe(1);
    expect(Http::recorded(fn ($request) => $request->method() === 'POST'))->toHaveCount(2);
});
