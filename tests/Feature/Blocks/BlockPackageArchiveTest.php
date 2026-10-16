<?php

declare(strict_types=1);

use App\Core\Blocks\BlockPackageManager;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

beforeEach(function (): void {
    $this->visualRoot = storage_path('app/private/blocks-'.bin2hex(random_bytes(8)));
    File::ensureDirectoryExists($this->visualRoot, 0700);
    config(['cms.block_packages.root' => $this->visualRoot.'/installed']);
});

afterEach(function (): void {
    File::deleteDirectory($this->visualRoot);
});

function visualArchive(string $root, array $extra = []): string
{
    $path = $root.'/visual.zip';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('Example/block-package.json', json_encode(['identifier' => 'example-visual', 'name' => 'Example', 'version' => '1.0.0', 'blocks' => ['BlockPackages\\Example\\ExampleBlock']], JSON_THROW_ON_ERROR));
    $zip->addFromString('Example/ExampleBlock.php', '<?php namespace BlockPackages\\Example; class ExampleBlock extends \\BlockPackages\\Basic\\Blocks\\HeroBlock { public function getType(): string { return "example-visual"; } }');
    foreach ($extra as $name => $contents) {
        $zip->addFromString($name, $contents);
    }
    $zip->close();

    return $path;
}

test('a package cannot reuse a bundled namespace under another identifier', function (): void {
    $path = $this->visualRoot.'/collision.zip';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE);
    $zip->addFromString('Basic/block-package.json', json_encode(['identifier' => 'other-basic', 'name' => 'Other', 'version' => '1.0.0', 'blocks' => ['BlockPackages\\Basic\\Blocks\\HeroBlock']], JSON_THROW_ON_ERROR));
    $zip->addFromString('Basic/Blocks/HeroBlock.php', '<?php namespace BlockPackages\\Basic\\Blocks; class HeroBlock {}');
    $zip->close();

    expect(fn () => app(BlockPackageManager::class)->install($path, (int) filesize($path)))->toThrow(ValidationException::class);
    expect(is_dir($this->visualRoot.'/installed/Basic'))->toBeFalse();
    expect(DB::table('block_packages')->where('identifier', 'other-basic')->exists())->toBeFalse();
});

test('invalid definitions never publish a package that would break the next boot', function (string $source): void {
    $path = visualArchive($this->visualRoot, ['Example/ExampleBlock.php' => $source]);

    expect(fn () => app(BlockPackageManager::class)->install($path, (int) filesize($path)))->toThrow(ValidationException::class);
    expect(is_dir($this->visualRoot.'/installed/Example'))->toBeFalse();
    expect(DB::table('block_packages')->where('identifier', 'example-visual')->exists())->toBeFalse();
    expect(class_exists('BlockPackages\\Example\\ExampleBlock', false))->toBeFalse();
})->with([
    'missing class' => '<?php namespace BlockPackages\\Example; class DifferentClass {}',
    'wrong contract' => '<?php namespace BlockPackages\\Example; class ExampleBlock {}',
    'duplicate type' => '<?php namespace BlockPackages\\Example; class ExampleBlock extends \\BlockPackages\\Basic\\Blocks\\HeroBlock {}',
    'throwing constructor' => '<?php namespace BlockPackages\\Example; class ExampleBlock extends \\BlockPackages\\Basic\\Blocks\\HeroBlock { public function __construct() { throw new \\RuntimeException("Broken constructor"); } public function getType(): string { return "example-visual"; } }',
    'early process exit' => '<?php exit(0);',
]);

test('uploaded blocks start disabled and cannot overwrite installed code', function (): void {
    $originalStorage = app()->storagePath();
    app()->useStoragePath($this->visualRoot);
    config(['cms.block_packages' => []]);

    try {
        $path = visualArchive($this->visualRoot);
        $manager = app(BlockPackageManager::class);
        expect($manager->install($path, (int) filesize($path)))->toBe('example-visual')
            ->and($manager->isEnabled('example-visual'))->toBeFalse()
            ->and(DB::table('block_packages')->where('identifier', 'example-visual')->value('is_enabled'))->toBeFalsy();
        expect(fn () => $manager->install($path, (int) filesize($path)))->toThrow(ValidationException::class);
        expect(is_file($this->visualRoot.'/app/extensions/blocks/Example/ExampleBlock.php'))->toBeTrue();
    } finally {
        app()->useStoragePath($originalStorage);
    }
});

