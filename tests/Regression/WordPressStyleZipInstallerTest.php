<?php

declare(strict_types=1);

use Illuminate\Encryption\Encrypter;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

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
        ->and($builder)->toContain("! -name 'packages.php'", "! -name 'services.php'")
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

test('host packaging gives Docker read only Git source and a distinct writable artifact directory', function (): void {
    $root = dirname(__DIR__, 2);
    $temporary = sys_get_temp_dir().'/dbcms-release-wrapper-'.bin2hex(random_bytes(6));
    mkdir($temporary.'/bin', 0755, true);
    mkdir($temporary.'/mock');
    mkdir($temporary.'/storage');
    copy($root.'/bin/build-release-zip.sh', $temporary.'/bin/build-release-zip.sh');
    file_put_contents($temporary.'/.env', 'PRIVATE_ENV_SENTINEL');
    file_put_contents($temporary.'/storage/installed', 'INSTALLATION_SENTINEL');
    file_put_contents($temporary.'/signing.pem', 'EXTERNAL_KEY_SENTINEL');
    // Simulate the host filesystem boundary when this regression runs in CI.
    // The production wrapper itself is executed unchanged.
    file_put_contents($temporary.'/host-environment.sh', <<<'BASH'
function [() {
    if [[ "$#" == 4 && "$1" == '!' && "$2" == '-f' && "$3" == '/.dockerenv' ]]; then
        return 0
    fi
    builtin [ "$@"
}
BASH);
    file_put_contents($temporary.'/mock/docker', <<<'BASH'
#!/usr/bin/env bash
printf '%s\0' "$@" > "$PACKAGING_TEST_LOG"
printf '%s' "$PWD" > "$PACKAGING_TEST_LOG.cwd"
exit 0
BASH);
    chmod($temporary.'/mock/docker', 0755);
    try {
        $process = new Process(['bash', $temporary.'/bin/build-release-zip.sh', '--version=9.8.7'], sys_get_temp_dir(), [
            'PATH' => $temporary.'/mock:'.getenv('PATH'),
            'BASH_ENV' => $temporary.'/host-environment.sh',
            'PACKAGING_TEST_LOG' => $temporary.'/docker.args',
            'DBCMS_UPDATE_SIGNING_KEY_FILE' => $temporary.'/signing.pem',
            'DBCMS_UPDATE_SIGNING_KEY_ID' => 'fixture-key',
        ]);
        expect($process->run())->toBe(0, $process->getErrorOutput());
        $arguments = explode("\0", rtrim((string) file_get_contents($temporary.'/docker.args'), "\0"));
        expect($arguments)->toBe([
            'compose', '-f', 'docker/ci/compose.yml', 'run', '--build', '--rm',
            '--volume', $temporary.':/var/www/html:ro',
            '--volume', $temporary.'/dist:/var/www/html/dist:rw',
            '--volume', $temporary.'/signing.pem:/run/secrets/updates-signing.pem:ro',
            '--env', 'DBCMS_UPDATE_SIGNING_KEY_FILE=/run/secrets/updates-signing.pem',
            '--env', 'DBCMS_UPDATE_SIGNING_KEY_ID=fixture-key',
            'app', 'bash', 'bin/build-release-zip.sh', '--version=9.8.7',
        ])->and(file_get_contents($temporary.'/docker.args.cwd'))->toBe($temporary)
            ->and(is_dir($temporary.'/dist'))->toBeTrue()
            ->and(file_get_contents($temporary.'/.env'))->toBe('PRIVATE_ENV_SENTINEL')
            ->and(file_get_contents($temporary.'/storage/installed'))->toBe('INSTALLATION_SENTINEL')
            ->and(file_get_contents($temporary.'/signing.pem'))->toBe('EXTERNAL_KEY_SENTINEL');
    } finally {
        (new Filesystem)->remove($temporary);
    }
});
