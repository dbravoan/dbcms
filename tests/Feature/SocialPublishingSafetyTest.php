<?php

declare(strict_types=1);

use App\Contracts\Services\ModuleSettingsServiceInterface;
use App\Core\Capabilities\ActorContext;
use App\Models\CmsModule;
use App\Models\Role;
use App\Models\User;
use App\Services\Capabilities\ActorContextFactory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\Passport;
use Modules\Marketing\DTOs\ArtifactDescriptor;
use Modules\Marketing\DTOs\CreativePackage;
use Modules\SocialPublishing\Contracts\DestinationValidatorInterface;
use Modules\SocialPublishing\DTOs\PublicationSnapshot;
use Modules\SocialPublishing\Models\SocialAccount;
use Modules\SocialPublishing\Models\SocialAttempt;
use Modules\SocialPublishing\Models\SocialMetric;
use Modules\SocialPublishing\Services\DeliveryWorker;
use Modules\SocialPublishing\Services\PublicationService;
use Modules\SocialPublishing\Services\ScheduleTime;
use Modules\SocialPublishing\Services\SocialGuides;
use Modules\SocialPublishing\Services\SocialOAuthService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\SocialAccountFixture;
use Tests\Support\SocialComposition;

uses(SocialComposition::class);

function socialSafetyActor(): ActorContext
{
    $user = User::factory()->create();
    $user->roles()->attach(Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin'])->id);
    Auth::guard('web')->login($user);

    return new ActorContext($user->id);
}

function socialSafetyAccount(): SocialAccount
{
    return SocialAccount::create(['provider' => 'facebook', 'remote_id' => '123', 'identity' => 'facebook:123', 'label' => 'Página aislada', 'status' => 'connected', 'grants' => ['pages_show_list', 'pages_manage_posts', 'pages_read_engagement'], 'access_token' => 'isolated-token', 'metadata' => SocialAccountFixture::metadata('facebook'), 'expires_at' => now()->addDay()]);
}

function socialSafetyPackage(string $text = 'Texto revisado', ?string $url = null): CreativePackage
{
    return new CreativePackage('package', 'revision', null, 'es', 'es', $text, $url, []);
}

it('reloads the persisted revision after preflight and never sends a stale approved object', function (): void {
    $actor = socialSafetyActor();
    $account = socialSafetyAccount();
    $service = app(PublicationService::class);
    $p = $service->create(socialSafetyPackage(), $account->id, [], $actor);
    $service->approve($p->id, $p->payload_hash, $actor);
    $d = $service->schedule($p->id, now()->toIso8601String(), $actor);
    $preflight = Mockery::mock(DestinationValidatorInterface::class);
    $preflight->shouldReceive('check')->once()->andReturnUsing(function () use ($actor, $account, $p, $service): void {
        $service->revise($p->id, $p->revision_id, socialSafetyPackage('Edición sin aprobar'), $account->id, [], $actor);
    });
    app()->instance(DestinationValidatorInterface::class, $preflight);
    Http::preventStrayRequests();
    app(DeliveryWorker::class)->run($d->id);
    expect($d->refresh()->status)->toBe('cancelled');
    Http::assertNothingSent();
});

it('uses live standard MCP scopes and blocks jobs whose scheduling token is revoked', function (): void {
    $human = socialSafetyActor();
    $account = socialSafetyAccount();
    $service = app(PublicationService::class);
    $p = $service->create(socialSafetyPackage(), $account->id, [], $human);
    $service->approve($p->id, $p->payload_hash, $human);
    $user = mcpTestBindIdentity(User::findOrFail($human->userId), ['mcp:read', 'mcp:write']);
    $actor = app(ActorContextFactory::class)->fromRequest(request(), 'mcp');
    $d = $service->schedule($p->id, now()->toIso8601String(), $actor);
    Passport::token()->findOrFail($actor->tokenId)->update(['revoked' => true]);
    Http::preventStrayRequests();
    app(DeliveryWorker::class)->run($d->id);
    expect($d->refresh()->status)->toBe('blocked');
    Http::assertNothingSent();
});

it('rejects forged token context even if its scope array claims every capability', function (): void {
    $actor = socialSafetyActor();
    $account = socialSafetyAccount();
    expect(fn () => app(PublicationService::class)->create(socialSafetyPackage(), $account->id, [], new ActorContext($actor->userId, 'api', ['*'])))->toThrow(HttpException::class);
});

it('performs provider HTTP outside database transactions', function (): void {
    $actor = socialSafetyActor();
    $p = app(PublicationService::class)->create(socialSafetyPackage(), socialSafetyAccount()->id, [], $actor);
    app(PublicationService::class)->approve($p->id, $p->payload_hash, $actor);
    $d = app(PublicationService::class)->schedule($p->id, now()->toIso8601String(), $actor);
    // RefreshDatabase wraps the test itself: provider HTTP must not enter an extra domain transaction.
    $level = DB::transactionLevel();
    Http::fake(fn () => tap(Http::response(['id' => '123_456']), fn () => expect(DB::transactionLevel())->toBe($level)));
    app(DeliveryWorker::class)->run($d->id);
    expect($d->refresh()->status)->toBe('published');
});

it('does not replay an expired mutation claim', function (): void {
    $actor = socialSafetyActor();
    $p = app(PublicationService::class)->create(socialSafetyPackage(), socialSafetyAccount()->id, [], $actor);
    app(PublicationService::class)->approve($p->id, $p->payload_hash, $actor);
    $d = app(PublicationService::class)->schedule($p->id, now()->toIso8601String(), $actor);
    $claim = (string) Str::uuid();
    $d->update(['claim' => $claim, 'status' => 'preparing', 'lease_until' => now()->subMinute()]);
    SocialAttempt::create(['delivery_id' => $d->id, 'operation_key' => $claim, 'step' => 'prepare', 'mutation' => true, 'status' => 'started', 'receipt' => []]);
    Http::preventStrayRequests();
    app(DeliveryWorker::class)->run($d->id);
    expect($d->refresh()->status)->toBe('pending_confirmation');
    Http::assertNothingSent();
});

it('keeps callback state session bound and single use with provider-discovered account selection', function (): void {
    $actor = socialSafetyActor();
    app(ModuleSettingsServiceInterface::class)->setMany('social-publishing', ['facebook_client_id' => 'client', 'facebook_client_secret' => 'secret']);
    $oauth = app(SocialOAuthService::class);
    $url = $oauth->begin('facebook', $actor, 'browser-session');
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    Http::fake([
        '*oauth/access_token' => Http::response(['access_token' => 'user-token', 'expires_in' => 3600]),
        '*me/permissions*' => Http::response(['data' => array_map(fn ($p) => ['permission' => $p, 'status' => 'granted'], ['pages_show_list', 'pages_manage_posts', 'pages_read_engagement'])]),
        '*me/accounts*' => Http::response(['data' => [['id' => '123', 'name' => 'Página comprobada', 'access_token' => 'page-token', 'tasks' => ['CREATE_CONTENT']]]]),
    ]);
    expect(fn () => $oauth->callback('facebook', $query['state'], 'code', $actor, 'another-session'))->toThrow(HttpException::class);
    Http::assertNothingSent();
    $saved = $oauth->callback('facebook', $query['state'], 'code', $actor, 'browser-session');
    expect($saved->toArray())->not->toHaveKey('discovery');
    expect(fn () => $oauth->callback('facebook', $query['state'], 'code', $actor, 'browser-session'))->toThrow(HttpException::class);
    expect(fn () => $oauth->select($saved->id, '999', $actor, 'browser-session'))->toThrow(HttpException::class);
    $account = $oauth->select($saved->id, '123', $actor, 'browser-session');
    expect($account->identity)->toBe('facebook:123')->and($account->status)->toBe('connected');
});

it('changes authorization generation on revocation and never leaks tokens', function (): void {
    $actor = socialSafetyActor();
    $account = socialSafetyAccount();
    $generation = $account->authorization_generation;
    app(SocialOAuthService::class)->revoke($account->id, $actor);
    expect($account->refresh()->status)->toBe('revoked')->and($account->authorization_generation)->toBe($generation + 1)->and($account->access_token)->toBeNull();
});

it('persists failed connection checks separately and blocks sends until a successful new probe', function (): void {
    $actor = socialSafetyActor();
    $account = socialSafetyAccount();
    $oauth = app(SocialOAuthService::class);
    Http::fakeSequence()->push([], 403)->push(['id' => '123']);
    expect($oauth->check($account->id, $actor))->toBeFalse();
    $account->refresh();
    expect($account->metadata['last_check']['status'])->toBe('insufficient_permissions')->and($account->metadata['last_check']['authorization_generation'])->toBe($account->authorization_generation);
    expect(fn () => $oauth->ready($account))->toThrow(ValidationException::class);
    expect($oauth->check($account->id, $actor))->toBeTrue()->and($account->refresh()->metadata['last_check']['status'])->toBe('verified');
    $oauth->ready($account);
    Http::assertNotSent(fn ($request) => $request->method() === 'POST');
});

it('binds ordered file hashes and canonical metadata in the approved snapshot', function (): void {
    $a = new ArtifactDescriptor('a', 'test/a.jpg', str_repeat('a', 64), 'image/jpeg', 100, 20, 20);
    $b = new ArtifactDescriptor('b', 'test/b.jpg', str_repeat('b', 64), 'image/jpeg', 100, 20, 20);
    $package = new CreativePackage('p', 'r', null, 'es', 'es', 'Texto', null, [$a, $b]);
    $hash = (new PublicationSnapshot($package, 'instagram:123', 1, ['title' => 'Título', 'kids' => false]))->hash();
    expect((new PublicationSnapshot($package, 'instagram:123', 1, ['kids' => false, 'title' => 'Título']))->hash())->toBe($hash);
    expect((new PublicationSnapshot(new CreativePackage('p', 'r', null, 'es', 'es', 'Texto', null, [$b, $a]), 'instagram:123', 1, ['title' => 'Título', 'kids' => false]))->hash())->not->toBe($hash);
});

it('rejects nonexistent local time and accepts both explicit repeated-hour offsets', function (): void {
    config(['social_publishing.timezone' => 'Europe/Madrid']);
    $clock = app(ScheduleTime::class);
    expect(fn () => $clock->parse('2026-03-29T02:30:00+01:00'))->toThrow(ValidationException::class);
    expect($clock->parse('2026-10-25T02:30:00+02:00')->toIso8601String())->toBe('2026-10-25T00:30:00+00:00');
    expect($clock->parse('2026-10-25T02:30:00+01:00')->toIso8601String())->toBe('2026-10-25T01:30:00+00:00');
});

it('keeps six local tutorials with seven substantive steps available while disabled', function (): void {
    $actor = socialSafetyActor();
    CmsModule::where('identifier', 'social-publishing')->update(['is_active' => false]);
    $guides = app(SocialGuides::class)->all();
    expect($guides)->toHaveCount(6);
    foreach ($guides as $guide) {
        expect($guide->steps)->toHaveCount(7);
        foreach ($guide->steps as $step) {
            expect(strlen($step->externalAction))->toBeGreaterThan(65)->and(strlen($step->cmsAction))->toBeGreaterThan(35)->and(strlen($step->expectedResult))->toBeGreaterThan(35);
        }
    }
    $this->get(route('admin.modules.guides.show', ['identifier' => 'social-publishing', 'guide' => 'tiktok']))->assertOk()->assertSee('publicar manualmente');
});

it('renders exact review and calendar under both admin brands', function (string $brand): void {
    config(['cms.brand' => $brand]);
    $actor = socialSafetyActor();
    $p = app(PublicationService::class)->create(socialSafetyPackage(), socialSafetyAccount()->id, [], $actor);
    $this->get(route('admin.social-publishing.index'))->assertOk()->assertSee('Calendario y resultados');
    $this->get(route('admin.social-publishing.preview', $p->id))->assertOk()->assertSee($p->payload_hash)->assertSee('Editar creando otra revisión');
})->with(['dbcms', 'termocms']);

it('does not pause another installations delivery backlog', function (): void {
    $actor = socialSafetyActor();
    $service = app(PublicationService::class);
    $p = $service->create(socialSafetyPackage(), socialSafetyAccount()->id, [], $actor);
    $service->approve($p->id, $p->payload_hash, $actor);
    $local = $service->schedule($p->id, now()->toIso8601String(), $actor);
    $other = $service->create(socialSafetyPackage('Otra instalación'), $p->account_id, [], $actor);
    $service->approve($other->id, $other->payload_hash, $actor);
    $foreign = $service->schedule($other->id, now()->toIso8601String(), $actor);
    $foreign->forceFill(['installation_id' => 'another-installation'])->save();

    app(DeliveryWorker::class)->pause();

    expect($local->refresh()->status)->toBe('paused')
        ->and($foreign->refresh()->status)->toBe('scheduled');
});

it('associates each social preparation and import control with its own unique label', function (string $brand): void {
    config(['cms.admin_theme' => $brand]);
    socialSafetyActor();
    $response = $this->get(route('admin.social-publishing.index'))->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $xpath = new DOMXPath($document);

    foreach (['account_id', 'metadata[title]', 'metadata[made_for_kids]'] as $name) {
        $controls = $xpath->query('//form//*[self::input or self::select][@name="'.$name.'"]');
        expect($controls->length)->toBe(2);
        foreach ($controls as $control) {
            $id = $control->getAttribute('id');
            expect($id)->not->toBe('');
            expect($xpath->query('//*[@id="'.$id.'"]')->length)->toBe(1);
            $form = $xpath->query('ancestor::form[1]', $control)->item(0);
            expect($xpath->query('.//label[@for="'.$id.'"]', $form)->length)->toBe(1);
        }
    }
})->with(['dbcms', 'termocms']);

it('does not fetch metrics for another installations delivery', function (): void {
    $actor = socialSafetyActor();
    $service = app(PublicationService::class);
    $p = $service->create(socialSafetyPackage(), socialSafetyAccount()->id, [], $actor);
    $service->approve($p->id, $p->payload_hash, $actor);
    $d = $service->schedule($p->id, now()->toIso8601String(), $actor);
    $d->forceFill(['installation_id' => 'another-installation', 'status' => 'published', 'remote_id' => '123_456'])->save();
    Http::preventStrayRequests();

    app(DeliveryWorker::class)->metrics($d->id);

    Http::assertNothingSent();
    expect(SocialMetric::where('delivery_id', $d->id)->exists())->toBeFalse();
});
