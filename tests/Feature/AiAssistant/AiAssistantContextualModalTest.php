<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\PlatformShowcaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Pages\Models\Page;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    $this->seed(PlatformShowcaseSeeder::class);
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
