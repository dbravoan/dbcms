<?php

declare(strict_types=1);

test('all canonical release version surfaces identify version 1.1.0', function (): void {
    $root = dirname(__DIR__, 2);
    $version = trim((string) file_get_contents($root.'/VERSION'));
    $package = json_decode((string) file_get_contents($root.'/package.json'), true, flags: JSON_THROW_ON_ERROR);
    $lock = json_decode((string) file_get_contents($root.'/package-lock.json'), true, flags: JSON_THROW_ON_ERROR);
    $core = json_decode((string) file_get_contents($root.'/resources/updates/core-version.json'), true, flags: JSON_THROW_ON_ERROR);
    $policy = json_decode((string) file_get_contents($root.'/resources/updates/release-policy.json'), true, flags: JSON_THROW_ON_ERROR);
    $cms = (string) file_get_contents($root.'/config/cms.php');
    $readme = (string) file_get_contents($root.'/README.md');

    expect($version)->toBe('1.1.0')
        ->and($package['version'] ?? null)->toBe($version)
        ->and($lock['version'] ?? null)->toBe($version)
        ->and($lock['packages']['']['version'] ?? null)->toBe($version)
        ->and($core['version'] ?? null)->toBe($version)
        ->and($core['repository'] ?? null)->toBe('dbravoan/dbcms')
        ->and($policy['version'] ?? null)->toBe($version)
        ->and($cms)->toContain("env('APP_VERSION', '1.1.0')")
        ->and($readme)->toContain('Versión-1.1.0', 'versión actual es **1.1.0**');
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
        'default: "1.1.0"',
        'Descarga este archivo',
        'Source code',
    );
});
