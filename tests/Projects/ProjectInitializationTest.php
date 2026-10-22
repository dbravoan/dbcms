<?php

declare(strict_types=1);

use App\Contracts\Services\ProjectSourceRepositoryInterface;
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
        file_put_contents($this->root.'/resources/views/themes/admin/'.$brand.'/theme.json', json_encode(['identifier' => $brand], JSON_THROW_ON_ERROR));
    }
    foreach (['db-cyber', 'db-minimal'] as $theme) {
        file_put_contents($this->root.'/themes/'.$theme.'/theme.json', json_encode(['identifier' => $theme], JSON_THROW_ON_ERROR));
    }
    file_put_contents($this->root.'/resources/updates/core-version.json', '{"version":"1.2.0"}');
    file_put_contents($this->root.'/.env', 'SECRET=preserve');
    file_put_contents($this->root.'/.env.example', "CMS_THEME=db-cyber\nCMS_BRAND=dbcms\n");
    file_put_contents($this->root.'/.github/workflows/release.yml', 'product');
    $resolver = Mockery::mock(SourceRevisionResolverInterface::class);
    $resolver->shouldReceive('resolve')->with('1.2.0')->andReturn(str_repeat('a', 40));
    $this->officialBytes = [];
    foreach ($this->files->allFiles($this->root, true) as $file) {
        $this->officialBytes[substr($file->getPathname(), strlen($this->root) + 1)] = $file->getContents();
    }
    $this->sources = Mockery::mock(ProjectSourceRepositoryInterface::class);
    $this->sources->shouldReceive('fetch')->andReturnNull();
    $this->sources->shouldReceive('read')->andReturnUsing(fn (string $directory, string $sha, string $path): string => $this->officialBytes[$path] ?? throw new DomainException('No official source: '.$path));
    $this->service = new ProjectInitializationService($this->files, $resolver, $this->root, $this->sources);
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

/** @param array<string,string> $requires */
function initializationModule(object $test, string $directory, string $id, array $requires = [], string $version = '1.0.0'): void
{
    $path = 'modules/'.$directory.'/module.json';
    $test->files->ensureDirectoryExists(dirname($test->root.'/'.$path));
    $bytes = json_encode(['name' => $directory, 'identifier' => $id, 'version' => $version, 'provider' => 'Modules\\'.$directory.'\\'.$directory.'ModuleServiceProvider', 'requires' => $requires], JSON_THROW_ON_ERROR);
    $test->files->put($test->root.'/'.$path, $bytes);
    $test->officialBytes[$path] = $bytes;
}

/** @param array<string,string> $requires */
function initializationPackage(object $test, string $directory, string $id, array $requires = []): void
{
    $path = 'block-packages/'.$directory.'/block-package.json';
    $test->files->ensureDirectoryExists(dirname($test->root.'/'.$path));
    $bytes = json_encode(['name' => $directory, 'identifier' => $id, 'version' => '1.0.0', 'blocks' => ['BlockPackages\\'.$directory.'\\Blocks\\ExampleBlock'], 'requires' => $requires], JSON_THROW_ON_ERROR);
    $test->files->put($test->root.'/'.$path, $bytes);
    $test->officialBytes[$path] = $bytes;
}

