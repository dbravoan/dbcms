<?php

declare(strict_types=1);

use App\Contracts\Services\ModuleSettingsServiceInterface;
use App\Core\Capabilities\ActorContext;
use App\Core\Capabilities\CapabilityAuthorizer;
use App\Core\Capabilities\ExecutionContext;
use App\Core\Content\Contracts\EntitySchemaRegistryInterface;
use App\Core\Content\EntitySchema;
use App\Services\Media\MediaManagerService;
use App\Services\Media\RemoteMediaUrlPolicy;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Sleep;
use Modules\AiAssistant\AiAssistantModuleServiceProvider;
use Modules\AiAssistant\Contracts\AiConfigBridgeInterface;
use Modules\AiAssistant\Contracts\AiModelCatalogInterface;
use Modules\AiAssistant\Contracts\AiProviderRegistryInterface;
use Modules\AiAssistant\DTOs\ImageGenerationRequest;
use Modules\AiAssistant\DTOs\VideoGenerationRequest;
use Modules\AiAssistant\Exceptions\AiProviderException;
use Modules\AiAssistant\Exceptions\AiProviderPendingException;
use Modules\AiAssistant\Providers\DeepSeekProvider;
use Modules\AiAssistant\Providers\GeminiProvider;
use Modules\AiAssistant\Providers\HiggsfieldProvider;
use Modules\AiAssistant\Providers\MagnificProvider;
use Modules\AiAssistant\Providers\OpenAiProvider;
use Modules\AiAssistant\Services\AiCapabilityAvailability;
use Modules\AiAssistant\Services\AiConfigBridge;
use Modules\AiAssistant\Services\AiModelCatalogService;
use Modules\AiAssistant\Services\AiPromptTemplateEngine;
use Modules\AiAssistant\Services\AiProviderRegistry;
use Modules\AiAssistant\Services\AiProviderSettings;
use Modules\AiAssistant\Services\AiResearchService;

beforeEach(function (): void {
    (new AiAssistantModuleServiceProvider(app()))->register();
    Sleep::fake();
});

test('remote media policy rejects private and ambiguous destinations', function (string $url): void {
    expect(fn () => (new RemoteMediaUrlPolicy)->resolve($url))->toThrow(InvalidArgumentException::class);
})->with(['http://127.0.0.1/a.png', 'https://[::1]/a.png', 'http://169.254.169.254/latest/meta-data/', 'https://user:pass@example.com/a.png', 'file:///etc/passwd', 'https://2130706433/a.png', 'https://100.64.0.1/a.png', 'https://[2001::a00:1]/a.png', 'https://[2002:7f00:1::]/a.png']);

test('AI provider manifest declares every shared credential and modality setting', function (): void {
    $manifest = json_decode(file_get_contents(base_path('modules/AiAssistant/module.json')), true);
    foreach (['vllm', 'magnific', 'higgsfield', 'openai', 'gemini'] as $provider) {
        foreach (['model', 'base_url'] as $field) {
            expect($manifest['settings'])->toHaveKey("{$provider}_{$field}");
        }
        $credentials = $provider === 'higgsfield' ? ['api_key_id', 'api_key_secret'] : ['api_key'];
        foreach ($credentials as $credential) {
            expect($manifest['settings']["{$provider}_{$credential}"]['secret'])->toBeTrue();
        }
    }
    foreach (['text', 'image', 'video'] as $modality) {
        expect($manifest['settings'])->toHaveKey("default_{$modality}_provider");
    }
});

test('failed research never creates statistics or invented sources', function (): void {
    $http = new Factory;
    $http->preventStrayRequests();
    $http->fake(['*' => $http->response('', 503)]);
    $bridge = Mockery::mock(AiConfigBridgeInterface::class);
    $bridge->shouldReceive('generateCompletion')->andThrow(new RuntimeException('offline'));
    $engine = Mockery::mock(AiPromptTemplateEngine::class);
    $result = (new AiResearchService($http, $bridge, $engine))->research('Battery efficiency');
    expect($result['statistics'])->toBe([])->and($result['sources'])->toBe([]);
});

