<?php

declare(strict_types=1);

use App\Core\Capabilities\ActorContext;
use App\Core\Modules\ModuleManager;
use App\Models\Language;
use App\Models\Market;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Modules\CreativeStudio\Services\CreativeStudioDomainService;
use Modules\Marketing\Contracts\MarketingArtifactStoreInterface;
use Modules\Marketing\DTOs\CreativePackage;
use Modules\SocialPublishing\Models\SocialAccount;
use Modules\SocialPublishing\Models\SocialRevision;
use Modules\SocialPublishing\Services\PublicationService;
use Tests\Support\AiMcpFixture;
use Tests\Support\CreativeSocialComposition;

uses(CreativeSocialComposition::class);

test('a composed JPEG transfers to Instagram as independent approved bytes and survives source changes', function (): void {
    Http::preventStrayRequests();
    Queue::fake();
    $modules = app(ModuleManager::class);
    $user = AiMcpFixture::admin();
    Auth::guard('web')->login($user);
    $actor = new ActorContext($user->id);
    config(['marketing.artifact_root' => sys_get_temp_dir().'/marketing-handoff-'.bin2hex(random_bytes(8))]);
    $market = Market::firstOrCreate(['code' => 'es'], ['name' => 'España', 'is_active' => true]);
    $language = Language::firstOrCreate(['code' => 'es'], ['name' => 'Español', 'native_name' => 'Español', 'is_active' => true]);
    $market->languages()->syncWithoutDetaching([$language->id]);
    $creative = app(CreativeStudioDomainService::class);
    $campaign = $creative->createCampaign(['title' => 'Campaña', 'brief' => 'Una invitación pública', 'mode' => 'free', 'objective' => 'Informar', 'audience' => 'Familias', 'locale' => 'es', 'market_ids' => [$market->id], 'budget_limit' => 0, 'currency' => 'USD'], $actor);
    $asset = $creative->importUpload($campaign->id, UploadedFile::fake()->image('source.png', 100, 100), $actor);
    $task = $creative->enqueueComposition($campaign->id, ['text' => 'Mensaje final', 'locale' => 'es', 'market' => 'es', 'format' => 'image', 'template' => 'square', 'image_encoding' => 'jpg', 'asset_ids' => [$asset->id]], $actor);
    $creative->processGeneration($task->id);
    $package = $creative->getPackage($task->fresh()->revision_id, $actor);
    expect($package->artifacts[0]->mimeType)->toBe('image/jpeg');
    $account = SocialAccount::create(['provider' => 'instagram', 'remote_id' => '123', 'identity' => 'instagram:123', 'label' => 'Instagram', 'status' => 'connected', 'access_token' => 'test-fixture', 'grants' => ['instagram_business_basic', 'instagram_business_content_publish'], 'expires_at' => now()->addDay()]);
    $social = app(PublicationService::class);
    $publication = $social->create($package, $account->id, [], $actor);
    $copied = CreativePackage::fromArray(SocialRevision::findOrFail($publication->revision_id)->package);
    expect($copied->artifacts[0]->path)->not->toBe($package->artifacts[0]->path)
        ->and($copied->artifacts[0]->sha256)->toBe($package->artifacts[0]->sha256);
    $social->approve($publication->id, $publication->payload_hash, $actor);
    $store = app(MarketingArtifactStoreInterface::class);
    file_put_contents($store->absolutePath($package->artifacts[0]), 'source changed');
    $modules->deactivate('creative-studio');
    expect($store->verify($copied->artifacts[0]))->toBeTrue()
        ->and($social->approved($publication->fresh()))->toBeTrue();
    Http::assertNothingSent();
});
