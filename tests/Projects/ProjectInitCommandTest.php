<?php

declare(strict_types=1);

use App\Console\Commands\ProjectInitCommand;
use App\Contracts\Services\ProjectSourceRepositoryInterface;
use App\Core\Updates\SignedManifestVerifier;
use App\Services\Projects\ProjectLocalEnvironmentService;
use App\Services\Projects\ProjectSourceBootstrapService;
use App\Services\Updates\GithubReleaseGateway;
use Dotenv\Dotenv;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->app->make(Kernel::class)->bootstrap();
    $this->originalBase = base_path();
    $this->files = new Filesystem;
    $this->projectRoot = sys_get_temp_dir().'/command-project-'.bin2hex(random_bytes(8));
    $this->files->ensureDirectoryExists($this->projectRoot);
    $this->official = [
        'composer.json' => '{}',
        'bin/project-init' => '#!/bin/sh',
        '.env.example' => "APP_KEY=\nCMS_BRAND=dbcms\nCMS_THEME=db-cyber\nCMS_SEED_DEMO=true\nCMS_QUICK_LOGIN=true\n",
        'resources/updates/core-version.json' => '{"version":"1.4.0","project_bootstrap":1}',
        'resources/views/themes/admin/dbcms/theme.json' => '{"identifier":"dbcms","name":"dbCMS"}',
        'resources/views/themes/admin/termocms/theme.json' => '{"identifier":"termocms","name":"TermoCMS"}',
        'themes/db-cyber/theme.json' => '{"identifier":"db-cyber","name":"Cyber"}',
        'themes/db-minimal/theme.json' => '{"identifier":"db-minimal","name":"Minimal"}',
        'modules/Base/module.json' => '{"identifier":"foundation","name":"Foundation tools","version":"1.0.0","provider":"Modules\\\\Base\\\\BaseProvider"}',
        'modules/Blog/module.json' => '{"identifier":"blog","name":"Editorial blog","version":"1.0.0","provider":"Modules\\\\Blog\\\\BlogProvider","requires":{"foundation":"^1.0"}}',
        'block-packages/Basic/block-package.json' => '{"identifier":"basic","name":"Basic visuals","version":"1.0.0","blocks":["BlockPackages\\\\Basic\\\\Blocks\\\\Hero"]}',
        'database/seeders/PlatformShowcaseSeeder.php' => '<?php',
    ];
    foreach ($this->official as $path => $bytes) {
        $this->files->ensureDirectoryExists(dirname($this->projectRoot.'/'.$path));
        file_put_contents($this->projectRoot.'/'.$path, $bytes);
    }
    $this->files->ensureDirectoryExists($this->projectRoot.'/custom');
    file_put_contents($this->projectRoot.'/custom/project.txt', 'project-owned');

    static $privatePem;
    if ($privatePem === null) {
        $key = openssl_pkey_new(['private_key_bits' => 3072, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $privatePem);
    }
    config(['updates.trusted_keys' => ['command-test' => openssl_pkey_get_details(openssl_pkey_get_private($privatePem))['key']]]);
    $manifest = [
        'schema' => 1, 'version' => '1.4.0', 'source_sha' => str_repeat('b', 40),
        'min_php' => '8.3', 'extensions' => [], 'supported_from' => [],
        'rollback_compatible' => true, 'migrations' => ['core' => [], 'modules' => []],
        'distributions' => ['source' => [
            'asset' => 'source.tar.gz', 'sha256' => str_repeat('f', 64), 'size' => 1,
            'files' => array_map(static fn (string $bytes): string => hash('sha256', $bytes), $this->official),
        ]],
    ];
    $raw = json_encode($manifest, JSON_THROW_ON_ERROR);
    openssl_sign($raw, $signature, $privatePem, OPENSSL_ALGO_SHA256);
    $download = 'https://github.com/dbravoan/dbcms/releases/download/v1.4.0/';
    $release = ['tag_name' => 'v1.4.0', 'draft' => false, 'prerelease' => false, 'published_at' => '2026-10-20T00:00:00Z', 'assets' => [
        ['name' => 'manifest.json', 'browser_download_url' => $download.'manifest.json'],
        ['name' => 'manifest.sig', 'browser_download_url' => $download.'manifest.sig'],
    ]];
    Http::swap(new Factory);
    Http::fake(static function (Request $request) use ($release, $download, $raw, $signature) {
        return match ($request->url()) {
            'https://api.github.com/repos/dbravoan/dbcms/releases?per_page=100' => Http::response([$release]),
            $download.'manifest.json' => Http::response($raw),
            $download.'manifest.sig' => Http::response(json_encode(['key_id' => 'command-test', 'signature' => base64_encode($signature)], JSON_THROW_ON_ERROR)),
            default => throw new RuntimeException('Unexpected release request: '.$request->url()),
        };
    });
    $this->sourceRepository = new class($this->official) implements ProjectSourceRepositoryInterface
    {
        public int $fetches = 0;

        /** @param array<string,string> $files */
        public function __construct(private readonly array $files) {}

        public function fetch(string $version, string $sha, string $directory): void
        {
            $this->fetches++;
        }

        public function read(string $directory, string $sha, string $path): string
        {
            return $this->files[$path] ?? throw new DomainException('Unavailable test source: '.$path);
        }
    };
    $this->app->instance(ProjectSourceBootstrapService::class, new ProjectSourceBootstrapService($this->files, new GithubReleaseGateway, new SignedManifestVerifier, $this->sourceRepository));
    $this->app->instance(ProjectLocalEnvironmentService::class, new ProjectLocalEnvironmentService);
    $this->app->setBasePath($this->projectRoot);
    $this->runInitialization = function (array $arguments = [], array $answers = [], bool $interactive = true): CommandTester {
        $command = new ProjectInitCommand;
        $command->setLaravel($this->app);
        $console = new Application;
        $console->add($command);
        $tester = new CommandTester($command);
        $tester->setInputs($answers);
        $tester->execute($arguments, ['interactive' => $interactive, 'decorated' => false]);

        return $tester;
    };
    $this->sourceHashes = function (): array {
        $hashes = [];
        foreach ($this->files->allFiles($this->projectRoot, true) as $file) {
            $hashes[$file->getRelativePathname()] = hash_file('sha256', $file->getPathname());
        }
        ksort($hashes);

        return $hashes;
    };
});

