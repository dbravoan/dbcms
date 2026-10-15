<?php

declare(strict_types=1);

use App\Contracts\Services\SourceRevisionResolverInterface;
use App\Services\Projects\ProjectInitializationService;
use Illuminate\Filesystem\Filesystem;

beforeEach(function (): void {
    $this->root = sys_get_temp_dir().'/dbcms-profile-'.bin2hex(random_bytes(8));
    $this->files = new Filesystem;
    foreach (['themes/db-cyber', 'themes/db-minimal', 'resources/views/themes/admin/dbcms', 'resources/views/themes/admin/termocms', 'resources/views/components', '.github/workflows', 'resources/updates'] as $dir) {
        $this->files->makeDirectory($this->root.'/'.$dir, 0755, true);
    }
    foreach (['dbcms', 'termocms'] as $brand) {
        file_put_contents($this->root.'/resources/views/themes/admin/'.$brand.'/theme.json', '{}');
    }
    foreach (['db-cyber', 'db-minimal'] as $theme) {
        file_put_contents($this->root.'/themes/'.$theme.'/theme.json', '{}');
    }
    file_put_contents($this->root.'/resources/updates/core-version.json', '{"version":"1.2.0"}');
    file_put_contents($this->root.'/.env', 'SECRET=preserve');
    file_put_contents($this->root.'/.env.example', "CMS_THEME=db-cyber\nCMS_BRAND=dbcms\n");
    file_put_contents($this->root.'/.github/workflows/release.yml', 'product');
    $resolver = Mockery::mock(SourceRevisionResolverInterface::class);
    $resolver->shouldReceive('resolve')->with('1.2.0')->andReturn(str_repeat('a', 40));
    $this->service = new ProjectInitializationService($this->files, $resolver, $this->root);
});

afterEach(function (): void {
    $this->files->deleteDirectory($this->root);
});

it('validates every selection before deleting anything', function (string $brand, string $themes): void {
    expect(fn () => $this->service->initialize('mi-web', $brand, $themes, 'none'))->toThrow(InvalidArgumentException::class);
    expect(is_dir($this->root.'/themes/db-cyber'))->toBeTrue();
    expect(file_exists($this->root.'/cms-project.json'))->toBeFalse();
})->with([['unknown', 'db-minimal'], ['dbcms', 'db-minimal,unknown'], ['dbcms', ''], ['dbcms', 'all,db-minimal']]);

it('prunes excluded resources and preserves secrets and shared resources', function (): void {
    $profile = $this->service->initialize('mi-web', 'termocms', 'db-minimal', 'none');
    expect($profile->defaultTheme)->toBe('db-minimal');
    expect(is_dir($this->root.'/themes/db-cyber'))->toBeFalse();
    expect(is_dir($this->root.'/resources/views/themes/admin/dbcms'))->toBeFalse();
    expect(is_dir($this->root.'/resources/views/components'))->toBeTrue();
    expect(file_get_contents($this->root.'/.env'))->toBe('SECRET=preserve');
    expect(file_exists($this->root.'/.github/workflows/release.yml'))->toBeFalse();
    expect(json_decode(file_get_contents($this->root.'/cms-project.json'), true)['core']['sha'])->toBe(str_repeat('a', 40));
    expect(file_get_contents($this->root.'/.env.example'))->toContain('CMS_BRAND=termocms');
});

it('is idempotent and rejects changed selection', function (): void {
    $this->service->initialize('mi-web', 'dbcms', 'db-minimal', 'none');
    $before = file_get_contents($this->root.'/cms-project.json');
    $this->service->initialize('mi-web', 'dbcms', 'db-minimal', 'none');
    expect(file_get_contents($this->root.'/cms-project.json'))->toBe($before);
    expect(fn () => $this->service->initialize('mi-web', 'termocms', 'db-minimal', 'none'))->toThrow(InvalidArgumentException::class);
});

it('deduplicates CSV in order and snapshots all with cyber default', function (): void {
    $profile = $this->service->initialize('mi-web', 'dbcms', 'db-minimal,db-cyber,db-minimal', 'none');
    expect($profile->themes)->toBe(['db-minimal', 'db-cyber']);
    expect($profile->defaultTheme)->toBe('db-minimal');
});

it('uses cyber by default for all and permits repeated all initialization', function (): void {
    $profile = $this->service->initialize('mi-web', 'dbcms', 'all', 'none');
    expect($profile->defaultTheme)->toBe('db-cyber');
    expect($this->service->initialize('mi-web', 'dbcms', 'all', 'none')->themes)->toBe($profile->themes);
});

