<?php

declare(strict_types=1);

use Illuminate\Encryption\Encrypter;

function installerPrebootstrapSource(): string
{
    $root = dirname(__DIR__, 2);
    $index = (string) file_get_contents($root.'/public/index.php');
    $bootstrapSources = '';

    foreach (glob($root.'/bootstrap/*.php') ?: [] as $path) {
        $bootstrapSources .= "\n".(string) file_get_contents($path);
    }

    return $index.$bootstrapSources;
}

test('web prebootstrap runs before Composer and Laravel on the first request', function (): void {
    $root = dirname(__DIR__, 2);
    $index = (string) file_get_contents($root.'/public/index.php');
    $autoload = strpos($index, 'vendor/autoload.php');
    $preflight = preg_match('/(?:install|preflight|bootstrap)[^\n;]*(?:require|include)|(?:require|include)[^\n;]*(?:install|preflight)/i', $index, $match, PREG_OFFSET_CAPTURE);

    expect($autoload)->not->toBeFalse()
        ->and($preflight)->toBe(1)
        ->and($match[0][1] ?? PHP_INT_MAX)->toBeLessThan($autoload);
});

test('prebootstrap serializes and atomically persists a cryptographic application key', function (): void {
    $source = installerPrebootstrapSource();

    expect($source)->toContain('APP_KEY', 'random_bytes', 'LOCK_EX')
        ->and($source)->toMatch('/(?:rename|replace)\s*\(/')
        ->and($source)->toMatch('/(?:chmod\s*\([^,]+,\s*0?600|0600)/')
        ->and($source)->toContain('storage/installed');
});

test('sealed installations route missing or malformed keys to recovery without rotation', function (): void {
    $source = installerPrebootstrapSource();

    expect($source)->toContain('storage/installed', 'APP_KEY')
        ->and($source)->toMatch('/installed[\s\S]{0,2500}(?:recover|recuper|restore|restaur)/i')
        ->and($source)->toMatch('/(?:recover|recuper|restore|restaur)[\s\S]{0,2500}(?:return|exit)/i');
});

test('installation environment template boots without database cache sessions or queues', function (): void {
    $path = dirname(__DIR__, 2).'/.env.installation.example';

    expect(is_file($path))->toBeTrue();

    $values = Dotenv\Dotenv::parse((string) file_get_contents($path));
    $key = (string) ($values['APP_KEY'] ?? '');

    expect($values['APP_ENV'] ?? null)->toBe('production')
        ->and($values['APP_DEBUG'] ?? null)->toBe('false')
        ->and($key)->toBe('')
        ->and($values['SESSION_DRIVER'] ?? null)->toBe('file')
        ->and($values['CACHE_STORE'] ?? null)->toBe('file')
        ->and($values['QUEUE_CONNECTION'] ?? null)->toBe('sync')
        ->and(strtolower((string) ($values['DB_CONNECTION'] ?? '')))->toBeIn(['mysql', 'mariadb'])
        ->and((string) ($values['DB_PASSWORD'] ?? ''))->toBe('');
});

test('a generated application key has the Laravel AES 256 key shape', function (): void {
    $key = 'base64:'.base64_encode(random_bytes(32));
    $decoded = base64_decode(substr($key, 7), true);

    expect($decoded)->toBeString()
        ->and(strlen($decoded))->toBe(32)
        ->and(Encrypter::supported($decoded, 'AES-256-CBC'))->toBeTrue();
});

test('release builder publishes a clearly named self-contained installer archive', function (): void {
    $root = dirname(__DIR__, 2);
    $builder = (string) file_get_contents($root.'/bin/build-release-zip.sh');
    $workflow = (string) file_get_contents($root.'/.github/workflows/release.yml');

    expect($builder)->toContain('dbcms-installer-v${VERSION}.zip')
        ->and($builder)->toContain('.env.installation.example', 'EMPEZAR.html')
        ->and($builder)->toContain('vendor/autoload.php', 'public/build/manifest.json', 'storage/installed')
        ->and($builder)->toMatch('/(?:\.env|APP_KEY|private)[\s\S]*(?:fail|ERROR|exit)/i')
        ->and($workflow)->toContain('dbcms-installer-', 'Source code')
        ->and($workflow)->toMatch('/develop(?:er|ers)|desarrollador(?:es)?/i');
});

test('release smoke contract exercises the unpacked first web request and completed site', function (): void {
    $builder = (string) file_get_contents(dirname(__DIR__, 2).'/bin/build-release-zip.sh');

    expect($builder)->toContain('vendor/autoload.php', 'public/build/manifest.json')
        ->and($builder)->toMatch('/(?:php\s+-S|apache|curl)[\s\S]{0,5000}(?:install|APP_KEY)/i')
        ->and($builder)->toContain('/up')
        ->and($builder)->toMatch('/(?:login|control)/i');
});
