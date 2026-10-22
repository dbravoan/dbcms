<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Laravel\Passport\Passport;

// Deployment-only probes. Boot the effective runtime so cache maintenance and
// externally configured Passport keys follow the same contracts as HTTP.
try {
    $root = realpath($argv[2] ?? dirname(__DIR__));
    if ($root === false) {
        throw new RuntimeException('Runtime directory is unavailable.');
    }
    chdir($root);
    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();

    if (($argv[1] ?? '') === 'maintenance') {
        echo $app->maintenanceMode()->active() ? 'active' : 'inactive';
        exit(0);
    }
    if (! in_array($argv[1] ?? '', ['passport', 'passport-initialize'], true)) {
        throw new RuntimeException('Expected maintenance, passport or passport-initialize.');
    }
    $private = str_replace('\\n', "\n", (string) config('passport.private_key'));
    $public = str_replace('\\n', "\n", (string) config('passport.public_key'));
    $privatePath = Passport::keyPath('oauth-private.key');
    $publicPath = Passport::keyPath('oauth-public.key');
    if (($argv[1] ?? '') === 'passport-initialize' && ! is_file(storage_path('installed'))
        && $private === '' && $public === '' && ! file_exists($privatePath) && ! file_exists($publicPath)) {
        if ($app->make(Kernel::class)->call('passport:keys', ['--no-interaction' => true]) !== 0) {
            throw new RuntimeException('Cannot initialize Passport keys.');
        }
    }
    $private = $private !== '' ? $private : 'file://'.$privatePath;
    $public = $public !== '' ? $public : 'file://'.$publicPath;
    // League CryptKey accepts PEM, file:// URIs and ordinary absolute paths.
    if (! str_starts_with($private, 'file://') && is_file($private)) {
        $private = 'file://'.$private;
    }
    if (! str_starts_with($public, 'file://') && is_file($public)) {
        $public = 'file://'.$public;
    }
    $privateKey = @openssl_pkey_get_private($private);
    $publicKey = @openssl_pkey_get_public($public);
    $privateDetails = $privateKey === false ? false : openssl_pkey_get_details($privateKey);
    $publicDetails = $publicKey === false ? false : openssl_pkey_get_details($publicKey);
    if ($privateDetails === false || $publicDetails === false
        || $privateDetails['type'] !== OPENSSL_KEYTYPE_RSA || $privateDetails['bits'] < 2048
        || ! hash_equals($privateDetails['key'], $publicDetails['key'])) {
        throw new RuntimeException('Passport keys are missing, invalid or mismatched. Restore the original pair or configure both Passport keys explicitly; installed runtimes never regenerate them.');
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'Runtime safety check failed: '.$exception->getMessage()."\n");
    exit(2);
}
