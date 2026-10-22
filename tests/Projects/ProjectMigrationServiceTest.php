<?php

declare(strict_types=1);

use App\Contracts\Services\ProjectMigrationServiceInterface;
use App\Contracts\Services\ProjectUpdateServiceInterface;
use App\Contracts\Services\UpdateMigrationServiceInterface;
use App\Core\Updates\SignedManifestVerifier;
use App\Models\CmsModule;
use App\Services\ProjectUpdates\ProjectMigrationService;
use App\Services\Updates\UpdateMigrationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Migrations\MigrationRepositoryInterface;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Filesystem\Filesystem;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->migrationRoot = sys_get_temp_dir().'/dbcms-project-migrate-'.bin2hex(random_bytes(8));
    mkdir($this->migrationRoot.'/storage', 0700, true);
    touch($this->migrationRoot.'/storage/installed');
    file_put_contents($this->migrationRoot.'/cms-project.json', '{"schema":1}');
    $this->migrationFiles = [];
    foreach (['database/migrations/2026_01_01_000001_core.php', 'database/migrations/2026_01_01_000002_project.php', 'vendor/laravel/passport/database/migrations/2026_01_01_000003_passport.php', 'modules/Ai/Database/Migrations/2026_01_01_000004_ai.php', 'modules/Own/Database/Migrations/2026_01_01_000005_own.php', 'modules/Shop/Database/Migrations/2026_01_01_000006_shop.php'] as $file) {
        (new Filesystem)->ensureDirectoryExists(dirname($this->migrationRoot.'/'.$file));
        file_put_contents($this->migrationRoot.'/'.$file, '<?php // fixture migration');
        $this->migrationFiles[] = $this->migrationRoot.'/'.$file;
    }
    foreach (['Ai' => 'ai_assistant', 'Own' => 'own', 'Shop' => 'shop'] as $folder => $identifier) {
        file_put_contents($this->migrationRoot.'/modules/'.$folder.'/module.json', json_encode(['identifier' => $identifier], JSON_THROW_ON_ERROR));
    }
    $this->moduleQuery = Mockery::mock(Builder::class);
    $this->moduleQuery->shouldReceive('where')->with('is_installed', true)->andReturnSelf()->byDefault();
    $this->moduleQuery->shouldReceive('pluck')->with('identifier')->andReturn(collect(['ai_assistant', 'own']))->byDefault();
    $this->modules = Mockery::mock(CmsModule::class);
    $this->modules->shouldReceive('newQuery')->andReturn($this->moduleQuery)->byDefault();
    $this->projectUpdates = Mockery::mock(ProjectUpdateServiceInterface::class);
    $this->signedMigrations = Mockery::mock(UpdateMigrationServiceInterface::class);
    $this->migrator = Mockery::mock(Migrator::class);
    $this->service = new ProjectMigrationService($this->migrator, $this->modules, $this->projectUpdates, $this->signedMigrations, new SignedManifestVerifier);
});

afterEach(function (): void {
    (new Filesystem)->deleteDirectory($this->migrationRoot);
});

test('project deployment migrates core Passport and installed modules including inactive or custom modules', function (): void {
    $expected = array_slice($this->migrationFiles, 0, 5);
    $this->migrator->shouldReceive('run')->once()->with($expected, ['pretend' => false, 'step' => true])->andReturn([]);
    $this->projectUpdates->shouldNotReceive('review');
    $this->signedMigrations->shouldNotReceive('pending');
    $this->service->migrate($this->migrationRoot);
});

test('the project migration command accepts the deployment force flag', function (): void {
    $handler = Mockery::mock(ProjectMigrationServiceInterface::class);
    $handler->shouldReceive('migrate')->once()->with(base_path());
    app()->instance(ProjectMigrationServiceInterface::class, $handler);
    $this->artisan('cms:project:migrate', ['--force' => true, '--no-interaction' => true])->assertExitCode(0);
});

test('uninstalled projects never run deployment migrations', function (): void {
    unlink($this->migrationRoot.'/storage/installed');
    $this->modules->shouldReceive('newQuery')->never();
    $this->migrator->shouldNotReceive('run');
    expect(fn () => $this->service->migrate($this->migrationRoot))->toThrow(DomainException::class);
});