test('Higgsfield generation authenticates with Key and polls the submitted request', function (): void {
    $http = new Factory;
    $http->preventStrayRequests();
    $http->fake([
        'api.higgsfield.ai/bytedance/seedance-2.0/text-to-video' => $http->response(['request_id' => 'job-1'], 202),
        'api.higgsfield.ai/requests/job-1/status' => $http->response(['status' => 'completed', 'video' => ['url' => 'https://cdn.example.com/video.mp4']]),
    ]);
    $result = (new HiggsfieldProvider($http))->generateVideo(new VideoGenerationRequest('Ocean'), 'test-key', 'bytedance/seedance-2.0/text-to-video');
    expect($result->url)->toBe('https://cdn.example.com/video.mp4');
    $http->assertSent(fn ($request) => $request->hasHeader('Authorization', 'Key test-key'));
});

test('Magnific Mystic uses its documented header endpoint and task lifecycle', function (): void {
    $http = new Factory;
    $http->preventStrayRequests();
    $http->fake([
        'api.magnific.com/v1/ai/mystic' => $http->response(['data' => ['task_id' => 'task-1', 'status' => 'CREATED']]),
        'api.magnific.com/v1/ai/mystic/task-1' => $http->response(['data' => ['status' => 'COMPLETED', 'generated' => ['https://cdn.example.com/photo.webp']]]),
    ]);
    $result = (new MagnificProvider($http))->generateImage(new ImageGenerationRequest('Ocean'), 'test-key', 'realism');
    expect($result->url)->toBe('https://cdn.example.com/photo.webp');
    $http->assertSent(fn ($request) => $request->method() === 'POST' && $request->hasHeader('x-magnific-api-key', 'test-key') && $request['aspect_ratio'] === 'widescreen_16_9');
});

test('media binary import refuses executable extensions before writing files', function (): void {
    expect(fn () => app(MediaManagerService::class)->saveFromBinary('<?php echo 1;', 'php', 'unsafe'))->toThrow(InvalidArgumentException::class);
});

test('documented visual catalogs never imply a verified account connection', function (): void {
    $storage = Mockery::mock(ModuleSettingsServiceInterface::class);
    $storage->shouldReceive('get')->andReturnUsing(fn ($module, $key, $default = null) => $key === 'magnific_api_key' ? 'test-account-key' : $default);
    $registry = app(AiProviderRegistryInterface::class);
    $settings = new AiProviderSettings($storage, $registry);
    $catalog = new AiModelCatalogService($registry, $settings);
    $catalog->refresh('magnific');
    expect($catalog->connectionState('magnific'))->toBe(['state' => 'configured', 'verified' => false]);
});

test('text generation in the test environment fails clearly when no shared credential exists', function (): void {
    $storage = Mockery::mock(ModuleSettingsServiceInterface::class);
    $storage->shouldReceive('get')->andReturn(null);
    $registry = app(AiProviderRegistryInterface::class);
    $settings = new AiProviderSettings($storage, $registry);
    $catalog = Mockery::mock(AiModelCatalogInterface::class);
    $bridge = new AiConfigBridge($registry, $catalog, $settings, $storage);
    config(['services.openai.key' => null]);
    expect(fn () => $bridge->generateCompletion('System', 'User', 'openai', ['model' => 'gpt-4o']))->toThrow(AiProviderException::class, 'not configured');
});

test('a pending visual task resumes polling without submitting twice', function (): void {
    $http = new Factory;
    $http->preventStrayRequests();
    $http->fake(['api.higgsfield.ai/requests/existing/status' => $http->response(['status' => 'completed', 'video' => ['url' => 'https://cdn.example.com/clip.mp4']])]);
    $result = (new HiggsfieldProvider($http))->generateVideo(new VideoGenerationRequest('Ocean', extraParameters: ['provider_request_id' => 'existing']), 'test-key', 'bytedance/seedance-2.0/text-to-video');
    expect($result->metadata['request_id'])->toBe('existing');
    $http->assertSentCount(1);
    $http->assertNotSent(fn ($request) => $request->method() === 'POST');
});

test('OpenAI image generation honors the visual model and decodes GPT image output', function (): void {
    $http = new Factory;
    $http->preventStrayRequests();
    $http->fake(['api.openai.com/v1/images/generations' => $http->response(['data' => [['b64_json' => base64_encode('test-image')]]])]);
    $result = (new OpenAiProvider($http))->generateImage(new ImageGenerationRequest('Ocean'), 'test-key', 'gpt-image-1');
    expect($result->model)->toBe('gpt-image-1')->and($result->binaryData)->toBe('test-image');
    $http->assertSent(fn ($request) => $request['model'] === 'gpt-image-1' && ! isset($request['response_format']));
});

