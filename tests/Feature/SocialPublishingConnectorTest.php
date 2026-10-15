<?php

declare(strict_types=1);

use App\Contracts\Services\CampaignAttributionGatewayInterface;
use App\Contracts\Services\MarketingArtifactStoreInterface;
use App\Core\Capabilities\ActorContext;
use App\DTOs\Marketing\ArtifactDescriptor;
use App\DTOs\Marketing\CreativePackage;
use App\Models\CmsModule;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Modules\SocialPublishing\Connectors\FacebookConnector;
use Modules\SocialPublishing\Connectors\InstagramConnector;
use Modules\SocialPublishing\Connectors\LinkedInConnector;
use Modules\SocialPublishing\Connectors\YouTubeConnector;
use Modules\SocialPublishing\Models\SocialAccount;
use Modules\SocialPublishing\Models\SocialMetric;
use Modules\SocialPublishing\Services\DeliveryWorker;
use Modules\SocialPublishing\Services\PublicationService;
use Modules\SocialPublishing\SocialPublishingModuleServiceProvider;

function socialConnectorFiles(): MarketingArtifactStoreInterface
{
    $files = Mockery::mock(MarketingArtifactStoreInterface::class);
    $files->shouldReceive('verify')->andReturn(true);
    $files->shouldReceive('copy')->andReturnUsing(fn (ArtifactDescriptor $file): ArtifactDescriptor => $file);
    $files->shouldReceive('readStream')->andReturnUsing(function (): mixed {
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, 'fake');
        rewind($stream);

        return $stream;
    });

    return $files;
}

function socialConnectorPackage(string $mime = 'image/jpeg', int $count = 1): CreativePackage
{
    $assets = [];
    for ($i = 0; $i < $count; $i++) {
        $assets[] = new ArtifactDescriptor((string) $i, 'fixture/'.$i.'.'.($mime === 'video/mp4' ? 'mp4' : 'jpg'), str_repeat('a', 64), $mime, 4, 1080, 1080, $mime === 'video/mp4' ? 10 : null, ['video_codec' => 'h264']);
    }

    return new CreativePackage('p', 'r', null, 'es', 'es', 'Texto exacto', null, $assets);
}

function socialConnectorAccount(string $provider = 'instagram', string $id = '123'): SocialAccount
{
    return new SocialAccount(['provider' => $provider, 'remote_id' => $id, 'identity' => $provider.':'.$id, 'access_token' => 'fixture-token']);
}

it('separates Instagram carousel child creation container processing and final publication', function (): void {
    $connector = new InstagramConnector(socialConnectorFiles());
    $account = socialConnectorAccount();
    $package = socialConnectorPackage(count: 2);
    Http::fakeSequence()->push(['id' => '11'])->push(['id' => '12'])->push(['id' => '13'])->push(['status_code' => 'FINISHED'])->push(['id' => '14']);
    $result = $connector->step('prepare', $account, $package, ['asset_urls' => ['https://site.test/a.jpg', 'https://site.test/b.jpg']], []);
    expect($result->nextStep)->toBe('child:1');
    $result = $connector->step($result->nextStep, $account, $package, $result->receipt, []);
    expect($result->nextStep)->toBe('parent');
    $result = $connector->step($result->nextStep, $account, $package, $result->receipt, []);
    expect($result->state)->toBe('remote_processing');
    $result = $connector->step('poll', $account, $package, $result->receipt, []);
    expect($result->state)->toBe('ready_to_publish');
    $result = $connector->step('publish', $account, $package, $result->receipt, []);
    expect($result->state)->toBe('published')->and($result->remoteId)->toBe('14');
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/media') && ($r->data()['media_type'] ?? null) === 'CAROUSEL' && ($r->data()['children'] ?? null) === '11,12');
});

it('makes Instagram confirmation strictly read only and fails closed without a correlated media id', function (): void {
    $connector = new InstagramConnector(socialConnectorFiles());
    $p = socialConnectorPackage();
    $a = socialConnectorAccount();
    Http::preventStrayRequests();
    expect($connector->step('confirm', $a, $p, [], [])->state)->toBe('pending_confirmation');
    Http::assertNothingSent();
    Http::fake(['*' => Http::response(['id' => '99', 'owner' => ['id' => '123'], 'caption' => 'Texto exacto'])]);
    expect($connector->step('confirm', $a, $p, ['id' => '99'], [])->state)->toBe('published');
    Http::assertSent(fn ($r) => $r->method() === 'GET');
    Http::assertNotSent(fn ($r) => $r->method() === 'POST');
});

it('rejects unknown Instagram operations and invalid child indices before HTTP', function (): void {
    Http::preventStrayRequests();
    $connector = new InstagramConnector(socialConnectorFiles());
    foreach (['unknown', 'child:99', 'poll-typo'] as $step) {
        expect($connector->step($step, socialConnectorAccount(), socialConnectorPackage(), [], [])->state)->toBe('failed');
    }
    Http::assertNothingSent();
});