it('freezes manifest IDs and transitive dependencies before pruning while preserving own files', function (): void {
    initializationModule($this, 'Foundation', 'foundation', ['core' => '^1.0']);
    initializationModule($this, 'Editorial', 'editorial', ['foundation' => '^1.0']);
    initializationModule($this, 'Commerce', 'commerce');
    initializationPackage($this, 'VisualCards', 'visual_cards', ['editorial' => '^1.0']);
    initializationPackage($this, 'Unselected', 'unselected');
    $this->files->ensureDirectoryExists($this->root.'/database/seeders');
    $this->files->put($this->root.'/database/seeders/PlatformShowcaseSeeder.php', '<?php // official showcase');
    $this->officialBytes['database/seeders/PlatformShowcaseSeeder.php'] = '<?php // official showcase';
    $this->files->ensureDirectoryExists($this->root.'/own');
    $this->files->put($this->root.'/own/content.txt', 'preserve own content');
    $shown = false;
    $profile = $this->service->initialize('my-project', 'dbcms', 'db-minimal', 'none', 'none', 'visual_cards', function ($profile, array $ids) use (&$shown): void {
        expect($ids)->toBe(['modules' => ['editorial', 'foundation'], 'block_packages' => ['visual_cards']]);
        expect($profile->sourceSelection)->toBe(['module_directories' => ['Editorial', 'Foundation'], 'block_package_directories' => ['VisualCards'], 'include_showcase' => false]);
        expect(is_dir($this->root.'/modules/Commerce'))->toBeTrue();
        expect(is_file($this->root.'/database/seeders/PlatformShowcaseSeeder.php'))->toBeTrue();
        expect(is_file($this->root.'/cms-project.json'))->toBeFalse();
        $shown = true;
    });
    expect($shown)->toBeTrue();
    expect(is_dir($this->root.'/modules/Commerce'))->toBeFalse();
    expect(is_dir($this->root.'/block-packages/Unselected'))->toBeFalse();
    expect(is_dir($this->root.'/modules/Foundation'))->toBeTrue();
    expect(is_file($this->root.'/database/seeders/PlatformShowcaseSeeder.php'))->toBeFalse();
    expect($this->files->get($this->root.'/own/content.txt'))->toBe('preserve own content');
    expect(json_decode($this->files->get($this->root.'/cms-project.json'), true)['source_selection'])->toBe($profile->sourceSelection);
    $this->service->initialize('my-project', 'dbcms', 'db-minimal', 'none', 'all', 'all');
    expect(fn () => $this->service->initialize('my-project', 'dbcms', 'db-minimal', 'none', 'foundation', 'none'))->toThrow(InvalidArgumentException::class);
});

it('permits a core-only project and snapshots all by default', function (string $modules, string $packages, array $expectedModules, array $expectedPackages): void {
    initializationModule($this, 'BlogDirectory', 'blog');
    initializationPackage($this, 'BasicDirectory', 'basic');
    $profile = $this->service->initialize('core-project', 'dbcms', 'all', 'none', $modules, $packages);
    expect($profile->sourceSelection)->toBe(['module_directories' => $expectedModules, 'block_package_directories' => $expectedPackages, 'include_showcase' => false]);
})->with([
    ['none', 'none', [], []],
    ['all', 'all', ['BlogDirectory'], ['BasicDirectory']],
]);

it('rejects unknown mixed or malformed source selections without mutation', function (string $modules, string $packages): void {
    initializationModule($this, 'Blog', 'blog');
    initializationPackage($this, 'Basic', 'basic');
    expect(fn () => $this->service->initialize('my-project', 'dbcms', 'db-minimal', 'none', $modules, $packages))->toThrow(InvalidArgumentException::class);
    expect(is_dir($this->root.'/themes/db-cyber'))->toBeTrue();
    expect(is_dir($this->root.'/modules/Blog'))->toBeTrue();
    expect(is_file($this->root.'/cms-project.json'))->toBeFalse();
})->with([['Blog', 'all'], ['unknown', 'none'], ['all,blog', 'none'], ['none,blog', 'none'], ['blog,', 'none'], ['', 'none'], ['none', 'Basic'], ['none', 'none,basic']]);

it('rejects missing cyclic or incompatible dependencies before modifying source', function (string $failure): void {
    initializationModule($this, 'Blog', 'blog', ['shared' => '^1.0']);
    if ($failure !== 'missing') {
        initializationModule($this, 'Shared', 'shared', $failure === 'cycle' ? ['blog' => '^1.0'] : [], $failure === 'version' ? '2.0.0' : '1.0.0');
    }
    expect(fn () => $this->service->initialize('my-project', 'dbcms', 'db-minimal', 'none', 'blog', 'none'))->toThrow(DomainException::class);
    expect(is_dir($this->root.'/themes/db-cyber'))->toBeTrue();
    expect(is_file($this->root.'/cms-project.json'))->toBeFalse();
})->with(['missing', 'cycle', 'version']);