test('Gemini native image generation uses header authentication and inline output', function (): void {
    $http = new Factory;
    $http->preventStrayRequests();
    $http->fake(['generativelanguage.googleapis.com/*' => $http->response(['candidates' => [['content' => ['parts' => [['inlineData' => ['mimeType' => 'image/png', 'data' => base64_encode('native-image')]]]]]]])]);
    $result = (new GeminiProvider($http))->generateImage(new ImageGenerationRequest('Ocean'), 'test-key', 'gemini-2.5-flash-image');
    expect($result->binaryData)->toBe('native-image')->and($result->model)->toBe('gemini-2.5-flash-image');
    $http->assertSent(fn ($request) => $request->hasHeader('x-goog-api-key', 'test-key') && ! str_contains($request->url(), 'key='));
});

test('provider verification reflects live evidence rather than the presence of a key', function (): void {
    $http = new Factory;
    $http->preventStrayRequests();
    $reject = false;
    $http->fake(function () use (&$reject, $http) {
        return $reject ? $http->response([], 401) : $http->response(['data' => [['id' => 'gpt-4o']]]);
    });
    $registry = new AiProviderRegistry([new OpenAiProvider($http)]);
    $storage = Mockery::mock(ModuleSettingsServiceInterface::class);
    $storage->shouldReceive('get')->andReturnUsing(fn ($module, $key, $default = null) => $key === 'openai_api_key' ? 'connection-state-fixture' : $default);
    $catalog = new AiModelCatalogService($registry, new AiProviderSettings($storage, $registry));
    expect($catalog->connectionState('openai'))->toBe(['state' => 'configured', 'verified' => false]);
    $catalog->refresh('openai');
    expect($catalog->connectionState('openai'))->toBe(['state' => 'verified', 'verified' => true]);
    $reject = true;
    $catalog->refresh('openai');
    expect($catalog->connectionState('openai'))->toBe(['state' => 'unavailable', 'verified' => false]);
});

test('provider endpoint changes cannot forward a preserved credential to a new destination', function (): void {
    $storage = Mockery::mock(ModuleSettingsServiceInterface::class);
    $storage->shouldReceive('get')->andReturnUsing(fn ($module, $key, $default = null) => match ($key) {
        'vllm_api_key' => 'kept-secret', 'vllm_base_url' => 'http://old-vllm:8000/v1', default => $default
    });
    $storage->shouldNotReceive('setMany');
    $settings = new AiProviderSettings($storage, app(AiProviderRegistryInterface::class));
    expect(fn () => $settings->save('vllm', ['base_url' => 'http://other-vllm:8000/v1', 'api_key' => AiProviderSettings::MASKED_KEY]))->toThrow(InvalidArgumentException::class, 'replacement API key');
});

test('AI availability requires both entity and media permissions for a persisted multimodal draft', function (): void {
    $authorizer = Mockery::mock(CapabilityAuthorizer::class);
    $authorizer->shouldReceive('allowed')->withArgs(fn ($actor, $ability, $permission) => $permission === 'use:ai')->andReturnTrue();
    $authorizer->shouldReceive('allowed')->withArgs(fn ($actor, $ability, $permission) => $permission === 'manage:media')->andReturnFalse();
    $storage = Mockery::mock(ModuleSettingsServiceInterface::class);
    $storage->shouldReceive('get')->andReturn(null);
    $providers = app(AiProviderRegistryInterface::class);
    $availability = new AiCapabilityAvailability($authorizer, app(EntitySchemaRegistryInterface::class), $providers, new AiProviderSettings($storage, $providers));
    $reason = $availability->reason('orchestrate_multimodal', new ActorContext(userId: 5), ['persist_draft' => true]);
    expect($reason)->toContain('biblioteca');
});

