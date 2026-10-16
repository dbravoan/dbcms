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
        ->and($policy['supported_from'] ?? null)->toBe([$version])
        ->and($policy['rollback_compatible'] ?? null)->toBeFalse()
        ->and($policy['review'] ?? '')->toContain($version, '1.x', 'unsupported')
        ->and($installer)->toContain('dbcms-installer-v'.$version.'.zip')
        ->and($start)->toContain('dbcms-installer-v'.$version.'.zip')
        ->and($readme)->toContain('Versión-'.$version, 'versión preparada en esta rama es **'.$version.'**');
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
    $workflow = (string) file_get_contents($root.'/.github/workflows/release.yml');

    expect($builder)->toContain(
        'dbcms-installer-v${VERSION}.zip',
        'EMPEZAR.html',
        'vendor/autoload.php',
        'public/build/manifest.json',
        'curl --fail-with-body',
    )->and($workflow)->toContain(
        'dbcms-installer-v',
        'default: "'.trim((string) file_get_contents($root.'/VERSION')).'"',
        'Descarga este archivo',
        'Source code',
    );
});

test('historical baselines pin original source and runtime without replacing published assets', function (): void {
    $builder = (string) file_get_contents(dirname(__DIR__, 2).'/bin/build-update-baselines.sh');

    expect($builder)->toContain(
        'for baseline_version in 1.0.0 1.0.1 1.1.0;',
        '3c8619b05b253f4ac609e54ff9ec73a6d59d42d3',
        'f90131bb7dc745210443ab37d8bc76fa83a46736',
        '25e1a5671726659c6fde149ef218715b7d07aadf',
        '7cb9b8eeb65cbfdd2144047ac5ead6a40f4e3eff94b50b3be02db8b5744f44a1',
        'be47c7f236a73b78aa89e7d8b6cddd214fbe308c66717e38699a010777c1de48',
        'Historical tag changed:',
        'sha256sum -c',
        'baseline_archive="dbcms-installer-v${baseline_version}.zip"',
        'sub(/^\\.\\//, "", name)',
        '--runtime-root=',
        "-name 'baseline-*.zip' -delete",
    )->not->toContain('gh release upload', 'git tag -f', 'git push');
});
