<?php

declare(strict_types=1);

use App\Contracts\Services\UpdateCatalogServiceInterface;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

beforeEach(function (): void {
    $this->updateStorage = storage_path('framework/testing/updates-'.bin2hex(random_bytes(8)));
    config(['updates.storage_path' => $this->updateStorage]);
});

afterEach(function (): void {
    File::deleteDirectory($this->updateStorage);
});
use App\Contracts\Services\UpdateHistoryServiceInterface;
use App\Core\Updates\UpdateRunStore;
use App\Services\Cms\AdminThemeService;
use Illuminate\Support\Facades\File;

function updateAdministrator(): User
{
    $role = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('protects update pages and manual checks with explicit administrative permission', function (): void {
    $user = User::factory()->create();
    $role = Role::create(['slug' => 'updates-reader', 'name' => 'Reader']);
    $permission = Permission::firstOrCreate(['slug' => 'access:admin'], ['name' => 'Access']);
    $role->permissions()->attach($permission);
    $user->assignRole($role);
    $this->actingAs($user)->getJson(route('admin.updates.index'))->assertForbidden();
    $this->actingAs($user)->postJson(route('admin.updates.check'))->assertForbidden();
});

it('renders the same update view with either administrative theme', function (string $brand): void {
    config(['cms.admin_theme' => $brand]);
    app(AdminThemeService::class)->setActiveTheme($brand);
    $catalog = Mockery::mock(UpdateCatalogServiceInterface::class);
    $catalog->shouldReceive('status')->andReturn(['installed' => ['version' => '1.1.0'], 'available' => null, 'checked_at' => null, 'error' => null]);
    app()->instance(UpdateCatalogServiceInterface::class, $catalog);
    $this->actingAs(updateAdministrator())->get(route('admin.updates.index'))->assertOk()->assertSee('Actualizaciones')->assertSee('theme-'.$brand)->assertHeader('X-Robots-Tag', 'noindex, nofollow');
})->with(['dbcms', 'termocms']);

it('requires recent password verification before accepting an approval', function (): void {
    $this->actingAs(updateAdministrator())->postJson(route('admin.updates.approve', ['run' => 'e4231d63-bd84-4b60-8940-0530a5b103f0']), ['plan_hash' => str_repeat('a', 64), 'hosting' => ['single_host' => true, 'no_unmanaged_workers' => true]])->assertStatus(423);
});

it('denies another administrator access to a private update execution', function (): void {
    $owner = updateAdministrator();
    $reader = updateAdministrator();
    $store = app(UpdateRunStore::class);
    $run = $store->create(['actor_id' => $owner->id, 'status' => 'prepared', 'mode' => 'managed', 'version' => '1.2.0', 'plan_hash' => str_repeat('a', 64)]);
    $this->actingAs($reader)->getJson(route('admin.updates.status', ['run' => $run['id']]))->assertForbidden();
});

it('returns only a useful public execution projection', function (): void {
    $owner = updateAdministrator();
    $store = app(UpdateRunStore::class);
    $run = $store->create(['actor_id' => $owner->id, 'status' => 'prepared', 'mode' => 'managed', 'version' => '1.2.0', 'plan_hash' => str_repeat('a', 64), 'stage_path' => '/private/secret-stage', 'manifest_raw' => 'private-manifest', 'recovery' => ['token_hash' => 'secret-token-hash'], 'backup' => ['path' => '/private/database.sql']]);
    $this->actingAs($owner)->getJson(route('admin.updates.status', ['run' => $run['id']]))->assertOk()->assertJsonPath('version', '1.2.0')->assertDontSee('secret-stage')->assertDontSee('private-manifest')->assertDontSee('secret-token-hash')->assertDontSee('database.sql');
});

it('checks the password itself before recording recent human confirmation', function (): void {
    $user = updateAdministrator();
    $this->actingAs($user)->postJson(route('admin.updates.confirm-password'), ['password' => 'incorrect'])->assertUnprocessable();
    $this->actingAs($user)->postJson(route('admin.updates.confirm-password'), ['password' => 'password'])->assertOk()->assertSessionHas('auth.password_confirmed_at');
});

it('rejects a continuation after update permission has been revoked', function (): void {
    $user = User::factory()->create();
    $role = Role::create(['slug' => 'update-maintainer', 'name' => 'Maintainer']);
    foreach (['access:admin', 'manage:updates'] as $slug) {
        $role->permissions()->attach(Permission::firstOrCreate(['slug' => $slug], ['name' => $slug]));
    }
    $user->assignRole($role);
    $role->permissions()->detach(Permission::where('slug', 'manage:updates')->value('id'));
    $this->actingAs($user)->postJson(route('admin.updates.advance', ['run' => 'e4231d63-bd84-4b60-8940-0530a5b103f0']))->assertForbidden();
});

it('does not let a CLI caller invent authority through an actor ID', function (): void {
    $user = User::factory()->create();
    $this->artisan('cms:updates', ['operation' => 'status', 'target' => 'e4231d63-bd84-4b60-8940-0530a5b103f0', '--actor' => $user->id])->assertExitCode(1);
});

it('does not accept noninteractive command line approval', function (): void {
    $user = updateAdministrator();
    $this->artisan('cms:updates', ['operation' => 'approve', 'target' => 'e4231d63-bd84-4b60-8940-0530a5b103f0', '--actor' => $user->id, '--no-interaction' => true])->assertExitCode(1);
});

it('requires the selected human password for interactive command line approval', function (): void {
    $user = updateAdministrator();
    $this->artisan('cms:updates', ['operation' => 'approve', 'target' => 'e4231d63-bd84-4b60-8940-0530a5b103f0', '--actor' => $user->id])
        ->expectsConfirmation('Apply this exact reviewed update with its verified backup?', 'yes')
        ->expectsQuestion('Confirm the CMS administrator password', 'incorrect')
        ->assertExitCode(1);
});

it('lists bounded persisted history only for the current authorized human', function (): void {
    $owner = updateAdministrator();
    $other = updateAdministrator();
    $store = app(UpdateRunStore::class);
    for ($index = 0; $index < 23; $index++) {
        $store->create(['actor_id' => $owner->id, 'status' => 'managed', 'mode' => 'managed', 'version' => '1.2.0', 'stage_path' => '/private/secret-stage']);
    }
    $foreign = $store->create(['actor_id' => $other->id, 'status' => 'managed', 'mode' => 'managed', 'version' => '1.3.0']);
    $history = app(UpdateHistoryServiceInterface::class)->recent($owner->id);
    expect($history)->toHaveCount(20)->and(array_column($history, 'id'))->not->toContain($foreign['id'])->and($history[0])->not->toHaveKeys(['stage_path', 'actor_id']);
    $response = $this->actingAs($owner)->get(route('admin.updates.index'))->assertOk();
    $document = new DOMDocument;
    $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $handlers = [];
    foreach ($document->getElementsByTagName('button') as $button) {
        $handlers[] = $button->getAttribute('x-on:click');
    }
    expect($handlers)->toContain("resume('".$history[0]['id']."')");
    $response->assertDontSee($foreign['id'])->assertDontSee('@js($previousRun', false);
});
