<?php

declare(strict_types=1);

use App\Contracts\Services\ProjectSourceRepositoryInterface;
use App\Contracts\Services\ProjectSourceSnapshotRepositoryInterface;
use App\Core\Updates\SignedManifestVerifier;
use App\Services\Projects\ProjectSourceBootstrapService;
use App\Services\ProjectUpdates\GitProjectSourceRepository;
use App\Services\Updates\GithubReleaseGateway;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    static $pem;
    if ($pem === null) {
        $key = openssl_pkey_new(['private_key_bits' => 3072, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);
    }
    $this->privatePem = $pem;
    config(['updates.trusted_keys' => ['bootstrap-test' => openssl_pkey_get_details(openssl_pkey_get_private($pem))['key']]]);
    $this->files = new Filesystem;
    $this->root = sys_get_temp_dir().'/dbcms-guided-test-'.bin2hex(random_bytes(8));
    $this->files->ensureDirectoryExists($this->root);
    $this->official = [
        'composer.json' => '{}',
        'bin/project-init' => '#!/bin/sh',
        '.env.example' => "CMS_BRAND=dbcms\nCMS_THEME=db-cyber\n",
        'resources/updates/core-version.json' => '{"version":"1.4.0","project_bootstrap":1}',
        'resources/views/themes/admin/dbcms/theme.json' => '{"identifier":"dbcms"}',
        'resources/views/themes/admin/termocms/theme.json' => '{"identifier":"termocms"}',
        'themes/db-cyber/theme.json' => '{"identifier":"db-cyber","name":"Cyber"}',
        'themes/db-minimal/theme.json' => '{"identifier":"db-minimal"}',
        '.github/workflows/release.yml' => 'official release',
        'database/seeders/PlatformShowcaseSeeder.php' => '<?php',
    ];
    $this->sources = new class implements ProjectSourceRepositoryInterface, ProjectSourceSnapshotRepositoryInterface
    {
        public array $files = [];

        public array $snapshotFiles = [];

        public array $baselineFiles = [];

        public int $fetches = 0;

        public function fetch(string $version, string $sha, string $directory): void
        {
            $this->fetches++;
        }

        public function read(string $directory, string $sha, string $path): string
        {
            return ($sha === str_repeat('a', 40) ? $this->baselineFiles[$path] : $this->files[$path]) ?? throw new DomainException('Missing source.');
        }

        public function snapshot(string $directory, ?string $root = null): array
        {
            return ['sha' => str_repeat('c', 40), 'files' => array_map(fn (string $bytes): string => hash('sha256', $bytes), $this->snapshotFiles)];
        }
    };
    $this->setupRelease = function (): void {
        $manifest = ['schema' => 1, 'version' => '1.4.0', 'source_sha' => str_repeat('b', 40), 'min_php' => '8.3', 'extensions' => [], 'supported_from' => [], 'rollback_compatible' => true, 'migrations' => ['core' => [], 'modules' => []], 'distributions' => ['source' => ['asset' => 'source.tar.gz', 'sha256' => str_repeat('f', 64), 'size' => 1, 'files' => array_map(fn (string $bytes): string => hash('sha256', $bytes), $this->official)]]];
        $raw = json_encode($manifest, JSON_THROW_ON_ERROR);
        openssl_sign($raw, $signature, $this->privatePem, OPENSSL_ALGO_SHA256);
        $release = ['tag_name' => 'v1.4.0', 'draft' => false, 'prerelease' => false, 'published_at' => '2026-10-20T00:00:00Z', 'assets' => [['name' => 'manifest.json', 'browser_download_url' => 'https://github.com/dbravoan/dbcms/releases/download/v1.4.0/manifest.json'], ['name' => 'manifest.sig', 'browser_download_url' => 'https://github.com/dbravoan/dbcms/releases/download/v1.4.0/manifest.sig']]];
        Http::swap(new Factory);
        Http::fake(function (Request $request) use ($release, $raw, $signature) {
            return match ($request->url()) {
                'https://api.github.com/repos/dbravoan/dbcms/releases?per_page=100' => Http::response([$release, [...$release, 'tag_name' => 'v2.0.0', 'prerelease' => true], [...$release, 'tag_name' => 'v3.0.1', 'draft' => true]]),
                'https://github.com/dbravoan/dbcms/releases/download/v1.4.0/manifest.json' => Http::response($raw),
                'https://github.com/dbravoan/dbcms/releases/download/v1.4.0/manifest.sig' => Http::response(json_encode(['key_id' => 'bootstrap-test', 'signature' => base64_encode($signature)], JSON_THROW_ON_ERROR)),
                default => throw new RuntimeException('Unexpected request: '.$request->url()),
            };
        });
        $this->sources->files = $this->official;
    };
    $this->writeEntry = function (array $files): void {
        foreach ($files as $path => $bytes) {
            $this->files->ensureDirectoryExists(dirname($this->root.'/'.$path));
            file_put_contents($this->root.'/'.$path, $bytes);
        }
    };
    ($this->setupRelease)();
    ($this->writeEntry)($this->official);
    ($this->writeEntry)(['.env' => 'APP_KEY=private', '.git/config' => 'private git', 'storage/installed' => 'private lock', 'custom/project.txt' => 'personalization']);
    $this->service = new ProjectSourceBootstrapService($this->files, new GithubReleaseGateway, new SignedManifestVerifier, $this->sources);
});

