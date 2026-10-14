<?php

declare(strict_types=1);

use App\Contracts\Services\UpdateManagedReconcilerServiceInterface;
use App\Contracts\Services\UpdateMigrationServiceInterface;
use App\Core\Updates\UpdateRunStore;
use Illuminate\Support\Facades\File;

function managedReconciliationFixture(string $directory): array
{
    static $key;
    $key ??= openssl_pkey_new(['private_key_bits' => 3072, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    $files = ['resources/updates/core-version.json' => '{"version":"1.2.0","repository":"dbravoan/dbcms"}', 'app/core.php' => 'new core', 'app/preserved.php' => 'official old'];
    $inventory = array_map(fn (string $bytes): string => hash('sha256', $bytes), $files);
    $manifest = ['schema' => 1, 'version' => '1.2.0', 'source_sha' => str_repeat('b', 40), 'min_php' => '8.3.0', 'extensions' => [], 'supported_from' => ['1.1.0'], 'rollback_compatible' => true, 'migrations' => ['core' => [], 'modules' => []], 'distributions' => ['source' => ['asset' => 'source.zip', 'sha256' => str_repeat('a', 64), 'size' => 1, 'files' => $inventory]]];
    $raw = json_encode($manifest, JSON_THROW_ON_ERROR);
    openssl_sign($raw, $signatureBytes, $key, OPENSSL_ALGO_SHA256);
    $signature = json_encode(['key_id' => 'managed-test', 'signature' => base64_encode($signatureBytes)], JSON_THROW_ON_ERROR);
    config(['updates.trusted_keys' => ['managed-test' => openssl_pkey_get_details($key)['key']]]);
    foreach ($files as $path => $contents) {
        File::ensureDirectoryExists(dirname($directory.'/root/'.$path), 0700);
        file_put_contents($directory.'/root/'.$path, $contents);
    }
    file_put_contents($directory.'/root/app/preserved.php', 'preserved custom');
    file_put_contents($directory.'/root/resources/updates/installed-manifest.json', $raw);
    file_put_contents($directory.'/root/resources/updates/installed-manifest.sig', $signature);
    $run = app(UpdateRunStore::class)->create(['actor_id' => 1, 'mode' => 'managed', 'status' => 'managed', 'version' => '1.2.0', 'manifest_raw' => $raw, 'signature_raw' => $signature, 'manifest_hash' => hash('sha256', $raw), 'preserved' => ['app/preserved.php'], 'preserved_hashes' => ['app/preserved.php' => hash('sha256', 'preserved custom')], 'plan' => [['path' => 'app/core.php', 'old_hash' => hash('sha256', 'old core'), 'new_hash' => hash('sha256', 'new core'), 'action' => 'replace']], 'conflicts' => []]);

    return $run;
}

beforeEach(function (): void {
    $this->managedDirectory = sys_get_temp_dir().'/managed-reconcile-'.bin2hex(random_bytes(8));
    File::ensureDirectoryExists($this->managedDirectory.'/root', 0700);
    config(['updates.root_path' => $this->managedDirectory.'/root', 'updates.storage_path' => $this->managedDirectory.'/state']);
    $migrations = Mockery::mock(UpdateMigrationServiceInterface::class);
    $migrations->shouldReceive('pending')->andReturn([]);
    app()->instance(UpdateMigrationServiceInterface::class, $migrations);
});

afterEach(function (): void {
    File::deleteDirectory($this->managedDirectory);
});

it('recognizes a deployed signed managed release without replacing preserved customizations', function (): void {
    $run = managedReconciliationFixture($this->managedDirectory);
    $result = app(UpdateManagedReconcilerServiceInterface::class)->reconcile($run['id']);
    expect($result['status'])->toBe('completed')->and(app(UpdateRunStore::class)->identity()['version'])->toBe('1.2.0')->and(file_get_contents($this->managedDirectory.'/root/app/preserved.php'))->toBe('preserved custom');
});

it('does not promote an incomplete deployment or an arbitrary custom merge as official', function (): void {
    $run = managedReconciliationFixture($this->managedDirectory);
    file_put_contents($this->managedDirectory.'/root/app/core.php', 'arbitrary merged core');
    $result = app(UpdateManagedReconcilerServiceInterface::class)->reconcile($run['id']);
    expect($result['status'])->toBe('managed')->and(app(UpdateRunStore::class)->identity())->toBeNull();
});

it('requires the reviewed preserved hashes and pending migrations to be satisfied', function (): void {
    $run = managedReconciliationFixture($this->managedDirectory);
    file_put_contents($this->managedDirectory.'/root/app/preserved.php', 'changed after preparation');
    expect(app(UpdateManagedReconcilerServiceInterface::class)->reconcile($run['id'])['status'])->toBe('managed');
    file_put_contents($this->managedDirectory.'/root/app/preserved.php', 'preserved custom');
    $pending = Mockery::mock(UpdateMigrationServiceInterface::class);
    $pending->shouldReceive('pending')->andReturn(['database/migrations/pending.php']);
    app()->instance(UpdateMigrationServiceInterface::class, $pending);
    app()->forgetInstance(UpdateManagedReconcilerServiceInterface::class);
    expect(app(UpdateManagedReconcilerServiceInterface::class)->reconcile($run['id'])['status'])->toBe('managed')->and(app(UpdateRunStore::class)->identity())->toBeNull();
});
