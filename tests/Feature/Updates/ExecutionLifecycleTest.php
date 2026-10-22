<?php

declare(strict_types=1);

use App\Contracts\Services\UpdateBackupServiceInterface;
use App\Contracts\Services\UpdateExecutionServiceInterface;
use App\Contracts\Services\UpdateManagedReconcilerServiceInterface;
use App\Contracts\Services\UpdateMigrationServiceInterface;
use App\Contracts\Services\UpdatePreflightServiceInterface;
use App\Core\Updates\FileUpdateEngine;
use App\Core\Updates\UpdateBarrier;
use App\Core\Updates\UpdateRunStore;
use App\Models\Role;
use App\Models\User;
use App\Services\Updates\UpdateExecutionService;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Response;

/** Already verified signed manifests/approvals are boundary fixtures here. */
function executionLifecycleRun(User $actor, UpdateRunStore $store, string $status): array
{
    $root = config('updates.root_path');
    $stage = $root.'/stage-'.bin2hex(random_bytes(6));
    mkdir($stage.'/app', 0700, true);
    file_put_contents($stage.'/app/example.php', 'new content');
    file_put_contents($stage.'/VERSION', '1.1.0');
    $inventory = ['app/example.php' => hash('sha256', 'new content'), 'VERSION' => hash('sha256', '1.1.0')];
    $comparison = ['plan' => [['path' => 'app/example.php', 'action' => 'replace', 'old_hash' => hash('sha256', 'old content'), 'new_hash' => $inventory['app/example.php']], ['path' => 'VERSION', 'action' => 'replace', 'old_hash' => hash('sha256', '1.0.1'), 'new_hash' => $inventory['VERSION']]], 'conflicts' => [], 'preserved' => []];
    $distribution = ['asset' => 'fixture.zip', 'sha256' => str_repeat('c', 64), 'size' => 1, 'files' => $inventory];
    $manifest = ['schema' => 1, 'version' => '1.1.0', 'source_sha' => str_repeat('b', 40), 'min_php' => '8.3.0', 'extensions' => [], 'supported_from' => ['1.0.1'], 'rollback_compatible' => false, 'migrations' => ['core' => [], 'modules' => []], 'distributions' => ['source' => $distribution, 'runtime' => $distribution]];
    $raw = json_encode($manifest, JSON_THROW_ON_ERROR);
    static $key;
    $key ??= openssl_pkey_new(['private_key_bits' => 3072, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_sign($raw, $signed, $key, OPENSSL_ALGO_SHA256);
    $signature = json_encode(['key_id' => 'lifecycle-fixture', 'signature' => base64_encode($signed)], JSON_THROW_ON_ERROR);
    $keys = ['lifecycle-fixture' => openssl_pkey_get_details($key)['key']];
    config(['updates.trusted_keys' => $keys]);
    $run = $store->create(['actor_id' => $actor->id, 'status' => $status, 'mode' => 'runtime', 'version' => '1.1.0', 'distribution' => 'runtime', 'manifest' => $manifest, 'manifest_raw' => $raw, 'signature_raw' => $signature, 'manifest_hash' => hash('sha256', $raw), ...$comparison, 'plan_hash' => hash('sha256', json_encode($comparison, JSON_THROW_ON_ERROR)), 'stage_path' => $stage, 'target_identity' => ['version' => '1.1.0', 'source_sha' => str_repeat('b', 40), 'distribution' => 'runtime', 'manifest_raw' => $raw, 'signature_raw' => $signature, 'files' => $inventory], 'backup_evidence' => [], 'recovery' => ['user_hash' => hash('sha256', $actor->getAuthPassword())]]);
    $directory = $store->directory($run['id']);
    FileUpdateEngine::writeJson($directory.'/runtime/trusted-keys.json', $keys);
    (new FileUpdateEngine($root, $stage, $directory.'/backup'))->backupPlan($run['plan']);
    copy($root.'/.env', $directory.'/backup/environment.backup');
    $backup = ['environment_sha256' => hash_file('sha256', $root.'/.env'), 'database' => ['kind' => 'external', 'reference' => 'lifecycle boundary fixture'], 'passport' => []];
    FileUpdateEngine::writeJson($directory.'/backup/backup.json', $backup);
    $run['approval'] = ['actor_id' => $actor->id, 'plan_hash' => $run['plan_hash'], 'manifest_hash' => $run['manifest_hash'], 'backup_hash' => hash('sha256', json_encode($backup, JSON_THROW_ON_ERROR)), 'transport' => 'cli'];

    return $store->save($run['id'], $run);
}

beforeEach(function (): void {
    $this->originalExecutionStorage = app()->storagePath();
    $this->executionTmp = sys_get_temp_dir().'/execution-lifecycle-'.bin2hex(random_bytes(8));
    mkdir($this->executionTmp.'/root/app', 0700, true);
    app()->useStoragePath($this->executionTmp.'/storage');
    config(['updates.root_path' => $this->executionTmp.'/root', 'updates.storage_path' => storage_path('app/private/core-updates')]);
    file_put_contents($this->executionTmp.'/root/app/example.php', 'old content');
    file_put_contents($this->executionTmp.'/root/VERSION', '1.0.1');
    file_put_contents($this->executionTmp.'/root/.env', 'APP_KEY=never-replace-existing-key');
    $role = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
    $this->executionActor = User::factory()->create();
    $this->executionActor->assignRole($role);
    $this->executionStore = new UpdateRunStore;
    $this->executionStore->atomic($this->executionStore->root().'/identity.json', json_encode(['version' => '1.0.1', 'source_sha' => str_repeat('a', 40), 'distribution' => 'runtime'], JSON_THROW_ON_ERROR));
    $this->executionPreflight = Mockery::mock(UpdatePreflightServiceInterface::class);
    $this->executionPreflight->shouldReceive('inspect')->andReturn(['mode' => 'runtime', 'ready' => true, 'issues' => []])->byDefault();
    $this->executionBackups = Mockery::mock(UpdateBackupServiceInterface::class);
    $this->executionMigrations = Mockery::mock(UpdateMigrationServiceInterface::class);
    $this->executionMigrations->shouldReceive('pending')->andReturn([])->byDefault();
    $this->executionManaged = Mockery::mock(UpdateManagedReconcilerServiceInterface::class);
    $this->execution = new UpdateExecutionService($this->executionStore, $this->executionPreflight, $this->executionBackups, $this->executionMigrations, $this->executionManaged);
});

afterEach(function (): void {
    app()->useStoragePath($this->originalExecutionStorage);
    File::deleteDirectory($this->executionTmp);
});

it('never repeats backup or migration phases after an uncertain interrupted request', function (string $phase): void {
    $run = executionLifecycleRun($this->executionActor, $this->executionStore, $phase);
    (new UpdateBarrier($this->executionStore->root()))->close(['run_id' => $run['id']]);
    $this->executionBackups->shouldNotReceive('create');
    $this->executionMigrations->shouldNotReceive('apply');
    $result = $this->execution->advance($run['id'], $this->executionActor->id);
    expect($result['status'])->toBe($phase === 'backing_up' ? 'prepared' : 'recovery_required');
    expect(is_file($this->executionStore->root().'/active.json'))->toBe($phase !== 'backing_up');
    if ($phase === 'backing_up') {
        expect($result['backup_required'])->toBeTrue();
    }
    expect($this->executionStore->identity()['version'])->toBe('1.0.1');
})->with(['backing_up', 'migrations_running']);

it('waits for admitted work to release its lease before creating the backup', function (): void {
    $barrier = new UpdateBarrier($this->executionStore->root());
    $lease = $barrier->admit();
    $run = executionLifecycleRun($this->executionActor, $this->executionStore, 'draining');
    $barrier->close(['run_id' => $run['id']]);
    $this->executionBackups->shouldNotReceive('create');
    try {
        $result = $this->execution->advance($run['id'], $this->executionActor->id);
        expect($result['status'])->toBe('draining')->and($result['message'])->toContain('Esperando');
        expect(file_get_contents(config('updates.root_path').'/app/example.php'))->toBe('old content');
    } finally {
        flock($lease, LOCK_UN);
        fclose($lease);
    }
});

it('binds the verified backup before starting any replacement', function (): void {
    $run = executionLifecycleRun($this->executionActor, $this->executionStore, 'draining');
    (new UpdateBarrier($this->executionStore->root()))->close(['run_id' => $run['id']]);
    $this->executionBackups->shouldReceive('create')->once()->andReturnUsing(function (string $directory, array $evidence): array {
        $metadata = json_decode(file_get_contents($directory.'/backup.json'), true, 128, JSON_THROW_ON_ERROR);

        return ['hash' => hash('sha256', json_encode($metadata, JSON_THROW_ON_ERROR))];
    });
    $result = $this->execution->advance($run['id'], $this->executionActor->id);
    expect($result['status'])->toBe('files');
    $saved = $this->executionStore->read($run['id']);
    expect($saved['approval']['backup_hash'])->toBe($run['approval']['backup_hash']);
    expect(file_get_contents(config('updates.root_path').'/app/example.php'))->toBe('old content');
    expect($this->executionStore->identity()['version'])->toBe('1.0.1');
});

it('can cancel before application and only opens the matching run barrier', function (): void {
    $run = executionLifecycleRun($this->executionActor, $this->executionStore, 'draining');
    (new UpdateBarrier($this->executionStore->root()))->close(['run_id' => $run['id']]);
    $result = $this->execution->cancel($run['id'], $this->executionActor->id);
    expect($result['status'])->toBe('cancelled')->and(is_file($this->executionStore->root().'/active.json'))->toBeFalse();
    expect(file_get_contents(config('updates.root_path').'/.env'))->toBe('APP_KEY=never-replace-existing-key');
    expect($this->executionStore->identity()['version'])->toBe('1.0.1');
});

it('keeps the site closed and original identity when approved backup bytes change', function (): void {
    $run = executionLifecycleRun($this->executionActor, $this->executionStore, 'files');
    (new UpdateBarrier($this->executionStore->root()))->close(['run_id' => $run['id']]);
    file_put_contents($this->executionStore->directory($run['id']).'/backup/environment.backup', 'tampered');
    expect(fn () => $this->execution->advance($run['id'], $this->executionActor->id))->toThrow(DomainException::class);
    expect($this->executionStore->read($run['id'])['status'])->toBe('recovery_required');
    expect(is_file($this->executionStore->root().'/active.json'))->toBeTrue();
    expect(file_get_contents(config('updates.root_path').'/app/example.php'))->toBe('old content');
    expect($this->executionStore->identity()['version'])->toBe('1.0.1');
});

it('keeps the site closed when a prepared replacement file changes after approval', function (): void {
    $run = executionLifecycleRun($this->executionActor, $this->executionStore, 'files');
    (new UpdateBarrier($this->executionStore->root()))->close(['run_id' => $run['id']]);
    file_put_contents($run['stage_path'].'/app/example.php', 'tampered stage');
    expect(fn () => $this->execution->advance($run['id'], $this->executionActor->id))->toThrow(DomainException::class);
    expect($this->executionStore->read($run['id'])['status'])->toBe('recovery_required');
    expect(is_file($this->executionStore->root().'/active.json'))->toBeTrue();
    expect(file_get_contents(config('updates.root_path').'/app/example.php'))->toBe('old content');
});

it('commits the installed identity only after new-process health has passed', function (): void {
    $run = executionLifecycleRun($this->executionActor, $this->executionStore, 'files');
    (new UpdateBarrier($this->executionStore->root()))->close(['run_id' => $run['id']]);
    $this->executionMigrations->shouldReceive('apply')->once()->with([]);
    $this->execution->advance($run['id'], $this->executionActor->id);
    expect($this->executionStore->identity()['version'])->toBe('1.0.1');
    $this->execution->advance($run['id'], $this->executionActor->id);
    Artisan::shouldReceive('call')->once()->with('optimize:clear')->andReturn(0);
    Artisan::shouldReceive('call')->once()->with('queue:restart')->andReturn(0);
    $this->execution->advance($run['id'], $this->executionActor->id);
    $run = $this->executionStore->read($run['id']);
    // Simulate the boundary of a separately booted request; HTTP recovery above
    // independently exercises real processes without Composer/vendor.
    $run['files_applied_request'] = 1;
    $this->executionStore->save($run['id'], $run);
    $kernel = Mockery::mock(Kernel::class);
    $kernel->shouldReceive('handle')->times(3)->andReturn(new Response('healthy', 200));
    $kernel->shouldReceive('terminate')->times(3);
    app()->instance(Kernel::class, $kernel);
    expect($this->execution->advance($run['id'], $this->executionActor->id)['status'])->toBe('health_verified');
    expect($this->executionStore->identity()['version'])->toBe('1.0.1')->and(is_file($this->executionStore->root().'/active.json'))->toBeTrue();
    expect($this->execution->advance($run['id'], $this->executionActor->id)['status'])->toBe('completed');
    expect($this->executionStore->identity()['version'])->toBe('1.1.0')->and(is_file($this->executionStore->root().'/active.json'))->toBeFalse();
});

it('refuses to activate another run while a global update is already active', function (): void {
    $first = executionLifecycleRun($this->executionActor, $this->executionStore, 'draining');
    $second = executionLifecycleRun($this->executionActor, $this->executionStore, 'prepared');
    (new UpdateBarrier($this->executionStore->root()))->close(['run_id' => $first['id']]);
    $request = Request::create('/');
    $request->attributes->set('_cms_update_cli_approval', true);
    app()->instance('request', $request);
    expect(fn () => $this->execution->approve($second['id'], $this->executionActor->id, $second['plan_hash']))->toThrow(DomainException::class);
    expect($this->executionStore->read($second['id'])['status'])->toBe('prepared');
    expect(json_decode(file_get_contents($this->executionStore->root().'/active.json'), true)['run_id'])->toBe($first['id']);
});

it('refuses to advance an approved run that does not own the global barrier', function (): void {
    $first = executionLifecycleRun($this->executionActor, $this->executionStore, 'draining');
    $second = executionLifecycleRun($this->executionActor, $this->executionStore, 'files');
    (new UpdateBarrier($this->executionStore->root()))->close(['run_id' => $first['id']]);
    expect(fn () => $this->execution->advance($second['id'], $this->executionActor->id))->toThrow(DomainException::class);
    expect(file_get_contents(config('updates.root_path').'/app/example.php'))->toBe('old content');
    expect(json_decode(file_get_contents($this->executionStore->root().'/active.json'), true)['run_id'])->toBe($first['id']);
});

it('records a real interactive CLI approval and discards its transient authority afterwards', function (): void {
    $run = executionLifecycleRun($this->executionActor, $this->executionStore, 'prepared');
    app()->instance(UpdateExecutionServiceInterface::class, $this->execution);
    $this->artisan('cms:updates', ['operation' => 'approve', 'target' => $run['id'], '--actor' => $this->executionActor->id, '--plan-hash' => $run['plan_hash']])
        ->expectsConfirmation('Apply this exact reviewed update with its verified backup?', 'yes')
        ->expectsQuestion('Confirm the CMS administrator password', 'password')
        ->assertExitCode(0);
    $approved = $this->executionStore->read($run['id']);
    expect($approved['status'])->toBe('draining')
        ->and($approved['approval']['transport'])->toBe('cli')
        ->and($approved['approval']['actor_id'])->toBe($this->executionActor->id)
        ->and(request()->attributes->has('_cms_update_cli_approval'))->toBeFalse()
        ->and(is_file($this->executionStore->root().'/active.json'))->toBeTrue();
});

it('preserves the approving request and session across health probes including failures', function (bool $healthy): void {
    $run = executionLifecycleRun($this->executionActor, $this->executionStore, 'health_pending');
    (new FileUpdateEngine(config('updates.root_path'), $run['stage_path'], $this->executionStore->directory($run['id']).'/backup'))->apply($run['plan']);
    $run['files_applied_request'] = 1;
    $this->executionStore->save($run['id'], $run);
    (new UpdateBarrier($this->executionStore->root()))->close(['run_id' => $run['id']]);
    config(['session.driver' => 'array']);
    $session = app('session')->driver('array');
    $session->start();
    $session->setId(str_repeat('r', 40));
    $session->put('outer.recovery', 'retain this approval');
    app()->instance('session.store', $session);
    $original = Request::create('/update.php', 'POST', [], [$session->getName() => $session->getId()]);
    $original->setLaravelSession($session);
    app()->instance('request', $original);
    Auth::forgetGuards();
    $guard = Auth::guard('web');
    $guard->setUser($this->executionActor);
    $session->put($guard->getName(), $this->executionActor->id);
    $sessionId = $session->getId();
    $attributes = $session->all();
    $kernel = Mockery::mock(Kernel::class);
    $kernel->shouldReceive('handle')->times($healthy ? 3 : 1)->andReturnUsing(function (Request $probe) use ($healthy, $session): Response {
        app()->instance('request', $probe);
        // Model a nested kernel reusing and mutating the outer session store.
        $session->setId(str_repeat('s', 40));
        $session->put('probe.leaked', true);
        app('session.store')->setId(str_repeat('t', 40));
        app('session.store')->put('probe.isolated', true);
        Auth::forgetGuards();

        return new Response($healthy ? 'healthy' : 'unavailable', $healthy ? 200 : 500);
    });
    $kernel->shouldReceive('terminate')->times($healthy ? 3 : 1);
    app()->instance(Kernel::class, $kernel);
    if ($healthy) {
        expect($this->execution->advance($run['id'], $this->executionActor->id)['status'])->toBe('health_verified');
    } else {
        expect(fn () => $this->execution->advance($run['id'], $this->executionActor->id))->toThrow(DomainException::class);
    }
    expect(request())->toBe($original)->and(app('session.store'))->toBe($session);
    expect($session->getId())->toBe($sessionId)->and($session->all())->toBe($attributes);
    expect(request()->session()->getId())->toBe($sessionId)->and(Auth::id())->toBe($this->executionActor->id);
    if ($healthy) {
        expect($this->execution->advance($run['id'], $this->executionActor->id)['status'])->toBe('completed');
    } else {
        expect(is_file($this->executionStore->root().'/active.json'))->toBeTrue();
    }
})->with([true, false]);
