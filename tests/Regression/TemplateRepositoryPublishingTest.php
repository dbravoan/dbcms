<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

test('a project created from the template publishes images to its own repository', function (): void {
    $workflow = (string) file_get_contents(__DIR__.'/../../.github/workflows/release.yml');
    preg_match('/^\s+images:\s*(.+)$/m', $workflow, $matches);
    $target = trim($matches[1] ?? '');
    // Simulate the same workflow being copied to the first independent project.
    $resolved = str_replace('${{ github.repository }}', 'dbravoan/regulase', $target);
    expect($resolved)->toBe('ghcr.io/dbravoan/regulase');
});

test('release builds each architecture on a native runner without QEMU', function (): void {
    $workflow = Yaml::parseFile(__DIR__.'/../../.github/workflows/release.yml');
    $job = $workflow['jobs']['docker-publish'];
    $matrix = $job['strategy']['matrix']['include'] ?? [];
    $runners = [];
    foreach ($matrix as $entry) {
        $runners[$entry['arch']] = [$entry['platform'], $entry['runner']];
    }

    expect($runners)->toEqual([
        'amd64' => ['linux/amd64', 'ubuntu-24.04'],
        'arm64' => ['linux/arm64', 'ubuntu-24.04-arm'],
    ])->and($job['runs-on'])->toBe('${{ matrix.runner }}');

    foreach ($workflow['jobs'] as $candidate) {
        foreach ($candidate['steps'] ?? [] as $step) {
            expect($step['uses'] ?? '')->not->toContain('setup-qemu');
        }
    }
});

test('release publishes immutable platform digests only after runtime validation', function (): void {
    $workflow = Yaml::parseFile(__DIR__.'/../../.github/workflows/release.yml');
    $steps = $workflow['jobs']['docker-publish']['steps'];
    $buildIndex = $smokeIndex = $uploadIndex = null;
    foreach ($steps as $index => $step) {
        if (str_starts_with($step['uses'] ?? '', 'docker/build-push-action@')) {
            $buildIndex = $index;
            expect($step['id'] ?? null)->toBe('build')
                ->and($step['with']['platforms'] ?? null)->toBe('${{ matrix.platform }}')
                ->and($step['with']['outputs'] ?? '')->toContain('push-by-digest=true', 'name-canonical=true')
                ->and($step['with']['tags'] ?? null)->toBeNull();
        }
        if (($step['id'] ?? '') === 'runtime-smoke') {
            $smokeIndex = $index;
            $runtime = ($step['run'] ?? '').json_encode($step['env'] ?? []);
            expect($runtime)->toContain('docker run', 'steps.build.outputs.digest', 'php artisan --version');
        }
        if (str_starts_with($step['uses'] ?? '', 'actions/upload-artifact@')) {
            $uploadIndex = $index;
            expect($step['with']['name'] ?? '')->toContain('digests', 'matrix.arch')
                ->and($step['if'] ?? 'success()')->not->toContain('always()');
        }
    }

    expect($buildIndex)->not->toBeNull()
        ->and($smokeIndex)->not->toBeNull()
        ->and($uploadIndex)->not->toBeNull()
        ->and($smokeIndex)->toBeGreaterThan($buildIndex)
        ->and($uploadIndex)->toBeGreaterThan($smokeIndex);
    $runs = implode("\n", array_column($steps, 'run'));
    expect($runs)->toContain('steps.build.outputs.digest');
});

test('release waits for both native images and verifies their merged manifest before publishing archives', function (): void {
    $workflow = Yaml::parseFile(__DIR__.'/../../.github/workflows/release.yml');
    $jobs = $workflow['jobs'];
    expect((array) $jobs['docker-publish']['needs'])->toContain('quality-gate')
        ->and((array) $jobs['build-standalone-zip']['needs'])->toContain('quality-gate')
        ->and($jobs)->toHaveKey('docker-merge');

    $merge = $jobs['docker-merge'];
    expect((array) $merge['needs'])->toContain('docker-publish')
        ->and((array) $jobs['create-github-release']['needs'])->toContain('build-standalone-zip', 'docker-merge');
    $download = array_values(array_filter($merge['steps'], static fn (array $step): bool => str_starts_with($step['uses'] ?? '', 'actions/download-artifact@')));
    expect($download)->toHaveCount(1)
        ->and($download[0]['with']['pattern'] ?? '')->toContain('digests')
        ->and($download[0]['with']['merge-multiple'] ?? false)->toBeTrue();

    $runs = implode("\n", array_column($merge['steps'], 'run'));
    expect($runs)->toContain('imagetools create', 'imagetools inspect', 'amd64', 'arm64');
    expect(strpos($runs, 'imagetools inspect'))->toBeGreaterThan(strpos($runs, 'imagetools create'));
});

test('the common release quality gate checks the requested version before testing or publishing', function (): void {
    $workflow = Yaml::parseFile(__DIR__.'/../../.github/workflows/release.yml');
    expect($workflow['jobs']['quality-gate']['uses'])->toBe('./.github/workflows/quality.yml');
    $quality = Yaml::parseFile(__DIR__.'/../../.github/workflows/quality.yml');
    $steps = $quality['jobs']['verify']['steps'];
    $versionIndex = $verificationIndex = null;
    foreach ($steps as $index => $step) {
        $run = $step['run'] ?? '';
        if (str_contains($run, '< VERSION')) {
            $versionIndex = $index;
            expect($run)->toContain('test ', 'RELEASE_VERSION', 'GITHUB_REF_NAME')
                ->and($step['env']['RELEASE_VERSION'] ?? '')->toContain('github.event.inputs.version');
        }
        if (str_contains($run, 'bin/ci-check')) {
            $verificationIndex = $index;
        }
    }
    expect($versionIndex)->not->toBeNull()
        ->and($verificationIndex)->not->toBeNull()
        ->and($verificationIndex)->toBeGreaterThan($versionIndex);
});
