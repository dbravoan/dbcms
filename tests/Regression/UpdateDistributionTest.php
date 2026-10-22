<?php

declare(strict_types=1);

use App\Core\Updates\InventoryComparator;
use App\Core\Updates\SignedManifestVerifier;
use App\Core\Updates\UpdatePathPolicy;
use Symfony\Component\Process\Process;

it('preserves customizations and blocks changed or deleted customized files', function (): void {
    $a = str_repeat('a', 64);
    $b = str_repeat('b', 64);
    $c = str_repeat('c', 64);
    $result = (new InventoryComparator)->compare(['app/a.php' => $a, 'app/b.php' => $a], ['app/a.php' => $b, 'app/b.php' => $b], ['app/a.php' => $a, 'app/b.php' => $c]);
    expect($result['preserved'])->toContain('app/a.php')->and($result['conflicts'])->toHaveCount(1)->and($result['plan'])->toBe([]);
    $deleted = (new InventoryComparator)->compare(['app/a.php' => $a], ['app/a.php' => $b], []);
    expect($deleted['conflicts'])->toHaveCount(1);
});

it('rejects private paths and traversal before extraction', function (string $path): void {
    expect((new UpdatePathPolicy)->allows($path))->toBeFalse();
})->with(['../secret', '.env', 'storage/installed', 'public/storage/file.php', '.git/config', 'bootstrap/cache/config.php', 'app/../.env', '/etc/passwd', 'app\\file.php', 'app//a.php', 'C:secret', 'app/a.php ', 'APP/../.ENV', '.ENV', "app/\x01a.php"]);

it('fails closed on an unknown manifest signing key', function (): void {
    (new SignedManifestVerifier)->verify('{}', '{"key_id":"unknown","signature":"YWJj"}', []);
})->throws(DomainException::class);

it('builds and verifies signed source and runtime artifacts without private state', function (): void {
    $tmp = sys_get_temp_dir().'/update-build-'.bin2hex(random_bytes(8));
    mkdir($tmp, 0700);
    foreach (['source/app', 'runtime/app', 'runtime/vendor', 'runtime/storage'] as $directory) {
        mkdir($tmp.'/'.$directory, 0700, true);
    }
    file_put_contents($tmp.'/source/app/a.php', '<?php return 1;');
    file_put_contents($tmp.'/runtime/app/a.php', '<?php return 1;');
    file_put_contents($tmp.'/runtime/vendor/autoload.php', '<?php return true;');
    file_put_contents($tmp.'/runtime/.env', 'APP_KEY=secret');
    file_put_contents($tmp.'/runtime/storage/installed', 'private');
    $key = openssl_pkey_new(['private_key_bits' => 3072, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($key, $pem);
    file_put_contents($tmp.'/key.pem', $pem);
    mkdir($tmp.'/source/resources/updates/trusted-keys', 0700, true);
    file_put_contents($tmp.'/source/resources/updates/trusted-keys/test.pub', openssl_pkey_get_details($key)['key']);
    try {
        $process = new Process([PHP_BINARY, dirname(__DIR__, 2).'/bin/build-update-release.php', '--source-root='.$tmp.'/source', '--runtime-root='.$tmp.'/runtime', '--version=1.2.0', '--sha='.str_repeat('a', 40), '--output='.$tmp.'/dist', '--key-file='.$tmp.'/key.pem', '--key-id=test']);
        $process->mustRun();
        $raw = file_get_contents($tmp.'/dist/manifest.json');
        $signature = file_get_contents($tmp.'/dist/manifest.sig');
        $manifest = (new SignedManifestVerifier)->verify($raw, $signature, ['test' => openssl_pkey_get_details($key)['key']]);
        expect($manifest->distribution('source')['files'])->toHaveKey('app/a.php')->not->toHaveKey('vendor/autoload.php');
        expect($manifest->distribution('runtime')['files'])->toHaveKey('vendor/autoload.php')->not->toHaveKeys(['.env', 'storage/installed']);
        foreach (['source', 'runtime'] as $distribution) {
            $data = $manifest->distribution($distribution);
            expect(hash_file('sha256', $tmp.'/dist/'.$data['asset']))->toBe($data['sha256']);
        }
    } finally {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($tmp);
    }
});

it('packages signed update inventories with the new installation', function (): void {
    $builder = file_get_contents(dirname(__DIR__, 2).'/bin/build-release-zip.sh');
    expect($builder)->toContain('build-update-release.php', 'installed-manifest.json')
        ->not->toContain('build-update-baselines.sh');
})->group('source-catalogue');
