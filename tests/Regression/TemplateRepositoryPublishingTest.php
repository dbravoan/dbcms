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

test('release waits for exact successful main CI and both native images before publishing', function (): void {
    $workflow = Yaml::parseFile(__DIR__.'/../../.github/workflows/release.yml');
    $jobs = $workflow['jobs'];
    expect($jobs)->toHaveKey('verify-ci')
        ->and($jobs)->toHaveKey('docker-merge')
        ->and((array) $jobs['docker-publish']['needs'])->toContain('verify-ci')
        ->and((array) $jobs['build-standalone-zip']['needs'])->toContain('verify-ci');

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

test('release reuses the successful CI push for the exact main commit without repeating quality', function (): void {
    $workflow = Yaml::parseFile(__DIR__.'/../../.github/workflows/release.yml');
    $source = (string) file_get_contents(__DIR__.'/../../.github/workflows/release.yml');
    $verifier = $workflow['jobs']['verify-ci'] ?? [];
    $dispatchInputs = $workflow['on']['workflow_dispatch']['inputs'] ?? [];
    $script = implode("\n", array_map(
        static fn (array $step): string => ($step['script'] ?? '')."\n".($step['run'] ?? ''),
        $verifier['steps'] ?? [],
    ));

    expect($source)->not->toContain('./.github/workflows/quality.yml', 'bin/ci-check')
        ->and($dispatchInputs)->toHaveKey('sha')
        ->and($dispatchInputs['sha']['required'] ?? false)->toBeTrue()
        ->and($script)->toContain('ci.yml', 'head_sha', 'main', 'push', 'success')
        ->and($script)->toMatch('/listWorkflowRuns|actions\/workflows\/ci\.yml\/runs/');
});

test('release uploads a clearly named self-contained browser installer', function (): void {
    $workflow = Yaml::parseFile(__DIR__.'/../../.github/workflows/release.yml');
    $archiveJob = $workflow['jobs']['build-standalone-zip'];
    $releaseJob = $workflow['jobs']['create-github-release'];
    $archiveRuns = implode("\n", array_column($archiveJob['steps'], 'run'));
    $releaseSource = json_encode($releaseJob, JSON_THROW_ON_ERROR);
    $releaseStep = array_values(array_filter(
        $releaseJob['steps'],
        static fn (array $step): bool => str_starts_with($step['uses'] ?? '', 'softprops/action-gh-release@'),
    ))[0];

    expect($archiveRuns)->toContain('dbcms-installer-v${RELEASE_VER}.zip')
        ->and($releaseSource)->toContain('Descarga este archivo', 'Source code')
        ->and($releaseStep['with']['files'] ?? '')->toContain('dist/*.zip', 'dist/*.tar.gz', 'dist/checksums.txt');
});

test('deployment consumes only the exact successful CI commit and never repeats quality', function (): void {
    $workflow = Yaml::parseFile(__DIR__.'/../../.github/workflows/deploy.yml');
    $source = (string) file_get_contents(__DIR__.'/../../.github/workflows/deploy.yml');
    $on = $workflow['on'] ?? [];
    $jobs = $workflow['jobs'] ?? [];
    $verifier = $jobs['verify-ci'] ?? [];
    $deploy = $jobs['deploy'] ?? [];
    $verification = implode("\n", array_map(
        static fn (array $step): string => ($step['script'] ?? '')."\n".($step['run'] ?? '')."\n".json_encode($step['env'] ?? []),
        $verifier['steps'] ?? [],
    ));
    $checkout = array_values(array_filter(
        $deploy['steps'] ?? [],
        static fn (array $step): bool => str_starts_with($step['uses'] ?? '', 'actions/checkout@'),
    ));

    expect($source)->not->toContain('./.github/workflows/quality.yml', 'bin/ci-check')
        ->and($on['workflow_run']['workflows'] ?? [])->toContain('Continuous Integration')
        ->and($on['workflow_run']['types'] ?? [])->toContain('completed')
        ->and($on)->toHaveKey('workflow_dispatch')
        ->and($on['workflow_dispatch']['inputs']['sha']['required'] ?? false)->toBeTrue()
        ->and($jobs)->toHaveKey('verify-ci')
        ->and((array) ($deploy['needs'] ?? []))->toContain('verify-ci')
        ->and($deploy['if'] ?? '')->toContain('VPS_DEPLOY_ENABLED')
        ->and($verification)->toContain('workflow_run', 'head_sha', 'head_branch', 'main', 'push', 'success')
        ->and($verification)->toMatch('/listWorkflowRuns|actions\/workflows\/ci\.yml\/runs/')
        ->and($checkout)->toHaveCount(1)
        ->and($checkout[0]['with']['ref'] ?? '')->toContain('needs.verify-ci.outputs');
});