it('rejects malformed package manifests without a Laravel application or writes', function (): void {
    initializationPackage($this, 'Basic', 'basic');
    $this->files->put($this->root.'/block-packages/Basic/block-package.json', '{"identifier":"basic","version":"1.0.0","name":"Bad","blocks":[],"requires":{}}');
    expect(fn () => $this->service->initialize('my-project', 'dbcms', 'db-minimal', 'none', 'none', 'basic'))->toThrow(DomainException::class);
    expect(is_dir($this->root.'/themes/db-cyber'))->toBeTrue();
});

it('rejects malformed duplicate and incompatible package requirements', function (string $failure): void {
    initializationModule($this, 'Blog', 'blog');
    initializationPackage($this, 'Basic', 'basic', $failure === 'invalid' ? ['blog' => 'not-semver'] : ['blog' => '^2.0']);
    if ($failure === 'duplicate') {
        initializationPackage($this, 'Other', 'basic');
    }
    expect(fn () => $this->service->initialize('my-project', 'dbcms', 'db-minimal', 'none', 'none', 'basic'))->toThrow(DomainException::class);
    expect(is_dir($this->root.'/themes/db-cyber'))->toBeTrue();
})->with(['invalid', 'version', 'duplicate']);

it('refuses to delete customized or unknown excluded resources', function (string $path, bool $official): void {
    $this->files->ensureDirectoryExists(dirname($this->root.'/'.$path));
    if ($official && ! isset($this->officialBytes[$path])) {
        $this->officialBytes[$path] = 'original source';
    }
    $changed = str_ends_with($path, 'theme.json') ? json_encode(['identifier' => basename(dirname($path)), 'name' => 'my local changes'], JSON_THROW_ON_ERROR) : 'my local changes';
    $this->files->put($this->root.'/'.$path, $changed);
    expect(fn () => $this->service->initialize('my-project', 'dbcms', 'db-minimal', 'none'))->toThrow($official ? RuntimeException::class : DomainException::class);
    expect($this->files->get($this->root.'/'.$path))->toBe($changed);
    expect(is_dir($this->root.'/themes/db-cyber'))->toBeTrue();
    expect(is_file($this->root.'/cms-project.json'))->toBeFalse();
})->with([
    ['themes/db-cyber/theme.json', true],
    ['themes/db-cyber/my-own-file.txt', false],
    ['.github/workflows/release.yml', true],
    ['database/seeders/PlatformShowcaseSeeder.php', true],
]);

it('rejects extension source symlinks before pruning', function (string $relative): void {
    initializationModule($this, 'Blog', 'blog');
    initializationPackage($this, 'Basic', 'basic');
    $path = $this->root.'/'.$relative;
    $target = $this->root.'/protected';
    if (is_dir($path)) {
        $this->files->copyDirectory($path, $target);
        $this->files->deleteDirectory($path);
    } else {
        $this->files->put($target, $this->files->get($path));
        unlink($path);
    }
    symlink($target, $path);
    expect(fn () => $this->service->initialize('my-project', 'dbcms', 'all', 'none'))->toThrow(InvalidArgumentException::class);
    expect(is_dir($this->root.'/themes/db-cyber'))->toBeTrue();
})->with(['modules', 'modules/Blog/module.json', 'block-packages/Basic', 'block-packages/Basic/block-package.json']);

it('accepts only normalized alpha-leading project slugs', function (string $name): void {
    expect(fn () => $this->service->initialize($name, 'dbcms', 'all', 'none'))->toThrow(InvalidArgumentException::class);
    expect(is_file($this->root.'/cms-project.json'))->toBeFalse();
})->with(['1project', 'project--name', 'project-', '-project', 'My-project']);

