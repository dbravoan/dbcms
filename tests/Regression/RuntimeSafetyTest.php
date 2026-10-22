<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/** @return array{string, Closure(string, array<string, string>): Process} */
function runtimeSafetyFixture(): array
{
    $root = dirname(__DIR__, 2);
    $temporary = sys_get_temp_dir().'/dbcms-runtime-safety-'.bin2hex(random_bytes(6));
    $files = new Filesystem;
    foreach (['bootstrap/cache', 'storage/framework/cache/data', 'storage/framework/views', 'storage/framework/sessions', 'storage/logs', 'public'] as $directory) {
        $files->ensureDirectoryExists($temporary.'/'.$directory);
    }
    $files->copyDirectory($root.'/config', $temporary.'/config');
    $files->copyDirectory($root.'/routes', $temporary.'/routes');
    foreach (['bootstrap/app.php', 'bootstrap/providers.php', 'composer.json', 'VERSION'] as $file) {
        copy($root.'/'.$file, $temporary.'/'.$file);
    }
    symlink($root.'/vendor', $temporary.'/vendor');
    $run = static function (string $action, array $environment = []) use ($root, $temporary): Process {
        $process = new Process([PHP_BINARY, $root.'/bin/runtime-safety.php', $action, $temporary], $temporary, [
            'APP_ENV' => 'testing', 'APP_KEY' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'CMS_MODULES' => '', 'CACHE_STORE' => 'file', 'APP_MAINTENANCE_DRIVER' => 'file',
            'PASSPORT_PRIVATE_KEY' => '', 'PASSPORT_PUBLIC_KEY' => '', ...$environment,
        ]);
        $process->run();

        return $process;
    };

    return [$temporary, $run];
}

it('never regenerates missing, incomplete, invalid or mismatched Passport keys in a sealed runtime', function (string $condition): void {
    [$temporary, $run] = runtimeSafetyFixture();
    touch($temporary.'/storage/installed');
    $key = openssl_pkey_new(['private_key_bits' => 2048]);
    openssl_pkey_export($key, $private);
    $public = openssl_pkey_get_details($key)['key'];
    if ($condition !== 'absent') {
        file_put_contents($temporary.'/storage/oauth-private.key', $condition === 'invalid' ? 'invalid' : $private);
    }
    if (in_array($condition, ['invalid', 'mismatched'], true)) {
        $other = openssl_pkey_new(['private_key_bits' => 2048]);
        file_put_contents($temporary.'/storage/oauth-public.key', $condition === 'invalid' ? 'invalid' : openssl_pkey_get_details($other)['key']);
    }
    $before = [];
    foreach (glob($temporary.'/storage/oauth-*.key') ?: [] as $file) {
        $before[$file] = file_get_contents($file);
    }
    try {
        $process = $run('passport-initialize');
        expect($process->getExitCode())->toBe(2, $process->getOutput().$process->getErrorOutput());
        expect($process->getErrorOutput())->toContain('Restore the original pair');
        $after = [];
        foreach (glob($temporary.'/storage/oauth-*.key') ?: [] as $file) {
            $after[$file] = file_get_contents($file);
        }
        expect($after)->toBe($before);
    } finally {
        (new Filesystem)->deleteDirectory($temporary);
    }
})->with(['absent', 'private only', 'invalid', 'mismatched']);

it('accepts an explicitly configured external Passport pair without creating local keys', function (bool $paths): void {
    [$temporary, $run] = runtimeSafetyFixture();
    touch($temporary.'/storage/installed');
    $key = openssl_pkey_new(['private_key_bits' => 2048]);
    openssl_pkey_export($key, $private);
    $public = openssl_pkey_get_details($key)['key'];
    file_put_contents($temporary.'/external-private.pem', $private);
    file_put_contents($temporary.'/external-public.pem', $public);
    try {
        $process = $run('passport-initialize', [
            'PASSPORT_PRIVATE_KEY' => $paths ? $temporary.'/external-private.pem' : str_replace("\n", '\\n', $private),
            'PASSPORT_PUBLIC_KEY' => $paths ? 'file://'.$temporary.'/external-public.pem' : str_replace("\n", '\\n', $public),
        ]);
        expect($process->getExitCode())->toBe(0, $process->getOutput().$process->getErrorOutput());
        expect(glob($temporary.'/storage/oauth-*.key'))->toBe([]);
        expect(file_get_contents($temporary.'/external-private.pem'))->toBe($private);
    } finally {
        (new Filesystem)->deleteDirectory($temporary);
    }
})->with([false, true]);

it('initializes a pristine Passport pair once and preserves its bytes on restart', function (): void {
    [$temporary, $run] = runtimeSafetyFixture();
    try {
        $process = $run('passport-initialize');
        expect($process->getExitCode())->toBe(0, $process->getOutput().$process->getErrorOutput());
        $private = file_get_contents($temporary.'/storage/oauth-private.key');
        $public = file_get_contents($temporary.'/storage/oauth-public.key');
        touch($temporary.'/storage/installed');
        expect($run('passport-initialize')->getExitCode())->toBe(0);
        expect(file_get_contents($temporary.'/storage/oauth-private.key'))->toBe($private);
        expect(file_get_contents($temporary.'/storage/oauth-public.key'))->toBe($public);
    } finally {
        (new Filesystem)->deleteDirectory($temporary);
    }
});

it('reads the effective maintenance state without replacing the existing payload', function (string $driver): void {
    [$temporary, $run] = runtimeSafetyFixture();
    $environment = ['APP_MAINTENANCE_DRIVER' => $driver, 'APP_MAINTENANCE_STORE' => 'file'];
    try {
        expect($run('maintenance', $environment)->getOutput())->toBe('inactive');
        $seed = new Process([PHP_BINARY, '-r', <<<'CODE'
            $root = $argv[1];
            require $root.'/vendor/autoload.php';
            $app = require $root.'/bootstrap/app.php';
            $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
            $app->maintenanceMode()->activate(['secret' => 'operator-secret', 'retry' => 60, 'status' => 503]);
            CODE, $temporary], $temporary, [
            'APP_ENV' => 'testing', 'APP_KEY' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'CMS_MODULES' => '', 'CACHE_STORE' => 'file', ...$environment,
        ]);
        $seed->mustRun();
        $hashes = static function () use ($temporary): array {
            $result = [];
            foreach ((new Filesystem)->allFiles($temporary.'/storage/framework') as $file) {
                $result[$file->getPathname()] = hash_file('sha256', $file->getPathname());
            }

            return $result;
        };
        $before = $hashes();
        $process = $run('maintenance', $environment);
        expect($process->getExitCode())->toBe(0, $process->getErrorOutput());
        expect($process->getOutput())->toBe('active');
        expect($hashes())->toBe($before);
    } finally {
        (new Filesystem)->deleteDirectory($temporary);
    }
})->with(['file', 'cache']);
