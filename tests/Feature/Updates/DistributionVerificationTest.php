<?php

declare(strict_types=1);

use App\Contracts\Services\UpdateDeploymentServiceInterface;
use App\Core\Updates\SafeUpdateArchive;
use App\Core\Updates\SignedManifestVerifier;
use App\Core\Updates\UpdateRunStore;
use App\Models\Role;
use App\Models\User;
use App\Services\Updates\GithubReleaseGateway;
use App\Services\Updates\UpdatePreparationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

function distributionFixture(array $files = ['app/example.php' => '<?php return 1;']): array
{
    static $key;
    $key ??= openssl_pkey_new(['private_key_bits' => 3072, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    $inventory = array_map(fn (string $value): string => hash('sha256', $value), $files);
    $manifest = ['schema' => 1, 'version' => '1.1.0', 'source_sha' => str_repeat('b', 40), 'min_php' => '8.3.0', 'extensions' => [], 'supported_from' => ['1.0.1'], 'rollback_compatible' => true, 'migrations' => ['core' => [], 'modules' => []], 'distributions' => ['source' => ['asset' => 'source.zip', 'sha256' => str_repeat('a', 64), 'size' => 1, 'files' => $inventory], 'runtime' => ['asset' => 'runtime.zip', 'sha256' => str_repeat('a', 64), 'size' => 1, 'files' => $inventory]]];

    return [$manifest, $key, openssl_pkey_get_details($key)['key']];
}

function distributionSign(array $manifest, OpenSSLAsymmetricKey $key): array
{
    $raw = json_encode($manifest, JSON_THROW_ON_ERROR);
    openssl_sign($raw, $signature, $key, OPENSSL_ALGO_SHA256);

    return [$raw, json_encode(['key_id' => 'test', 'signature' => base64_encode($signature)], JSON_THROW_ON_ERROR)];
}

function distributionPrepareFixture(string $directory, bool $managed): UpdatePreparationService
{
    [$manifest, $key, $public] = distributionFixture(['app/example.php' => 'new content']);
    $baseline = $manifest;
    $baseline['version'] = '1.0.1';
    $baseline['source_sha'] = str_repeat('a', 40);
    foreach (['source', 'runtime'] as $dist) {
        $baseline['distributions'][$dist]['files'] = ['app/example.php' => hash('sha256', 'old content')];
    }
    [$oldRaw, $oldSignature] = distributionSign($baseline, $key);
    config(['updates.trusted_keys' => ['test' => $public]]);
    $store = new UpdateRunStore;
    $store->atomic($store->root().'/identity.json', json_encode(['version' => '1.0.1', 'source_sha' => str_repeat('a', 40), 'distribution' => 'runtime', 'manifest_raw' => $oldRaw, 'signature_raw' => $oldSignature], JSON_THROW_ON_ERROR));
    mkdir($directory.'/root/app');
    file_put_contents($directory.'/root/app/example.php', 'old content');
    mkdir($directory.'/root/.github/workflows', 0700, true);
    file_put_contents($directory.'/root/.github/workflows/ci.yml', 'included in official ZIP');
    if ($managed) {
        file_put_contents($directory.'/root/.git', 'gitdir: external-worktree');
    }
    $zip = new ZipArchive;
    $archive = $directory.'/download.zip';
    $zip->open($archive, ZipArchive::CREATE);
    $zip->addFromString('app/example.php', 'new content');
    $zip->close();
    foreach (['source', 'runtime'] as $dist) {
        $manifest['distributions'][$dist]['sha256'] = hash_file('sha256', $archive);
        $manifest['distributions'][$dist]['size'] = filesize($archive);
    }
    [$raw, $signature] = distributionSign($manifest, $key);
    $url = 'https://github.com/dbravoan/dbcms/releases/download/v1.1.0/';
    $assets = array_map(fn (string $name): array => ['name' => $name, 'browser_download_url' => $url.$name], ['manifest.json', 'manifest.sig', 'source.zip', 'runtime.zip']);
    Http::fake(['api.github.com/repos/dbravoan/dbcms/releases/tags/v1.1.0' => Http::response(['draft' => false, 'prerelease' => false, 'published_at' => '2026-10-14', 'tag_name' => 'v1.1.0', 'assets' => $assets]), $url.'manifest.json' => Http::response($raw), $url.'manifest.sig' => Http::response($signature), $url.'source.zip' => Http::response(file_get_contents($archive)), $url.'runtime.zip' => Http::response(file_get_contents($archive))]);

    return app(UpdatePreparationService::class);
}

function distributionSourceRuntimeFixture(string $directory, bool $withRuntime = true, string $installedVersion = '1.0.1'): UpdatePreparationService
{
    $source = ['app/example.php' => 'old content', 'composer.json' => '{"name":"dbravoan/dbcms"}', 'composer.lock' => '{"packages":[]}'];
    $runtime = [...$source, 'vendor/autoload.php' => 'official autoloader', 'vendor/composer/installed.php' => 'official installed dependencies', 'public/build/manifest.json' => '{"app":"app.js"}', 'public/build/app.js' => 'official compiled asset'];
    [$manifest, $key, $public] = distributionFixture();
    $manifest['supported_from'] = [$installedVersion];
    $baseline = $manifest;
    $baseline['version'] = $installedVersion;
    $baseline['source_sha'] = str_repeat('a', 40);
    $baseline['distributions']['source']['files'] = array_map(fn (string $content): string => hash('sha256', $content), $source);
    if ($withRuntime) {
        $baseline['distributions']['runtime']['files'] = array_map(fn (string $content): string => hash('sha256', $content), $runtime);
    } else {
        unset($baseline['distributions']['runtime']);
    }
    [$oldRaw, $oldSignature] = distributionSign($baseline, $key);
    config(['updates.trusted_keys' => ['test' => $public]]);
    $store = new UpdateRunStore;
    $store->atomic($store->root().'/identity.json', json_encode(['version' => $installedVersion, 'source_sha' => str_repeat('a', 40), 'distribution' => 'source', 'manifest_raw' => $oldRaw, 'signature_raw' => $oldSignature], JSON_THROW_ON_ERROR));
    foreach ($runtime as $path => $contents) {
        if (! is_dir(dirname($directory.'/root/'.$path))) {
            mkdir(dirname($directory.'/root/'.$path), 0700, true);
        }
        file_put_contents($directory.'/root/'.$path, $contents);
    }
    $newSource = [...$source, 'app/example.php' => 'new content'];
    $newRuntime = [...$runtime, ...$newSource, 'vendor/autoload.php' => 'new official autoloader'];
    $archives = [];
    foreach (['source' => $newSource, 'runtime' => $newRuntime] as $name => $files) {
        $zip = new ZipArchive;
        $path = $directory.'/'.$name.'.zip';
        $zip->open($path, ZipArchive::CREATE);
        foreach ($files as $file => $contents) {
            $zip->addFromString($file, $contents);
        }
        $zip->close();
        $archives[$name] = file_get_contents($path);
        $manifest['distributions'][$name] = ['asset' => $name.'.zip', 'sha256' => hash_file('sha256', $path), 'size' => filesize($path), 'files' => array_map(fn (string $contents): string => hash('sha256', $contents), $files)];
    }
    [$raw, $signature] = distributionSign($manifest, $key);
    $url = 'https://github.com/dbravoan/dbcms/releases/download/v1.1.0/';
    $assets = array_map(fn (string $name): array => ['name' => $name, 'browser_download_url' => $url.$name], ['manifest.json', 'manifest.sig', 'source.zip', 'runtime.zip']);
    Http::fake(['api.github.com/repos/dbravoan/dbcms/releases/tags/v1.1.0' => Http::response(['draft' => false, 'prerelease' => false, 'published_at' => '2026-10-14', 'tag_name' => 'v1.1.0', 'assets' => $assets]), $url.'manifest.json' => Http::response($raw), $url.'manifest.sig' => Http::response($signature), $url.'source.zip' => Http::response($archives['source']), $url.'runtime.zip' => Http::response($archives['runtime'])]);

    return app(UpdatePreparationService::class);
}

beforeEach(function (): void {
    $this->updateTmp = sys_get_temp_dir().'/distribution-'.bin2hex(random_bytes(8));
    mkdir($this->updateTmp, 0700);
    mkdir($this->updateTmp.'/root', 0700);
    config(['updates.root_path' => $this->updateTmp.'/root', 'updates.storage_path' => $this->updateTmp.'/state']);
    Http::preventStrayRequests();
    $role = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
    $this->updateActor = User::factory()->create();
    $this->updateActor->assignRole($role);
    app()->instance(UpdateDeploymentServiceInterface::class, new class implements UpdateDeploymentServiceInterface
    {
        public function isManaged(string $root): bool
        {
            return file_exists($root.'/.git');
        }
    });
});

afterEach(function (): void {
    File::deleteDirectory($this->updateTmp);
});

it('verifies exact manifest bytes and rejects tampering or weak signatures', function (): void {
    [$manifest, $key, $public] = distributionFixture();
    [$raw, $signature] = distributionSign($manifest, $key);
    expect((new SignedManifestVerifier)->verify($raw, $signature, ['test' => $public])->data['version'])->toBe('1.1.0');
    expect(fn () => (new SignedManifestVerifier)->verify($raw.' ', $signature, ['test' => $public]))->toThrow(DomainException::class);
});

it('admits only stable published releases and rejects redirected private network downloads', function (): void {
    $release = ['draft' => false, 'prerelease' => false, 'published_at' => '2026-10-14', 'tag_name' => 'v1.1.0'];
    Http::fake(['api.github.com/repos/dbravoan/dbcms/releases?per_page=100' => Http::response([$release, [...$release, 'draft' => true], [...$release, 'prerelease' => true], [...$release, 'tag_name' => 'v1.2.0-beta']]), 'github.com/dbravoan/dbcms/releases/download/v1.1.0/a.zip' => Http::response('', 302, ['Location' => 'https://127.0.0.1/private'])]);
    expect((new GithubReleaseGateway)->listStable())->toHaveCount(1);
    expect(fn () => (new GithubReleaseGateway)->contents('https://github.com/dbravoan/dbcms/releases/download/v1.1.0/a.zip'))->toThrow(DomainException::class);
    Http::assertSentCount(2);
});

it('extracts only a complete signed inventory and rejects extra entries and symlinks', function (): void {
    $zip = new ZipArchive;
    $archive = $this->updateTmp.'/package.zip';
    $zip->open($archive, ZipArchive::CREATE);
    $zip->addFromString('app/a.php', 'hello');
    $zip->close();
    (new SafeUpdateArchive)->extract($archive, $this->updateTmp.'/stage', ['app/a.php' => hash('sha256', 'hello')]);
    expect(file_get_contents($this->updateTmp.'/stage/app/a.php'))->toBe('hello');
    $zip->open($archive);
    $zip->addFromString('app/b.php', 'extra');
    $zip->close();
    expect(fn () => (new SafeUpdateArchive)->extract($archive, $this->updateTmp.'/stage2', ['app/a.php' => hash('sha256', 'hello')]))->toThrow(DomainException::class);
    $zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('app/a.php', 'hello');
    $zip->setExternalAttributesName('app/a.php', ZipArchive::OPSYS_UNIX, 0120777 << 16);
    $zip->close();
    expect(fn () => (new SafeUpdateArchive)->extract($archive, $this->updateTmp.'/stage3', ['app/a.php' => hash('sha256', 'hello')]))->toThrow(DomainException::class);
});

it('authorizes trust key additions only through an already signed next key declaration', function (): void {
    [$manifest, $key, $public] = distributionFixture();
    $path = 'resources/updates/trusted-keys/rotated.pub';
    $manifest['distributions']['source']['files'][$path] = hash('sha256', $public);
    [$raw, $signature] = distributionSign($manifest, $key);
    expect(fn () => (new SignedManifestVerifier)->verify($raw, $signature, ['test' => $public]))->toThrow(DomainException::class);
    $manifest['next_keys'] = ['rotated' => $public];
    [$raw, $signature] = distributionSign($manifest, $key);
    expect((new SignedManifestVerifier)->verify($raw, $signature, ['test' => $public])->data['next_keys']['rotated'])->toBe($public);
});

it('adopts signed original hashes while recording customizations without changing local files', function (): void {
    [$manifest, $key, $public] = distributionFixture();
    $manifest['version'] = '1.0.1';
    [$raw, $signature] = distributionSign($manifest, $key);
    config(['updates.trusted_keys' => ['test' => $public]]);
    mkdir($this->updateTmp.'/root/app');
    file_put_contents($this->updateTmp.'/root/app/example.php', 'customized');
    $url = 'https://github.com/dbravoan/dbcms/releases/download/v1.0.1/';
    Http::fake(['api.github.com/repos/dbravoan/dbcms/releases/tags/v1.0.1' => Http::response(['draft' => false, 'prerelease' => false, 'published_at' => '2026-10-14', 'tag_name' => 'v1.0.1', 'assets' => [['name' => 'manifest.json', 'browser_download_url' => $url.'manifest.json'], ['name' => 'manifest.sig', 'browser_download_url' => $url.'manifest.sig']]]), $url.'manifest.json' => Http::response($raw), $url.'manifest.sig' => Http::response($signature)]);
    $identity = app(UpdatePreparationService::class)->adopt('1.0.1', 'source', $this->updateActor->id);
    expect($identity['customizations'])->toHaveCount(1)->and(file_get_contents($this->updateTmp.'/root/app/example.php'))->toBe('customized');
    expect((new UpdateRunStore)->identity()['files']['app/example.php'])->toBe(hash('sha256', '<?php return 1;'));
    expect($identity)->not->toHaveKey('manifest_raw');
});

it('rejects an invalid adoption signature without trying historical release fallbacks', function (): void {
    [$manifest, $key, $public] = distributionFixture();
    $manifest['version'] = '1.0.1';
    [$raw, $signature] = distributionSign($manifest, $key);
    config(['updates.trusted_keys' => ['test' => $public]]);
    $url = 'https://github.com/dbravoan/dbcms/releases/download/v1.0.1/';
    Http::preventStrayRequests();
    Http::fake([
        'api.github.com/repos/dbravoan/dbcms/releases/tags/v1.0.1' => Http::response([
            'draft' => false, 'prerelease' => false, 'published_at' => '2026-10-14', 'tag_name' => 'v1.0.1',
            'assets' => [['name' => 'manifest.json', 'browser_download_url' => $url.'manifest.json'], ['name' => 'manifest.sig', 'browser_download_url' => $url.'manifest.sig']],
        ]),
        $url.'manifest.json' => Http::response($raw.' '),
        $url.'manifest.sig' => Http::response($signature),
    ]);
    expect(fn () => app(UpdatePreparationService::class)->adopt('1.0.1', 'source', $this->updateActor->id))->toThrow(DomainException::class, 'Firma de actualización no válida.');
    expect((new UpdateRunStore)->identity())->toBeNull();
    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'baseline-') || $request->url() === 'https://api.github.com/repos/dbravoan/dbcms/releases');
});

