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
    $workflow = (string) file_get_contents($root.'/bin/github-delivery.py');

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

/** A real Git export omits runtime directories that contain no tracked files. */
function releasePackagingFixture(): string
{
    $root = dirname(__DIR__, 2);
    $fixture = sys_get_temp_dir().'/dbcms-release-source-'.bin2hex(random_bytes(6));
    foreach (['bin', 'mock', 'bootstrap/cache', 'app', 'storage/app', 'public', 'resources/updates'] as $directory) {
        mkdir($fixture.'/'.$directory, 0755, true);
    }
    copy($root.'/bin/build-release-zip.sh', $fixture.'/bin/build-release-zip.sh');
    foreach (['.env.example', '.env.production.example', '.env.installation.example'] as $template) {
        file_put_contents($fixture.'/'.$template, "APP_KEY=\n");
    }
    file_put_contents($fixture.'/VERSION', "9.8.7\n");
    file_put_contents($fixture.'/app/source-proof.txt', 'original application bytes');
    file_put_contents($fixture.'/bootstrap/app.php', '<?php');
    file_put_contents($fixture.'/storage/app/.gitignore', "*\n!.gitignore\n");
    file_put_contents($fixture.'/EMPEZAR.html', '<p>Installer</p>');
    file_put_contents($fixture.'/.htaccess', 'RewriteRule ^(.*)$ public/$1 [L]');
    file_put_contents($fixture.'/public/.htaccess', 'RewriteEngine On');
    file_put_contents($fixture.'/public/index.php', '<?php');
    file_put_contents($fixture.'/resources/updates/.gitignore', "\n");
    foreach ([['git', 'init', '--quiet'], ['git', 'add', '.'], ['git', '-c', 'user.name=Release fixture', '-c', 'user.email=fixture@example.invalid', '-c', 'commit.gpgsign=false', 'commit', '--quiet', '-m', 'Application source']] as $command) {
        (new Process($command, $fixture))->mustRun();
    }
    file_put_contents($fixture.'/mock/composer', <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
for directory in bootstrap/cache storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs; do
    test -d "$directory"
done
cat VERSION > "$RELEASE_TEST_LOG.version"
cat app/source-proof.txt > "$RELEASE_TEST_LOG.application"
mkdir -p vendor
printf '<?php' > vendor/autoload.php
exit "${RELEASE_TEST_COMPOSER_EXIT:-0}"
BASH);
    file_put_contents($fixture.'/mock/npm', <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
mkdir -p public/build
printf '{}' > public/build/manifest.json
BASH);
    file_put_contents($fixture.'/mock/php', <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
printf '%s\0' "$@" > "$RELEASE_TEST_LOG.php-arguments"
for argument in "$@"; do
    case "$argument" in
        --source-root=*) source_root=${argument#*=} ;;
        --output=*) output=${argument#*=} ;;
        --sha=*) source_sha=${argument#*=} ;;
    esac
done
cat "$source_root/VERSION" > "$RELEASE_TEST_LOG.manifest-version"
cat "$source_root/app/source-proof.txt" > "$RELEASE_TEST_LOG.manifest-application"
printf '{"schema":1,"source_sha":"%s"}\n' "$source_sha" > "$output/manifest.json"
printf 'fixture-signature\n' > "$output/manifest.sig"
exit 0
BASH);
    // Stop at the external HTTP smoke boundary, after the real archives and
    // embedded inventories have been written by the production builder.
    file_put_contents($fixture.'/mock/unzip', <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
printf '%s\0' "$@" > "$RELEASE_TEST_LOG.unzip-arguments"
exit 72
BASH);
    foreach (['composer', 'npm', 'php', 'unzip'] as $binary) {
        chmod($fixture.'/mock/'.$binary, 0755);
    }

    return $fixture;
}

/** @param list<string> $arguments
 * @param  array<string,string>  $environment
 */
function runReleasePackagingFixture(string $fixture, array $arguments, array $environment = []): Process
{
    $process = new Process(['bash', $fixture.'/bin/build-release-zip.sh', ...$arguments], $fixture, [
        'PATH' => $fixture.'/mock:'.getenv('PATH'),
        'RELEASE_TEST_LOG' => $fixture.'/proof',
        ...$environment,
    ]);
    $process->setTimeout(30);
    $process->run();

    return $process;
}

