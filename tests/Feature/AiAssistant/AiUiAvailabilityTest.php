<?php

declare(strict_types=1);
use App\Core\Modules\ModuleManager;
use App\Models\CmsModule;
use App\Models\User;
use Tests\Support\AiComposition;
use Tests\Support\CmsFixtures;

uses(AiComposition::class);
beforeEach(function (): void {
    $this->withoutVite();
    CmsFixtures::localization();
});
test('editors without AI permission never receive AI controls', function (): void {
    CmsFixtures::identities(['editor' => ['access:admin', 'manage:pages']]);
    $u = User::where('email', 'editor@db-cms.io')->sole();
    expect($u->can('use:ai'))->toBeFalse();
    $this->actingAs($u)->get('/admin/pages/create')->assertOk()->assertDontSee('aiCopilotBridge', false)->assertDontSee('cms-open-ai-blocks', false);
    $this->postJson('/admin/ai-assistant/generate', ['action' => 'text', 'prompt' => 'test'])->assertForbidden();
});
test('disabled AI never injects UI contributions', function (): void {
    CmsFixtures::identities();
    $u = User::where('email', 'admin@db-cms.io')->sole();
    CmsModule::where('identifier', 'ai_assistant')->update(['is_active' => false]);
    expect(app(ModuleManager::class)->isModuleEnabled('ai_assistant'))->toBeFalse();
    $this->actingAs($u)->get('/admin/pages/create')->assertOk()->assertDontSee('aiCopilotBridge', false)->assertDontSee('cms-open-ai-blocks', false);
    $this->postJson('/admin/ai-assistant/generate', ['action' => 'text', 'prompt' => 'test'])->assertNotFound();
});
