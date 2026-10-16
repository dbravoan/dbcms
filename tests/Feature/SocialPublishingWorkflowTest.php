<?php

declare(strict_types=1);

use App\Core\Capabilities\ActorContext;
use App\Models\CmsModule;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Modules\Marketing\DTOs\CreativePackage;
use Modules\SocialPublishing\Models\SocialAccount;
use Modules\SocialPublishing\Models\SocialPublication;
use Modules\SocialPublishing\Services\DeliveryWorker;
use Modules\SocialPublishing\Services\PublicationService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\SocialAccountFixture;
use Tests\Support\SocialComposition;

uses(SocialComposition::class);

function socialTestActor(string $origin = 'web'): ActorContext
{
    $user = User::factory()->create();
    $user->roles()->attach(Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin'])->id);
    Auth::guard('web')->login($user);
    config(['modules.social-publishing.is_enabled' => true]);

    return new ActorContext($user->id, $origin);
}

function socialTestAccount(): SocialAccount
{
    return SocialAccount::create(['provider' => 'facebook', 'remote_id' => '123', 'label' => 'Página de prueba', 'identity' => 'facebook:123', 'authorization_generation' => 1, 'status' => 'connected', 'access_token' => 'isolated-token', 'grants' => ['pages_show_list', 'pages_manage_posts', 'pages_read_engagement'], 'metadata' => SocialAccountFixture::metadata('facebook'), 'expires_at' => now()->addDay()]);
}

function socialTestDraft(ActorContext $actor): SocialPublication
{
    $package = new CreativePackage('manual-one', 'source-one', null, 'es', 'es', 'Texto exacto', null, []);

    return app(PublicationService::class)->create($package, socialTestAccount()->id, [], $actor);
}

it('requires a trusted human session for an exact approval', function (): void {
    $actor = socialTestActor();
    $p = socialTestDraft($actor);
    expect(fn () => app(PublicationService::class)->approve($p->id, $p->payload_hash, new ActorContext($actor->userId, 'mcp')))->toThrow(HttpException::class);
    app(PublicationService::class)->approve($p->id, $p->payload_hash, $actor);
    expect($p->refresh()->status)->toBe('approved');
});

it('rejects approval of a stale preview and preserves manual standalone input', function (): void {
    $actor = socialTestActor();
    $p = socialTestDraft($actor);
    expect(fn () => app(PublicationService::class)->approve($p->id, str_repeat('0', 64), $actor))->toThrow(ValidationException::class);
    expect($p->refresh()->status)->toBe('draft');
});

it('does not repeat a published remote mutation on duplicated jobs', function (): void {
    $actor = socialTestActor();
    $p = socialTestDraft($actor);
    app(PublicationService::class)->approve($p->id, $p->payload_hash, $actor);
    $d = app(PublicationService::class)->schedule($p->id, now()->toIso8601String(), $actor);
    Http::fake(['*' => Http::response(['id' => '123_456'])]);
    app(DeliveryWorker::class)->run($d->id);
    app(DeliveryWorker::class)->run($d->id);
    expect($d->refresh()->status)->toBe('published');
    Http::assertSentCount(1);
});

it('never blindly repeats uncertain posts after a timeout or lost worker', function (): void {
    $actor = socialTestActor();
    $p = socialTestDraft($actor);
    app(PublicationService::class)->approve($p->id, $p->payload_hash, $actor);
    $d = app(PublicationService::class)->schedule($p->id, now()->toIso8601String(), $actor);
    Http::fake(['*' => Http::response([], 500)]);
    app(DeliveryWorker::class)->run($d->id);
    app(DeliveryWorker::class)->run($d->id);
    expect($d->refresh()->status)->toBe('pending_confirmation');
    Http::assertSentCount(1);
});

it('cancels unsent deliveries and preserves confirmed remote outcomes', function (): void {
    $actor = socialTestActor();
    $p = socialTestDraft($actor);
    app(PublicationService::class)->approve($p->id, $p->payload_hash, $actor);
    $d = app(PublicationService::class)->schedule($p->id, now()->toIso8601String(), $actor);
    app(PublicationService::class)->cancel($d->id, $actor);
    Http::preventStrayRequests();
    app(DeliveryWorker::class)->run($d->id);
    expect($d->refresh()->status)->toBe('cancelled');
    Http::assertNothingSent();
    $d->update(['status' => 'published', 'remote_id' => '123_456']);
    app(PublicationService::class)->cancel($d->id, $actor);
    expect($d->refresh()->status)->toBe('published')->and($d->cancel_requested)->toBeTrue();
});

it('pauses overdue work when the module is unavailable', function (): void {
    $actor = socialTestActor();
    $p = socialTestDraft($actor);
    app(PublicationService::class)->approve($p->id, $p->payload_hash, $actor);
    $d = app(PublicationService::class)->schedule($p->id, now()->toIso8601String(), $actor);
    CmsModule::where('identifier', 'social-publishing')->update(['is_active' => false]);
    Http::preventStrayRequests();
    app(DeliveryWorker::class)->run($d->id);
    expect($d->refresh()->status)->toBe('paused');
    Http::assertNothingSent();
});

it('hides encrypted social credentials in every serialized account', function (): void {
    socialTestActor();
    $account = socialTestAccount();
    expect(json_encode($account->toArray()))->not->toContain('isolated-token');
    expect(DB::table('sp_accounts')->value('access_token'))->not->toBe('isolated-token');
});

it('does not allow an arbitrary owned remote identifier to settle uncertainty', function (): void {
    $actor = socialTestActor();
    $p = socialTestDraft($actor);
    app(PublicationService::class)->approve($p->id, $p->payload_hash, $actor);
    $d = app(PublicationService::class)->schedule($p->id, now()->toIso8601String(), $actor);
    $d->update(['status' => 'pending_confirmation']);
    expect(fn () => app(DeliveryWorker::class)->reconcile($d->id, 'foreign_candidate'))->toThrow(ValidationException::class);
    expect($d->refresh()->status)->toBe('pending_confirmation');
});
