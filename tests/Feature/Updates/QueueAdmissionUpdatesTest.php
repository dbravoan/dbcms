<?php

declare(strict_types=1);

use App\Core\Updates\FileUpdateEngine;
use App\Core\Updates\QueueUpdateAdmission;
use App\Core\Updates\UpdateBarrier;
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
