<?php

declare(strict_types=1);

use App\Core\Modules\ModuleManager;
use App\Models\CmsModule;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\AiAssistant\Contracts\AiAssistantDomainServiceInterface;
use Modules\AiAssistant\Contracts\AiConfigBridgeInterface;
use Modules\AiAssistant\DTOs\ChatRequest;
use Modules\AiAssistant\Providers\OllamaProvider;

uses(RefreshDatabase::class);

const OLLAMA_BASE = 'http://host.docker.internal:11434/v1';

beforeEach(function (): void {
    Http::preventStrayRequests();
    $role = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
    $user = User::factory()->create(['email' => 'admin@db-cms.io']);
    $user->roles()->syncWithoutDetaching([$role->id]);

    app(ModuleManager::class)->discoverModules();
    CmsModule::updateOrCreate(['identifier' => 'ai_assistant'], ['is_active' => true, 'is_installed' => true]);

    Cache::forget('ai_assistant:models:ollama');
});

test('ollama provider is registered and needs no api key', function (): void {
    $bridge = app(AiConfigBridgeInterface::class);

    expect($bridge->getProviderIds())->toContain('ollama');

    $catalog = $bridge->getProvidersCatalog();
    expect($catalog['ollama']['name'])->toBe('Ollama (Local)')
        ->and($catalog['ollama']['requires_api_key'])->toBeFalse()
        ->and($catalog['ollama']['default_base_url'])->toContain('11434');
});

test('ollama is not configured until an endpoint is saved', function (): void {
    $bridge = app(AiConfigBridgeInterface::class);

    expect($bridge->isConfigured('ollama'))->toBeFalse();

    $bridge->saveProviderConfig('ollama', ['base_url' => OLLAMA_BASE]);

    expect($bridge->isConfigured('ollama'))->toBeTrue();
});

test('ollama lists only the live models returned by the endpoint', function (): void {
    Http::fake([
        'host.docker.internal:11434/v1/models' => Http::response([
            'object' => 'list',
            'data' => [
                ['id' => 'llama3.2:3b'],
                ['id' => 'qwen2.5:7b'],
            ],
        ]),
    ]);

    $models = app(OllamaProvider::class)->listModels(null, OLLAMA_BASE);

    expect(array_column(array_map(static fn ($m): array => $m->toArray(), $models), 'id'))
        ->toBe(['llama3.2:3b', 'qwen2.5:7b']);
});

test('ollama chat speaks the openai-compatible contract', function (): void {
    Http::fake([
        'host.docker.internal:11434/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => '{"status":"connected"}']]],
            'usage' => ['prompt_tokens' => 12, 'completion_tokens' => 4],
        ]),
    ]);

    $result = app(OllamaProvider::class)->chat(
        new ChatRequest(systemPrompt: 'sys', userPrompt: 'user', temperature: 0.7, jsonMode: true),
        '',
        'llama3.2:3b',
        OLLAMA_BASE
    );

    expect($result->content)->toBe('{"status":"connected"}')
        ->and($result->provider)->toBe('ollama')
        ->and($result->model)->toBe('llama3.2:3b')
        ->and($result->promptTokens)->toBe(12)
        ->and($result->completionTokens)->toBe(4);

    Http::assertSent(fn ($request): bool => $request->url() === OLLAMA_BASE.'/chat/completions'
        && $request['response_format'] === ['type' => 'json_object']
        && $request['max_tokens'] === 2048);
});

test('the catalog exposes ollama models only once the endpoint is reachable', function (): void {
    Http::fake([
        'host.docker.internal:11434/v1/models' => Http::response([
            'data' => [['id' => 'llama3.2:3b']],
        ]),
    ]);

    $bridge = app(AiConfigBridgeInterface::class);

    // Not configured -> nothing advertised.
    expect($bridge->getRegisteredModels('ollama'))->toBe([]);

    $bridge->saveProviderConfig('ollama', ['base_url' => OLLAMA_BASE, 'model' => 'llama3.2:3b']);

    expect(array_column($bridge->getRegisteredModels('ollama'), 'id'))->toContain('llama3.2:3b');

    Cache::forget('ai_assistant:models:ollama');
});

test('the connection test succeeds for a configured ollama provider', function (): void {
    Http::fake(['host.docker.internal:11434/v1/models' => Http::response(['data' => [['id' => 'llama3.2:3b']]])]);
    app(AiConfigBridgeInterface::class)->saveProviderConfig('ollama', ['base_url' => OLLAMA_BASE, 'model' => 'llama3.2:3b']);
    $result = app(AiAssistantDomainServiceInterface::class)->testProviderConnection('ollama');

    expect($result['success'])->toBeTrue()
        ->and($result['provider'])->toBe('ollama');
});
