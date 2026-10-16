<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

beforeEach(function (): void {
    $this->qualityRoot = sys_get_temp_dir().'/dbcms-derived-quality-'.bin2hex(random_bytes(8));
    $this->qualityFiles = new Filesystem;
    $this->qualityFiles->ensureDirectoryExists($this->qualityRoot.'/bin');
    $this->qualityFiles->copy(dirname(__DIR__, 2).'/bin/ci-derived-tests', $this->qualityRoot.'/bin/ci-derived-tests');
    $this->qualityFiles->put($this->qualityRoot.'/bin/php', '#!/usr/bin/env bash'."\n".'printf \'%s\\n\' "$@" >> "$DBCMS_SELECTION_CAPTURE"'."\n");
    chmod($this->qualityRoot.'/bin/php', 0755);
    foreach (['modules/Blog/module.json', 'block-packages/Basic/block-package.json', 'themes/ia-practica/theme.json', 'resources/views/themes/admin/dbcms/theme.json'] as $manifest) {
        $this->qualityFiles->ensureDirectoryExists(dirname($this->qualityRoot.'/'.$manifest));
        $this->qualityFiles->put($this->qualityRoot.'/'.$manifest, '{}');
    }
    $this->qualityRun = fn (array $arguments = []): Process => (new Process(
        ['bash', $this->qualityRoot.'/bin/ci-derived-tests', ...$arguments],
        $this->qualityRoot,
        ['PATH' => $this->qualityRoot.'/bin:'.getenv('PATH'), 'DBCMS_SELECTION_CAPTURE' => $this->qualityRoot.'/selected.txt', 'DBCMS_TEST_ISOLATED' => '1'],
    ))->setTimeout(10);
});

afterEach(function (): void {
    $this->qualityFiles->deleteDirectory($this->qualityRoot);
});

it('runs real fast and core Blog test entrypoints for the exact physical learning profile', function (): void {
    $profile = ($this->qualityRun)(['--profile']);
    $profile->mustRun();
    expect(trim($profile->getOutput()))->toBe('ai-learning');
    $process = ($this->qualityRun)();
    $process->mustRun();
    $arguments = file($this->qualityRoot.'/selected.txt', FILE_IGNORE_NEW_LINES);
    expect($arguments)->toContain('--configuration=phpunit.fast.xml', 'tests/Feature/Security', 'tests/Feature/Install/FinalInstallationSafetyTest.php', 'tests/Feature/Blog/PublicContentReadContractTest.php', 'tests/Feature/Projects/AiLearningStarterTest.php')
        ->not->toContain('tests/Unit', 'tests/Feature/Auth/RbacPermissionsTest.php', 'tests/Feature/Maintenance');
});

it('keeps the comprehensive derived selection when the full module sources exist', function (): void {
    foreach (['Ads', 'AiAssistant', 'Analytics', 'Captcha', 'ContentAudit', 'CreativeStudio', 'Forms', 'Gallery', 'Maintenance', 'Marketing', 'Mcp', 'Meta', 'Orchestration', 'Search', 'Shop', 'SocialPublishing'] as $module) {
        $this->qualityFiles->ensureDirectoryExists($this->qualityRoot.'/modules/'.$module);
        $this->qualityFiles->put($this->qualityRoot.'/modules/'.$module.'/module.json', '{}');
    }
    $profile = ($this->qualityRun)(['--profile']);
    $profile->mustRun();
    expect(trim($profile->getOutput()))->toBe('full-catalogue');
    ($this->qualityRun)()->mustRun();
    $arguments = file($this->qualityRoot.'/selected.txt', FILE_IGNORE_NEW_LINES);
    expect($arguments)->toContain('tests/Projects', 'tests/Unit', 'tests/Feature/Auth/RbacPermissionsTest.php', 'tests/Feature/Maintenance');
});

it('rejects unsupported partial catalogues before executing any test runner', function (string $directory): void {
    $this->qualityFiles->ensureDirectoryExists($this->qualityRoot.'/'.$directory);
    $process = ($this->qualityRun)();
    $process->run();
    expect($process->getExitCode())->toBe(2)
        ->and($process->getErrorOutput())->toContain('Unsupported derived quality profile')
        ->and(is_file($this->qualityRoot.'/selected.txt'))->toBeFalse();
})->with(['modules/Shop', 'block-packages/Charts', 'themes/another-theme', 'resources/views/themes/admin/termocms']);

it('refuses a non-isolated runtime before executing installer or database tests', function (): void {
    $process = ($this->qualityRun)();
    $process->setEnv(['DBCMS_TEST_ISOLATED' => '0']);
    $process->run();
    expect($process->getExitCode())->toBe(1)
        ->and($process->getErrorOutput())->toContain('isolated Docker filesystem and database')
        ->and(is_file($this->qualityRoot.'/selected.txt'))->toBeFalse();
});
