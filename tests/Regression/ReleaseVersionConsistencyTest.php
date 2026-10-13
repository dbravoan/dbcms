<?php

declare(strict_types=1);

test('all canonical release version surfaces identify version 1.0.1', function (): void {
    $root = dirname(__DIR__, 2);
    $version = trim((string) file_get_contents($root.'/VERSION'));
    $package = json_decode((string) file_get_contents($root.'/package.json'), true, flags: JSON_THROW_ON_ERROR);
    $lock = json_decode((string) file_get_contents($root.'/package-lock.json'), true, flags: JSON_THROW_ON_ERROR);
    $cms = (string) file_get_contents($root.'/config/cms.php');
    $readme = (string) file_get_contents($root.'/README.md');

    expect($version)->toBe('1.0.1')
        ->and($package['version'] ?? null)->toBe($version)
        ->and($lock['version'] ?? null)->toBe($version)
        ->and($lock['packages']['']['version'] ?? null)->toBe($version)
        ->and($cms)->toContain("env('APP_VERSION', '1.0.1')")
        ->and($readme)->toContain('Versión-1.0.1', 'versión actual es **1.0.1**');
});