afterEach(function (): void {
    $this->app->setBasePath($this->originalBase);
    $this->files->deleteDirectory($this->projectRoot);
});

test('interactive project command applies defaults only after explicit deployment and confirmation', function (): void {
    $tester = ($this->runInitialization)(['--prepare-only' => true], ['', '', '', '', '4', 'no', 'yes']);
    expect($tester->getStatusCode())->toBe(0, $tester->getDisplay())
        ->and($tester->getDisplay())->toContain('Project name', 'Administrative brand', 'Public theme', 'Deployment tools to retain (choose explicitly)', 'Apply this plan?', 'Editorial blog [blog]', 'Basic visuals [basic]');
    $profile = json_decode(file_get_contents($this->projectRoot.'/cms-project.json'), true, flags: JSON_THROW_ON_ERROR);
    expect($profile['name'])->toBe(basename($this->projectRoot))
        ->and($profile['brand'])->toBe('dbcms')
        ->and($profile['themes'])->toBe(['db-cyber'])
        ->and($profile['deployment'])->toBe('none')
        ->and($profile['source_selection']['module_directories'])->toBe(['Base', 'Blog'])
        ->and($profile['source_selection']['block_package_directories'])->toBe(['Basic'])
        ->and(is_file($this->projectRoot.'/.env'))->toBeFalse()
        ->and(file_get_contents($this->projectRoot.'/custom/project.txt'))->toBe('project-owned');
});

test('declining local initialization leaves every project source and existing environment unchanged', function (bool $existingEnvironment): void {
    if ($existingEnvironment) {
        file_put_contents($this->projectRoot.'/.env', "APP_KEY=private\nAPP_PORT=41234\n");
    }
    $before = ($this->sourceHashes)();
    $tester = ($this->runInitialization)(['name' => 'cancelled', '--brand' => 'dbcms', '--theme' => 'db-cyber', '--deploy' => 'none', '--modules' => 'all', '--local' => true], ['no']);
    expect($tester->getStatusCode())->toBe(130)
        ->and($tester->getDisplay())->toContain('Initialization cancelled.')
        ->and(($this->sourceHashes)())->toBe($before)
        ->and(is_file($this->projectRoot.'/cms-project.json'))->toBeFalse();
})->with([false, true]);

test('noninteractive new project command rejects omitted required inputs before contacting releases', function (string $missing): void {
    $arguments = ['name' => 'sample', '--brand' => 'dbcms', '--theme' => 'db-cyber', '--deploy' => 'none', '--prepare-only' => true];
    unset($arguments[$missing]);
    $before = ($this->sourceHashes)();
    $tester = ($this->runInitialization)($arguments, interactive: false);
    expect($tester->getStatusCode())->toBe(1)
        ->and($tester->getDisplay())->toContain('Noninteractive initialization requires name, --brand, --theme and --deploy.')
        ->and(($this->sourceHashes)())->toBe($before);
    Http::assertNothingSent();
})->with(['name', '--brand', '--theme', '--deploy']);

