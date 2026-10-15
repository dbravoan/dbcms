<?php

declare(strict_types=1);

test('all canonical release version surfaces identify the prepared release', function (): void {
    $root = dirname(__DIR__, 2);
    $version = trim((string) file_get_contents($root.'/VERSION'));
    $package = json_decode((string) file_get_contents($root.'/package.json'), true, flags: JSON_THROW_ON_ERROR);
    $lock = json_decode((string) file_get_contents($root.'/package-lock.json'), true, flags: JSON_THROW_ON_ERROR);
    $core = json_decode((string) file_get_contents($root.'/resources/updates/core-version.json'), true, flags: JSON_THROW_ON_ERROR);
    $policy = json_decode((string) file_get_contents($root.'/resources/updates/release-policy.json'), true, flags: JSON_THROW_ON_ERROR);
    $cms = (string) file_get_contents($root.'/config/cms.php');
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
        ->and($policy['supported_from'] ?? null)->toBe(['1.0.0', '1.0.1', '1.1.0', '1.2.0', $version])
        ->and($policy['rollback_compatible'] ?? null)->toBeTrue()
        ->and($policy['review'] ?? '')->toContain($version, 'without adding database migrations', 'no automatic database rollback')
        ->and($installer)->toContain('dbcms-installer-v'.$version.'.zip')
        ->and($start)->toContain('dbcms-installer-v'.$version.'.zip')
        ->and($cms)->toContain("env('APP_VERSION', '".$version."')")
        ->and($readme)->toContain('Versión-'.$version, 'versión preparada en esta rama es **'.$version.'**');
});

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