test('a configured visual provider is usable without claiming account verification', function (): void {
    $authorizer = Mockery::mock(CapabilityAuthorizer::class);
    $authorizer->shouldReceive('allowed')->andReturnTrue();
    $storage = Mockery::mock(ModuleSettingsServiceInterface::class);
    $storage->shouldReceive('get')->andReturnUsing(fn ($module, $key, $default = null) => $key === 'magnific_api_key' ? 'configured-fixture' : $default);
    $providers = app(AiProviderRegistryInterface::class);
    $availability = new AiCapabilityAvailability($authorizer, app(EntitySchemaRegistryInterface::class), $providers, new AiProviderSettings($storage, $providers));
    expect($availability->reason('image', new ActorContext(userId: 6), ['provider' => 'magnific']))->toBeNull();
});

test('draft availability checks the permission of its registered entity type', function (): void {
    $authorizer = Mockery::mock(CapabilityAuthorizer::class);
    $authorizer->shouldReceive('allowed')->andReturnUsing(fn ($actor, $ability, $permission) => $permission !== 'manage:pages');
    $entities = Mockery::mock(EntitySchemaRegistryInterface::class);
    $entities->shouldReceive('get')->with('page')->andReturn(new EntitySchema(type: 'page', label: 'Page', model: 'not-used', permission: 'manage:pages'));
    $storage = Mockery::mock(ModuleSettingsServiceInterface::class);
    $storage->shouldReceive('get')->andReturn(null);
    $providers = app(AiProviderRegistryInterface::class);
    $availability = new AiCapabilityAvailability($authorizer, $entities, $providers, new AiProviderSettings($storage, $providers));
    expect($availability->reason('create_element', new ActorContext(userId: 6), ['type' => 'page']))->toContain('entidad');
});

test('text generation honors the provider and model of the server execution context', function (): void {
    $http = new Factory;
    $http->preventStrayRequests();
    $http->fake(['*' => $http->response(['choices' => [['message' => ['content' => '{}']]]])]);
    $registry = new AiProviderRegistry([new OpenAiProvider($http), new DeepSeekProvider($http)]);
    $storage = Mockery::mock(ModuleSettingsServiceInterface::class);
    $storage->shouldReceive('get')->andReturnUsing(fn ($module, $key, $default = null) => str_ends_with($key, '_api_key') ? 'provider-choice-fixture' : $default);
    $settings = new AiProviderSettings($storage, $registry);
    $catalog = Mockery::mock(AiModelCatalogInterface::class);
    $bridge = new AiConfigBridge($registry, $catalog, $settings, $storage);
    $context = app(ExecutionContext::class);
    $previousArguments = $context->arguments;
    $previousActor = $context->actor;
    $previousName = $context->capabilityName;
    $context->actor = new ActorContext(userId: 5);
    $context->capabilityName = 'cms_ai_draft_article';
    try {
        $context->arguments = ['provider' => 'deepseek', 'model' => 'deepseek-chat'];
        $deepseek = $bridge->generateCompletion('System', 'User');
        expect($deepseek['provider'])->toBe('deepseek')->and($deepseek['model'])->toBe('deepseek-chat');
        $context->arguments = ['provider' => 'openai', 'model' => 'gpt-4o'];
        $openai = $bridge->generateCompletion('System', 'User');
        expect($openai['provider'])->toBe('openai')->and($openai['model'])->toBe('gpt-4o');
        $http->assertSent(fn ($request) => str_contains($request->url(), 'api.deepseek.com') && $request['model'] === 'deepseek-chat');
        $http->assertSent(fn ($request) => str_contains($request->url(), 'api.openai.com') && $request['model'] === 'gpt-4o');
    } finally {
        $context->arguments = $previousArguments;
        $context->actor = $previousActor;
        $context->capabilityName = $previousName;
    }
});

test('a rejected remote polling credential is terminal instead of pending forever', function (): void {
    $http = new Factory;
    $http->preventStrayRequests();
    $http->fake(['api.higgsfield.ai/requests/forbidden/status' => $http->response([], 403)]);
    try {
        (new HiggsfieldProvider($http))->generateVideo(new VideoGenerationRequest('Ocean', extraParameters: ['provider_request_id' => 'forbidden']), 'bad-key', 'bytedance/seedance-2.0/text-to-video');
        test()->fail('Polling should fail explicitly.');
    } catch (AiProviderException $exception) {
        expect($exception)->not->toBeInstanceOf(AiProviderPendingException::class);
    }
});