it('does not prune when official provenance is unavailable', function (): void {
    $resolver = Mockery::mock(SourceRevisionResolverInterface::class);
    $resolver->shouldReceive('resolve')->andThrow(new RuntimeException('tag unavailable'));
    $service = new ProjectInitializationService($this->files, $resolver, $this->root);
    expect(fn () => $service->initialize('mi-web', 'dbcms', 'db-minimal', 'none'))->toThrow(RuntimeException::class);
    expect(is_dir($this->root.'/themes/db-cyber'))->toBeTrue();
    expect(is_dir($this->root.'/resources/views/themes/admin/termocms'))->toBeTrue();
    expect(file_exists($this->root.'/cms-project.json'))->toBeFalse();
});

it('rejects invalid slugs before modifying the project', function (): void {
    expect(fn () => $this->service->initialize('../escape', 'dbcms', 'all', 'none'))->toThrow(InvalidArgumentException::class);
    expect(is_dir($this->root.'/themes/db-minimal'))->toBeTrue();
});

it('requires the selected administrative manifest before pruning', function (): void {
    unlink($this->root.'/resources/views/themes/admin/termocms/theme.json');
    expect(fn () => $this->service->initialize('mi-web', 'termocms', 'db-minimal', 'none'))->toThrow(InvalidArgumentException::class);
    expect(is_dir($this->root.'/themes/db-cyber'))->toBeTrue();
    expect(is_dir($this->root.'/resources/views/themes/admin/dbcms'))->toBeTrue();
});

it('rejects symbolic links before pruning or rewriting metadata', function (string $relative): void {
    $target = $this->root.'/protected-target';
    if (is_dir($this->root.'/'.$relative)) {
        $this->files->copyDirectory($this->root.'/'.$relative, $target);
        $this->files->deleteDirectory($this->root.'/'.$relative);
    } else {
        file_put_contents($target, 'preserve');
        if (file_exists($this->root.'/'.$relative)) {
            $this->files->delete($this->root.'/'.$relative);
        }
    }
    symlink($target, $this->root.'/'.$relative);
    expect(fn () => $this->service->initialize('mi-web', 'termocms', 'db-minimal', 'none'))->toThrow(InvalidArgumentException::class);
    expect(is_dir($this->root.'/themes/db-cyber'))->toBeTrue();
    if (is_file($target)) {
        expect(file_get_contents($target))->toBe('preserve');
    }
})->with(['cms-project.json', '.env.example', 'resources/views/themes/admin/termocms', 'resources/views/themes/admin/dbcms', 'resources/views/themes/admin', 'resources/views/themes/admin/termocms/theme.json']);

it('rejects a symlinked project root before writing environment examples', function (): void {
    $alias = $this->root.'-alias';
    symlink($this->root, $alias);
    try {
        $resolver = Mockery::mock(SourceRevisionResolverInterface::class);
        $service = new ProjectInitializationService($this->files, $resolver, $alias);
        expect(fn () => $service->initialize('mi-web', 'termocms', 'db-minimal', 'none'))->toThrow(InvalidArgumentException::class);
        expect(file_get_contents($this->root.'/.env.example'))->toContain('CMS_BRAND=dbcms');
        expect(is_dir($this->root.'/themes/db-cyber'))->toBeTrue();
    } finally {
        unlink($alias);
    }
});

it('records the deployment selection and refuses to change an established deployment', function (): void {
    $profile = $this->service->initialize('mi-web', 'dbcms', 'db-minimal', 'rsync');
    expect($profile->toArray()['deployment'])->toBe('rsync');
    $before = file_get_contents($this->root.'/cms-project.json');
    expect(fn () => $this->service->initialize('mi-web', 'dbcms', 'db-minimal', 'docker'))->toThrow(InvalidArgumentException::class);
    expect(file_get_contents($this->root.'/cms-project.json'))->toBe($before);
});

it('requires a valid deployment before removing any project resources', function (string $deployment): void {
    expect(fn () => $this->service->initialize('mi-web', 'dbcms', 'db-minimal', $deployment))->toThrow(InvalidArgumentException::class);
    expect(is_dir($this->root.'/themes/db-cyber'))->toBeTrue();
    expect(file_exists($this->root.'/.github/workflows/release.yml'))->toBeTrue();
    expect(file_exists($this->root.'/cms-project.json'))->toBeFalse();
})->with(['', 'ssh', 'all', 'rsync,docker']);

it('checks deployment paths and parents for symlinks before removing themes', function (): void {
    mkdir($this->root.'/bin');
    file_put_contents($this->root.'/protected-control', 'preserve');
    symlink($this->root.'/protected-control', $this->root.'/bin/deploy-container.sh');
    expect(fn () => $this->service->initialize('mi-web', 'dbcms', 'db-minimal', 'none'))->toThrow(InvalidArgumentException::class);
    expect(is_dir($this->root.'/themes/db-cyber'))->toBeTrue();
    expect(file_get_contents($this->root.'/protected-control'))->toBe('preserve');
});