test('module migration symlinks are rejected before executing any migration', function (): void {
    $path = $this->migrationRoot.'/modules/Own/Database/Migrations/2026_01_01_000005_own.php';
    unlink($path);
    file_put_contents($this->migrationRoot.'/outside.php', '<?php // external');
    symlink($this->migrationRoot.'/outside.php', $path);
    $this->migrator->shouldNotReceive('run');
    expect(fn () => $this->service->migrate($this->migrationRoot))->toThrow(DomainException::class);
});

test('duplicate migration names cannot hide another installed migration', function (): void {
    copy($this->migrationFiles[0], $this->migrationRoot.'/modules/Own/Database/Migrations/'.basename($this->migrationFiles[0]));
    $this->migrator->shouldNotReceive('run');
    expect(fn () => $this->service->migrate($this->migrationRoot))->toThrow(DomainException::class);
});

test('project update review and signed migration checks precede execution', function (): void {
    (new Filesystem)->ensureDirectoryExists($this->migrationRoot.'/resources/updates');
    file_put_contents($this->migrationRoot.'/resources/updates/project-update.json', '{}');
    $this->projectUpdates->shouldReceive('review')->once()->with($this->migrationRoot)->andThrow(new DomainException('Unresolved signed conflicts.'));
    $this->modules->shouldReceive('newQuery')->never();
    $this->signedMigrations->shouldNotReceive('pending');
    $this->migrator->shouldNotReceive('run');
    expect(fn () => $this->service->migrate($this->migrationRoot))->toThrow(DomainException::class, 'Unresolved signed conflicts.');
});

test('changed pending signed migrations abort before executing core or custom migrations', function (): void {
    static $privatePem;
    if ($privatePem === null) {
        $key = openssl_pkey_new(['private_key_bits' => 3072, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $privatePem);
    }
    $publicKey = openssl_pkey_get_details(openssl_pkey_get_private($privatePem))['key'];
    config(['updates.trusted_keys' => ['migration-test' => $publicKey]]);
    (new Filesystem)->ensureDirectoryExists($this->migrationRoot.'/resources/updates');
    file_put_contents($this->migrationRoot.'/resources/updates/project-update.json', '{}');
    $path = 'database/migrations/2026_01_01_000001_core.php';
    $manifest = ['schema' => 1, 'version' => '1.3.0', 'source_sha' => str_repeat('a', 40), 'min_php' => '8.3', 'extensions' => [], 'supported_from' => ['1.2.0'], 'rollback_compatible' => true, 'migrations' => ['core' => [$path], 'modules' => []], 'distributions' => ['source' => ['asset' => 'source.tar.gz', 'sha256' => str_repeat('f', 64), 'size' => 1, 'files' => [$path => hash_file('sha256', $this->migrationRoot.'/'.$path)]]]];
    $raw = json_encode($manifest, JSON_THROW_ON_ERROR);
    openssl_sign($raw, $signature, $privatePem, OPENSSL_ALGO_SHA256);
    file_put_contents($this->migrationRoot.'/resources/updates/installed-manifest.json', $raw);
    file_put_contents($this->migrationRoot.'/resources/updates/installed-manifest.sig', json_encode(['key_id' => 'migration-test', 'signature' => base64_encode($signature)], JSON_THROW_ON_ERROR));
    file_put_contents($this->migrationRoot.'/'.$path, '<?php // changed after signing');
    $repository = Mockery::mock(MigrationRepositoryInterface::class);
    $repository->shouldReceive('getRan')->once()->andReturn([]);
    $this->migrator->shouldReceive('getRepository')->once()->andReturn($repository);
    $this->migrator->shouldNotReceive('run');
    $this->projectUpdates->shouldReceive('review')->once()->with($this->migrationRoot);
    $this->modules->shouldReceive('newQuery')->never();
    $previousRoot = config('updates.root_path');
    $service = new ProjectMigrationService($this->migrator, $this->modules, $this->projectUpdates, new UpdateMigrationService($this->migrator), new SignedManifestVerifier);
    expect(fn () => $service->migrate($this->migrationRoot))->toThrow(RuntimeException::class, 'La migración ha cambiado')
        ->and(config('updates.root_path'))->toBe($previousRoot);
});
