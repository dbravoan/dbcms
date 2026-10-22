<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Tests\Support\DerivedTestSelection;

beforeEach(function (): void {
    $this->qualityRoot = sys_get_temp_dir().'/dbcms-derived-quality-'.bin2hex(random_bytes(8));
    $this->qualityFiles = new Filesystem;
    foreach (['tests/Feature', 'modules', 'block-packages', 'themes/editorial-own', 'resources/views/themes/admin/termocms', 'resources/updates'] as $directory) {
        $this->qualityFiles->ensureDirectoryExists($this->qualityRoot.'/'.$directory);
    }
    $this->qualityFiles->put($this->qualityRoot.'/VERSION', '0.1.0');
    $this->qualityFiles->put($this->qualityRoot.'/resources/updates/core-version.json', '{"version":"2.0.0"}');
    $this->qualityFiles->put($this->qualityRoot.'/phpunit.xml', '<phpunit><testsuites><testsuite name="Feature"><directory>tests/Feature</directory></testsuite></testsuites></phpunit>');
    $this->qualityFiles->put($this->qualityRoot.'/phpunit.fast.xml', '<phpunit><testsuites><testsuite name="Fast"><file>tests/Feature/CoreTest.php</file></testsuite></testsuites></phpunit>');
    foreach (['themes/editorial-own' => 'editorial-own', 'resources/views/themes/admin/termocms' => 'termocms'] as $directory => $id) {
        $this->qualityFiles->put($this->qualityRoot.'/'.$directory.'/theme.json', json_encode(['identifier' => $id], JSON_THROW_ON_ERROR));
    }
    foreach (['CoreTest.php', 'BlogTest.php', 'NewUnknownTest.php'] as $test) {
        $this->qualityFiles->put($this->qualityRoot.'/tests/Feature/'.$test, '<?php');
    }
    $this->qualityProfile = [
        'schema' => 1, 'name' => 'quality-child', 'brand' => 'termocms', 'deployment' => 'none', 'themes' => ['editorial-own'], 'default_theme' => 'editorial-own',
        'core' => ['repository' => 'dbravoan/dbcms', 'version' => '2.0.0', 'sha' => str_repeat('a', 40)],
        'source_selection' => ['module_directories' => [], 'block_package_directories' => [], 'include_showcase' => false],
    ];
    $this->qualityRequirements = ['tests/Feature/BlogTest.php' => ['modules' => ['Blog']]];
    $this->qualityPlan = function (): array {
        $this->qualityFiles->put($this->qualityRoot.'/cms-project.json', json_encode($this->qualityProfile, JSON_THROW_ON_ERROR));

        return (new DerivedTestSelection($this->qualityRoot, $this->qualityRequirements))->plan();
    };
    $this->qualityModule = function (string $directory, string $identifier, array $requires = []): void {
        $this->qualityFiles->ensureDirectoryExists($this->qualityRoot.'/modules/'.$directory);
        $this->qualityFiles->put($this->qualityRoot.'/modules/'.$directory.'/module.json', json_encode([
            'identifier' => $identifier, 'name' => $directory, 'version' => '1.0.0',
            'provider' => 'Modules\\'.$directory.'\\'.$directory.'ModuleServiceProvider', 'requires' => $requires,
        ], JSON_THROW_ON_ERROR));
    };
});

afterEach(function (): void {
    $this->qualityFiles->deleteDirectory($this->qualityRoot);
});

it('keeps core and unknown tests for a declared core-only project with its own theme and either brand', function (): void {
    $plan = ($this->qualityPlan)();
    expect($plan['files'])->toBe(['tests/Feature/CoreTest.php', 'tests/Feature/NewUnknownTest.php']);
    expect($plan['profile']['brand'])->toBe('termocms');
    expect($plan['profile']['default_theme'])->toBe('editorial-own');
});

