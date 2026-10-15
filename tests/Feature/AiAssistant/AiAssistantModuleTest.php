<?php

declare(strict_types=1);

namespace Tests\Feature\AiAssistant;

use App\Core\Modules\ModuleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\AiAssistant\Contracts\AiAssistantDomainServiceInterface;
use Modules\AiAssistant\Models\AiGenerationLog;
use Modules\Mcp\Services\McpServerService;
use Tests\Support\AiMcpFixture;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    app(ModuleManager::class)->discoverModules();
});

test('ai assistant module is discovered and active', function (): void {
    $manager = app(ModuleManager::class);
    $discovered = $manager->discoverModules();

    expect($discovered)->toHaveKey('ai_assistant');
    expect($manager->isModuleEnabled('ai_assistant'))->toBeTrue();
});

test('generate block returns valid schema and defaults', function (): void {
    $aiService = app(AiAssistantDomainServiceInterface::class);

    $result = $aiService->generateBlock(
        blockType: 'hero',
        prompt: 'Landing corporativa para SaaS empresarial con alta conversión',
        locale: 'es',
        market: 'es'
    );

    expect($result['type'])->toBe('hero');
    expect($result['data'])->toBeArray();
    expect($result['reasoning'])->not->toBeEmpty();

    $data = $result['data'];
    expect($data)->toHaveKey('title');
    expect($data)->toHaveKey('content');
    expect($data)->toHaveKey('cta_text');
    expect($data)->toHaveKey('cta_url');
    expect($data)->toHaveKey('badge');
});

test('recommend blocks returns sequenced layout', function (): void {
    $aiService = app(AiAssistantDomainServiceInterface::class);

    $result = $aiService->recommendBlocks('Quiero una landing page para captar leads de un software de ciberseguridad');

    expect($result['recommended_blocks'])->not->toBeEmpty();
    $firstBlock = $result['recommended_blocks'][0];
    expect($firstBlock)->toHaveKeys(['type', 'name', 'rationale']);
});

test('draft article enforces anti ai guardrails for spanish and english', function (): void {
    $aiService = app(AiAssistantDomainServiceInterface::class);

    // Spanish draft
    $draftEs = $aiService->draftArticle(
        topic: 'Impacto de la inteligencia artificial en la automatización contable',
        market: 'es',
        locale: 'es',
        style: 'periodistico',
        includeBlocks: true
    );

    expect($draftEs['title'])->not->toBeEmpty();
    expect($draftEs['content'])->not->toBeEmpty();
    expect($draftEs['meta_title'])->not->toBeEmpty();
    expect($draftEs['meta_description'])->not->toBeEmpty();
    expect($draftEs)->toHaveKey('anti_ai_audit');
    expect($draftEs['anti_ai_audit']['score'])->toBeGreaterThanOrEqual(80);
    expect($draftEs)->toHaveKey('recommended_blocks');

    // English draft
    $draftEn = $aiService->draftArticle(
        topic: 'Enterprise Cloud Architecture and Cost Optimization in 2026',
        market: 'us',
        locale: 'en',
        style: 'periodistico'
    );

    expect($draftEn['title'])->not->toBeEmpty();
    expect($draftEn['content'])->not->toBeEmpty();
    expect($draftEn)->toHaveKey('anti_ai_audit');
    expect($draftEn['anti_ai_audit']['score'])->toBeGreaterThanOrEqual(80);
});

test('proofread and humanize eradicates cliches', function (): void {
    $aiService = app(AiAssistantDomainServiceInterface::class);

    // Spanish synthetic text with clichés
    $syntheticSpanish = 'En el vertiginoso mundo actual, es un tapiz de innovación sin precedentes que marca un antes y un después.';
    $humanized = $aiService->humanize($syntheticSpanish, 'es');

    expect($humanized)->toHaveKeys(['humanized', 'cliches_removed']);
    expect($humanized['cliches_removed'])->not->toBeEmpty();
    expect($humanized['humanized'])->not->toContain('En el vertiginoso mundo actual');
    expect($humanized['anti_ai_score'])->toBeGreaterThanOrEqual(70);

    // English synthetic text with clichés
    $syntheticEnglish = "In today's fast-paced world, we delve into a rich tapestry of cutting-edge solutions.";
    $humanizedEn = $aiService->humanize($syntheticEnglish, 'en');

    expect($humanizedEn['humanized'])->not->toContain("In today's fast-paced world");
    expect($humanizedEn['cliches_removed'])->not->toBeEmpty();
});

test('generate seo metadata returns bounded length', function (): void {
    $aiService = app(AiAssistantDomainServiceInterface::class);

    $seo = $aiService->generateSeo(
        title: 'Guía Completa de Despliegue de Servidores Web',
        content: 'Configuración paso a paso de servidores Nginx, PHP-FPM y bases de datos seguras.',
        locale: 'es'
    );

    expect($seo['meta_title'])->not->toBeEmpty();
    expect($seo['meta_description'])->not->toBeEmpty();
    expect($seo['keywords'])->not->toBeEmpty();
    expect(mb_strlen($seo['meta_title']))->toBeLessThanOrEqual(65);
    expect(mb_strlen($seo['meta_description']))->toBeLessThanOrEqual(160);
});

