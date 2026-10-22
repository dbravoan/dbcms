<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

test('web release publication consumes signed local artifacts without waiting for images or rebuilding', function (): void {
    $root = dirname(__DIR__, 2);
    $workflow = Yaml::parseFile($root.'/.github/workflows/release.yml');
    $publish = $workflow['jobs']['publish'];
    $runs = implode("\n", array_column($publish['steps'], 'run'));
    expect($publish['if'])->toContain("github.repository == 'dbravoan/dbcms'")
        ->and($publish['needs'] ?? [])->toBe([])
        ->and($runs)->toContain('github-delivery.py stage', '--kind release', 'extract-runtime', 'artisan --version', 'github-delivery.py publish')
        ->not->toContain('bin/ci-check', 'build-release-zip', 'npm ci', 'composer install')
        ->and($workflow['on']['workflow_dispatch']['inputs']['sha']['required'])->toBeTrue();
    foreach ($workflow['jobs'] as $job) {
        expect($job['uses'] ?? '')->not->toContain('quality.yml');
        foreach ($job['steps'] ?? [] as $step) {
            expect($step['uses'] ?? '')->not->toContain('docker/build-push-action');
        }
    }
});

test('independent images build on native runners with matching read and write cache scopes', function (): void {
    $workflow = Yaml::parseFile(dirname(__DIR__, 2).'/.github/workflows/docker-publish.yml');
    $job = $workflow['jobs']['docker-publish'];
    $runners = [];
    foreach ($job['strategy']['matrix']['include'] as $entry) {
        $runners[$entry['arch']] = [$entry['platform'], $entry['runner']];
    }
    expect($runners)->toEqual([
        'amd64' => ['linux/amd64', 'ubuntu-24.04'],
        'arm64' => ['linux/arm64', 'ubuntu-24.04-arm'],
    ])->and($job['runs-on'])->toBe('${{ matrix.runner }}');
    foreach ($job['steps'] as $step) {
        expect($step['uses'] ?? '')->not->toContain('setup-qemu');
        if (str_starts_with($step['uses'] ?? '', 'docker/build-push-action@')) {
            expect($step['with']['cache-from'])->toContain('scope=production-${{ matrix.arch }}')
                ->and($step['with']['cache-to'])->toContain('scope=production-${{ matrix.arch }}');
        }
    }
});

test('images expose platform digests only after the runtime smoke and verify the candidate manifest before stable promotion', function (): void {
    $workflow = Yaml::parseFile(dirname(__DIR__, 2).'/.github/workflows/docker-publish.yml');
    $steps = $workflow['jobs']['docker-publish']['steps'];
    $buildIndex = $smokeIndex = $uploadIndex = null;
    foreach ($steps as $index => $step) {
        if (str_starts_with($step['uses'] ?? '', 'docker/build-push-action@')) {
            $buildIndex = $index;
            expect($step['with']['outputs'])->toContain('push-by-digest=true', 'name-canonical=true')
                ->and($step['with']['tags'] ?? null)->toBeNull();
        }
        if (($step['id'] ?? '') === 'runtime-smoke') {
            $smokeIndex = $index;
            expect(($step['run'] ?? '').json_encode($step['env'] ?? []))->toContain('steps.build.outputs.digest', 'php artisan --version');
        }
        if (str_starts_with($step['uses'] ?? '', 'actions/upload-artifact@')) {
            $uploadIndex = $index;
        }
    }
    expect($buildIndex)->not->toBeNull()->and($smokeIndex)->toBeGreaterThan($buildIndex)->and($uploadIndex)->toBeGreaterThan($smokeIndex);
    $merge = $workflow['jobs']['docker-merge'];
    expect((array) $merge['needs'])->toContain('docker-publish');
    $runs = implode("\n", array_column($merge['steps'], 'run'));
    expect($runs)->toContain('imagetools inspect', 'amd64', 'arm64');
    expect(strpos($runs, 'imagetools create "${tag_args[@]}"'))->toBeGreaterThan(strpos($runs, 'imagetools inspect'));
});

test('deployment verifies prepared local bytes and preserves its protected serialized boundary', function (): void {
    $workflow = Yaml::parseFile(dirname(__DIR__, 2).'/.github/workflows/deploy.yml');
    $deploy = $workflow['jobs']['deploy'];
    $source = json_encode($workflow, JSON_THROW_ON_ERROR);
    $runs = implode("\n", array_column($deploy['steps'], 'run'));
    expect($workflow['on']['workflow_dispatch']['inputs']['sha']['required'])->toBeTrue()
        ->and($workflow['on'])->not->toHaveKey('workflow_run')
        ->and($workflow['concurrency']['cancel-in-progress'])->toBeFalse()
        ->and($workflow['concurrency']['group'])->toBe('deploy-production')
        ->and($deploy['environment'])->toBe('production')
        ->and($deploy['if'])->toContain('DEPLOY_ENABLED', 'refs/heads/main')
        ->and($runs)->toContain('github-delivery.py stage', '--kind deploy', 'extract-runtime', 'StrictHostKeyChecking=yes', 'VPS_SSH_FINGERPRINT')
        ->and($source)->not->toContain('docker/build-push-action', 'composer install', 'npm ci', 'bin/ci-check');
});
