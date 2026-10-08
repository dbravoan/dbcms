<?php

declare(strict_types=1);

use App\Core\Capabilities\ActorContext;
use App\Core\Capabilities\AvailableCapabilities;
use App\Core\Capabilities\CapabilityExecutor;
use App\Core\Modules\ModuleManager;
use App\Models\Language;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Modules\AiAssistant\Contracts\AiConfigBridgeInterface;
use Modules\Mcp\Services\McpServerService;
use Modules\Pages\Models\Page;
use Tests\Support\AiMcpFixture;

beforeEach(function (): void {
    app(ModuleManager::class)->discoverModules();
    Language::firstOrCreate(['code' => 'es'], ['name' => 'Spanish', 'native_name' => 'Español', 'is_default' => true, 'is_active' => true]);
    $this->user = User::factory()->create();
    $this->role = Role::firstOrCreate(['slug' => 'module-executor'], ['name' => 'Module executor']);
    foreach (['access:admin', 'manage:pages', 'use:ai'] as $slug) {
        $permission = Permission::firstOrCreate(['slug' => $slug], ['name' => $slug]);
        $this->role->permissions()->syncWithoutDetaching([$permission->getKey()]);
    }
    $this->user->roles()->attach($this->role);
    $this->actor = new ActorContext((int) $this->user->getKey());
    Http::preventStrayRequests();
});

test('native AI and entity execution survive disabling MCP with live role permissions', function (): void {
    $bridge = app(AiConfigBridgeInterface::class);
    $bridge->saveProviderConfig('openai', ['api_key' => 'test-module-key', 'model' => 'gpt-4o']);
    $bridge->setDefaultProvider('openai');
    app(ModuleManager::class)->deactivate('mcp');
    expect(app(ModuleManager::class)->isModuleEnabled('mcp'))->toBeFalse();
    $names = array_column(app(AvailableCapabilities::class)->manifest($this->actor), 'name');
    expect($names)->toContain('cms_ai_humanize', 'cms_entity_create');
    Http::fake(['api.openai.com/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => '{"humanized":"Contenido remoto verificado."}']]], 'usage' => ['prompt_tokens' => 2, 'completion_tokens' => 4]])]);
    app()->instance('env', 'production');
    try {
        $result = app(CapabilityExecutor::class)->execute('cms_ai_humanize', ['content' => 'Texto original.'], $this->actor);
        expect($result['humanized'] ?? null)->toBe('Contenido remoto verificado.');
    } finally {
        app()->instance('env', 'testing');
    }
    Http::assertSentCount(1);
    $page = app(CapabilityExecutor::class)->execute('cms_entity_create', ['type' => 'page', 'translations' => ['es' => ['title' => 'Native page']]], $this->actor);
    expect($page)->toHaveKey('id');
    expect(Page::findOrFail($page['id'])->is_published)->toBeFalse();
    $this->role->permissions()->detach(Permission::where('slug', 'use:ai')->firstOrFail());
    expect(app(CapabilityExecutor::class)->execute('cms_ai_humanize', ['content' => 'Revoked'], $this->actor))->toHaveKey('denied');
    expect(array_column(app(AvailableCapabilities::class)->manifest($this->actor), 'name'))->not->toContain('cms_ai_humanize');
    Http::assertSentCount(1);
});

test('concrete MCP entity operations survive disabling AI without any LLM call', function (): void {
    AiMcpFixture::configure($this->user);
    app(ModuleManager::class)->deactivate('ai_assistant');
    expect(app(ModuleManager::class)->isModuleEnabled('ai_assistant'))->toBeFalse();
    $server = app(McpServerService::class);
    $manifest = $server->handle(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list']);
    $names = array_column($manifest['result']['tools'], 'name');
    expect($names)->toContain('cms_entity_create')->not->toContain('cms_ai_humanize', 'cms_ai_generate_block');
    $response = $server->handle(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/call', 'params' => ['name' => 'cms_entity_create', 'arguments' => ['type' => 'page', 'translations' => ['es' => ['title' => 'Concrete MCP page']]]]]);
    $result = json_decode($response['result']['content'][0]['text'], true, 512, JSON_THROW_ON_ERROR);
    expect($result)->toHaveKey('id');
    $page = Page::with('translations')->findOrFail($result['id']);
    expect($page->is_published)->toBeFalse()->and($page->translations->first()->title)->toBe('Concrete MCP page');
    Http::assertNothingSent();
});
