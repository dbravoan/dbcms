<?php

declare(strict_types=1);

use App\Core\Capabilities\ActorContext;
use App\Core\Integrations\IntegrationGuideRegistry;
use App\Core\Modules\ModuleManager;
use App\Models\Language;
use App\Models\Market;
use App\Models\User;
use App\Services\Cms\AdminThemeService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Modules\CreativeStudio\Models\CreativeCampaign;
use Modules\CreativeStudio\Models\CreativeGenerationTask;
use Modules\CreativeStudio\Services\CreativeStudioDomainService;
use Modules\Marketing\Contracts\CreativeGenerationGatewayInterface;
use Modules\Marketing\DTOs\GenerationReceipt;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\Support\AiMcpFixture;
use Tests\Support\CreativeWorkflowComposition;
use Tests\Support\PassportTestKeys;

uses(CreativeWorkflowComposition::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    Queue::fake();
    app(ModuleManager::class)->discoverModules();
    app(ModuleManager::class)->activate('creative-studio');
    $this->actor = new ActorContext(AiMcpFixture::admin()->id);
    app(ModuleManager::class)->activate('ai_assistant');
    $this->gateway = Mockery::mock(CreativeGenerationGatewayInterface::class);
    $this->gateway->shouldReceive('assertAvailable')->zeroOrMoreTimes()->andReturnNull();
    app()->instance(CreativeGenerationGatewayInterface::class, $this->gateway);
    config(['marketing.artifact_root' => sys_get_temp_dir().'/creative-tests-'.bin2hex(random_bytes(8))]);
    $this->market = Market::query()->firstOrCreate(['code' => 'es'], ['name' => 'España', 'is_active' => true]);
    $language = Language::query()->firstOrCreate(['code' => 'es'], ['name' => 'Español', 'native_name' => 'Español', 'is_active' => true]);
    $this->market->languages()->syncWithoutDetaching([$language->id]);
});

function creativeCampaignInput(): array
{
    return ['title' => 'Biblioteca de verano', 'brief' => 'Una invitación a leer al aire libre', 'mode' => 'free', 'objective' => 'Dar a conocer la biblioteca', 'audience' => 'Familias', 'locale' => 'es', 'market_ids' => [Market::where('code', 'es')->value('id')], 'budget_limit' => 1000, 'currency' => 'USD'];
}

test('campaign shares identity across markets and captures only supplied free context', function (): void {
    $service = app(CreativeStudioDomainService::class);
    $campaign = $service->createCampaign(creativeCampaignInput(), $this->actor);

    expect($campaign)->toBeInstanceOf(CreativeCampaign::class);
    expect($campaign->translations()->count())->toBe(1);
    expect($campaign->markets()->count())->toBe(1);
    expect($campaign->context_snapshot)->not->toHaveKey('users');
    expect($campaign->context_snapshot['brief'])->toBe('Una invitación a leer al aire libre');
});

test('editing creates immutable revisions and distinct package fingerprints', function (): void {
    $service = app(CreativeStudioDomainService::class);
    $campaign = $service->createCampaign(creativeCampaignInput(), $this->actor);
    $first = $service->createRevision($campaign->id, ['text' => 'El verano empieza con un libro.', 'locale' => 'es', 'market' => 'es', 'format' => 'text'], $this->actor);
    $second = $service->createRevision($campaign->id, ['text' => 'Ven a descubrir nuestras novedades.', 'locale' => 'es', 'market' => 'es', 'format' => 'text'], $this->actor);

    expect($first->fresh()->text)->toBe('El verano empieza con un libro.');
    expect($second->number)->toBe(2);
    expect($service->getPackage($first->id, $this->actor)->fingerprint())->not->toBe($service->getPackage($second->id, $this->actor)->fingerprint());
});

test('unauthorized users cannot create campaigns', function (): void {
    $actor = new ActorContext(User::factory()->create()->id);
    app(CreativeStudioDomainService::class)->createCampaign(creativeCampaignInput(), $actor);
})->throws(AuthorizationException::class);

