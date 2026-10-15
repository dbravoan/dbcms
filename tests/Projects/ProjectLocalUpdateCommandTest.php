<?php

declare(strict_types=1);

use App\Contracts\Services\ProjectUpdateServiceInterface;
use App\DTOs\ProjectUpdatePlan;
use Illuminate\Filesystem\Filesystem;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->originalBase = $this->app->basePath();
    $this->updateCopy = sys_get_temp_dir().'/dbcms-local-update-'.bin2hex(random_bytes(8));
    mkdir($this->updateCopy, 0755, true);
    $this->app->setBasePath($this->updateCopy);
});

afterEach(function (): void {
    $this->app->setBasePath($this->originalBase);
    (new Filesystem)->deleteDirectory($this->updateCopy);
});

it('prepares signed source changes in an explicitly selected working copy without Git', function (): void {
    $service = Mockery::mock(ProjectUpdateServiceInterface::class);
    $service->shouldReceive('prepare')->once()->with($this->updateCopy, '1.3.0')->andReturn(new ProjectUpdatePlan([], [], [], []));
    $this->app->instance(ProjectUpdateServiceInterface::class, $service);

    $this->artisan('cms:project:update', ['version' => '1.3.0', '--working-copy' => true])->assertSuccessful();
    expect(file_exists($this->updateCopy.'/.git'))->toBeFalse();
});

it('requires a Git working tree or an explicit working-copy flag before changing sources', function (): void {
    $service = Mockery::mock(ProjectUpdateServiceInterface::class);
    $service->shouldNotReceive('prepare');
    $this->app->instance(ProjectUpdateServiceInterface::class, $service);

    $this->artisan('cms:project:update', ['version' => '1.3.0'])->assertFailed();
});

it('rejects source preparation in production even with the working-copy flag', function (): void {
    $this->app['env'] = 'production';
    $service = Mockery::mock(ProjectUpdateServiceInterface::class);
    $service->shouldNotReceive('prepare');
    $this->app->instance(ProjectUpdateServiceInterface::class, $service);

    $this->artisan('cms:project:update', ['version' => '1.3.0', '--working-copy' => true])->assertFailed();
});
