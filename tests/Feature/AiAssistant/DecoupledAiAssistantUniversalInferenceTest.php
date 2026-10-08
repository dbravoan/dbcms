<?php

declare(strict_types=1);

namespace Tests\Feature\AiAssistant;

use App\Core\Modules\ModuleManager;
use App\Models\User;
use Database\Seeders\PlatformShowcaseSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['services.gemini.key' => 'fake-gemini-key']);
    Http::preventStrayRequests();
    $this->seed(PlatformShowcaseSeeder::class);
    app(ModuleManager::class)->discoverModules();
});

test('admin layout dynamically receives copilot bridge via view composer stack without hardcoded coupling', function (): void {
    $admin = User::where('email', 'admin@db-cms.io')->first() ?? User::factory()->create();

    $response = $this->actingAs($admin)
        ->get(route('admin.pages.index'));

    $response->assertOk();

    // Verify Copilot Bridge was injected into admin_modals stack
    $response->assertSee('aiCopilotBridge(', false);
    $response->assertSee('Alt+A', false);
    $response->assertSee('cms-insert-block', false);
    $response->assertSee('openCopilotModal()', false);

    // And it must be injected exactly once (regression: boot() ran twice -> 2 modals).
    expect(substr_count((string) $response->getContent(), 'function aiCopilotBridge('))->toBe(1);
});

test('pages module has zero hardcoded knowledge of ai_assistant and content_audit', function (): void {
    $pagesDir = base_path('modules/Pages');

    // Models should not import ContentAudit traits
    $pageModel = file_get_contents($pagesDir.'/Models/Page.php');
    expect($pageModel)->not->toContain('HasContentAudit')
        ->and($pageModel)->not->toContain('Modules\\ContentAudit');

    // Controllers should not inject or import ContentAudit
    $postController = file_get_contents($pagesDir.'/Http/Controllers/Admin/PagePostController.php');
    expect($postController)->not->toContain('ContentAudit')
        ->and($postController)->not->toContain('contentAuditService');

    $putController = file_get_contents($pagesDir.'/Http/Controllers/Admin/PagePutController.php');
    expect($putController)->not->toContain('ContentAudit')
        ->and($putController)->not->toContain('contentAuditService');
});

test('blog module has zero hardcoded knowledge of ai_assistant and content_audit', function (): void {
    $blogDir = base_path('modules/Blog');

    // Models should not import ContentAudit traits
    $postModel = file_get_contents($blogDir.'/Models/Post.php');
    expect($postModel)->not->toContain('HasContentAudit')
        ->and($postModel)->not->toContain('Modules\\ContentAudit');

    // Controllers should not inject or import ContentAudit
    $postController = file_get_contents($blogDir.'/Http/Controllers/Admin/PostPostController.php');
    expect($postController)->not->toContain('ContentAudit')
        ->and($postController)->not->toContain('contentAuditService');

    $putController = file_get_contents($blogDir.'/Http/Controllers/Admin/PostPutController.php');
    expect($putController)->not->toContain('ContentAudit')
        ->and($putController)->not->toContain('contentAuditService');
});

test('shop module has zero hardcoded knowledge of content_audit', function (): void {
    $shopDir = base_path('modules/Shop');

    $productModel = file_get_contents($shopDir.'/Models/Product.php');
    expect($productModel)->not->toContain('HasContentAudit')
        ->and($productModel)->not->toContain('Modules\\ContentAudit');
});

test('ai assistant generate endpoint responds with content for copilot bridge', function (): void {
    $admin = User::where('email', 'admin@db-cms.io')->first() ?? User::factory()->create();

    $response = $this->actingAs($admin)
        ->withoutMiddleware(ValidateCsrfToken::class)
        ->postJson(route('admin.ai_assistant.generate'), [
            'action' => 'draft',
            'prompt' => 'Resumen ejecutivo de dbCMS y sus capacidades de arquitectura limpia',
            'topic' => 'Resumen ejecutivo de dbCMS y sus capacidades de arquitectura limpia',
            'locale' => 'es',
        ]);

    $response->assertOk();
    $response->assertJsonStructure([
        'success',
        'action',
        'data',
    ]);
});