test('test provider connection requires configured credentials instead of synthesizing success', function (): void {
    $aiService = app(AiAssistantDomainServiceInterface::class);

    $test = $aiService->testProviderConnection('gemini');

    expect($test['success'])->toBeFalse();
    expect($test['connection_state'])->toBe('unconfigured');
    expect($test['generation_verified'])->toBeFalse();
    Http::assertNothingSent();
    expect($test['provider'])->toBe('gemini');
    expect($test['latency_ms'])->toBeGreaterThanOrEqual(0);
});

test('ai assistant workbench route accessible by authorized user', function (): void {
    $user = AiMcpFixture::admin();

    $response = $this->actingAs($user, 'web')->get(route('admin.ai_assistant.workbench'));

    $response->assertStatus(200);
    $response->assertSee('Asistente IA de Página');
    $response->assertSee('Redactar');
    $response->assertSee('Humanizar y SEO');
    $response->assertSee('Historial y Tokens');
});

test('ai assistant workbench renders when generation logs exist', function (): void {
    $user = AiMcpFixture::admin();

    $log = AiGenerationLog::create([
        'provider' => 'ollama',
        'model' => 'llama3.2:3b',
        'action' => 'page_element',
        'locale' => 'es',
        'market' => 'es',
        'prompt_tokens' => 120,
        'completion_tokens' => 480,
        'latency_ms' => 1234,
    ]);

    expect($log->total_tokens)->toBe(600);

    $response = $this->actingAs($user, 'web')->get(route('admin.ai_assistant.workbench'));

    $response->assertOk();
    $response->assertSee('llama3.2:3b');
    $response->assertSee('600');
});

test('ai assistant generate endpoint ajax succeeds', function (): void {
    config(['services.gemini.key' => 'fake-key']);
    $text = json_encode(['type' => 'pricing', 'data' => ['title' => 'Planes', 'items' => []], 'reasoning' => 'Plan comparativo']);
    $responseBody = ['candidates' => [['content' => ['parts' => [['text' => $text]]]]]];
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response($responseBody)]);
    $user = AiMcpFixture::admin();

    $response = $this->actingAs($user, 'web')->postJson(route('admin.ai_assistant.generate'), [
        'action' => 'block',
        'block_type' => 'pricing',
        'prompt' => 'Tres planes: Básico, Profesional y Enterprise para SaaS',
        'locale' => 'es',
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('action', 'block');
    $response->assertJsonPath('data.type', 'pricing');
});

test('ai assistant logs recorded in database', function (): void {
    AiGenerationLog::truncate();
    expect(AiGenerationLog::count())->toBe(0);

    $aiService = app(AiAssistantDomainServiceInterface::class);
    $aiService->generateBlock('hero', 'Hero corporativo con botón CTA');

    expect(AiGenerationLog::count())->toBe(1);
    $log = AiGenerationLog::first();
    expect($log)->not->toBeNull();
    expect($log->action)->toBe('block');
    expect($log->provider)->toBe('gemini');
});

test('cohesion mcp lists and executes ai assistant tools when enabled', function (): void {
    AiMcpFixture::configure();
    $mcpServer = app(McpServerService::class);

    // 1. Check tools/list contains cms_ai_*
    $listResponse = $mcpServer->handle([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ]);

    $tools = $listResponse['result']['tools'] ?? [];
    $toolNames = array_column($tools, 'name');

    expect($toolNames)->toContain('cms_ai_generate_block');
    expect($toolNames)->toContain('cms_ai_draft_article');
    expect($toolNames)->toContain('cms_ai_humanize');

    // 2. Call cms_ai_generate_block via MCP
    $callResponse = $mcpServer->handle([
        'jsonrpc' => '2.0',
        'id' => 2,
        'method' => 'tools/call',
        'params' => [
            'name' => 'cms_ai_generate_block',
            'arguments' => [
                'block_type' => 'features',
                'prompt' => '4 características diferenciales de arquitectura limpia',
                'locale' => 'es',
            ],
        ],
    ]);

    expect($callResponse)->not->toHaveKey('error');
    expect($callResponse)->toHaveKey('result');
    $text = $callResponse['result']['content'][0]['text'];
    $decoded = json_decode($text, true);

    expect($decoded['type'])->toBe('features');
    expect($decoded)->toHaveKey('data');
});

test('decoupling mcp works cleanly when ai assistant is disabled', function (): void {
    AiMcpFixture::configure();
    $manager = app(ModuleManager::class);
    $manager->disable('ai_assistant');

    $mcpServer = app(McpServerService::class);

    $listResponse = $mcpServer->handle([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ]);

    $tools = $listResponse['result']['tools'] ?? [];
    $toolNames = array_column($tools, 'name');

    // AI tools should NOT be present when module is disabled
    expect($toolNames)->not->toContain('cms_ai_generate_block');
    expect($toolNames)->not->toContain('cms_ai_draft_article');
    expect($toolNames)->not->toContain('cms_ai_humanize');

    // Standard MCP tools must remain completely functional
    expect($toolNames)->toContain('cms_list_pages');
    expect($toolNames)->toContain('cms_list_posts');
});