test('budget reservation includes every generation stage and duplicate requests do not spend twice', function (): void {
    config(['creative_studio.installation_budget' => 1000, 'creative_studio.prices' => ['text' => 10, 'image' => 40, 'video' => 100]]);
    $service = app(CreativeStudioDomainService::class);
    $campaign = $service->createCampaign(creativeCampaignInput(), $this->actor);
    $input = ['idempotency_key' => 'request-one', 'format' => 'carousel', 'slides' => 3, 'locale' => 'es', 'market' => 'es', 'provider' => 'gemini', 'image_provider' => 'openai'];
    $task = $service->enqueueGeneration($campaign->id, $input, $this->actor);
    $same = $service->enqueueGeneration($campaign->id, $input, $this->actor);

    expect($task)->toBeInstanceOf(CreativeGenerationTask::class);
    expect($same->id)->toBe($task->id);
    expect($task->reserved_cost)->toBe(130);
    expect(CreativeGenerationTask::where('campaign_id', $campaign->id)->count())->toBe(1);
});

test('exhausted or unconfigured installation budgets reject paid generation before dispatch', function (): void {
    config(['creative_studio.installation_budget' => 20, 'creative_studio.prices' => ['text' => 10, 'image' => 40, 'video' => 100]]);
    $service = app(CreativeStudioDomainService::class);
    $campaign = $service->createCampaign(creativeCampaignInput(), $this->actor);
    $service->enqueueGeneration($campaign->id, ['idempotency_key' => 'exhausted', 'format' => 'image', 'locale' => 'es', 'market' => 'es'], $this->actor);
})->throws(ValidationException::class);

test('traffic campaigns reject private or missing source content', function (): void {
    app(CreativeStudioDomainService::class)->createCampaign(array_replace(creativeCampaignInput(), ['mode' => 'traffic', 'source_type' => 'user', 'source_id' => 1]), $this->actor);
})->throws(ValidationException::class);

test('disabled module cannot create campaigns even for administrators', function (): void {
    app(ModuleManager::class)->deactivate('creative-studio');
    app(CreativeStudioDomainService::class)->createCampaign(creativeCampaignInput(), $this->actor);
})->throws(AuthorizationException::class);

test('a completed text operation creates one sealed revision and duplicate workers do not regenerate', function (): void {
    config(['creative_studio.installation_budget' => 1000, 'creative_studio.prices.text' => 10]);
    $this->gateway->shouldReceive('generate')->once()->andReturn(['receipt' => new GenerationReceipt('operation', 'completed', 'gemini', 'test', null, ['prompt_tokens' => 12], 10, null), 'text' => '{"text":"Ven a leer con nosotros.","visual_prompts":[]}', 'artifact' => null, 'pending' => false]);
    $service = app(CreativeStudioDomainService::class);
    $campaign = $service->createCampaign(creativeCampaignInput(), $this->actor);
    $task = $service->enqueueGeneration($campaign->id, ['idempotency_key' => 'text-once', 'format' => 'text', 'locale' => 'es', 'market' => 'es'], $this->actor);
    $service->processGeneration($task->id);
    $service->processGeneration($task->id);
    $service->processGeneration($task->id);
    expect($task->fresh()->status)->toBe('completed');
    expect($campaign->revisions()->count())->toBe(1);
    expect($service->getPackage($task->fresh()->revision_id, $this->actor)->text)->toBe('Ven a leer con nosotros.');
});

test('a submitting paid operation without remote identity stays uncertain and preserves its reservation', function (): void {
    config(['creative_studio.installation_budget' => 1000, 'creative_studio.prices.text' => 10]);
    $service = app(CreativeStudioDomainService::class);
    $campaign = $service->createCampaign(creativeCampaignInput(), $this->actor);
    $task = $service->enqueueGeneration($campaign->id, ['idempotency_key' => 'uncertain', 'format' => 'text', 'locale' => 'es', 'market' => 'es'], $this->actor);
    $stages = $task->stages;
    $stages[0]['state'] = 'submitting';
    $task->update(['stages' => $stages]);
    $this->gateway->shouldNotReceive('generate');
    $service->processGeneration($task->id);
    expect($task->fresh()->status)->toBe('uncertain');
    expect($task->fresh()->reserved_cost)->toBe(10);
    $service->reconcileGeneration($task->id, $this->actor);
})->throws(ConflictHttpException::class);

