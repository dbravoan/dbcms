<?php

declare(strict_types=1);
use App\Contracts\Services\ModuleSettingsServiceInterface;
use App\Models\User;
use Modules\AiAssistant\Contracts\AiMultimodalOrchestratorInterface;
use Modules\AiAssistant\DTOs\MultimodalOrchestrationRequest;
use Modules\AiAssistant\DTOs\MultimodalOrchestrationResult;
use Modules\AiAssistant\Services\AiProviderSettings;
use Tests\Support\AiComposition;
use Tests\Support\CmsFixtures;

uses(AiComposition::class);
test('the canonical image choice reaches the orchestration request without overriding false', function (): void {
    CmsFixtures::localization();
    CmsFixtures::identities();
    $u = User::where('email', 'admin@db-cms.io')->sole();
    app(AiProviderSettings::class)->save('openai', ['api_key' => 'AUDIT-NONFUNCTIONAL-LOCAL-FAKE']);
    app(ModuleSettingsServiceInterface::class)->set('ai_assistant', 'default_text_provider', 'openai');
    app(ModuleSettingsServiceInterface::class)->set('ai_assistant', 'default_image_provider', 'openai');
    $flags = [];
    $mock = Mockery::mock(AiMultimodalOrchestratorInterface::class);
    $mock->shouldReceive('orchestratePage')->twice()->andReturnUsing(function (MultimodalOrchestrationRequest $r) use (&$flags) {
        $flags[] = $r->generateImage;

        return new MultimodalOrchestrationResult('page', null, 'Audit', 'audit', '', '', '', '');
    });
    app()->instance(AiMultimodalOrchestratorInterface::class, $mock);
    foreach ([true, false] as $generateImage) {
        $this->actingAs($u)->postJson('/admin/ai-assistant/generate', ['action' => 'orchestrate_multimodal', 'entity_type' => 'page', 'topic' => 'Tema audit', 'locale' => 'es', 'market' => 'es', 'generate_image' => $generateImage, 'generate_video' => false, 'persist_draft' => false])->assertOk();
    }
    expect($flags)->toBe([true, false]);
});
