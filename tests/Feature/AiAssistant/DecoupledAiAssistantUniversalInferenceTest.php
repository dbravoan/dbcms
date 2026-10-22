<?php

declare(strict_types=1);

namespace Tests\Feature\AiAssistant;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\AiCompletionFixture;
use Tests\Support\AiComposition;
use Tests\Support\CmsFixtures;

uses(RefreshDatabase::class, AiComposition::class);

beforeEach(function (): void {
    config(['services.gemini.key' => 'fake-gemini-key']);
    Http::preventStrayRequests();
    CmsFixtures::localization();
    CmsFixtures::identities();
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

test('ai assistant generate endpoint responds with content for copilot bridge', function (): void {
    AiCompletionFixture::configure();
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