afterEach(function (): void {
    if (isset($this->session)) {
        $this->service->cleanup($this->session);
    }
    $this->files->deleteDirectory($this->root);
});

test('guided source preparation freezes stable release and projects both brands without touching private files', function (string $brand, string $deployment): void {
    $this->session = $this->service->prepare($this->root);
    expect($this->session->version)->toBe('1.4.0')
        ->and($this->service->choices($this->session)['themes']['db-cyber'])->toBe('Cyber')
        ->and(is_file($this->root.'/cms-project.json'))->toBeFalse();
    $profile = $this->service->apply($this->session, 'sample', $brand, 'db-cyber', $deployment);
    expect($profile->brand)->toBe($brand)
        ->and($profile->coreSha)->toBe(str_repeat('b', 40))
        ->and(is_file($this->root.'/themes/db-minimal/theme.json'))->toBeFalse()
        ->and(is_file($this->root.'/database/seeders/PlatformShowcaseSeeder.php'))->toBeFalse()
        ->and(file_get_contents($this->root.'/.env'))->toBe('APP_KEY=private')
        ->and(file_get_contents($this->root.'/.git/config'))->toBe('private git')
        ->and(file_get_contents($this->root.'/storage/installed'))->toBe('private lock')
        ->and(file_get_contents($this->root.'/custom/project.txt'))->toBe('personalization')
        ->and($this->sources->fetches)->toBe(1);
    Http::assertSentCount(3);
})->with(['dbcms', 'termocms'])->with(['github-actions', 'ansistrano', 'rsync', 'docker', 'none']);

test('cancelling a projected bootstrap leaves sources and environment unchanged', function (): void {
    $this->session = $this->service->prepare($this->root);
    expect(fn () => $this->service->apply($this->session, 'sample', 'dbcms', 'db-cyber', 'none', beforeApply: function ($profile, $identifiers, $changes): void {
        expect($changes)->not->toBeEmpty();
        throw new RuntimeException('cancelled');
    }))->toThrow(RuntimeException::class, 'cancelled');
    foreach ($this->official as $path => $bytes) {
        expect(file_get_contents($this->root.'/'.$path))->toBe($bytes);
    }
    expect(is_file($this->root.'/cms-project.json'))->toBeFalse()
        ->and(file_get_contents($this->root.'/.env'))->toBe('APP_KEY=private');
});