it('keeps a published Instagram container without a media identifier uncertain', function (): void {
    Http::fake(['*' => Http::response(['status_code' => 'PUBLISHED'])]);
    $connector = new InstagramConnector(socialConnectorFiles());
    expect($connector->step('poll', socialConnectorAccount(), socialConnectorPackage(), ['container' => '13'], [])->state)->toBe('pending_confirmation');
});

it('uses LinkedIn organic multiImage payload and canonical response header', function (): void {
    Http::fake(['*' => Http::response([], 201, ['x-restli-id' => 'urn:li:share:456'])]);
    $connector = new LinkedInConnector(socialConnectorFiles());
    $result = $connector->step('publish', socialConnectorAccount('linkedin', 'urn:li:organization:123'), socialConnectorPackage(count: 2), ['images' => [['id' => 'urn:li:image:1'], ['id' => 'urn:li:image:2']]], []);
    expect($result->remoteId)->toBe('urn:li:share:456');
    Http::assertSent(fn ($r) => $r->hasHeader('Linkedin-Version', '202609') && isset($r['content']['multiImage']) && ! isset($r['content']['carousel']));
});

it('recovers a known LinkedIn image upload only with a GET on its persisted asset receipt', function (): void {
    $connector = new LinkedInConnector(socialConnectorFiles());
    Http::fake(['*' => Http::response(['id' => 'urn:li:image:1', 'owner' => 'urn:li:organization:123', 'status' => 'AVAILABLE'])]);
    $result = $connector->step('poll:image:0', socialConnectorAccount('linkedin', 'urn:li:organization:123'), socialConnectorPackage(), ['images' => [['id' => 'urn:li:image:1', 'url' => 'https://www.linkedin.com/upload']]], []);
    expect($result->nextStep)->toBe('publish');
    Http::assertSent(fn ($r) => $r->method() === 'GET');
    Http::assertNotSent(fn ($r) => in_array($r->method(), ['POST', 'PUT'], true));
});

