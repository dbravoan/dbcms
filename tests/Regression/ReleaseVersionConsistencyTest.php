<?php

declare(strict_types=1);
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

test('all canonical release version surfaces identify the prepared release', function (): void {
    $root = dirname(__DIR__, 2);
    $version = trim((string) file_get_contents($root.'/VERSION'));
    $package = json_decode((string) file_get_contents($root.'/package.json'), true, flags: JSON_THROW_ON_ERROR);
    $lock = json_decode((string) file_get_contents($root.'/package-lock.json'), true, flags: JSON_THROW_ON_ERROR);
    $core = json_decode((string) file_get_contents($root.'/resources/updates/core-version.json'), true, flags: JSON_THROW_ON_ERROR);
    $policy = json_decode((string) file_get_contents($root.'/resources/updates/release-policy.json'), true, flags: JSON_THROW_ON_ERROR);
    $readme = (string) file_get_contents($root.'/README.md');
    $installer = (string) file_get_contents($root.'/bootstrap/InstallerBootstrap.php');
    $start = (string) file_get_contents($root.'/EMPEZAR.html');

    expect($version)->toMatch('/^\d+\.\d+\.\d+$/')
        ->and($package['version'] ?? null)->toBe($version)
        ->and($lock['version'] ?? null)->toBe($version)
        ->and($lock['packages']['']['version'] ?? null)->toBe($version)
        ->and($core['version'] ?? null)->toBe($version)
        ->and($core['repository'] ?? null)->toBe('dbravoan/dbcms')
        ->and($policy['version'] ?? null)->toBe($version)
        ->and($policy['supported_from'] ?? null)->toBeArray()->not->toBeEmpty()
        ->and($policy['supported_from'])->toBe(array_values(array_unique($policy['supported_from'])))
        ->and(collect($policy['supported_from'])->every(fn (mixed $origin): bool => is_string($origin) && preg_match('/^\d+\.\d+\.\d+$/D', $origin) === 1 && version_compare($origin, $version, '<=')))->toBeTrue()
        ->and(collect($policy['supported_from'])->contains(fn (string $origin): bool => version_compare($origin, $version, '<')) || in_array($version, $policy['supported_from'], true))->toBeTrue()
        ->and($policy['rollback_compatible'] ?? null)->toBeFalse()
        ->and($installer)->toContain('dbcms-installer-v'.$version.'.zip')
        ->and($start)->toContain('dbcms-installer-v'.$version.'.zip')
        ->and($readme)->toMatch('/versi[oó]n\s+(?:\*\*)?'.preg_quote($version, '/').'(?![\d.])/iu');
});

test('runtime version reads the VERSION file and respects an application override', function (?string $override): void {
    $root = dirname(__DIR__, 2);
    $fixture = sys_get_temp_dir().'/dbcms-version-config-'.bin2hex(random_bytes(8));
    mkdir($fixture, 0700);
    file_put_contents($fixture.'/VERSION', "9.8.7\n");
    $script = <<<'PHP'
    require $argv[1].'/vendor/autoload.php';
    new Illuminate\Foundation\Application($argv[2]);
    $configuration = require $argv[1].'/config/cms.php';
    echo json_encode($configuration['version'], JSON_THROW_ON_ERROR);
    PHP;
    try {
        $process = new Process([PHP_BINARY, '-r', $script, $root, $fixture], $root, ['APP_VERSION' => $override ?? false]);
        $process->mustRun();
        expect(json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR))->toBe($override ?? '9.8.7');
    } finally {
        (new Filesystem)->remove($fixture);
    }
})->with(['release fallback' => [null], 'application override' => ['customer-4.2.1']]);

test('the standalone release is presented as a browser installer', function (): void {
    $root = dirname(__DIR__, 2);
    $builder = (string) file_get_contents($root.'/bin/build-release-zip.sh');
    $workflow = (string) file_get_contents($root.'/bin/github-delivery.py');

    expect($builder)->toContain(
        'dbcms-installer-v${VERSION}.zip',
        'EMPEZAR.html',
        'vendor/autoload.php',
        'public/build/manifest.json',
        'curl --fail-with-body',
    )->and($workflow)->toContain(
        'dbcms-installer-v',
        'Descarga este archivo',
        'Source code',
    );
});