test('private uploaded images compose into a genuine PNG and survive AiAssistant deactivation', function (): void {
    app(ModuleManager::class)->deactivate('ai_assistant');
    $service = app(CreativeStudioDomainService::class);
    $campaign = $service->createCampaign(creativeCampaignInput(), $this->actor);
    $asset = $service->importUpload($campaign->id, UploadedFile::fake()->image('photo.png', 600, 400), $this->actor);
    $task = $service->enqueueComposition($campaign->id, ['text' => 'Un verano para leer.', 'locale' => 'es', 'market' => 'es', 'format' => 'image', 'template' => 'square', 'asset_ids' => [$asset->id]], $this->actor);
    $service->processGeneration($task->id);
    $task->refresh();
    expect($task->status)->toBe('completed');
    $package = $service->getPackage($task->revision_id, $this->actor);
    $artifact = $package->artifacts[0];
    expect($artifact->mimeType)->toBe('image/png');
    expect([$artifact->width, $artifact->height])->toBe([1080, 1080]);
    expect($artifact->path)->toStartWith('creative-studio/'.$campaign->id.'/');
    expect(file_exists(public_path($artifact->path)))->toBeFalse();
});

test('composing still images creates a thirty second vertical MP4 with real probe metadata', function (): void {
    $service = app(CreativeStudioDomainService::class);
    $campaign = $service->createCampaign(creativeCampaignInput(), $this->actor);
    $asset = $service->importUpload($campaign->id, UploadedFile::fake()->image('scene.png', 600, 400), $this->actor);
    $task = $service->enqueueComposition($campaign->id, ['text' => 'Una biblioteca para todos. Descubre tu próximo libro.', 'locale' => 'es', 'market' => 'es', 'format' => 'video', 'template' => 'video', 'duration' => 30, 'asset_ids' => [$asset->id]], $this->actor);
    $service->processGeneration($task->id);
    $task->refresh();
    expect($task->status)->toBe('completed');
    $artifact = $service->getPackage($task->revision_id, $this->actor)->artifacts[0];
    expect($artifact->mimeType)->toBe('video/mp4');
    expect([$artifact->width, $artifact->height])->toBe([1080, 1920]);
    expect(abs($artifact->durationSeconds - 30))->toBeLessThanOrEqual(0.5);
});

test('an asset from another campaign cannot be supplied to a revision', function (): void {
    $service = app(CreativeStudioDomainService::class);
    $first = $service->createCampaign(creativeCampaignInput(), $this->actor);
    $second = $service->createCampaign(creativeCampaignInput(), $this->actor);
    $asset = $service->importUpload($first->id, UploadedFile::fake()->image('secret.png', 100, 100), $this->actor);
    $service->createRevision($second->id, ['text' => 'Another campaign', 'locale' => 'es', 'market' => 'es', 'format' => 'image', 'asset_ids' => [$asset->id]], $this->actor);
})->throws(ModelNotFoundException::class);

test('campaign budget cannot be lowered below a paid reservation', function (): void {
    config(['creative_studio.installation_budget' => 1000, 'creative_studio.prices.text' => 10]);
    $service = app(CreativeStudioDomainService::class);
    $campaign = $service->createCampaign(creativeCampaignInput(), $this->actor);
    $service->enqueueGeneration($campaign->id, ['idempotency_key' => 'reserved', 'format' => 'text', 'locale' => 'es', 'market' => 'es'], $this->actor);
    $service->setBudget($campaign->id, ['budget_limit' => 5, 'currency' => 'USD'], $this->actor);
})->throws(ValidationException::class);

test('the same request key cannot identify different generation contents', function (): void {
    config(['creative_studio.installation_budget' => 1000, 'creative_studio.prices.text' => 10]);
    $service = app(CreativeStudioDomainService::class);
    $campaign = $service->createCampaign(creativeCampaignInput(), $this->actor);
    $input = ['idempotency_key' => 'same', 'format' => 'text', 'locale' => 'es', 'market' => 'es'];
    $service->enqueueGeneration($campaign->id, $input, $this->actor);
    $service->enqueueGeneration($campaign->id, $input + ['instruction' => 'Different instructions'], $this->actor);
})->throws(ConflictHttpException::class);

test('private ZIP export remains available without AI and contains the sealed copy and manifest', function (): void {
    $service = app(CreativeStudioDomainService::class);
    $campaign = $service->createCampaign(creativeCampaignInput(), $this->actor);
    $revision = $service->createRevision($campaign->id, ['text' => 'El verano empieza con un libro.', 'locale' => 'es', 'market' => 'es', 'format' => 'text'], $this->actor);
    app(ModuleManager::class)->deactivate('ai_assistant');
    $response = $this->actingAs(User::findOrFail($this->actor->userId), 'web')->get(route('admin.creative-studio.export', $revision->id));
    $response->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    $zip = new ZipArchive;
    expect($zip->open($response->baseResponse->getFile()->getPathname()))->toBeTrue();
    expect($zip->getFromName('copy.txt'))->toBe('El verano empieza con un libro.');
    $manifest = json_decode($zip->getFromName('manifest.json'), true);
    expect($manifest['revisionId'])->toBe($revision->id);
    $zip->close();
});