it('preserves an existing explicit showcase selection and its project content on repeat', function (): void {
    $this->service->initialize('existing-project', 'dbcms', 'all', 'none');
    $this->files->ensureDirectoryExists($this->root.'/database/seeders');
    $this->files->put($this->root.'/database/seeders/PlatformShowcaseSeeder.php', '<?php // preserved project showcase');
    $profile = json_decode($this->files->get($this->root.'/cms-project.json'), true, flags: JSON_THROW_ON_ERROR);
    $profile['source_selection']['include_showcase'] = true;
    $before = json_encode($profile, JSON_THROW_ON_ERROR);
    $this->files->put($this->root.'/cms-project.json', $before);

    $result = $this->service->initialize('existing-project', 'dbcms', 'all', 'none');

    expect($result->sourceSelection['include_showcase'])->toBeTrue();
    expect($this->files->get($this->root.'/cms-project.json'))->toBe($before);
    expect($this->files->get($this->root.'/database/seeders/PlatformShowcaseSeeder.php'))->toBe('<?php // preserved project showcase');
});

it('rejects an explicit null source selection instead of treating it as an absent declaration', function (): void {
    $this->service->initialize('existing-project', 'dbcms', 'all', 'none');
    $profile = json_decode($this->files->get($this->root.'/cms-project.json'), true, flags: JSON_THROW_ON_ERROR);
    $profile['source_selection'] = null;
    $before = json_encode($profile, JSON_THROW_ON_ERROR);
    $this->files->put($this->root.'/cms-project.json', $before);

    expect(fn () => $this->service->initialize('existing-project', 'dbcms', 'all', 'none'))->toThrow(InvalidArgumentException::class);
    expect($this->files->get($this->root.'/cms-project.json'))->toBe($before);
});

it('rejects extension directories without manifests before source or profile mutation', function (string $directory): void {
    $this->files->ensureDirectoryExists($this->root.'/'.$directory.'/Uncatalogued');
    $this->files->put($this->root.'/'.$directory.'/Uncatalogued/own.txt', 'preserve own content');
    expect(fn () => $this->service->initialize('my-project', 'dbcms', 'db-minimal', 'none'))->toThrow(DomainException::class, 'no regular manifest');
    expect($this->files->get($this->root.'/'.$directory.'/Uncatalogued/own.txt'))->toBe('preserve own content');
    expect(is_dir($this->root.'/themes/db-cyber'))->toBeTrue();
    expect(is_file($this->root.'/cms-project.json'))->toBeFalse();
})->with(['modules', 'block-packages']);

it('rejects missing or mismatched theme identities before any project writes', function (string $path, array $manifest): void {
    $this->files->put($this->root.'/'.$path, json_encode((object) $manifest, JSON_THROW_ON_ERROR));
    $before = [];
    foreach ($this->files->allFiles($this->root, true) as $file) {
        $before[$file->getRelativePathname()] = $file->getContents();
    }
    expect(fn () => $this->service->initialize('mi-web', 'termocms', 'db-minimal', 'none'))
        ->toThrow(InvalidArgumentException::class, 'manifests must identify their directory');
    $after = [];
    foreach ($this->files->allFiles($this->root, true) as $file) {
        $after[$file->getRelativePathname()] = $file->getContents();
    }
    expect($after)->toBe($before);
})->with([
    ['themes/db-minimal/theme.json', []],
    ['themes/db-minimal/theme.json', ['identifier' => 'other']],
    ['themes/db-cyber/theme.json', []],
    ['themes/db-cyber/theme.json', ['identifier' => 'other']],
    ['resources/views/themes/admin/termocms/theme.json', []],
    ['resources/views/themes/admin/termocms/theme.json', ['identifier' => 'other']],
    ['resources/views/themes/admin/dbcms/theme.json', []],
    ['resources/views/themes/admin/dbcms/theme.json', ['identifier' => 'other']],
]);