it('reconciles a worker LinkedIn upload timeout from its durable asset receipt without another write', function (): void {
    $user = User::factory()->create();
    $user->roles()->attach(Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin'])->id);
    Auth::guard('web')->login($user);
    CmsModule::updateOrCreate(['identifier' => 'social-publishing'], ['name' => 'SocialPublishing', 'provider' => SocialPublishingModuleServiceProvider::class, 'version' => '1.0.0', 'is_active' => true, 'is_installed' => true, 'settings' => ['is_enabled' => true]]);
    app()->instance(MarketingArtifactStoreInterface::class, socialConnectorFiles());
    $actor = new ActorContext($user->id);
    $account = SocialAccount::create(['provider' => 'linkedin', 'remote_id' => 'urn:li:organization:123', 'identity' => 'linkedin:urn:li:organization:123', 'label' => 'Organización', 'status' => 'connected', 'access_token' => 'fixture', 'expires_at' => now()->addDay(), 'grants' => ['w_organization_social', 'r_organization_social', 'rw_organization_admin'], 'metadata' => []]);
    $service = app(PublicationService::class);
    $p = $service->create(socialConnectorPackage(), $account->id, [], $actor);
    $service->approve($p->id, $p->payload_hash, $actor);
    $d = $service->schedule($p->id, now()->toIso8601String(), $actor);
    Http::fakeSequence()->push(['value' => ['image' => 'urn:li:image:1', 'uploadUrl' => 'https://www.linkedin.com/dms-uploads/one']], 200)->push([], 500)->push(['id' => 'urn:li:image:1', 'owner' => 'urn:li:organization:123', 'status' => 'AVAILABLE']);
    $worker = app(DeliveryWorker::class);
    $worker->run($d->id);
    expect($d->refresh()->step)->toBe('image-upload:0');
    $worker->run($d->id);
    expect($d->refresh()->status)->toBe('pending_confirmation');
    $worker->reconcile($d->id);
    expect($d->refresh()->step)->toBe('publish')->and($d->status)->toBe('preparing');
    Http::assertSentCount(3);
    Http::assertSent(fn ($r) => $r->method() === 'GET' && str_contains($r->url(), 'rest/images/'));
    $requests = Http::recorded();
    expect($requests->filter(fn ($pair) => $pair[0]->method() === 'POST'))->toHaveCount(1)->and($requests->filter(fn ($pair) => $pair[0]->method() === 'PUT'))->toHaveCount(1);
});

it('distinguishes YouTube resumable upload processing and confirmed public visibility', function (): void {
    $connector = new YouTubeConnector(socialConnectorFiles());
    $account = socialConnectorAccount('youtube', 'channel');
    $package = socialConnectorPackage('video/mp4');
    $metadata = ['title' => 'Vídeo aprobado', 'made_for_kids' => false];
    Http::fakeSequence()->push([], 200, ['Location' => 'https://www.googleapis.com/upload/youtube/v3/videos?upload_id=one'])->push(['id' => 'video123'], 201)->push(['items' => [['id' => 'video123', 'snippet' => ['channelId' => 'channel'], 'status' => ['uploadStatus' => 'processed', 'privacyStatus' => 'private'], 'processingDetails' => ['processingStatus' => 'succeeded']]]])->push(['id' => 'video123'])->push(['items' => [['id' => 'video123', 'snippet' => ['channelId' => 'channel'], 'status' => ['uploadStatus' => 'processed', 'privacyStatus' => 'public'], 'processingDetails' => ['processingStatus' => 'succeeded']]]]);
    $result = $connector->step('prepare', $account, $package, [], $metadata);
    expect($result->nextStep)->toBe('upload:0');
    $result = $connector->step('upload:0', $account, $package, $result->receipt, $metadata);
    expect($result->state)->toBe('remote_processing');
    $result = $connector->step('poll', $account, $package, $result->receipt, $metadata);
    expect($result->nextStep)->toBe('publish');
    $result = $connector->step('publish', $account, $package, $result->receipt, $metadata);
    expect($result->state)->not->toBe('published')->and($result->nextStep)->toBe('confirm');
    $result = $connector->step('confirm', $account, $package, $result->receipt, $metadata);
    expect($result->state)->toBe('published')->and($result->remoteId)->toBe('video123');
});

it('queries YouTube upload progress before any resumed bytes are sent', function (): void {
    Http::fake(['*' => Http::response([], 308, ['Range' => 'bytes=0-1'])]);
    $connector = new YouTubeConnector(socialConnectorFiles());
    $result = $connector->step('reconcile', socialConnectorAccount('youtube', 'channel'), socialConnectorPackage('video/mp4'), ['session' => 'https://www.googleapis.com/upload/youtube/v3/videos?upload_id=one'], ['title' => 'Vídeo', 'made_for_kids' => false]);
    expect($result->nextStep)->toBe('upload:2');
    Http::assertSent(fn ($r) => $r->method() === 'PUT' && $r->hasHeader('Content-Range', 'bytes */4') && $r->body() === '');
});

it('keeps absent social counters unavailable and rejects foreign ownership', function (): void {
    $connector = new FacebookConnector(socialConnectorFiles());
    $account = socialConnectorAccount('facebook');
    Http::fakeSequence()->push(['id' => '123_456', 'from' => ['id' => '123'], 'is_published' => true, 'shares' => ['count' => 0]])->push(['id' => '123_456', 'from' => ['id' => '999'], 'is_published' => true]);
    $metrics = $connector->metrics($account, '123_456');
    expect($metrics['values']['shares']['value'])->toBe(0)->and($metrics['values']['comments']['value'])->toBeNull();
    expect(fn () => $connector->metrics($account, '123_456'))->toThrow(ValidationException::class);
});

it('collects GA4 attribution asynchronously from the frozen UTM and retains stale status', function (): void {
    $user = User::factory()->create();
    $user->roles()->attach(Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin'])->id);
    Auth::guard('web')->login($user);
    CmsModule::updateOrCreate(['identifier' => 'social-publishing'], ['name' => 'SocialPublishing', 'provider' => SocialPublishingModuleServiceProvider::class, 'version' => '1.0.0', 'is_active' => true, 'is_installed' => true, 'settings' => ['is_enabled' => true]]);
    $actor = new ActorContext($user->id);
    $account = SocialAccount::create(['provider' => 'facebook', 'remote_id' => '123', 'identity' => 'facebook:123', 'label' => 'Página', 'status' => 'connected', 'access_token' => 'fixture', 'expires_at' => now()->addDay(), 'grants' => ['pages_show_list', 'pages_manage_posts', 'pages_read_engagement'], 'metadata' => []]);
    $package = new CreativePackage('p', 'r', 'campaign-one', 'es', 'es', 'Texto', config('app.url').'/es/es/page?utm_campaign=frozen-campaign&utm_content=frozen-content', []);
    $service = app(PublicationService::class);
    $p = $service->create($package, $account->id, [], $actor);
    $service->approve($p->id, $p->payload_hash, $actor);
    $d = $service->schedule($p->id, now()->toIso8601String(), $actor);
    $d->update(['status' => 'published', 'remote_id' => '123_456']);
    $gateway = Mockery::mock(CampaignAttributionGatewayInterface::class);
    $gateway->shouldReceive('report')->once()->with('frozen-campaign', 'frozen-content', now()->toDateString(), now()->toDateString())->andReturn(['source' => 'ga4', 'status' => 'stale', 'sessions' => 3, 'key_events' => 1.0, 'fetched_at' => now()->subDay()->toIso8601String()]);
    app()->instance(CampaignAttributionGatewayInterface::class, $gateway);
    Http::fake(['*' => Http::response(['id' => '123_456', 'from' => ['id' => '123'], 'is_published' => true])]);
    app(DeliveryWorker::class)->metrics($d->id);
    $m = SocialMetric::where('delivery_id', $d->id)->where('source', 'ga4')->firstOrFail();
    expect($m->availability)->toBe('stale')->and($m->metrics['sessions'])->toBe(3);
    Http::fake(fn () => throw new LogicException('El informe nunca se consulta desde la interfaz.'));
    $this->get(route('admin.social-publishing.preview', $p->id))->assertOk()->assertSee('Visitas atribuidas')->assertSee('stale');
});
