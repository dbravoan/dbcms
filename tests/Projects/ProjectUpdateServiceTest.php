<?php

declare(strict_types=1);

use App\Contracts\Services\ProjectSourceRepositoryInterface;
use App\Contracts\Services\SourceRevisionResolverInterface;
use App\Contracts\Services\UpdateMigrationServiceInterface;
use App\Core\Updates\SignedManifestVerifier;
use App\Core\Updates\UpdateRunStore;
use App\Services\Projects\ProjectDeploymentAdoptionService;
use App\Services\Projects\ProjectInitializationService;
use App\Services\ProjectUpdates\ProjectUpdateService;
use App\Services\Updates\GithubReleaseGateway;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

/** @return array{raw:string,signature:string} */
function projectUpdateSignedFixture(array $files, string $version, string $sha, string $privateKey): array
{
    $manifest = ['schema' => 1, 'version' => $version, 'source_sha' => $sha, 'min_php' => '8.3', 'extensions' => [], 'supported_from' => ['1.2.0'], 'rollback_compatible' => true, 'migrations' => ['core' => [], 'modules' => []], 'distributions' => ['source' => ['asset' => 'source-'.$version.'.tar.gz', 'sha256' => str_repeat('f', 64), 'size' => 1, 'files' => array_map(fn (string $bytes): string => hash('sha256', $bytes), $files)]]];
    $raw = json_encode($manifest, JSON_THROW_ON_ERROR);
    openssl_sign($raw, $signature, $privateKey, OPENSSL_ALGO_SHA256);

    return ['raw' => $raw, 'signature' => json_encode(['key_id' => 'test-release', 'signature' => base64_encode($signature)], JSON_THROW_ON_ERROR)];
}

function projectUpdateFakeReleases(array $fixtures): void
{
    Http::swap(new Factory);
    $responses = [];
    foreach ($fixtures as $version => $fixture) {
        $prefix = 'https://github.com/dbravoan/dbcms/releases/download/v'.$version.'/';
        $responses['https://api.github.com/repos/dbravoan/dbcms/releases/tags/v'.$version] = ['tag_name' => 'v'.$version, 'draft' => false, 'prerelease' => false, 'published_at' => '2026-10-01T10:00:00Z', 'assets' => [['name' => 'manifest.json', 'browser_download_url' => $prefix.'manifest.json'], ['name' => 'manifest.sig', 'browser_download_url' => $prefix.'manifest.sig']]];
        $responses[$prefix.'manifest.json'] = $fixture['raw'];
        $responses[$prefix.'manifest.sig'] = $fixture['signature'];
    }
    Http::fake(function (Request $request) use ($responses) {
        if (! array_key_exists($request->url(), $responses)) {
            throw new RuntimeException('Unexpected outbound request: '.$request->url());
        }

        return Http::response($responses[$request->url()]);
    });
}

