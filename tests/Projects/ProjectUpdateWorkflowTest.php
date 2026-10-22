<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

test('Git source updates remain review proposals and never publish project archives', function (): void {
    $root = dirname(__DIR__, 2);
    $manifest = $root.'/cms-project.json';
    if (is_file($manifest)) {
        $profile = json_decode((string) file_get_contents($manifest), true, 512, JSON_THROW_ON_ERROR);
        if (isset($profile['deployment']) && $profile['deployment'] !== 'github-actions') {
            $this->markTestSkipped('This local deployment profile intentionally excludes GitHub workflows.');
        }
    }
    $source = (string) file_get_contents($root.'/.github/workflows/update-dbcms.yml');
    $workflow = Yaml::parse($source);
    $job = $workflow['jobs']['propose'];
    $checkout = array_values(array_filter($job['steps'], fn (array $step): bool => str_starts_with($step['uses'] ?? '', 'actions/checkout@')))[0];

    expect($job['if'])->toContain("github.repository != 'dbravoan/dbcms'")
        ->and($checkout['with']['ref'])->toBe('main')
        ->and($checkout['with']['persist-credentials'])->toBeFalse()
        ->and($source)->toContain('cms:project:update "$1"', 'composer update --with-all-dependencies --no-scripts', 'conflicts=true', 'gh pr create --draft', 'bin/prepare-delivery')
        ->not->toContain('build-release-zip', 'actions/upload-artifact', 'git push origin main', 'gh pr merge');
});

test('project mutation workflows defer Git credential setup until source preparation finishes', function (string $file): void {
    $root = dirname(__DIR__, 2);
    if ($file === 'prepare-project.yml' && is_file($root.'/cms-project.json')) {
        expect(is_file($root.'/.github/workflows/'.$file))->toBeFalse();

        return;
    }
    if (! is_file($root.'/.github/workflows/'.$file) && is_file($root.'/cms-project.json')) {
        $profile = json_decode((string) file_get_contents($root.'/cms-project.json'), true, 512, JSON_THROW_ON_ERROR);
        expect($profile['deployment'] ?? null)->not->toBe('github-actions');
        $this->markTestSkipped('The selected local profile intentionally excludes GitHub workflows.');
    }
    $workflow = Yaml::parseFile($root.'/.github/workflows/'.$file);
    $job = array_values($workflow['jobs'])[0];
    $credentialStep = $validationStep = null;
    foreach ($job['steps'] as $index => $step) {
        $script = $step['run'] ?? '';
        if (str_contains($script, './bin/project-init') || str_contains($script, 'cms:project:update')) {
            $validationStep = $index;
        }
        if (str_contains($script, 'gh auth setup-git')) {
            $credentialStep = $index;
        }
    }
    expect($job['env']['GH_TOKEN'])->toBe('${{ secrets.PROJECT_BOT_TOKEN }}')
        ->and($validationStep)->not->toBeNull()
        ->and($credentialStep)->toBeGreaterThan($validationStep);
})->with(['prepare-project.yml', 'update-dbcms.yml']);

test('project preparation requires a reviewed deployment selection', function (): void {
    $root = dirname(__DIR__, 2);
    if (is_file($root.'/cms-project.json')) {
        expect(is_file($root.'/.github/workflows/prepare-project.yml'))->toBeFalse();

        return;
    }
    $source = (string) file_get_contents($root.'/.github/workflows/prepare-project.yml');
    $workflow = Yaml::parse($source);
    $input = $workflow['on']['workflow_dispatch']['inputs']['deployment'];
    expect($input['required'])->toBeTrue()
        ->and($input['options'])->toBe(['github-actions', 'ansistrano', 'rsync', 'docker', 'none'])
        ->and($source)->toContain('--deploy="$PROJECT_DEPLOYMENT"', 'PROJECT_DEPLOYMENT: ${{ inputs.deployment }}');

    $preparation = array_values(array_filter(
        $workflow['jobs']['prepare']['steps'],
        static fn (array $step): bool => str_contains($step['run'] ?? '', './bin/project-init '),
    ));
    expect($preparation)->toHaveCount(1)
        ->and($preparation[0]['run'])->toContain('--prepare-only', '--no-interaction')
        ->not->toContain('vendor/bin/sail');
});
