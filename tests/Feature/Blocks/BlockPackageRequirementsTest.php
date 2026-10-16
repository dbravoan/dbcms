<?php

declare(strict_types=1);

use App\Core\Blocks\BlockPackageManager;
use App\Core\Blocks\Contracts\BlockRegistryInterface;
use App\Core\Modules\ModuleManager;
use BlockPackages\Basic\Blocks\HeroBlock;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

test('availability reevaluates required core and module versions after activation', function (string $requirement): void {
    $root = storage_path('app/private/requirements-'.bin2hex(random_bytes(8)));
    File::ensureDirectoryExists($root.'/Example');
    File::put($root.'/Example/block-package.json', json_encode([
        'identifier' => 'example', 'name' => 'Example', 'version' => '1.0.0',
        'blocks' => ['BlockPackages\\Example\\ExampleBlock'], 'requires' => [$requirement => '^1.0'],
    ], JSON_THROW_ON_ERROR));
    config(['cms.block_packages.bundled_root' => $root, 'cms.version' => '1.0.0']);
    $version = '1.0.0';
    if ($requirement !== 'core') {
        $modules = Mockery::mock(ModuleManager::class);
        $modules->shouldReceive('isModuleEnabled')->with($requirement)->andReturn(true);
        $modules->shouldReceive('getModuleManifest')->with($requirement)->andReturnUsing(function () use (&$version): array {
            return ['version' => $version];
        });
        app()->instance(ModuleManager::class, $modules);
    }
    try {
        $manager = app(BlockPackageManager::class);
        $manager->setEnabled('example', true);
        expect($manager->isEnabled('example'))->toBeTrue();
        $version = '2.0.0';
        config(['cms.version' => $version]);
        expect($manager->isEnabled('example'))->toBeFalse();
        expect(fn () => $manager->setEnabled('example', true))->toThrow(ValidationException::class);
    } finally {
        File::deleteDirectory($root);
    }
})->with(['core', 'optional_owner']);

test('required schema fields are enforced at the root and inside repeaters', function (): void {
    $registry = app(BlockRegistryInterface::class);
    $registry->register(new class extends HeroBlock
    {
        public function getType(): string
        {
            return 'required-fields';
        }

        public function getEditorSchema(): array
        {
            return [
                'title' => ['type' => 'text', 'required' => true, 'rules' => 'string|max:40'],
                'items' => ['type' => 'repeater', 'fields' => ['value' => ['type' => 'number', 'required' => true]]],
            ];
        }
    });
    expect(fn () => $registry->validate('required-fields', []))->toThrow(ValidationException::class)
        ->and(fn () => $registry->validate('required-fields', ['title' => '', 'items' => []]))->toThrow(ValidationException::class)
        ->and(fn () => $registry->validate('required-fields', ['title' => 'Valid', 'items' => [[]]]))->toThrow(ValidationException::class);
    $registry->validate('required-fields', ['title' => 'Valid', 'items' => [['value' => 0]]]);
});