test('initialization resumes the recorded profile without checking releases or fetching source', function (): void {
    $this->session = $this->service->prepare($this->root);
    $profile = $this->service->apply($this->session, 'sample', 'termocms', 'db-minimal', 'docker');
    Http::swap(new Factory);
    Http::preventStrayRequests();
    $resumed = $this->service->prepare($this->root);
    expect($resumed->existingProfile->toArray())->toBe($profile->toArray())
        ->and($this->sources->fetches)->toBe(1);
    Http::assertNothingSent();
});

test('official main template and zip snapshots normalize to the frozen stable source', function (): void {
    $development = [...$this->official, 'composer.json' => '{"development":true}', 'app/development.php' => '<?php'];
    $this->sources->snapshotFiles = $development;
    ($this->writeEntry)($development);
    $this->session = $this->service->prepare($this->root);
    $this->service->apply($this->session, 'sample', 'dbcms', 'all', 'none');
    expect(file_get_contents($this->root.'/composer.json'))->toBe('{}')
        ->and(is_file($this->root.'/app/development.php'))->toBeFalse();
});

test('tampered source inventory bytes fail before project writes', function (): void {
    $this->sources->files['composer.json'] = 'tampered';
    expect(fn () => $this->service->prepare($this->root))->toThrow(DomainException::class, 'signed inventory');
    expect(file_get_contents($this->root.'/composer.json'))->toBe('{}')
        ->and(is_file($this->root.'/cms-project.json'))->toBeFalse();
});

test('customized official files cannot be normalized', function (): void {
    file_put_contents($this->root.'/composer.json', 'project customization');
    $this->sources->snapshotFiles = $this->official;
    expect(fn () => $this->service->prepare($this->root))->toThrow(DomainException::class, 'local changes');
    expect(file_get_contents($this->root.'/composer.json'))->toBe('project customization');
});

test('unsupported stable releases fail clearly before source writes', function (): void {
    $this->official['resources/updates/core-version.json'] = '{"version":"1.4.0"}';
    ($this->setupRelease)();
    expect(fn () => $this->service->prepare($this->root))->toThrow(DomainException::class, 'does not support guided');
    expect(file_get_contents($this->root.'/resources/updates/core-version.json'))->toContain('project_bootstrap');
});

test('new official paths cannot overwrite unrelated project additions', function (): void {
    $this->sources->snapshotFiles = $this->official;
    ($this->writeEntry)(['app/added.php' => 'project owned']);
    $this->official['app/added.php'] = 'new stable source';
    ($this->setupRelease)();
    expect(fn () => $this->service->prepare($this->root))->toThrow(DomainException::class, 'collides');
    expect(file_get_contents($this->root.'/app/added.php'))->toBe('project owned');
});

test('confirmation rechecks source ownership before applying the staged plan', function (): void {
    $this->session = $this->service->prepare($this->root);
    expect(fn () => $this->service->apply($this->session, 'sample', 'dbcms', 'db-cyber', 'none', beforeApply: function (): void {
        file_put_contents($this->root.'/composer.json', 'concurrent edit');
    }))->toThrow(DomainException::class, 'changed while');
    expect(is_file($this->root.'/themes/db-minimal/theme.json'))->toBeTrue()
        ->and(is_file($this->root.'/cms-project.json'))->toBeFalse();
});