it('selects optional tests from the canonical declaration without a business profile', function (): void {
    ($this->qualityModule)('Blog', 'blog');
    $this->qualityProfile['source_selection']['module_directories'] = ['Blog'];
    expect(($this->qualityPlan)()['files'])->toContain('tests/Feature/BlogTest.php', 'tests/Feature/CoreTest.php', 'tests/Feature/NewUnknownTest.php');
});

it('does not infer exclusions when the source-selection declaration is absent', function (): void {
    unset($this->qualityProfile['source_selection']);
    expect(($this->qualityPlan)()['files'])->toContain('tests/Feature/BlogTest.php');
});

it('rejects missing declared sources before selecting tests', function (): void {
    $this->qualityProfile['source_selection']['module_directories'] = ['Blog'];
    expect(fn () => ($this->qualityPlan)())->toThrow(DomainException::class);
});

it('rejects undeclared physical sources instead of treating them as selected', function (): void {
    ($this->qualityModule)('Blog', 'blog');
    expect(fn () => ($this->qualityPlan)())->toThrow(DomainException::class);
});

it('rejects missing transitive module dependencies before selecting tests', function (): void {
    ($this->qualityModule)('Dependent', 'dependent', ['blog' => '^1.0']);
    $this->qualityProfile['source_selection']['module_directories'] = ['Dependent'];
    expect(fn () => ($this->qualityPlan)())->toThrow(DomainException::class);
});

it('rejects malformed selections and symbolic manifests', function (): void {
    $this->qualityProfile['source_selection']['module_directories'] = ['../Blog'];
    expect(fn () => ($this->qualityPlan)())->toThrow(DomainException::class);
    $this->qualityProfile['source_selection']['module_directories'] = [];
    $manifest = $this->qualityRoot.'/themes/editorial-own/theme.json';
    rename($manifest, $manifest.'.actual');
    symlink($manifest.'.actual', $manifest);
    expect(fn () => ($this->qualityPlan)())->toThrow(DomainException::class);
});

it('rejects absent or extra public themes instead of falling back to a product theme', function (): void {
    $this->qualityProfile['themes'] = ['missing'];
    $this->qualityProfile['default_theme'] = 'missing';
    expect(fn () => ($this->qualityPlan)())->toThrow(DomainException::class);
});

it('keeps the fast lane restricted to its configured entrypoints', function (): void {
    ($this->qualityPlan)();
    $plan = (new DerivedTestSelection($this->qualityRoot, $this->qualityRequirements))->plan('fast');
    expect($plan['files'])->toBe(['tests/Feature/CoreTest.php']);
});

it('validates core requirements against the core descriptor rather than the child commercial version', function (): void {
    ($this->qualityModule)('Blog', 'blog', ['core' => '^2.0']);
    $this->qualityProfile['source_selection']['module_directories'] = ['Blog'];
    expect(($this->qualityPlan)()['files'])->toContain('tests/Feature/BlogTest.php');
});

it('rejects malformed canonical profiles before selecting test files', function (string $field, mixed $value): void {
    if (str_starts_with($field, 'core.')) {
        $this->qualityProfile['core'][substr($field, 5)] = $value;
    } else {
        $this->qualityProfile[$field] = $value;
    }
    expect(fn () => ($this->qualityPlan)())->toThrow(DomainException::class);
})->with([
    'invalid name' => ['name', '../child'],
    'unknown brand' => ['brand', 'unknown'],
    'missing deployment' => ['deployment', null],
    'foreign repository' => ['core.repository', 'other/repository'],
    'invalid version' => ['core.version', 'next'],
    'invalid revision' => ['core.sha', 'not-a-revision'],
]);

it('still filters source catalogue and unavailable brand fixtures when source selection is absent', function (): void {
    unset($this->qualityProfile['source_selection']);
    $this->qualityRequirements['tests/Feature/CoreTest.php'] = ['brands' => ['dbcms', 'termocms']];
    $this->qualityRequirements['tests/Feature/BlogTest.php'] = ['source_catalogue' => true];
    expect(($this->qualityPlan)()['files'])->toBe(['tests/Feature/NewUnknownTest.php']);
});
