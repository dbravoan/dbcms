<?php

declare(strict_types=1);

use App\Contracts\Services\ProjectSourceRepositoryInterface;
use App\Contracts\Services\SourceRevisionResolverInterface;
use App\Services\Projects\ProjectInitializationService;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

it('physically retains only the selected deployment for either administrative brand', function (string $brand, string $deployment): void {
    $files = new Filesystem;
    $source = dirname(__DIR__, 2);
    $root = sys_get_temp_dir().'/dbcms-deployment-selection-'.bin2hex(random_bytes(8));
    $files->ensureDirectoryExists($root);
    try {
        foreach (['.github', 'bin', 'docker', 'deploy'] as $directory) {
            if (is_dir($source.'/'.$directory)) {
                $files->copyDirectory($source.'/'.$directory, $root.'/'.$directory);
            }
        }
        if (is_file($source.'/docker-compose.prod.yml')) {
            $files->copy($source.'/docker-compose.prod.yml', $root.'/docker-compose.prod.yml');
        }
        foreach (['dbcms', 'termocms'] as $identifier) {
            $files->ensureDirectoryExists($root.'/resources/views/themes/admin/'.$identifier);
            $files->put($root.'/resources/views/themes/admin/'.$identifier.'/theme.json', json_encode(['identifier' => $identifier], JSON_THROW_ON_ERROR));
        }
        foreach (['db-cyber', 'db-minimal'] as $theme) {
            $files->ensureDirectoryExists($root.'/themes/'.$theme);
            $files->put($root.'/themes/'.$theme.'/theme.json', json_encode(['identifier' => $theme], JSON_THROW_ON_ERROR));
        }
        $files->ensureDirectoryExists($root.'/resources/updates');
        $files->put($root.'/resources/updates/core-version.json', '{"version":"1.3.0"}');
        $files->put($root.'/.env.example', "CMS_BRAND=dbcms\nCMS_THEME=db-cyber\n");
        $resolver = Mockery::mock(SourceRevisionResolverInterface::class);
        $resolver->shouldReceive('resolve')->andReturn(str_repeat('a', 40));
        $baseline = [];
        foreach ($files->allFiles($root, true) as $file) {
            $baseline[substr($file->getPathname(), strlen($root) + 1)] = $file->getContents();
        }
        $sources = Mockery::mock(ProjectSourceRepositoryInterface::class);
        $sources->shouldReceive('fetch')->andReturnNull();
        $sources->shouldReceive('read')->andReturnUsing(fn (string $directory, string $sha, string $path): string => $baseline[$path] ?? throw new DomainException('No official source: '.$path));
        $service = new ProjectInitializationService($files, $resolver, $root, $sources);
        $profile = $service->initialize('profile-matrix', $brand, 'db-minimal', $deployment);
        expect($profile->toArray()['deployment'])->toBe($deployment);
        expect(is_dir($root.'/themes/db-minimal'))->toBeTrue();
        expect(is_dir($root.'/themes/db-cyber'))->toBeFalse();
        expect(array_map('basename', $files->directories($root.'/resources/views/themes/admin')))->toBe([$brand]);
        foreach (['release.yml', 'docker-publish.yml', 'prepare-project.yml'] as $workflow) {
            expect(is_file($root.'/.github/workflows/'.$workflow))->toBeFalse();
        }
        expect(is_dir($root.'/.github'))->toBe($deployment === 'github-actions');
        expect(is_file($root.'/.github/workflows/deploy.yml'))->toBe($deployment === 'github-actions');
        expect(is_file($root.'/.github/workflows/update-dbcms.yml'))->toBe($deployment === 'github-actions');
        expect(is_file($root.'/bin/deploy-rsync'))->toBe($deployment === 'rsync');
        expect(is_dir($root.'/deploy/ansistrano'))->toBe($deployment === 'ansistrano');
        expect(is_file($root.'/bin/deploy-ansistrano'))->toBe($deployment === 'ansistrano');
        expect(is_file($root.'/bin/deploy-docker'))->toBe($deployment === 'docker');
        expect(is_file($root.'/bin/deploy-container.sh'))->toBe($deployment === 'docker');
        expect(is_file($root.'/docker-compose.prod.yml'))->toBe($deployment === 'docker');
        expect(is_file($root.'/bin/deploy-local-common.sh'))->toBe(in_array($deployment, ['rsync', 'ansistrano', 'docker'], true));
        expect(is_file($root.'/bin/deploy-vps-release.sh'))->toBe(in_array($deployment, ['github-actions', 'rsync', 'ansistrano'], true));
        expect(is_file($root.'/bin/vps-bootstrap.sh'))->toBe($deployment !== 'none');
        expect(is_file($root.'/docker/production/Dockerfile'))->toBeTrue();
        expect(is_file($root.'/docker/ci/compose.yml'))->toBeTrue();
        expect(is_dir($root.'/.git'))->toBeFalse();
        $before = $files->get($root.'/cms-project.json');
        $service->initialize('profile-matrix', $brand, 'db-minimal', $deployment);
        expect($files->get($root.'/cms-project.json'))->toBe($before);
    } finally {
        $files->deleteDirectory($root);
    }
})->with([
    ['dbcms', 'github-actions'], ['dbcms', 'rsync'], ['dbcms', 'ansistrano'], ['dbcms', 'docker'], ['dbcms', 'none'],
    ['termocms', 'github-actions'], ['termocms', 'rsync'], ['termocms', 'ansistrano'], ['termocms', 'docker'], ['termocms', 'none'],
])->group('source-catalogue');

it('keeps the selected deployment available in the physically initialized repository', function (): void {
    $root = dirname(__DIR__, 2);
    if (! is_file($root.'/cms-project.json')) {
        $this->markTestSkipped('The source catalogue is covered by the ten-case projection matrix.');
    }
    $profile = json_decode((string) file_get_contents($root.'/cms-project.json'), true, 512, JSON_THROW_ON_ERROR);
    expect($profile)->toHaveKey('deployment');
    $deployment = $profile['deployment'];
    expect($deployment)->toBeIn(['github-actions', 'ansistrano', 'rsync', 'docker', 'none']);
    foreach (['deploy.yml', 'update-dbcms.yml', 'ci.yml'] as $workflow) {
        expect(is_file($root.'/.github/workflows/'.$workflow))->toBe($deployment === 'github-actions');
    }
    expect(is_file($root.'/.github/workflows/prepare-project.yml'))->toBeFalse();
    expect(is_file($root.'/.github/workflows/release.yml'))->toBeFalse();
    expect(is_file($root.'/.github/workflows/docker-publish.yml'))->toBeFalse();
    expect(is_file($root.'/docker/production/Dockerfile'))->toBeTrue();
    expect(is_file($root.'/docker/ci/compose.yml'))->toBeTrue();
});

it('runs the local Docker quality gate without mounting or creating a Git repository', function (): void {
    $compose = Yaml::parseFile(dirname(__DIR__, 2).'/docker/ci/compose.yml');
    foreach ($compose['services']['app']['volumes'] ?? [] as $mount) {
        expect($mount)->not->toContain('.git');
    }
    expect($compose['services']['app']['build']['dockerfile'])->toBe('docker/production/Dockerfile');
});
