<?php

declare(strict_types=1);

use App\Core\Updates\ProjectUpdatePlanner;

function updateProjectProfile(): array
{
    return ['schema' => 1, 'name' => 'sample', 'brand' => 'termocms', 'themes' => ['db-minimal'], 'default_theme' => 'db-minimal', 'core' => ['repository' => 'dbravoan/dbcms', 'version' => '1.2.0', 'sha' => str_repeat('a', 40)]];
}

test('project update compares upstream base local and target without overwriting customizations', function (): void {
    $base = ['app/clean.php' => 'old', 'app/custom.php' => 'base', 'app/conflict.php' => 'base', 'app/deleted.php' => 'delete'];
    $local = [...$base, 'app/custom.php' => 'project', 'app/conflict.php' => 'project', 'modules/Own/custom.php' => 'mine'];
    $target = ['app/clean.php' => 'new', 'app/custom.php' => 'base', 'app/conflict.php' => 'upstream', 'app/added.php' => 'added'];
    $plan = (new ProjectUpdatePlanner)->compare($base, $local, $target, updateProjectProfile());

    expect(array_column($plan->changes, 'path'))->toBe(['app/added.php', 'app/clean.php', 'app/deleted.php'])
        ->and($plan->conflicts)->toBe([['path' => 'app/conflict.php', 'reason' => 'locally_modified']])
        ->and($plan->preserved)->toContain('app/custom.php');
});

test('project update never restores excluded themes brands or product publishing', function (): void {
    $target = ['themes/db-cyber/theme.css' => 'css', 'themes/db-minimal/theme.css' => 'css', 'resources/views/themes/admin/dbcms/theme.json' => '{}', 'resources/views/themes/admin/termocms/theme.json' => '{}', '.github/workflows/release.yml' => 'publish', 'resources/views/themes/components/shared.blade.php' => 'shared'];
    $plan = (new ProjectUpdatePlanner)->compare([], [], $target, updateProjectProfile());

    expect(array_column($plan->changes, 'path'))->toBe(['resources/views/themes/admin/termocms/theme.json', 'resources/views/themes/components/shared.blade.php', 'themes/db-minimal/theme.css'])
        ->and($plan->excluded)->toContain('themes/db-cyber/theme.css', '.github/workflows/release.yml');
});

test('project update detects added local collisions and modified deletions', function (): void {
    $plan = (new ProjectUpdatePlanner)->compare(['app/deleted.php' => 'base'], ['app/deleted.php' => 'mine', 'app/added.php' => 'own'], ['app/added.php' => 'upstream'], updateProjectProfile());

    expect($plan->changes)->toBeEmpty()
        ->and(array_column($plan->conflicts, 'reason'))->toBe(['local_collision', 'locally_modified']);
});

test('identical local and upstream edits need no replacement', function (): void {
    $plan = (new ProjectUpdatePlanner)->compare(['app/a.php' => 'base'], ['app/a.php' => 'same'], ['app/a.php' => 'same'], updateProjectProfile());

    expect($plan->changes)->toBeEmpty()->and($plan->conflicts)->toBeEmpty();
});

test('project update rejects private traversal and symlink source paths', function (string $path): void {
    expect(fn () => (new ProjectUpdatePlanner)->compare([], [], [$path => 'x'], updateProjectProfile()))->toThrow(DomainException::class);
})->with(['../.env', '.env', 'storage/app/installed', 'app/../../public/uploads/payload.php']);

