<?php

declare(strict_types=1);

use App\Contracts\Services\ModuleNotificationServiceInterface;
use App\Contracts\Services\NotificationRecipientResolverInterface;
use App\Contracts\Services\UpdateCatalogServiceInterface;
use App\Core\Updates\UpdateRunStore;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemNotification;
use App\Models\User;
use App\Services\Updates\UpdateNotificationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->updateStorage = storage_path('framework/testing/updates-'.bin2hex(random_bytes(8)));
    config(['updates.storage_path' => $this->updateStorage]);
});

afterEach(function (): void {
    File::deleteDirectory($this->updateStorage);
});

it('checks only published stable releases and caches failures without claiming a successful check', function (): void {
    Queue::fake();
    Cache::flush();
    Http::fake(['api.github.com/*' => Http::response([['tag_name' => 'v1.2.0', 'draft' => false, 'prerelease' => false, 'name' => 'dbCMS 1.2.0', 'body' => 'Changes', 'published_at' => '2026-10-14T10:00:00Z', 'assets' => []], ['tag_name' => 'v9.0.0', 'draft' => false, 'prerelease' => true]], 200)]);
    $catalog = app(UpdateCatalogServiceInterface::class);
    $state = $catalog->check();
    expect($state['available']['version'])->toBe('1.2.0')->and($state['error'])->toBeNull();
    $catalog->check();
    Http::assertSentCount(1);
    Http::fake(['*' => Http::response([], 503)]);
    $failed = $catalog->check(true);
    expect($failed['error'])->not->toBeNull()->and($failed['checked_at'])->toBe($state['checked_at'])->and($failed['available']['version'])->toBe('1.2.0');
});

it('deduplicates private core notices and honours email preferences without a module', function (): void {
    Queue::fake();
    $role = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
    $user = User::factory()->create();
    $user->assignRole($role);
    $notifications = app(UpdateNotificationService::class);
    $notifications->notify(['version' => '1.2.0']);
    $notifications->notify(['version' => '1.2.0']);
    $notice = SystemNotification::where('module', 'core_updates')->sole();
    expect($notice->user_id)->toBe($user->id)->and($notice->is_global)->toBeFalse();
    $resolver = app(NotificationRecipientResolverInterface::class);
    expect($resolver->allows($notice, $user))->toBeTrue();
    $resolver->setPreference($user, 'core_updates', false);
    expect($resolver->allows($notice, $user))->toBeFalse();
});

it('hides existing update notifications once the human loses their permission', function (): void {
    Queue::fake();
    $role = Role::create(['slug' => 'updates-custom', 'name' => 'Updates']);
    foreach (['access:admin', 'manage:updates'] as $slug) {
        $role->permissions()->attach(Permission::firstOrCreate(['slug' => $slug], ['name' => $slug]));
    }
    $user = User::factory()->create();
    $user->assignRole($role);
    app(UpdateNotificationService::class)->notify(['version' => '1.2.0']);
    $notice = SystemNotification::where('module', 'core_updates')->sole();
    $role->permissions()->detach(Permission::where('slug', 'manage:updates')->value('id'));
    $this->actingAs($user);
    $service = app(ModuleNotificationServiceInterface::class);
    expect($service->getUnreadCount())->toBe(0)->and($service->markAsRead($notice->id))->toBeFalse();
});

it('keeps inventory and signatures private and hides a release already installed', function (): void {
    Cache::put('cms.updates.catalog.v1', ['available' => ['version' => '1.2.0']]);
    $store = app(UpdateRunStore::class);
    $store->atomic($store->root().'/identity.json', json_encode(['version' => '1.2.0', 'source_sha' => str_repeat('a', 40), 'distribution' => 'runtime', 'manifest_raw' => 'private-inventory', 'files' => ['private/path' => str_repeat('b', 64)]], JSON_THROW_ON_ERROR));
    $state = app(UpdateCatalogServiceInterface::class)->status();
    expect($state['available'])->toBeNull()->and($state['installed'])->not->toHaveKeys(['files', 'manifest_raw']);
});
