<?php

declare(strict_types=1);

use App\Contracts\Services\UpdateDeploymentServiceInterface;
use App\Core\Modules\ModuleManager;
use App\Core\Updates\FileUpdateEngine;
use App\Core\Updates\QueueUpdateAdmission;
use App\Core\Updates\UpdateBarrier;
use App\Services\Updates\UpdatePreflightService;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobAttempted;
use Illuminate\Queue\Events\JobPopping;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->updateStorage = storage_path('framework/testing/queue-updates-'.bin2hex(random_bytes(8)));
    config(['updates.storage_path' => $this->updateStorage]);
    mkdir($this->updateStorage, 0700, true);
});

afterEach(function (): void {
    app(QueueUpdateAdmission::class)->release();
    File::deleteDirectory($this->updateStorage);
});

it('holds admission before reserving jobs and releases it when the attempt finishes', function (): void {
    Event::dispatch(new JobPopping('database', 'default'));
    $barrier = new UpdateBarrier($this->updateStorage);
    expect($barrier->drain())->toBeFalse();
    $job = Mockery::mock(Job::class);
    Event::dispatch(new JobAttempted('database', $job));
    expect($barrier->drain())->toBeTrue();
});

it('blocks worker reservation before consuming a retry when an update closes admission', function (): void {
    FileUpdateEngine::writeJson($this->updateStorage.'/active.json', ['run_id' => 'test']);
    expect(fn () => Event::dispatch(new JobPopping('database', 'default')))->toThrow(DomainException::class);
});

it('keeps the outer worker lease when a synchronous nested job finishes', function (): void {
    Event::dispatch(new JobPopping('database', 'default'));
    $job = Mockery::mock(Job::class);
    Event::dispatch(new JobAttempted('sync', $job));
    expect((new UpdateBarrier($this->updateStorage))->drain())->toBeFalse();
    Event::dispatch(new JobAttempted('database', $job));
    expect((new UpdateBarrier($this->updateStorage))->drain())->toBeTrue();
});

it('drains every explicit Redis worker independently while the default connection remains database', function (): void {
    config(['queue.default' => 'database']);
    $workers = array_map(fn (): QueueUpdateAdmission => new QueueUpdateAdmission, range(1, 4));
    $barrier = new UpdateBarrier($this->updateStorage);
    try {
        foreach ($workers as $worker) {
            $worker->beforePop('marketing');
        }
        $barrier->close(['run_id' => 'marketing-drain']);
        expect(fn () => (new QueueUpdateAdmission)->beforePop('marketing'))->toThrow(DomainException::class);
        foreach ($workers as $worker) {
            expect($barrier->drain())->toBeFalse();
            $worker->attempted('database');
            expect($barrier->drain())->toBeFalse();
            $worker->attempted('marketing');
        }
        expect($barrier->drain())->toBeTrue();
    } finally {
        foreach ($workers as $worker) {
            $worker->release();
        }
    }
});

it('checks module ownership before requiring managed deployment for nondefault workers', function (array $owners, bool $enabled, bool $blocked): void {
    config([
        'queue.default' => 'database',
        'queue.connections' => ['database' => ['driver' => 'database'], 'external-campaign' => ['driver' => 'redis', 'requires_managed_updates' => true, 'update_owner_modules' => $owners]],
        'updates.root_path' => base_path(),
        'session.driver' => 'file',
    ]);
    $deployment = Mockery::mock(UpdateDeploymentServiceInterface::class);
    $deployment->shouldReceive('isManaged')->once()->andReturn(false);
    app()->instance(UpdateDeploymentServiceInterface::class, $deployment);
    $modules = Mockery::mock(ModuleManager::class);
    $modules->shouldReceive('isModuleEnabled')->with('custom-campaign')->andReturn($enabled);
    app()->instance(ModuleManager::class, $modules);
    $result = app(UpdatePreflightService::class)->inspect(['mode' => 'runtime'], ['single_host' => true, 'no_unmanaged_workers' => true]);
    expect(str_contains(implode(' ', $result['issues']), 'external-campaign'))->toBe($blocked);
    if ($blocked) {
        expect($result['ready'])->toBeFalse();
    }
})->with([
    'inactive module needs no Redis deployment' => [['custom-campaign'], false, false],
    'active module on explicit Redis despite database default' => [['custom-campaign'], true, true],
    'unowned external worker always requires deployment' => [[], false, true],
]);