test('deployment projection keeps only selected official controls and preserves project owned scripts', function (string $deployment, array $kept): void {
    $paths = ['.github/workflows/ci.yml', '.github/workflows/quality.yml', '.github/workflows/update-dbcms.yml', '.github/workflows/deploy.yml', '.github/workflows/deploy-docker.yml', '.github/workflows/deploy-ansistrano.yml', '.github/workflows/prepare-project.yml', 'bin/deploy-container.sh', 'bin/deploy-docker', 'bin/deploy-vps-release.sh', 'bin/vps-artisan.sh', 'bin/vps-install.sh', 'bin/vps-preflight.sh', 'bin/vps-bootstrap.sh', 'bin/deploy-rsync', 'bin/deploy-ansistrano', 'bin/deploy-local-common.sh', 'docker-compose.prod.yml', 'deploy/ansistrano/deploy.yml'];
    $target = array_fill_keys($paths, 'new');
    $target += ['bin/deploy-own.sh' => 'own', 'docker/production/Dockerfile' => 'build', 'docker/ci/compose.yml' => 'ci'];
    $profile = [...updateProjectProfile(), 'deployment' => $deployment];
    $plan = (new ProjectUpdatePlanner)->compare([], [], $target, $profile);
    $expected = [...$kept, 'bin/deploy-own.sh', 'docker/production/Dockerfile', 'docker/ci/compose.yml'];
    sort($expected);
    expect(array_column($plan->changes, 'path'))->toBe($expected);
})->with([
    'github-actions' => ['github-actions', ['.github/workflows/ci.yml', '.github/workflows/quality.yml', '.github/workflows/update-dbcms.yml', '.github/workflows/deploy.yml', 'bin/deploy-vps-release.sh', 'bin/vps-artisan.sh', 'bin/vps-install.sh', 'bin/vps-preflight.sh', 'bin/vps-bootstrap.sh']],
    'rsync' => ['rsync', ['bin/deploy-vps-release.sh', 'bin/vps-artisan.sh', 'bin/vps-install.sh', 'bin/vps-preflight.sh', 'bin/vps-bootstrap.sh', 'bin/deploy-rsync', 'bin/deploy-local-common.sh']],
    'ansistrano' => ['ansistrano', ['bin/deploy-vps-release.sh', 'bin/vps-artisan.sh', 'bin/vps-install.sh', 'bin/vps-preflight.sh', 'bin/vps-bootstrap.sh', 'bin/deploy-ansistrano', 'bin/deploy-local-common.sh', 'deploy/ansistrano/deploy.yml']],
    'docker' => ['docker', ['bin/deploy-local-common.sh', 'bin/deploy-container.sh', 'bin/deploy-docker', 'bin/vps-bootstrap.sh', 'docker-compose.prod.yml']],
    'none' => ['none', []],
]);

test('legacy deployment projection retains alternatives without guessing a driver', function (): void {
    $target = ['.github/workflows/deploy.yml' => 'rsync', '.github/workflows/deploy-docker.yml' => 'docker', '.github/workflows/deploy-ansistrano.yml' => 'ansistrano', '.github/workflows/prepare-project.yml' => 'bootstrap'];
    $plan = (new ProjectUpdatePlanner)->compare([], [], $target, updateProjectProfile());
    expect(array_column($plan->changes, 'path'))->toBe(['.github/workflows/deploy-ansistrano.yml', '.github/workflows/deploy-docker.yml', '.github/workflows/deploy.yml']);
});

test('excluded deployment controls already customized locally are untouched during core updates', function (): void {
    $plan = (new ProjectUpdatePlanner)->compare(['bin/deploy-container.sh' => 'base'], ['bin/deploy-container.sh' => 'own'], ['bin/deploy-container.sh' => 'upstream'], [...updateProjectProfile(), 'deployment' => 'rsync']);
    expect($plan->changes)->toBeEmpty()->and($plan->conflicts)->toBeEmpty()->and($plan->excluded)->toContain('bin/deploy-container.sh');
});

test('local deployment excludes future upstream github controls without deleting project files', function (): void {
    $plan = (new ProjectUpdatePlanner)->compare([], ['.github/workflows/own.yml' => 'custom'], ['.github/dependabot.yml' => 'upstream', '.github/workflows/own.yml' => 'upstream'], [...updateProjectProfile(), 'deployment' => 'rsync']);
    expect($plan->changes)->toBeEmpty()->and($plan->conflicts)->toBeEmpty()->and($plan->excluded)->toContain('.github/dependabot.yml', '.github/workflows/own.yml');
});