test('confirmed local command previews configuration then creates isolated environment with only application key', function (): void {
    $tester = ($this->runInitialization)(['name' => 'local-site', '--brand' => 'termocms', '--theme' => 'db-minimal', '--deploy' => 'none', '--modules' => 'all', '--local' => true], ['yes']);
    expect($tester->getStatusCode())->toBe(0)
        ->and($tester->getDisplay())->toContain('Create .env with demo and quick login disabled.', 'Installer: http://localhost:', '(planned)', 'Apply this plan?');
    $values = Dotenv::parse(file_get_contents($this->projectRoot.'/.env'));
    expect($values['APP_NAME'])->toBe('local-site')
        ->and($values['CMS_BRAND'])->toBe('termocms')
        ->and($values['CMS_THEME'])->toBe('db-minimal')
        ->and($values['CMS_SEED_DEMO'])->toBe('false')
        ->and($values['CMS_QUICK_LOGIN'])->toBe('false')
        ->and($values['APP_KEY'])->toStartWith('base64:')
        ->and(strlen(base64_decode(substr($values['APP_KEY'], 7), true)))->toBe(32)
        ->and($values['APP_URL'])->toBe('http://localhost:'.$values['APP_PORT'])
        ->and($values['SESSION_COOKIE'])->toBe($values['COMPOSE_PROJECT_NAME'].'_session')
        ->and(is_file($this->projectRoot.'/storage/oauth-private.key'))->toBeFalse()
        ->and(is_file($this->projectRoot.'/storage/installed'))->toBeFalse();
});

test('recorded project command resumes locally without release lookup and preserves keys', function (): void {
    $initial = ($this->runInitialization)(['name' => 'pinned', '--brand' => 'dbcms', '--theme' => 'db-cyber', '--deploy' => 'none', '--prepare-only' => true], interactive: false);
    expect($initial->getStatusCode())->toBe(0);
    $manifest = file_get_contents($this->projectRoot.'/cms-project.json');
    $environment = "APP_KEY=preserved\nAPP_PORT=41235\nCOMPOSE_PROJECT_NAME=preserved-project\n";
    file_put_contents($this->projectRoot.'/.env', $environment);
    Http::swap(new Factory);
    Http::preventStrayRequests();
    $tester = ($this->runInitialization)(['--local' => true], interactive: false);
    expect($tester->getStatusCode())->toBe(0)
        ->and($tester->getDisplay())->toContain('Resuming the recorded project', 'Preserve the existing .env and keys')
        ->and(file_get_contents($this->projectRoot.'/cms-project.json'))->toBe($manifest)
        ->and(file_get_contents($this->projectRoot.'/.env'))->toBe($environment)
        ->and($this->sourceRepository->fetches)->toBe(1);
    Http::assertNothingSent();
});

test('recorded project command rejects explicitly changed composition without writes or release lookup', function (array $changed): void {
    $initial = ($this->runInitialization)(['name' => 'pinned', '--brand' => 'dbcms', '--theme' => 'all', '--deploy' => 'none', '--prepare-only' => true], interactive: false);
    expect($initial->getStatusCode())->toBe(0);
    $before = ($this->sourceHashes)();
    Http::swap(new Factory);
    Http::preventStrayRequests();
    $tester = ($this->runInitialization)(['--prepare-only' => true, ...$changed], interactive: false);
    expect($tester->getStatusCode())->toBe(1)
        ->and($tester->getDisplay())->toContain('already initialized')
        ->and(($this->sourceHashes)())->toBe($before);
    Http::assertNothingSent();
})->with([
    'name' => [['name' => 'changed']],
    'brand' => [['--brand' => 'termocms']],
    'theme' => [['--theme' => 'db-minimal']],
    'deployment' => [['--deploy' => 'rsync']],
    'modules' => [['--modules' => 'none']],
    'packages' => [['--block-packages' => 'none']],
]);

test('interactive custom source labels resolve dependencies before applying selection', function (): void {
    $tester = ($this->runInitialization)(['name' => 'custom-site', '--brand' => 'dbcms', '--theme' => 'db-cyber', '--deploy' => 'none', '--prepare-only' => true], ['yes', 'Editorial blog [blog]', 'No optional sources', 'yes']);
    expect($tester->getStatusCode())->toBe(0)
        ->and($tester->getDisplay())->toContain('Foundation tools [foundation]', 'Editorial blog [blog]', 'Basic visuals [basic]', 'Required modules added: foundation');
    $profile = json_decode(file_get_contents($this->projectRoot.'/cms-project.json'), true, flags: JSON_THROW_ON_ERROR);
    expect($profile['source_selection']['module_directories'])->toBe(['Base', 'Blog'])
        ->and($profile['source_selection']['block_package_directories'])->toBe([])
        ->and(is_file($this->projectRoot.'/block-packages/Basic/block-package.json'))->toBeFalse();
});