it('refuses preparation without a cryptographically verified baseline', function (): void {
    file_put_contents($this->updateTmp.'/root/VERSION', '1.0.1');
    expect(fn () => app(UpdatePreparationService::class)->prepare('1.1.0', $this->updateActor->id))->toThrow(DomainException::class);
    Http::assertNothingSent();
});

it('prepares a runtime ZIP privately despite packaged workflow files', function (): void {
    $service = distributionPrepareFixture($this->updateTmp, false);
    $public = $service->prepare('1.1.0', $this->updateActor->id);
    $run = (new UpdateRunStore)->read($public['id']);
    expect($public['mode'])->toBe('runtime')->and($public['status'])->toBe('prepared')->and($public)->not->toHaveKeys(['manifest_raw', 'stage_path']);
    expect($run['plan'])->toHaveCount(1)->and(file_get_contents($run['stage_path'].'/app/example.php'))->toBe('new content');
    expect(file_get_contents($this->updateTmp.'/root/app/example.php'))->toBe('old content');
});

it('exports a source-only managed proposal with reviewed files and ownership', function (): void {
    $service = distributionPrepareFixture($this->updateTmp, true);
    $public = $service->prepare('1.1.0', $this->updateActor->id);
    expect($public['mode'])->toBe('managed')->and($public['status'])->toBe('managed');
    expect(fn () => $service->proposal($public['id'], 999999))->toThrow(AuthorizationException::class);
    $archive = $service->proposal($public['id'], $this->updateActor->id);
    $zip = new ZipArchive;
    $zip->open($archive);
    expect($zip->getFromName('files/app/example.php'))->toBe('new content')->and($zip->getFromName('README.txt'))->toContain('revise eliminaciones');
    expect($zip->getFromName('files/resources/updates/installed-manifest.json'))->toBeString()->and($zip->getFromName('files/resources/updates/installed-manifest.sig'))->toBeString();
    $zip->close();
    expect(file_get_contents($this->updateTmp.'/root/app/example.php'))->toBe('old content');
});