test('historical official ZIP snapshots are identified from exported Git history', function (): void {
    $sandbox = $this->root.'/git-fixture';
    $repository = $sandbox.'/official';
    $entry = $sandbox.'/entry';
    $this->files->ensureDirectoryExists($repository);
    $this->files->ensureDirectoryExists($entry);
    $run = static function (array $arguments, ?string $cwd = null): string {
        $process = new Process($arguments, $cwd);
        $process->mustRun();

        return trim($process->getOutput());
    };
    $run(['git', 'init', '-b', 'main', $repository]);
    foreach ($this->official + ['.gitattributes' => '/agent-private export-ignore', 'agent-private' => 'excluded'] as $path => $bytes) {
        $this->files->ensureDirectoryExists(dirname($repository.'/'.$path));
        file_put_contents($repository.'/'.$path, $bytes);
        if ($path !== 'agent-private') {
            $this->files->ensureDirectoryExists(dirname($entry.'/'.$path));
            file_put_contents($entry.'/'.$path, $bytes);
        }
    }
    $run(['git', 'add', '.'], $repository);
    $run(['git', '-c', 'user.name=Test', '-c', 'user.email=test@example.invalid', 'commit', '-m', 'historical official source'], $repository);
    $historicalSha = $run(['git', 'rev-parse', 'HEAD'], $repository);
    file_put_contents($repository.'/different.php', '<?php // later source');
    $run(['git', 'add', '.'], $repository);
    $run(['git', '-c', 'user.name=Test', '-c', 'user.email=test@example.invalid', 'commit', '-m', 'new main'], $repository);
    foreach (['fetched', 'modified'] as $name) {
        $run(['git', 'init', '--bare', $sandbox.'/'.$name]);
        $run(['git', '--git-dir='.$sandbox.'/'.$name, 'config', 'url.file://'.$repository.'.insteadOf', 'https://github.com/dbravoan/dbcms.git']);
    }
    $snapshot = (new GitProjectSourceRepository)->snapshot($sandbox.'/fetched', $entry);
    expect($snapshot['sha'])->toBe($historicalSha)
        ->and($snapshot['files'])->not->toHaveKey('agent-private');
    file_put_contents($entry.'/composer.json', 'project customized');
    expect(fn () => (new GitProjectSourceRepository)->snapshot($sandbox.'/modified', $entry))->toThrow(DomainException::class, 'unverifiable');
});

test('an older signed stable source can normalize to latest without trusting its descriptor alone', function (): void {
    $baseline = [...$this->official, 'resources/updates/core-version.json' => '{"version":"1.3.0"}', 'composer.json' => '{"older":true}'];
    ($this->writeEntry)($baseline);
    $this->sources->baselineFiles = $baseline;
    $responses = [];
    foreach (['1.3.0' => $baseline, '1.4.0' => $this->official] as $version => $files) {
        $sha = str_repeat($version === '1.3.0' ? 'a' : 'b', 40);
        $manifest = ['schema' => 1, 'version' => $version, 'source_sha' => $sha, 'min_php' => '8.3', 'extensions' => [], 'supported_from' => [], 'rollback_compatible' => true, 'migrations' => ['core' => [], 'modules' => []], 'distributions' => ['source' => ['asset' => 'source.tar.gz', 'sha256' => str_repeat('f', 64), 'size' => 1, 'files' => array_map(fn (string $bytes): string => hash('sha256', $bytes), $files)]]];
        $raw = json_encode($manifest, JSON_THROW_ON_ERROR);
        openssl_sign($raw, $signature, $this->privatePem, OPENSSL_ALGO_SHA256);
        $prefix = 'https://github.com/dbravoan/dbcms/releases/download/v'.$version.'/';
        $release = ['tag_name' => 'v'.$version, 'draft' => false, 'prerelease' => false, 'published_at' => '2026-10-20T00:00:00Z', 'assets' => [['name' => 'manifest.json', 'browser_download_url' => $prefix.'manifest.json'], ['name' => 'manifest.sig', 'browser_download_url' => $prefix.'manifest.sig']]];
        $responses['https://api.github.com/repos/dbravoan/dbcms/releases/tags/v'.$version] = $release;
        if ($version === '1.4.0') {
            $responses['https://api.github.com/repos/dbravoan/dbcms/releases?per_page=100'] = [$release];
        }
        $responses[$prefix.'manifest.json'] = $raw;
        $responses[$prefix.'manifest.sig'] = json_encode(['key_id' => 'bootstrap-test', 'signature' => base64_encode($signature)], JSON_THROW_ON_ERROR);
    }
    Http::swap(new Factory);
    Http::fake(fn (Request $request) => Http::response($responses[$request->url()] ?? throw new RuntimeException('Unexpected request.')));
    $this->session = $this->service->prepare($this->root);
    $profile = $this->service->apply($this->session, 'sample', 'dbcms', 'db-cyber', 'none');
    expect($profile->coreVersion)->toBe('1.4.0')
        ->and(file_get_contents($this->root.'/composer.json'))->toBe('{}')
        ->and($this->sources->fetches)->toBe(2);
});

