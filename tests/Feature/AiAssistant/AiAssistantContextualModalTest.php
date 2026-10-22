<?php

declare(strict_types=1);

use App\Content\Pages\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\AiComposition;
use Tests\Support\CmsFixtures;
use Tests\Support\VisualContentFixtures;

uses(RefreshDatabase::class, AiComposition::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    CmsFixtures::localization();
    CmsFixtures::identities();
    VisualContentFixtures::home();
    $this->admin = User::where('email', 'admin@db-cms.io')->firstOrFail();
});

test('the copilot bridge is contextual (no manual tab bar)', function (): void {
    $response = $this->actingAs($this->admin, 'web')->get(route('admin.ai_assistant.workbench'));

    $response->assertOk();

    // Contextual behaviour is wired: block-manager origin + auto draft/improve.
    $response->assertSee('cms-open-ai-blocks', false);
    $response->assertSee('openBlockCopilot', false);
    $response->assertSee('isMetadataField', false);

    // The old manual 4-tab bar is gone.
    $response->assertDontSee("activeTab = 'block'", false);
    $response->assertDontSee("activeTab = 'seo'", false);
});

test('the block manager exposes the AI section generator trigger', function (): void {
    $page = Page::firstOrFail();

    $response = $this->actingAs($this->admin, 'web')->get(route('admin.pages.edit', $page->id));

    $response->assertOk();
    $response->assertSee('cms-open-ai-blocks', false);
});