test('release packaging creates runtime directories omitted by the actual Git archive before Composer', function (): void {
    $fixture = releasePackagingFixture();
    try {
        $archive = new Process(['git', 'ls-tree', '-r', '--name-only', 'HEAD', '--', 'bootstrap/cache'], $fixture);
        $archive->mustRun();
        expect($archive->getOutput())->toBe('');
        $process = runReleasePackagingFixture($fixture, [], ['RELEASE_TEST_COMPOSER_EXIT' => '71']);
        expect($process->getExitCode())->toBe(71, $process->getErrorOutput())
            ->and(file_get_contents($fixture.'/proof.application'))->toBe('original application bytes');
    } finally {
        (new Filesystem)->remove($fixture);
    }
});

test('release packaging pins both exported application and signing inputs to the selected commit', function (): void {
    $fixture = releasePackagingFixture();
    try {
        $revision = new Process(['git', 'rev-parse', 'HEAD'], $fixture);
        $revision->mustRun();
        $sha = trim($revision->getOutput());
        file_put_contents($fixture.'/VERSION', "9.8.8\n");
        file_put_contents($fixture.'/app/source-proof.txt', 'newer tools checkout application bytes');
        foreach ([['git', 'add', '.'], ['git', '-c', 'user.name=Release fixture', '-c', 'user.email=fixture@example.invalid', '-c', 'commit.gpgsign=false', 'commit', '--quiet', '-m', 'Newer tools checkout']] as $command) {
            (new Process($command, $fixture))->mustRun();
        }
        file_put_contents($fixture.'/test-signing-key.pem', 'external signing boundary fixture');
        $process = runReleasePackagingFixture($fixture, ['--version=9.8.7', '--source-ref='.$sha], ['DBCMS_UPDATE_SIGNING_KEY_FILE' => $fixture.'/test-signing-key.pem']);
        expect($process->getExitCode())->toBe(72, $process->getErrorOutput())
            ->and(trim((string) file_get_contents($fixture.'/proof.version')))->toBe('9.8.7')
            ->and(file_get_contents($fixture.'/proof.application'))->toBe('original application bytes')
            ->and(trim((string) file_get_contents($fixture.'/proof.manifest-version')))->toBe('9.8.7')
            ->and(file_get_contents($fixture.'/proof.manifest-application'))->toBe('original application bytes');
        $arguments = explode("\0", rtrim((string) file_get_contents($fixture.'/proof.php-arguments'), "\0"));
        expect($arguments)->toContain('--sha='.$sha, '--version=9.8.7');
        $zip = new ZipArchive;
        expect($zip->open($fixture.'/dist/dbcms-installer-v9.8.7.zip'))->toBeTrue();
        expect($zip->getFromName('app/source-proof.txt'))->toBe('original application bytes')
            ->and(json_decode($zip->getFromName('resources/updates/installed-manifest.json'), true, flags: JSON_THROW_ON_ERROR)['source_sha'])->toBe($sha)
            ->and($zip->getFromName('resources/updates/installed-manifest.sig'))->toBe("fixture-signature\n");
        $zip->close();
    } finally {
        (new Filesystem)->remove($fixture);
    }
});

test('release packaging rejects a version different from the selected source before dependencies', function (): void {
    $fixture = releasePackagingFixture();
    try {
        $process = runReleasePackagingFixture($fixture, ['--version=9.8.6']);
        expect($process->getExitCode())->toBe(64)
            ->and($process->getErrorOutput())->toContain('does not match')
            ->and(is_file($fixture.'/proof.version'))->toBeFalse();
    } finally {
        (new Filesystem)->remove($fixture);
    }
});

test('release packaging refuses symbolic abbreviated or noncommit source references', function (string $selection): void {
    $fixture = releasePackagingFixture();
    try {
        $revision = new Process(['git', 'rev-parse', 'HEAD^{tree}'], $fixture);
        $revision->mustRun();
        $reference = match ($selection) {
            'branch' => 'HEAD',
            'abbreviated' => substr(trim($revision->getOutput()), 0, 8),
            'tree' => trim($revision->getOutput()),
            default => str_repeat('f', 40),
        };
        $process = runReleasePackagingFixture($fixture, ['--source-ref='.$reference]);
        expect($process->getExitCode())->toBe(64)
            ->and(is_file($fixture.'/proof.version'))->toBeFalse();
    } finally {
        (new Filesystem)->remove($fixture);
    }
})->with(['branch', 'abbreviated', 'tree', 'missing']);