test('excluding a theme with project additions fails before applying source changes', function (): void {
    ($this->writeEntry)(['themes/db-minimal/custom.blade.php' => 'customized theme addition']);
    $this->session = $this->service->prepare($this->root);
    expect(fn () => $this->service->apply($this->session, 'sample', 'dbcms', 'db-cyber', 'none'))->toThrow(DomainException::class, 'project-owned additions');
    expect(file_get_contents($this->root.'/themes/db-minimal/custom.blade.php'))->toBe('customized theme addition')
        ->and(is_file($this->root.'/themes/db-minimal/theme.json'))->toBeTrue()
        ->and(is_file($this->root.'/cms-project.json'))->toBeFalse();
});

test('failed source application restores already changed official files and permits preparation again', function (): void {
    $files = new class($this->root) extends Filesystem
    {
        public function __construct(private readonly string $root) {}

        public function replace($path, $content, $mode = null): void
        {
            if ($path === $this->root.'/cms-project.json') {
                throw new RuntimeException('simulated profile write failure');
            }
            parent::replace($path, $content, $mode);
        }
    };
    $service = new ProjectSourceBootstrapService($files, new GithubReleaseGateway, new SignedManifestVerifier, $this->sources);
    $this->session = $service->prepare($this->root);
    expect(fn () => $service->apply($this->session, 'sample', 'termocms', 'db-minimal', 'none'))->toThrow(RuntimeException::class, 'simulated profile');
    foreach ($this->official as $path => $bytes) {
        expect(file_get_contents($this->root.'/'.$path))->toBe($bytes);
    }
    $resumed = $service->prepare($this->root);
    try {
        expect($resumed->version)->toBe('1.4.0')->and($resumed->existingProfile)->toBeNull();
    } finally {
        $service->cleanup($resumed);
    }
});

test('source path symlinks and case collisions fail without modifying sources', function (string $kind): void {
    if ($kind === 'link') {
        rename($this->root.'/composer.json', $this->root.'/composer-private.json');
        symlink($this->root.'/composer-private.json', $this->root.'/composer.json');
    } else {
        mkdir($this->root.'/Themes');
    }
    expect(fn () => $this->service->prepare($this->root))->toThrow(DomainException::class);
    expect(is_file($this->root.'/cms-project.json'))->toBeFalse();
})->with(['link', 'case']);

test('custom extension selection previews labels and closes module and package dependencies', function (): void {
    foreach (['Alpha' => ['identifier' => 'alpha', 'requires' => ['core' => '^1.0', 'beta' => '^1.0']], 'Beta' => ['identifier' => 'beta', 'requires' => ['core' => '^1.0']], 'Extra' => ['identifier' => 'extra']] as $directory => $properties) {
        $this->official['modules/'.$directory.'/module.json'] = json_encode(['name' => $directory.' feature', 'version' => '1.0.0', 'provider' => 'Modules\\'.$directory.'\\ServiceProvider', ...$properties], JSON_THROW_ON_ERROR);
    }
    $this->official['block-packages/Visual/block-package.json'] = json_encode(['identifier' => 'visual', 'name' => 'Visual package', 'version' => '1.0.0', 'blocks' => ['BlockPackages\\Visual\\Blocks\\ExampleBlock'], 'requires' => ['alpha' => '^1.0']], JSON_THROW_ON_ERROR);
    ($this->setupRelease)();
    ($this->writeEntry)($this->official);
    $this->session = $this->service->prepare($this->root);
    expect($this->service->choices($this->session)['modules']['alpha'])->toBe('Alpha feature');
    $profile = $this->service->apply($this->session, 'sample', 'dbcms', 'all', 'none', 'none', 'visual', function ($profile, $identifiers): void {
        expect($identifiers)->toBe(['modules' => ['alpha', 'beta'], 'block_packages' => ['visual']]);
    });
    expect($profile->sourceSelection['module_directories'])->toBe(['Alpha', 'Beta'])
        ->and(is_dir($this->root.'/modules/Extra'))->toBeFalse();
    $resumed = $this->service->prepare($this->root);
    expect($resumed->existingProfile->sourceSelection)->toBe($profile->sourceSelection);
});

