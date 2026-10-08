<?php

declare(strict_types=1);

use App\Contracts\Services\InstallationEnvironmentServiceInterface;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->originalStorage = app()->storagePath();
    $this->privateStorage = sys_get_temp_dir().'/dbcms-web-install-'.bin2hex(random_bytes(8));
    File::ensureDirectoryExists($this->privateStorage, 0700);
    app()->useStoragePath($this->privateStorage);
});

afterEach(function (): void {
    app()->useStoragePath($this->originalStorage);
    File::deleteDirectory($this->privateStorage);
});

function safeInstallerPayload(): array
{
    return ['name' => 'QA Admin', 'email' => 'new-admin@example.invalid', 'password' => 'private-password', 'password_confirmation' => 'private-password', 'site_name' => 'RegulaSE "Europa"', 'admin_path' => 'control'];
}

test('web installer rejects DSN controls and malformed database names before connection', function (): void {
    $this->postJson(route('install.test-db'), ['host' => 'mysql;dbname=other', 'port' => '0', 'database' => 'bad-name', 'username' => 'sail', 'password' => 'secret'])->assertUnprocessable()->assertJsonValidationErrors(['host', 'port', 'database']);
});

test('web installer does not save failed database credentials or pretend connection success', function (): void {
    $this->postJson(route('install.save-db'), ['host' => env('DB_HOST'), 'port' => '3306', 'database' => 'testing', 'username' => 'sail', 'password' => 'incorrect-qa-password', 'auto_create' => false])->assertUnprocessable();
});

test('web migration exit failure does not claim provisioning success', function (): void {
    Artisan::shouldReceive('call')->with('migrate', ['--force' => true], Mockery::any())->once()->andReturn(1);
    $this->postJson(route('install.run-migrations'), ['with_demo' => false])->assertStatus(500)->assertJson(['success' => false]);
    expect(File::exists(storage_path('installed')))->toBeFalse();
});

test('web installer never overwrites an existing user account', function (): void {
    $original = User::factory()->create(['email' => 'new-admin@example.invalid', 'name' => 'Original']);
    $this->post(route('install.store-admin'), safeInstallerPayload())->assertSessionHasErrors('email');
    expect($original->fresh()->name)->toBe('Original')->and(File::exists(storage_path('installed')))->toBeFalse();
});

test('web admin finalization rolls back account creation when installation cannot be sealed', function (): void {
    File::ensureDirectoryExists(storage_path('installed'));
    $this->postJson(route('install.store-admin'), safeInstallerPayload())->assertServerError();
    expect(User::where('email', 'new-admin@example.invalid')->exists())->toBeFalse();
});

test('web admin finalization restores configuration and rolls back after a late environment failure', function (): void {
    $path = app()->environmentFilePath();
    $original = File::get($path);
    $environment = Mockery::mock(InstallationEnvironmentServiceInterface::class);
    $environment->shouldReceive('write')->once()->andReturnUsing(function () use ($path): void {
        File::put($path, "APP_NAME=partial\n");
        throw new RuntimeException('Late write failure');
    });
    app()->instance(InstallationEnvironmentServiceInterface::class, $environment);
    $this->postJson(route('install.store-admin'), safeInstallerPayload())->assertServerError();
    expect(User::where('email', 'new-admin@example.invalid')->exists())->toBeFalse()
        ->and(File::get($path))->toBe($original)
        ->and(File::exists(storage_path('installed')))->toBeFalse();
});

test('web provisioning preserves existing OAuth keys and does not opt into demonstration seeding', function (): void {
    $keyRoot = storage_path();
    $this->postJson(route('install.run-migrations'))->assertOk()->assertJson(['success' => true]);
    $private = File::get($keyRoot.'/oauth-private.key');
    $public = File::get($keyRoot.'/oauth-public.key');
    $this->postJson(route('install.run-migrations'))->assertOk()->assertJson(['success' => true]);
    expect(File::get($keyRoot.'/oauth-private.key'))->toBe($private)
        ->and(File::get($keyRoot.'/oauth-public.key'))->toBe($public)
        ->and(User::where('email', 'admin@db-cms.io')->exists())->toBeFalse();
});

test('web provisioning rejects existing invalid OAuth keys without rotating them', function (): void {
    File::put(storage_path('oauth-private.key'), 'invalid private QA');
    File::put(storage_path('oauth-public.key'), 'invalid public QA');
    $this->postJson(route('install.run-migrations'), ['with_demo' => false])->assertServerError()->assertJson(['success' => false]);
    expect(File::get(storage_path('oauth-private.key')))->toBe('invalid private QA');
});