it('keeps customized files untouched and blocks their concurrent target changes', function (): void {
    $service = distributionPrepareFixture($this->updateTmp, false);
    file_put_contents($this->updateTmp.'/root/app/example.php', 'personalized');
    $public = $service->prepare('1.1.0', $this->updateActor->id);
    expect($public['status'])->toBe('conflict')->and($public['conflicts'])->toHaveCount(1);
    expect(file_get_contents($this->updateTmp.'/root/app/example.php'))->toBe('personalized');
});

it('denies preparation and adoption from actors without administrative update authority', function (): void {
    $unprivileged = User::factory()->create();
    $service = app(UpdatePreparationService::class);
    expect(fn () => $service->prepare('1.1.0', $unprivileged->id))->toThrow(AuthorizationException::class);
    expect(fn () => $service->adopt('1.0.1', 'source', $unprivileged->id))->toThrow(AuthorizationException::class);
    $this->updateActor->forceFill(['is_service_account' => true])->save();
    expect(fn () => $service->prepare('1.1.0', $this->updateActor->id))->toThrow(AuthorizationException::class);
    Http::assertNothingSent();
});

it('allows standalone source projects to prepare a runtime update with exact signed dependencies and assets', function (): void {
    $service = distributionSourceRuntimeFixture($this->updateTmp);
    $prepared = $service->prepare('1.1.0', $this->updateActor->id);
    $run = (new UpdateRunStore)->read($prepared['id']);
    expect($prepared['mode'])->toBe('runtime')->and($prepared['status'])->toBe('prepared');
    expect($run['distribution'])->toBe('runtime')->and($run['target_identity']['distribution'])->toBe('runtime');
    expect(array_column($run['plan'], 'path'))->toContain('vendor/autoload.php');
    expect(file_get_contents($this->updateTmp.'/root/vendor/autoload.php'))->toBe('official autoloader');
    expect((new UpdateRunStore)->identity()['distribution'])->toBe('source');
});