test('both admin brands render the campaign workbench using canonical components', function (): void {
    $service = app(CreativeStudioDomainService::class);
    $campaign = $service->createCampaign(creativeCampaignInput(), $this->actor);
    foreach (['dbcms', 'termocms'] as $brand) {
        app(AdminThemeService::class)->setActiveTheme($brand);
        $this->actingAs(User::findOrFail($this->actor->userId), 'web')->get(route('admin.creative-studio.show', $campaign->id))->assertOk()->assertSee('Identidad visual')->assertSee('Componer con plantillas');
    }
});

test('creative REST endpoints reject an unauthenticated reader', function (): void {
    PassportTestKeys::configure();
    $this->getJson('/api/v1/creative-studio')->assertUnauthorized();
});

test('a late worker cannot overwrite a newer claim or dispatch another paid stage', function (): void {
    config(['creative_studio.installation_budget' => 1000, 'creative_studio.prices.text' => 10]);
    $service = app(CreativeStudioDomainService::class);
    $campaign = $service->createCampaign(creativeCampaignInput(), $this->actor);
    $task = $service->enqueueGeneration($campaign->id, ['idempotency_key' => 'late-worker', 'format' => 'text', 'locale' => 'es', 'market' => 'es'], $this->actor);
    $calls = 0;
    $oldClaim = null;
    $this->gateway->shouldReceive('generate')->twice()->andReturnUsing(function ($modality, $prompt, $provider, $model, $options, $operationId) use (&$calls, &$oldClaim, $service, $task): array {
        $calls++;
        if ($calls === 1) {
            $oldClaim = $task->fresh()->claim_id;
            $options['checkpoint'](new GenerationReceipt($operationId, 'pending_remote', 'gemini', 'test', 'remote-known'), null);
            $task->fresh()->update(['lease_until' => now()->subMinute()]);
            expect($service->recoverExpired())->toBe(1);
            $service->reconcileGeneration($task->id, $this->actor);
            $service->processGeneration($task->id);

            return ['receipt' => new GenerationReceipt($operationId, 'completed', 'gemini', 'old-worker', 'remote-known'), 'text' => '{"text":"This obsolete worker must not win."}', 'artifact' => null, 'pending' => false];
        }
        expect($task->fresh()->claim_id)->not->toBe($oldClaim);

        return ['receipt' => new GenerationReceipt($operationId, 'pending_remote', 'gemini', 'new-worker', 'remote-known'), 'text' => null, 'artifact' => null, 'pending' => true];
    });
    $service->processGeneration($task->id);
    $task->refresh();
    expect($task->status)->toBe('pending_remote');
    expect($task->stages[0]['receipt']['model'])->toBe('new-worker');
    expect($task->claim_id)->toBeNull();
    expect($campaign->revisions()->count())->toBe(0);
    expect($task->reserved_cost)->toBe(10);
});

test('provider guides belong to AiAssistant while manual CreativeStudio remains available without it', function (): void {
    $registry = app(IntegrationGuideRegistry::class);
    expect($registry->find('ai_assistant', 'openai'))->not->toBeNull()
        ->and($registry->find('creative-studio', 'openai'))->toBeNull()
        ->and($registry->find('creative-studio', 'media-composition'))->not->toBeNull();
    $campaign = app(CreativeStudioDomainService::class)->createCampaign(creativeCampaignInput(), $this->actor);
    $this->actingAs(User::findOrFail($this->actor->userId), 'web');
    $this->get(route('admin.creative-studio.show', $campaign->id))->assertOk()
        ->assertSee(route('admin.modules.guides.index', 'ai_assistant'), false);
    app(ModuleManager::class)->deactivate('ai_assistant');
    $this->get(route('admin.creative-studio.show', $campaign->id))->assertOk()
        ->assertSee('Componer con plantillas')
        ->assertDontSee(route('admin.modules.guides.index', 'ai_assistant'), false);
});
