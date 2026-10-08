<?php

declare(strict_types=1);

use Database\Seeders\PlatformShowcaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\Post;
use Modules\Mcp\Services\McpServerService;
use Modules\Pages\Models\Page;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(PlatformShowcaseSeeder::class);
    $this->mcp = app(McpServerService::class);
});

test('mcp server tools/list returns complete tools manifest', function (): void {
    $response = mcpHumanApprovedRequest([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ]);

    expect($response['id'])->toBe(1);
    expect($response['result']['tools'])->toBeArray();

    $toolNames = array_column($response['result']['tools'], 'name');
    expect($toolNames)->toContain('cms_list_modules');
    expect($toolNames)->toContain('cms_list_posts');
    expect($toolNames)->toContain('cms_create_post');
    expect($toolNames)->toContain('cms_update_post');
    expect($toolNames)->toContain('cms_delete_post');
    expect($toolNames)->toContain('cms_list_categories');
    expect($toolNames)->toContain('cms_create_category');
    expect($toolNames)->toContain('cms_create_page');
    expect($toolNames)->toContain('cms_delete_page');
    expect($toolNames)->toContain('cms_audit_seo_geo');
});

test('mcp server creates, lists, updates, and deletes a blog post', function (): void {
    // 1. CREATE POST via MCP
    $createResponse = mcpHumanApprovedRequest([
        'jsonrpc' => '2.0',
        'id' => 2,
        'method' => 'tools/call',
        'params' => [
            'name' => 'cms_create_post',
            'arguments' => [
                'title' => 'Post Creado por Agente IA',
                'content' => '## Contenido Autogenerado\n\nEste post fue creado mediante el servidor MCP.',
                'summary' => 'Resumen del post generado por IA.',
                'locale' => 'es',
                'market_codes' => ['es', 'us'],
            ],
        ],
    ]);

    expect($createResponse)->toHaveKey('result');
    $createContent = json_decode($createResponse['result']['content'][0]['text'], true);
    expect($createContent['success'])->toBeTrue();
    $postId = $createContent['post_id'];
    expect($postId)->toBeGreaterThan(0);

    // Verify in database
    $postInDb = Post::with('translations')->find($postId);
    expect($postInDb)->not->toBeNull();
    expect($postInDb->translations->where('locale', 'es')->first()->title)->toBe('Post Creado por Agente IA');

    // 2. LIST POSTS via MCP
    $listResponse = mcpHumanApprovedRequest([
        'jsonrpc' => '2.0',
        'id' => 3,
        'method' => 'tools/call',
        'params' => [
            'name' => 'cms_list_posts',
            'arguments' => [
                'search' => 'Agente IA',
            ],
        ],
    ]);
    $listContent = json_decode($listResponse['result']['content'][0]['text'], true);
    expect($listContent)->toBeArray();
    expect(count($listContent))->toBeGreaterThanOrEqual(1);

    // 3. UPDATE POST via MCP
    $updateResponse = mcpHumanApprovedRequest([
        'jsonrpc' => '2.0',
        'id' => 4,
        'method' => 'tools/call',
        'params' => [
            'name' => 'cms_update_post',
            'arguments' => [
                'id' => $postId,
                'title' => 'Post Actualizado por IA (v2)',
                'content' => '## Contenido Modificado por Agente',
                'locale' => 'es',
            ],
        ],
    ]);
    $updateContent = json_decode($updateResponse['result']['content'][0]['text'], true);
    expect($updateContent['success'])->toBeTrue();

    // Verify in database
    $postUpdated = Post::with('translations')->find($postId);
    expect($postUpdated->translations->where('locale', 'es')->first()->title)->toBe('Post Actualizado por IA (v2)');

    // 4. DELETE POST via MCP
    $deleteResponse = mcpHumanApprovedRequest([
        'jsonrpc' => '2.0',
        'id' => 5,
        'method' => 'tools/call',
        'params' => [
            'name' => 'cms_delete_post',
            'arguments' => [
                'id' => $postId,
            ],
        ],
    ]);
    $deleteContent = json_decode($deleteResponse['result']['content'][0]['text'], true);
    expect($deleteContent['success'])->toBeTrue();

    // Verify deleted in database
    expect(Post::find($postId))->toBeNull();
});