it('keeps source projects with unverifiable runtime trees on the source review path', function (string $change): void {
    $service = distributionSourceRuntimeFixture($this->updateTmp);
    $root = $this->updateTmp.'/root';
    match ($change) {
        'vendor' => file_put_contents($root.'/vendor/autoload.php', 'custom autoloader'),
        'dependencies' => file_put_contents($root.'/composer.json', '{"name":"custom/project"}'),
        'extra-vendor' => file_put_contents($root.'/vendor/custom-package.php', 'extra dependency'),
        'extra-build' => file_put_contents($root.'/public/build/custom.js', 'extra compiled asset'),
        'symlink' => symlink($root.'/app/example.php', $root.'/vendor/unverified-link'),
    };
    $before = file_get_contents($root.'/vendor/autoload.php');
    $prepared = $service->prepare('1.1.0', $this->updateActor->id);
    $run = (new UpdateRunStore)->read($prepared['id']);
    expect($prepared['mode'])->toBe('managed')->and($run['distribution'])->toBe('source');
    expect(array_column($run['plan'], 'path'))->not->toContain('vendor/autoload.php');
    expect(file_get_contents($root.'/vendor/autoload.php'))->toBe($before);
})->with(['vendor', 'dependencies', 'extra-vendor', 'extra-build', 'symlink']);

it('requires managed review when the original source release has no signed runtime inventory', function (): void {
    $service = distributionSourceRuntimeFixture($this->updateTmp, false, '1.0.0');
    $prepared = $service->prepare('1.1.0', $this->updateActor->id);
    expect($prepared['mode'])->toBe('managed');
    expect((new UpdateRunStore)->read($prepared['id'])['distribution'])->toBe('source');
    expect(file_get_contents($this->updateTmp.'/root/vendor/autoload.php'))->toBe('official autoloader');
});