test('an installed ZIP boots in a new process and can activate and render its real definition', function (): void {
    $path = visualArchive($this->visualRoot, [
        'Example/block-package.json' => json_encode(['identifier' => 'example-visual', 'name' => 'Example', 'version' => '1.0.0', 'blocks' => ['BlockPackages\\Example\\DerivedBlock', 'BlockPackages\\Example\\ExampleBlock']], JSON_THROW_ON_ERROR),
        'Example/DerivedBlock.php' => '<?php namespace BlockPackages\\Example; class DerivedBlock extends ExampleBlock { public function getType(): string { return "derived-visual"; } }',
    ]);
    app(BlockPackageManager::class)->install($path, (int) filesize($path));
    expect(class_exists('BlockPackages\\Example\\ExampleBlock', false))->toBeFalse();
    $configuration = config()->all();
    $configuration['cms']['modules'] = [];
    $configuration['database']['default'] = 'sqlite';
    $configuration['database']['connections']['sqlite']['database'] = ':memory:';
    $script = <<<'PHP'
require $argv[1].'/vendor/autoload.php';
$configuration = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
$app = require $argv[1].'/bootstrap/app.php';
$app->beforeBootstrapping(\Illuminate\Foundation\Bootstrap\RegisterProviders::class, function ($app) use ($configuration) { $app->make('config')->set($configuration); });
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
(require $argv[1].'/database/migrations/2026_09_01_000010_create_rbac_tables.php')->up();
(require $argv[1].'/database/migrations/2026_10_15_120000_create_block_packages_table.php')->up();
\Illuminate\Support\Facades\Event::fake([\App\Core\Blocks\Events\BlockPackageAvailabilityChanged::class]);
$manager = app(\App\Core\Blocks\BlockPackageManager::class);
$registry = app(\App\Core\Blocks\Contracts\BlockRegistryInterface::class);
$before = $registry->isAvailable('example-visual');
$manager->setEnabled('example-visual', true);
echo json_encode(['before' => $before, 'after' => $registry->isAvailable('example-visual'), 'html' => $registry->render('example-visual', ['title' => 'Installed ZIP rendered'])], JSON_THROW_ON_ERROR);
PHP;
    $process = new Process([PHP_BINARY, '-r', $script, base_path()], base_path());
    $process->setInput(json_encode($configuration, JSON_THROW_ON_ERROR))->setTimeout(30)->mustRun();
    $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    expect($result['before'])->toBeFalse()->and($result['after'])->toBeTrue()
        ->and($result['html'])->toContain('Installed ZIP rendered');
});

test('an extension that never finishes validation is not published', function (): void {
    config(['cms.block_packages.validation_timeout' => 1]);
    $path = visualArchive($this->visualRoot, ['Example/ExampleBlock.php' => '<?php sleep(10);']);
    expect(fn () => app(BlockPackageManager::class)->install($path, (int) filesize($path)))->toThrow(ValidationException::class, 'validation time limit');
    expect(is_dir($this->visualRoot.'/installed/Example'))->toBeFalse()
        ->and(File::directories($this->visualRoot.'/installed/.package-staging'))->toBe([]);
});

test('a discovered package identifier cannot be installed again under a different folder', function (): void {
    $existing = $this->visualRoot.'/installed/Existing';
    File::ensureDirectoryExists($existing);
    File::put($existing.'/block-package.json', json_encode([
        'identifier' => 'example-visual', 'name' => 'Existing', 'version' => '1.0.0',
        'blocks' => ['BlockPackages\\Existing\\ExistingBlock'],
    ], JSON_THROW_ON_ERROR));
    $path = visualArchive($this->visualRoot);

    expect(DB::table('block_packages')->where('identifier', 'example-visual')->exists())->toBeFalse();
    expect(fn () => app(BlockPackageManager::class)->install($path, (int) filesize($path)))->toThrow(ValidationException::class);
    expect(is_dir($this->visualRoot.'/installed/Example'))->toBeFalse()
        ->and(is_file($existing.'/block-package.json'))->toBeTrue();
});

test('uploaded package metadata cannot impersonate a bundled package or another directory', function (): void {
    $path = visualArchive($this->visualRoot);
    $zip = new ZipArchive;
    $zip->open($path);
    $manifest = json_decode((string) $zip->getFromName('Example/block-package.json'), true, flags: JSON_THROW_ON_ERROR);
    $manifest['bundled'] = true;
    $manifest['directory'] = app(BlockPackageManager::class)->bundledRoot().'/Basic';
    $zip->addFromString('Example/block-package.json', json_encode($manifest, JSON_THROW_ON_ERROR));
    $zip->close();
    $manager = app(BlockPackageManager::class);
    $identifier = $manager->install($path, (int) filesize($path));

    expect($manager->all()[$identifier]['bundled'])->toBeFalse()
        ->and($manager->all()[$identifier]['directory'])->toBe($this->visualRoot.'/installed/Example');
    $manager->uninstall($identifier);
    expect(is_dir($this->visualRoot.'/installed/Example'))->toBeFalse()
        ->and(is_file($manager->bundledRoot().'/Basic/block-package.json'))->toBeTrue();
});

test('block ZIP rejects traversal and links before extraction', function (string $unsafe): void {
    $path = visualArchive($this->visualRoot, [$unsafe => 'unsafe']);
    expect(fn () => app(BlockPackageManager::class)->install($path, (int) filesize($path)))->toThrow(ValidationException::class);
    expect(is_dir($this->visualRoot.'/installed/Example'))->toBeFalse();
})->with(['../escape.php', 'Example/../../escape.php', 'Example/.env', 'Other/block-package.json']);

test('failed package registration rolls back newly extracted code', function (): void {
    $path = visualArchive($this->visualRoot);
    $connection = DB::connection();
    $dispatcher = $connection->getEventDispatcher();
    $isolated = clone $dispatcher;
    $connection->setEventDispatcher($isolated);
    $isolated->listen(QueryExecuted::class, static function ($event): void {
        if (str_contains(strtolower($event->sql), 'insert into') && str_contains($event->sql, 'block_packages')) {
            throw new RuntimeException('Registration failed');
        }
    });
    try {
        expect(fn () => app(BlockPackageManager::class)->install($path, (int) filesize($path)))->toThrow(RuntimeException::class, 'Registration failed');
        expect(is_dir($this->visualRoot.'/installed/Example'))->toBeFalse();
        expect(DB::table('block_packages')->where('identifier', 'example-visual')->exists())->toBeFalse();
    } finally {
        $connection->setEventDispatcher($dispatcher);
    }
});
