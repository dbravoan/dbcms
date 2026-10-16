<?php

declare(strict_types=1);

namespace Tests\Feature\AiAssistant;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\AiAssistant\Contracts\AiConfigBridgeInterface;
use Modules\AiAssistant\Contracts\AiProviderRegistryInterface;
use Modules\AiAssistant\Http\Controllers\Admin\Providers\Save\PostController as SaveProviderPostController;
use Modules\AiAssistant\Http\Controllers\Admin\Providers\SetDefault\PostController as SetDefaultProviderPostController;
use Tests\Support\AiComposition;
use Tests\TestCase;

class AiModelCatalogAndProviderConnectionTest extends TestCase
{
    use AiComposition;
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();

        $role = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        $this->adminUser = User::factory()->create([
            'email' => 'admin@db-cms.io',
        ]);
        $this->adminUser->roles()->syncWithoutDetaching([$role->id]);

    }

    public function test_catalog_returns_all_grouped_providers(): void
    {
        /** @var AiConfigBridgeInterface $bridge */
        $bridge = app(AiConfigBridgeInterface::class);

        $catalog = $bridge->getProvidersCatalog();

        $this->assertArrayHasKey('gemini', $catalog);
        $this->assertArrayHasKey('openai', $catalog);
        $this->assertArrayHasKey('deepseek', $catalog);
        $this->assertArrayHasKey('anthropic', $catalog);
        $this->assertArrayHasKey('custom', $catalog);
        $this->assertArrayHasKey('opencode', $catalog);
        $this->assertArrayHasKey('ollama', $catalog);

        // Verify provider metadata
        $this->assertEquals('Google Gemini', $catalog['gemini']['name']);
        $this->assertEquals('OpenAI', $catalog['openai']['name']);
        $this->assertEquals('DeepSeek', $catalog['deepseek']['name']);
        $this->assertEquals('Anthropic Claude', $catalog['anthropic']['name']);
        $this->assertEquals('OpenCode (Zen + Go)', $catalog['opencode']['name']);
        $this->assertEquals('Compatible OpenAI (vLLM / LMStudio)', $catalog['custom']['name']);
        $this->assertEquals('Ollama (Local)', $catalog['ollama']['name']);
    }

    public function test_provider_registry_exposes_every_adapter(): void
    {
        /** @var AiProviderRegistryInterface $registry */
        $registry = app(AiProviderRegistryInterface::class);

        $this->assertEqualsCanonicalizing(
            ['gemini', 'openai', 'deepseek', 'anthropic', 'custom', 'opencode', 'ollama', 'vllm', 'magnific', 'higgsfield'],
            $registry->ids()
        );

        $this->assertSame('opencode', $registry->resolve('opencode')->definition()->id);
        $this->assertTrue($registry->has('opencode'));
        $this->assertFalse($registry->has('does-not-exist'));
    }

    public function test_no_provider_exposes_hardcoded_models_without_credentials(): void
    {
        /** @var AiConfigBridgeInterface $bridge */
        $bridge = app(AiConfigBridgeInterface::class);

        // The whole point: nothing is advertised until a provider is reachable.
        foreach ($bridge->getProviderIds() as $provider) {
            $this->assertSame(
                [],
                $bridge->getRegisteredModels($provider),
                "Provider [{$provider}] must not expose hardcoded models."
            );
        }
    }

    public function test_models_are_listed_only_once_credentials_are_set(): void
    {
        /** @var AiConfigBridgeInterface $bridge */
        $bridge = app(AiConfigBridgeInterface::class);

        Http::fake([
            'api.openai.com/v1/models' => Http::response([
                'data' => [['id' => 'gpt-4o'], ['id' => 'gpt-4o-mini']],
            ]),
        ]);

        // Unconfigured -> empty.
        $this->assertSame([], $bridge->getRegisteredModels('openai'));

        // Configured -> live catalog.
        $bridge->saveProviderConfig('openai', ['api_key' => 'sk-test-key', 'model' => 'gpt-4o']);

        $ids = array_column($bridge->getRegisteredModels('openai'), 'id');
        $this->assertContains('gpt-4o', $ids);
        $this->assertContains('gpt-4o-mini', $ids);

        Cache::forget('ai_assistant:models:openai');
    }

    public function test_model_catalog_is_fetched_remotely_and_cached(): void
    {
        /** @var AiConfigBridgeInterface $bridge */
        $bridge = app(AiConfigBridgeInterface::class);
        $bridge->saveProviderConfig('openai', ['api_key' => 'sk-test-key', 'model' => 'gpt-4o']);

        Http::fake([
            'api.openai.com/v1/models' => Http::response([
                'data' => [['id' => 'gpt-4o'], ['id' => 'gpt-4o-mini'], ['id' => 'brand-new-model']],
            ]),
        ]);

        $fresh = $bridge->refreshModels('openai');
        $this->assertContains('brand-new-model', array_column($fresh, 'id'));

        // Second read must be served from cache (network now fails).
        Http::fake(['api.openai.com/*' => Http::response([], 500)]);
        $cached = $bridge->listModels('openai');
        $this->assertContains('brand-new-model', array_column($cached, 'id'));

        Cache::forget('ai_assistant:models:openai');
    }

    public function test_models_endpoint_returns_live_catalog_for_opencode(): void
    {
        $this->actingAs($this->adminUser);

        Http::fake([
            'opencode.ai/zen/v1/models' => Http::response([
                'object' => 'list',
                'data' => [['id' => 'deepseek-v4.1-flash'], ['id' => 'kimi-k3']],
            ]),
            'opencode.ai/zen/go/v1/models' => Http::response([
                'object' => 'list',
                'data' => [['id' => 'glm-5.3'], ['id' => 'gpt-5.5']],
            ]),
        ]);

        /** @var AiConfigBridgeInterface $bridge */
        $bridge = app(AiConfigBridgeInterface::class);
        $bridge->saveProviderConfig('opencode', ['api_key' => 'opencode-test-key', 'model' => 'deepseek-v4.1-flash']);

        $response = $this->getJson(route('admin.ai_assistant.providers.models', [
            'provider' => 'opencode',
            'refresh' => 1,
        ]));

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('provider', 'opencode');

        $ids = array_column($response->json('models'), 'id');
        $this->assertContains('deepseek-v4.1-flash', $ids);
        $this->assertContains('kimi-k3', $ids);
        $this->assertContains('glm-5.3', $ids);
        // The Go catalog is merged in.
        $this->assertContains('gpt-5.5', $ids);

        // gpt-5.5 belongs to the OpenAI Responses family.
        $gpt = collect($response->json('models'))->firstWhere('id', 'gpt-5.5');
        $this->assertEquals('responses', $gpt['family']);
        $this->assertEquals('go', $gpt['source']);

        Cache::forget('ai_assistant:models:opencode');
    }

    public function test_admin_can_save_opencode_provider_config(): void
    {
        $this->actingAs($this->adminUser);

        $response = $this->postJson(route('admin.ai_assistant.providers.save'), [
            'provider' => 'opencode',
            'api_key' => 'opencode-test-key-1234567890',
            'model' => 'kimi-k3',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('model', 'kimi-k3');
        $response->assertJsonPath('has_key', true);

        /** @var AiConfigBridgeInterface $bridge */
        $bridge = app(AiConfigBridgeInterface::class);
        $this->assertEquals('kimi-k3', $bridge->getModelForProvider('opencode'));
        $this->assertTrue($bridge->hasApiKeyForProvider('opencode'));
    }

    public function test_unknown_provider_is_rejected(): void
    {
        $this->actingAs($this->adminUser);

        $this->postJson(route('admin.ai_assistant.providers.save'), [
            'provider' => 'not-a-provider',
            'api_key' => 'x',
        ])->assertStatus(422);
    }

    public function test_admin_can_save_provider_config_via_invokable_controller(): void
    {
        $this->actingAs($this->adminUser);

        $this->assertTrue(is_callable(new SaveProviderPostController(
            app(AiConfigBridgeInterface::class)
        )));

        $response = $this->postJson(route('admin.ai_assistant.providers.save'), [
            'provider' => 'deepseek',
            'api_key' => 'sk-deepseek-test-1234567890',
            'model' => 'deepseek-reasoner',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('model', 'deepseek-reasoner');
        $response->assertJsonPath('has_key', true);

        /** @var AiConfigBridgeInterface $bridge */
        $bridge = app(AiConfigBridgeInterface::class);
        $this->assertEquals('deepseek-reasoner', $bridge->getModelForProvider('deepseek'));
        $this->assertTrue($bridge->hasApiKeyForProvider('deepseek'));
    }

    public function test_admin_can_set_default_provider_via_invokable_controller(): void
    {
        $this->actingAs($this->adminUser);

        $this->assertTrue(is_callable(new SetDefaultProviderPostController(
            app(AiConfigBridgeInterface::class)
        )));

        $response = $this->postJson(route('admin.ai_assistant.providers.set_default'), [
            'provider' => 'anthropic',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('default_provider', 'anthropic');

        /** @var AiConfigBridgeInterface $bridge */
        $bridge = app(AiConfigBridgeInterface::class);
        $this->assertEquals('anthropic', $bridge->getActiveProvider());
    }

    public function test_dedicated_ai_assistant_settings_view_loads_custom_provider_hub(): void
    {
        $this->actingAs($this->adminUser);

        $response = $this->get(route('admin.modules.settings', ['identifier' => 'ai_assistant']));

        $response->assertOk();
        $response->assertSee('Hub Central de Proveedores de Inteligencia Artificial');
        $response->assertSee('Google Gemini');
        $response->assertSee('OpenAI');
        $response->assertSee('DeepSeek');
        $response->assertSee('Anthropic Claude');
        $response->assertSee('Ollama (Local)');
        $response->assertSee('Compatible OpenAI (vLLM / LMStudio)');
    }
}
