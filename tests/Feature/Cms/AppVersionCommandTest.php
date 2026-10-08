<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Process\Process;

beforeEach(function (): void {
    // Resolve commands before replacing paths; never mutate the project's files or Git refs.
    app(Kernel::class)->bootstrap();
    Artisan::all();
    $this->originalBasePath = base_path();
    $this->originalEnvironmentPath = app()->environmentPath();
    $this->originalWorkingDirectory = getcwd();
    $this->privateRuntime = sys_get_temp_dir().'/dbcms-version-test-'.bin2hex(random_bytes(8));
    File::makeDirectory($this->privateRuntime, 0700, true);
    app()->setBasePath($this->privateRuntime);
    app()->useEnvironmentPath($this->privateRuntime);
    chdir($this->privateRuntime);

    $this->versionFile = base_path('VERSION');
    File::put($this->versionFile, "1.0.0\n");
    File::put(base_path('.env'), "APP_NAME=VersionFixture\nAPP_VERSION=1.0.0\n");
    chmod(base_path('.env'), 0600);
    (new Process(['git', 'init', '--quiet'], $this->privateRuntime))->mustRun();
    (new Process(['git', 'config', 'user.name', 'Version Fixture'], $this->privateRuntime))->mustRun();
    (new Process(['git', 'config', 'user.email', 'version@example.test'], $this->privateRuntime))->mustRun();
    (new Process(['git', 'add', 'VERSION'], $this->privateRuntime))->mustRun();
    (new Process([
        'git', '-c', 'user.name=Version Fixture', '-c', 'user.email=version@example.test',
        '-c', 'commit.gpgsign=false', 'commit', '--quiet', '--no-verify', '-m', 'Fixture version',
    ], $this->privateRuntime))->mustRun();
    (new Process(['git', 'tag', 'fixture-existing'], $this->privateRuntime))->mustRun();
});

afterEach(function (): void {
    app()->setBasePath($this->originalBasePath);
    app()->useEnvironmentPath($this->originalEnvironmentPath);
    chdir($this->originalWorkingDirectory);
    File::deleteDirectory($this->privateRuntime);
});

test('it displays the current canonical version', function (): void {
    $this->artisan('app:version')
        ->expectsOutputToContain('dbCMS Application Version')
        ->expectsOutputToContain('1.0.0')
        ->assertSuccessful();
});

test('it calculates semver bumps correctly', function (): void {
    // Patch bump (1.0.0 -> 1.0.1)
    $this->artisan('app:version', ['--bump' => 'patch'])
        ->expectsOutputToContain('1.0.1')
        ->assertSuccessful();
    expect(trim((string) File::get($this->versionFile)))->toBe('1.0.1');

    // Minor bump (1.0.1 -> 1.1.0)
    $this->artisan('app:version', ['--bump' => 'minor'])
        ->expectsOutputToContain('1.1.0')
        ->assertSuccessful();
    expect(trim((string) File::get($this->versionFile)))->toBe('1.1.0');

    // Major bump (1.1.0 -> 2.0.0)
    $this->artisan('app:version', ['--bump' => 'major'])
        ->expectsOutputToContain('2.0.0')
        ->assertSuccessful();
    expect(trim((string) File::get($this->versionFile)))->toBe('2.0.0')
        ->and(File::get(base_path('.env')))->toContain('APP_VERSION=2.0.0');
});

test('it sets an explicit semver version string', function (): void {
    $this->artisan('app:version', ['--set' => '3.4.2'])
        ->expectsOutputToContain('3.4.2')
        ->assertSuccessful();

    expect(trim((string) File::get($this->versionFile)))->toBe('3.4.2')
        ->and(File::get(base_path('.env')))->toContain('APP_VERSION=3.4.2');
});

test('it rejects invalid semver formats', function (): void {
    $this->artisan('app:version', ['--set' => 'invalid-semver'])
        ->expectsOutputToContain('Invalid SemVer format')
        ->assertFailed();

    $this->artisan('app:version', ['--bump' => 'unknown'])
        ->expectsOutputToContain('Invalid bump type')
        ->assertFailed();
});

test('deprecated tagging fails without changing version environment or Git tags', function (array $options): void {
    $versionBefore = File::get($this->versionFile);
    $environmentBefore = File::get(base_path('.env'));
    $tagsBefore = (new Process(['git', 'show-ref', '--tags'], $this->privateRuntime))->mustRun()->getOutput();

    $exitCode = Artisan::call('app:version', $options);
    $output = Artisan::output();

    expect(File::get($this->versionFile))->toBe($versionBefore)
        ->and(File::get(base_path('.env')))->toBe($environmentBefore)
        ->and((new Process(['git', 'show-ref', '--tags'], $this->privateRuntime))->mustRun()->getOutput())->toBe($tagsBefore)
        ->and($exitCode)->toBe(Command::FAILURE)
        ->and($output)->toContain('--tag is deprecated');
})->with([
    'tag only' => [['--tag' => true]],
    'bump with tag' => [['--bump' => 'patch', '--tag' => true]],
    'explicit version with tag' => [['--set' => '2.0.0', '--tag' => true]],
]);