beforeEach(function (): void {
    static $privatePem;
    if ($privatePem === null) {
        $key = openssl_pkey_new(['private_key_bits' => 3072, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $privatePem);
    }
    $this->sandbox = sys_get_temp_dir().'/dbcms-project-service-'.bin2hex(random_bytes(8));
    $this->projectRoot = $this->sandbox.'/project';
    (new Filesystem)->ensureDirectoryExists($this->projectRoot.'/resources/updates');
    file_put_contents($this->sandbox.'/test-private.pem', $privatePem);
    $this->privateKey = $privatePem;
    $this->publicKey = openssl_pkey_get_details(openssl_pkey_get_private($privatePem))['key'];
    file_put_contents($this->sandbox.'/test-public.pub', $this->publicKey);
    config(['updates.trusted_keys' => ['test-release' => $this->publicKey], 'updates.storage_path' => $this->sandbox.'/private-state', 'updates.repository' => 'dbravoan/dbcms']);
    $this->profile = ['schema' => 1, 'name' => 'sample', 'brand' => 'termocms', 'themes' => ['db-minimal'], 'default_theme' => 'db-minimal', 'core' => ['repository' => 'dbravoan/dbcms', 'version' => '1.2.0', 'sha' => str_repeat('a', 40)]];
    file_put_contents($this->projectRoot.'/cms-project.json', json_encode($this->profile, JSON_THROW_ON_ERROR));
    $this->baseFiles = ['app/clean.php' => 'old', 'resources/updates/core-version.json' => '{"version":"1.2.0","repository":"dbravoan/dbcms"}', 'themes/db-minimal/theme.json' => '{"id":"db-minimal"}', 'resources/views/themes/admin/termocms/theme.json' => '{"id":"termocms"}'];
    $this->targetFiles = [...$this->baseFiles, 'app/clean.php' => 'new', 'resources/updates/core-version.json' => '{"version":"1.3.0","repository":"dbravoan/dbcms"}'];
    $this->sources = new class implements ProjectSourceRepositoryInterface
    {
        public array $files = [];

        public array $fetches = [];

        public function fetch(string $version, string $sha, string $directory): void
        {
            $this->fetches[] = [$version, $sha];
        }

        public function read(string $directory, string $sha, string $path): string
        {
            return $this->files[$sha][$path] ?? throw new DomainException('Missing fake source path.');
        }
    };
    $this->migrations = Mockery::mock(UpdateMigrationServiceInterface::class);
    $this->migrations->shouldReceive('pending')->andReturn([])->byDefault();
    $this->service = new ProjectUpdateService(new GithubReleaseGateway, new SignedManifestVerifier, $this->sources, new UpdateRunStore, $this->migrations);
    $this->refreshFixtures = function (): array {
        $base = projectUpdateSignedFixture($this->baseFiles, '1.2.0', str_repeat('a', 40), $this->privateKey);
        $target = projectUpdateSignedFixture($this->targetFiles, '1.3.0', str_repeat('b', 40), $this->privateKey);
        $this->sources->files = [str_repeat('a', 40) => $this->baseFiles, str_repeat('b', 40) => $this->targetFiles];
        projectUpdateFakeReleases(['1.2.0' => $base, '1.3.0' => $target]);
        foreach ($this->baseFiles as $path => $bytes) {
            (new Filesystem)->ensureDirectoryExists(dirname($this->projectRoot.'/'.$path));
            file_put_contents($this->projectRoot.'/'.$path, $bytes);
        }

        return [$base, $target];
    };
    ($this->refreshFixtures)();
});

afterEach(function (): void {
    (new Filesystem)->deleteDirectory($this->sandbox);
});

test('a later proposal cannot discard unresolved conflicts from an earlier update', function (): void {
    file_put_contents($this->projectRoot.'/app/clean.php', 'project customization');
    $this->service->prepare($this->projectRoot, '1.3.0');
    $report = file_get_contents($this->projectRoot.'/resources/updates/project-update.json');
    $fetches = $this->sources->fetches;

    expect(fn () => $this->service->prepare($this->projectRoot, '1.4.0'))->toThrow(DomainException::class, 'Resolve and acknowledge');
    expect(file_get_contents($this->projectRoot.'/resources/updates/project-update.json'))->toBe($report)
        ->and($this->sources->fetches)->toBe($fetches);
});

test('signed Git preparation preserves customization conflicts and excludes omitted theme families', function (): void {
    $this->baseFiles += ['app/custom.php' => 'base', 'app/conflict.php' => 'base', 'app/deleted.php' => 'delete'];
    $this->targetFiles += ['app/custom.php' => 'base', 'app/conflict.php' => 'upstream', 'app/added.php' => 'added', 'themes/db-cyber/theme.json' => '{}'];
    ($this->refreshFixtures)();
    file_put_contents($this->projectRoot.'/app/custom.php', 'project');
    file_put_contents($this->projectRoot.'/app/conflict.php', 'project');
    file_put_contents($this->projectRoot.'/.env', 'PRIVATE_SENTINEL');
    $plan = $this->service->prepare($this->projectRoot, '1.3.0');

    expect(file_get_contents($this->projectRoot.'/app/clean.php'))->toBe('new')
        ->and(file_get_contents($this->projectRoot.'/app/custom.php'))->toBe('project')
        ->and(file_get_contents($this->projectRoot.'/app/conflict.php'))->toBe('project')
        ->and(file_exists($this->projectRoot.'/app/deleted.php'))->toBeFalse()
        ->and(file_exists($this->projectRoot.'/themes/db-cyber'))->toBeFalse()
        ->and(file_get_contents($this->projectRoot.'/.env'))->toBe('PRIVATE_SENTINEL')
        ->and($plan->conflicts)->toBe([['path' => 'app/conflict.php', 'reason' => 'locally_modified']]);
    expect(fn () => $this->service->review($this->projectRoot))->toThrow(DomainException::class);
});

test('all signed Git bytes are verified before the first checkout mutation', function (): void {
    $this->sources->files[str_repeat('b', 40)]['app/clean.php'] = 'tampered';
    expect(fn () => $this->service->prepare($this->projectRoot, '1.3.0'))->toThrow(DomainException::class);
    expect(file_get_contents($this->projectRoot.'/app/clean.php'))->toBe('old')
        ->and(json_decode(file_get_contents($this->projectRoot.'/cms-project.json'), true)['core']['version'])->toBe('1.2.0');
});

test('project-owned selected themes survive an upstream release without becoming mandatory upstream files', function (): void {
    $profile = $this->profile;
    $profile['themes'][] = 'own-site';
    $profile['default_theme'] = 'own-site';
    file_put_contents($this->projectRoot.'/cms-project.json', json_encode($profile, JSON_THROW_ON_ERROR));
    mkdir($this->projectRoot.'/themes/own-site');
    file_put_contents($this->projectRoot.'/themes/own-site/theme.json', '{"identifier":"own-site"}');
    file_put_contents($this->projectRoot.'/themes/own-site/theme.css', 'OWN_THEME');
    $plan = $this->service->prepare($this->projectRoot, '1.3.0');

    expect($plan->conflicts)->toBeEmpty()
        ->and(file_get_contents($this->projectRoot.'/themes/own-site/theme.css'))->toBe('OWN_THEME')
        ->and(file_get_contents($this->projectRoot.'/app/clean.php'))->toBe('new');
});

test('generated metadata symlinks are rejected before any source mutation', function (string $metadata): void {
    file_put_contents($this->sandbox.'/outside', 'EXTERNAL_SENTINEL');
    symlink($this->sandbox.'/outside', $this->projectRoot.'/resources/updates/'.$metadata);
    expect(fn () => $this->service->prepare($this->projectRoot, '1.3.0'))->toThrow(DomainException::class);
    expect(file_get_contents($this->sandbox.'/outside'))->toBe('EXTERNAL_SENTINEL')
        ->and(file_get_contents($this->projectRoot.'/app/clean.php'))->toBe('old');
})->with(['installed-manifest.json', 'installed-manifest.sig', 'project-update.json']);

test('a selected theme or brand cannot disappear from the target release', function (string $path): void {
    unset($this->targetFiles[$path]);
    ($this->refreshFixtures)();
    expect(fn () => $this->service->prepare($this->projectRoot, '1.3.0'))->toThrow(DomainException::class);
    expect(file_get_contents($this->projectRoot.'/app/clean.php'))->toBe('old');
})->with(['themes/db-minimal/theme.json', 'resources/views/themes/admin/termocms/theme.json']);

test('historical trusted keys cannot be replaced under the same key identifier', function (): void {
    $this->baseFiles['resources/updates/trusted-keys/test-release.pub'] = $this->publicKey;
    $this->targetFiles['resources/updates/trusted-keys/test-release.pub'] = 'replacement';
    ($this->refreshFixtures)();
    expect(fn () => $this->service->prepare($this->projectRoot, '1.3.0'))->toThrow(DomainException::class);
    expect(file_get_contents($this->projectRoot.'/app/clean.php'))->toBe('old');
});

test('project profiles reject unsafe or inconsistent theme selections', function (array $selection): void {
    file_put_contents($this->projectRoot.'/cms-project.json', json_encode([...$this->profile, ...$selection], JSON_THROW_ON_ERROR));
    expect(fn () => $this->service->prepare($this->projectRoot, '1.3.0'))->toThrow(DomainException::class);
    expect($this->sources->fetches)->toBeEmpty();
})->with([
    'traversal theme' => [['themes' => ['../external']]],
    'missing default' => [['default_theme' => 'db-cyber']],
    'duplicate theme' => [['themes' => ['db-minimal', 'db-minimal']]],
    'theme map' => [['themes' => ['chosen' => 'db-minimal']]],
]);

test('reconciliation requires a prepared report before adopting signed provenance', function (): void {
    [, $target] = ($this->refreshFixtures)();
    file_put_contents($this->projectRoot.'/resources/updates/installed-manifest.json', $target['raw']);
    file_put_contents($this->projectRoot.'/resources/updates/installed-manifest.sig', $target['signature']);
    (new Filesystem)->ensureDirectoryExists($this->projectRoot.'/storage');
    touch($this->projectRoot.'/storage/installed');
    expect(fn () => $this->service->reconcile($this->projectRoot))->toThrow(DomainException::class);
    expect(file_exists($this->sandbox.'/private-state/identity.json'))->toBeFalse();
});

test('reconciliation adopts only the exact prepared signed target after migrations', function (): void {
    $this->service->prepare($this->projectRoot, '1.3.0');
    (new Filesystem)->ensureDirectoryExists($this->projectRoot.'/storage');
    touch($this->projectRoot.'/storage/installed');
    file_put_contents($this->projectRoot.'/app/clean.php', 'reviewed project customization');
    $this->service->reconcile($this->projectRoot);
    $identity = json_decode(file_get_contents($this->sandbox.'/private-state/identity.json'), true, 64, JSON_THROW_ON_ERROR);
    expect($identity['version'])->toBe('1.3.0')->and($identity['source_sha'])->toBe(str_repeat('b', 40))
        ->and($identity['customizations'])->toContain('app/clean.php')->and($identity['managed_project'])->toBe('sample');
});

test('review rejects an old core descriptor even when the proposed profile names the signed target', function (): void {
    $this->service->prepare($this->projectRoot, '1.3.0');
    file_put_contents($this->projectRoot.'/resources/updates/core-version.json', $this->baseFiles['resources/updates/core-version.json']);
    expect(fn () => $this->service->review($this->projectRoot))->toThrow(DomainException::class);
});

test('a forged signed target is rejected before fetching source', function (): void {
    [$base, $target] = ($this->refreshFixtures)();
    $target['raw'] = str_replace('"version":"1.3.0"', '"version":"1.4.0"', $target['raw']);
    projectUpdateFakeReleases(['1.2.0' => $base, '1.3.0' => $target]);
    expect(fn () => $this->service->prepare($this->projectRoot, '1.3.0'))->toThrow(DomainException::class);
    expect($this->sources->fetches)->toBeEmpty()
        ->and(file_get_contents($this->projectRoot.'/app/clean.php'))->toBe('old');
});

test('the signed baseline commit must match the project provenance', function (): void {
    $profile = $this->profile;
    $profile['core']['sha'] = str_repeat('c', 40);
    file_put_contents($this->projectRoot.'/cms-project.json', json_encode($profile, JSON_THROW_ON_ERROR));
    expect(fn () => $this->service->prepare($this->projectRoot, '1.3.0'))->toThrow(DomainException::class);
    expect($this->sources->fetches)->toBeEmpty();
});

test('source parent symlinks cannot redirect an otherwise signed update', function (): void {
    (new Filesystem)->deleteDirectory($this->projectRoot.'/app');
    mkdir($this->sandbox.'/external');
    file_put_contents($this->sandbox.'/external/clean.php', 'EXTERNAL_SENTINEL');
    symlink($this->sandbox.'/external', $this->projectRoot.'/app');
    expect(fn () => $this->service->prepare($this->projectRoot, '1.3.0'))->toThrow(DomainException::class);
    expect(file_get_contents($this->sandbox.'/external/clean.php'))->toBe('EXTERNAL_SENTINEL')
        ->and(file_exists($this->projectRoot.'/resources/updates/project-update.json'))->toBeFalse();
});

test('pending migrations preserve the previous adopted identity', function (): void {
    $this->service->prepare($this->projectRoot, '1.3.0');
    (new Filesystem)->ensureDirectoryExists($this->projectRoot.'/storage');
    touch($this->projectRoot.'/storage/installed');
    $store = new UpdateRunStore;
    $store->atomic($store->root().'/identity.json', '{"version":"previous"}');
    $this->migrations->shouldReceive('pending')->once()->andReturn(['database/migrations/pending.php']);
    expect(fn () => $this->service->reconcile($this->projectRoot))->toThrow(DomainException::class);
    expect(file_get_contents($store->root().'/identity.json'))->toBe('{"version":"previous"}');
});

test('reconciliation rejects profile provenance that differs from the signed prepared release', function (): void {
    $this->service->prepare($this->projectRoot, '1.3.0');
    (new Filesystem)->ensureDirectoryExists($this->projectRoot.'/storage');
    touch($this->projectRoot.'/storage/installed');
    $profile = json_decode(file_get_contents($this->projectRoot.'/cms-project.json'), true, 64, JSON_THROW_ON_ERROR);
    $profile['core']['sha'] = str_repeat('c', 40);
    file_put_contents($this->projectRoot.'/cms-project.json', json_encode($profile, JSON_THROW_ON_ERROR));
    expect(fn () => $this->service->reconcile($this->projectRoot))->toThrow(DomainException::class);
    expect(file_exists($this->sandbox.'/private-state/identity.json'))->toBeFalse();
});

test('legacy projects explicitly adopt a deployment only after signed controls are proven intact', function (): void {
    $this->baseFiles += ['bin/deploy-container.sh' => 'official-docker', '.github/workflows/ci.yml' => 'official-ci', '.github/workflows/deploy.yml' => 'official-deploy'];
    ($this->refreshFixtures)();
    file_put_contents($this->projectRoot.'/bin/deploy-own.sh', 'own-control');
    file_put_contents($this->projectRoot.'/.github/workflows/own.yml', 'own-workflow');
    $adoption = new ProjectDeploymentAdoptionService(new GithubReleaseGateway, new SignedManifestVerifier, $this->sources);
    $resolver = Mockery::mock(SourceRevisionResolverInterface::class);
    $initializer = new ProjectInitializationService(new Filesystem, $resolver, $this->projectRoot, $adoption);
    $profile = $initializer->initialize('sample', 'termocms', 'db-minimal', 'rsync');

    expect($profile->deployment)->toBe('rsync')
        ->and(json_decode(file_get_contents($this->projectRoot.'/cms-project.json'), true)['deployment'])->toBe('rsync')
        ->and(file_exists($this->projectRoot.'/bin/deploy-container.sh'))->toBeFalse()
        ->and(file_exists($this->projectRoot.'/.github/workflows/ci.yml'))->toBeFalse()
        ->and(file_get_contents($this->projectRoot.'/bin/deploy-own.sh'))->toBe('own-control')
        ->and(file_get_contents($this->projectRoot.'/.github/workflows/own.yml'))->toBe('own-workflow');
    expect($this->sources->fetches)->toBe([['1.2.0', str_repeat('a', 40)]]);
});

test('legacy deployment adoption rejects customized ambiguous or tampered controls before any deletion', function (string $failure): void {
    $this->baseFiles += ['bin/deploy-container.sh' => 'official-docker', '.github/workflows/ci.yml' => 'official-ci'];
    ($this->refreshFixtures)();
    if ($failure === 'customized') {
        file_put_contents($this->projectRoot.'/bin/deploy-container.sh', 'project-customization');
    } elseif ($failure === 'unproven') {
        file_put_contents($this->projectRoot.'/bin/deploy-ansistrano', 'unproven-control');
    } elseif ($failure === 'tampered') {
        $this->sources->files[str_repeat('a', 40)]['bin/deploy-container.sh'] = 'tampered-git';
    } else {
        $profile = $this->profile;
        $profile['core']['sha'] = str_repeat('f', 40);
        file_put_contents($this->projectRoot.'/cms-project.json', json_encode($profile, JSON_THROW_ON_ERROR));
    }
    $before = file_get_contents($this->projectRoot.'/cms-project.json');
    $adoption = new ProjectDeploymentAdoptionService(new GithubReleaseGateway, new SignedManifestVerifier, $this->sources);
    $initializer = new ProjectInitializationService(new Filesystem, Mockery::mock(SourceRevisionResolverInterface::class), $this->projectRoot, $adoption);
    expect(fn () => $initializer->initialize('sample', 'termocms', 'db-minimal', 'rsync'))->toThrow(DomainException::class);
    expect(file_exists($this->projectRoot.'/bin/deploy-container.sh'))->toBeTrue()
        ->and(file_exists($this->projectRoot.'/.github/workflows/ci.yml'))->toBeTrue()
        ->and(file_get_contents($this->projectRoot.'/cms-project.json'))->toBe($before);
})->with(['customized', 'unproven', 'tampered', 'wrong-baseline']);

test('signed updates preserve deployment selection and do not restore excluded github or deployment controls', function (): void {
    $profile = [...$this->profile, 'deployment' => 'rsync'];
    file_put_contents($this->projectRoot.'/cms-project.json', json_encode($profile, JSON_THROW_ON_ERROR));
    $this->targetFiles += ['.github/workflows/ci.yml' => 'upstream-ci', 'bin/deploy-container.sh' => 'upstream-docker', 'bin/deploy-rsync' => 'upstream-rsync'];
    ($this->refreshFixtures)();
    $plan = $this->service->prepare($this->projectRoot, '1.3.0');
    expect(json_decode(file_get_contents($this->projectRoot.'/cms-project.json'), true)['deployment'])->toBe('rsync')
        ->and(file_exists($this->projectRoot.'/.github'))->toBeFalse()
        ->and(file_exists($this->projectRoot.'/bin/deploy-container.sh'))->toBeFalse()
        ->and(file_get_contents($this->projectRoot.'/bin/deploy-rsync'))->toBe('upstream-rsync')
        ->and($plan->excluded)->toContain('.github/workflows/ci.yml', 'bin/deploy-container.sh');
    mkdir($this->projectRoot.'/storage');
    file_put_contents($this->projectRoot.'/storage/installed', 'yes');
    $this->service->reconcile($this->projectRoot);
    $identity = json_decode(file_get_contents($this->sandbox.'/private-state/identity.json'), true);
    expect($identity['excluded'])->toContain('.github/workflows/ci.yml', 'bin/deploy-container.sh');
});

test('unknown deployment metadata is rejected before a core update changes files', function (): void {
    file_put_contents($this->projectRoot.'/cms-project.json', json_encode([...$this->profile, 'deployment' => 'ssh'], JSON_THROW_ON_ERROR));
    expect(fn () => $this->service->prepare($this->projectRoot, '1.3.0'))->toThrow(DomainException::class);
    expect(file_get_contents($this->projectRoot.'/app/clean.php'))->toBe('old');
});
