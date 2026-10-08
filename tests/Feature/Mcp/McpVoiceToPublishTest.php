<?php

declare(strict_types=1);

use App\Contracts\Services\ModuleSettingsServiceInterface;
use App\Events\PagePublished;
use App\Events\PostPublished;
use App\Models\User;
use Database\Seeders\PlatformShowcaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Blog\Models\Post;
use Modules\Mcp\Services\McpServerService;
use Modules\Pages\Models\Page;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(PlatformShowcaseSeeder::class);
    $this->mcp = app(McpServerService::class);
});

test('mcp inspects system topology, active modules, and entity counts', function (): void {
    // The report reflects the real editorial enforcement setting.
    app(ModuleSettingsServiceInterface::class)
        ->set('content_audit', 'enforce_editorial_quality', true);

    $response = mcpHumanApprovedRequest([
        'jsonrpc' => '2.0',
        'id' => 101,
        'method' => 'tools/call',
        'params' => [
            'name' => 'cms_inspect_system',
            'arguments' => [],
        ],
    ]);

    expect($response)->toHaveKey('result');
    $data = json_decode($response['result']['content'][0]['text'], true);

    expect($data['status'])->toBe('operational');
    expect($data['modules'])->toBeArray();
    expect($data['markets'])->toBeArray();
    expect($data['statistics']['posts_count'])->toBeGreaterThanOrEqual(1);
    expect($data['editorial_rules']['anti_ai_enforced'])->toBeTrue();
});

test('mcp cross-entity search finds relevant posts, pages, and products', function (): void {
    $response = mcpHumanApprovedRequest([
        'jsonrpc' => '2.0',
        'id' => 102,
        'method' => 'tools/call',
        'params' => [
            'name' => 'cms_search_content',
            'arguments' => [
                'query' => 'mercado',
                'entity_type' => 'all',
            ],
        ],
    ]);

    $data = json_decode($response['result']['content'][0]['text'], true);
    expect($data['total_count'])->toBeGreaterThanOrEqual(1);
    expect($data['results'][0])->toHaveKey('url');
    expect($data['results'][0])->toHaveKey('title');
});

test('mcp research topic context suggests internal links and candidate categories', function (): void {
    $response = mcpHumanApprovedRequest([
        'jsonrpc' => '2.0',
        'id' => 103,
        'method' => 'tools/call',
        'params' => [
            'name' => 'cms_research_topic_context',
            'arguments' => [
                'topic' => 'arquitectura modular y rendimiento',
            ],
        ],
    ]);

    $data = json_decode($response['result']['content'][0]['text'], true);
    expect($data['topic'])->toBe('arquitectura modular y rendimiento');
    expect($data)->toHaveKey('candidate_categories');
    expect($data)->toHaveKey('editorial_notes');
});

test('mcp retrieves official editorial prompt for external AI calibration', function (): void {
    $response = mcpHumanApprovedRequest([
        'jsonrpc' => '2.0',
        'id' => 104,
        'method' => 'tools/call',
        'params' => [
            'name' => 'cms_get_editorial_prompt',
            'arguments' => ['locale' => 'es'],
        ],
    ]);

    $data = json_decode($response['result']['content'][0]['text'], true);
    expect($data['locale'])->toBe('es');
    expect($data['system_prompt'])->toContain('Guía Maestra y Prompt de Sistema');
    expect($data['system_prompt'])->toContain('Burstiness');
});

test('mcp publish post executes atomically and dispatches PostPublished event', function (): void {
    Event::fake([PostPublished::class]);

    $response = mcpHumanApprovedRequest([
        'jsonrpc' => '2.0',
        'id' => 105,
        'method' => 'tools/call',
        'params' => [
            'name' => 'cms_publish_post',
            'arguments' => [
                'title' => 'El impacto del nuevo reglamento de inteligencia artificial en las empresas',
                'content' => "## Análisis de la nueva directiva\n\nEl Parlamento Europeo establece requisitos estrictos. No hay marcha atrás.\n\n### Preguntas frecuentes\n¿Afecta a todas las empresas? Principalmente a aquellas que desarrollan o integran modelos de alto riesgo.",
                'summary' => 'Claves del nuevo marco regulatorio europeo de IA.',
                'locale' => 'es',
                'market_codes' => ['es'],
            ],
        ],
    ]);

    $data = json_decode($response['result']['content'][0]['text'], true);
    expect($data['success'])->toBeTrue();
    expect($data['post_id'])->toBeGreaterThan(0);
    expect($data['editorial_quality'])->toHaveKey('anti_ai_score');

    Event::assertDispatched(PostPublished::class);

    $postInDb = Post::find($data['post_id']);
    expect($postInDb)->not->toBeNull();
});

test('mcp publish page creates page and dispatches PagePublished event', function (): void {
    Event::fake([PagePublished::class]);

    $response = mcpHumanApprovedRequest([
        'jsonrpc' => '2.0',
        'id' => 106,
        'method' => 'tools/call',
        'params' => [
            'name' => 'cms_publish_page',
            'arguments' => [
                'title' => 'Centro de Innovación y Tecnología',
                'slug' => 'centro-innovacion-tecnologia',
                'locale' => 'es',
            ],
        ],
    ]);

    $data = json_decode($response['result']['content'][0]['text'], true);
    expect($data['success'])->toBeTrue();
    expect($data['page_id'])->toBeGreaterThan(0);

    Event::assertDispatched(PagePublished::class);

    $pageInDb = Page::find($data['page_id']);
    expect($pageInDb)->not->toBeNull();
});

test('authenticated admin can download official editorial prompt via web route', function (): void {
    $admin = User::where('email', 'admin@db-cms.io')->firstOrFail();

    $response = $this->actingAs($admin, 'web')->get('/admin/ai/download-prompt?locale=es');
    $response->assertOk();
    $response->assertHeader('content-type', 'text/markdown; charset=UTF-8');
    expect($response->headers->get('content-disposition'))->toContain('PROMPT_EDITORIAL_HUMAN_IA.md');
});