test('mcp server creates and deletes pages and categories', function (): void {
    // 1. Create Category via MCP
    $createCatResponse = mcpHumanApprovedRequest([
        'jsonrpc' => '2.0',
        'id' => 6,
        'method' => 'tools/call',
        'params' => [
            'name' => 'cms_create_category',
            'arguments' => [
                'translations' => [
                    'es' => ['name' => 'Ciberseguridad IA', 'slug' => 'ciberseguridad-ia'],
                    'en' => ['name' => 'AI Cybersecurity', 'slug' => 'ai-cybersecurity'],
                ],
            ],
        ],
    ]);
    $catContent = json_decode($createCatResponse['result']['content'][0]['text'], true);
    expect($catContent['success'])->toBeTrue();
    $catId = $catContent['category_id'];

    expect(Category::find($catId))->not->toBeNull();

    // Delete Category via MCP
    $delCatResponse = mcpHumanApprovedRequest([
        'jsonrpc' => '2.0',
        'id' => 7,
        'method' => 'tools/call',
        'params' => [
            'name' => 'cms_delete_category',
            'arguments' => ['id' => $catId],
        ],
    ]);
    $delCatContent = json_decode($delCatResponse['result']['content'][0]['text'], true);
    expect($delCatContent['success'])->toBeTrue();
    expect(Category::find($catId))->toBeNull();

    // 2. Create Page via MCP
    $createPageResponse = mcpHumanApprovedRequest([
        'jsonrpc' => '2.0',
        'id' => 8,
        'method' => 'tools/call',
        'params' => [
            'name' => 'cms_create_page',
            'arguments' => [
                'title' => 'Landing Page Generada por IA',
                'slug' => 'landing-ia-mcp',
                'locale' => 'es',
            ],
        ],
    ]);
    $pageContent = json_decode($createPageResponse['result']['content'][0]['text'], true);
    expect($pageContent['success'])->toBeTrue();
    $pageId = $pageContent['page_id'];

    expect(Page::find($pageId))->not->toBeNull();

    // Delete Page via MCP
    $delPageResponse = mcpHumanApprovedRequest([
        'jsonrpc' => '2.0',
        'id' => 9,
        'method' => 'tools/call',
        'params' => [
            'name' => 'cms_delete_page',
            'arguments' => ['id' => $pageId],
        ],
    ]);
    $delPageContent = json_decode($delPageResponse['result']['content'][0]['text'], true);
    expect($delPageContent['success'])->toBeTrue();
    expect(Page::find($pageId))->toBeNull();
});

test('mcp server performs SEO and GEO content quality audit', function (): void {
    $auditResponse = mcpHumanApprovedRequest([
        'jsonrpc' => '2.0',
        'id' => 10,
        'method' => 'tools/call',
        'params' => [
            'name' => 'cms_audit_seo_geo',
            'arguments' => [
                'title' => 'Guía de Optimización GEO y Modelos LLM',
                'content' => "## Introducción al GEO\n\nEl Generative Engine Optimization es vital para indexación.\n\n### Preguntas Frecuentes (FAQ)\n\n¿Qué es GEO?\nEs la optimización para motores de IA como Perplexity y ChatGPT.",
                'meta_description' => 'Guía completa sobre Generative Engine Optimization.',
                'primary_keyword' => 'GEO',
            ],
        ],
    ]);

    expect($auditResponse)->toHaveKey('result');
    $auditResult = json_decode($auditResponse['result']['content'][0]['text'], true);
    expect($auditResult)->toHaveKey('overall_score');
    expect($auditResult['overall_score'])->toBeGreaterThanOrEqual(40);
    expect($auditResult)->toHaveKey('seo_score');
    expect($auditResult)->toHaveKey('geo_score');
    expect($auditResult)->toHaveKey('metrics');
    expect($auditResult)->toHaveKey('recommendations');
});