test('resuming preserves the recorded default independently of theme ordering', function (): void {
    $this->session = $this->service->prepare($this->root);
    $this->service->apply($this->session, 'sample', 'dbcms', 'db-minimal,db-cyber', 'none');
    $profile = json_decode(file_get_contents($this->root.'/cms-project.json'), true, flags: JSON_THROW_ON_ERROR);
    $profile['default_theme'] = 'db-cyber';
    file_put_contents($this->root.'/cms-project.json', json_encode($profile, JSON_THROW_ON_ERROR));
    Http::swap(new Factory);
    Http::preventStrayRequests();
    $resumed = $this->service->prepare($this->root);
    expect($resumed->existingProfile->defaultTheme)->toBe('db-cyber')
        ->and($resumed->existingProfile->themes)->toBe(['db-minimal', 'db-cyber']);
    Http::assertNothingSent();
});

test('invalid release signatures fail before fetching or changing project sources', function (): void {
    config(['updates.trusted_keys' => []]);
    expect(fn () => $this->service->prepare($this->root))->toThrow(DomainException::class)
        ->and($this->sources->fetches)->toBe(0)
        ->and(is_file($this->root.'/cms-project.json'))->toBeFalse();
});

test('staging cleanup removes verified temporary sources after cancellation', function (): void {
    $this->session = $this->service->prepare($this->root);
    $stage = $this->session->stagePath;
    expect(is_dir($stage))->toBeTrue();
    $this->service->cleanup($this->session);
    expect(is_dir(dirname($stage)))->toBeFalse()
        ->and(is_file($this->root.'/composer.json'))->toBeTrue();
});

test('release discovery network failure preserves the entry and permits a clean retry', function (): void {
    Http::swap(new Factory);
    Http::fake(static fn () => throw new ConnectionException('simulated offline release gateway'));
    expect(fn () => $this->service->prepare($this->root))->toThrow(ConnectionException::class, 'offline');
    foreach ($this->official as $path => $bytes) {
        expect(file_get_contents($this->root.'/'.$path))->toBe($bytes);
    }
    expect(is_file($this->root.'/cms-project.json'))->toBeFalse()
        ->and($this->sources->fetches)->toBe(0);
    ($this->setupRelease)();
    $this->session = $this->service->prepare($this->root);
    $profile = $this->service->apply($this->session, 'sample', 'dbcms', 'db-cyber', 'none');
    expect($profile->coreVersion)->toBe('1.4.0')->and($this->sources->fetches)->toBe(1);
});

test('absence of a published stable never falls back to draft or prerelease sources', function (): void {
    Http::swap(new Factory);
    Http::fake(static fn () => Http::response([
        ['tag_name' => 'v3.0.1', 'draft' => true, 'prerelease' => false, 'published_at' => '2026-10-20T00:00:00Z'],
        ['tag_name' => 'v2.0.0', 'draft' => false, 'prerelease' => true, 'published_at' => '2026-10-20T00:00:00Z'],
    ]));
    expect(fn () => $this->service->prepare($this->root))->toThrow(DomainException::class, 'No published stable')
        ->and($this->sources->fetches)->toBe(0)
        ->and(is_file($this->root.'/cms-project.json'))->toBeFalse()
        ->and(file_get_contents($this->root.'/composer.json'))->toBe($this->official['composer.json']);
});
