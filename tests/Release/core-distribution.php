<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

if (! is_file('/.dockerenv') || getenv('DBCMS_TEST_ISOLATED') !== '1') {
    throw new RuntimeException('An isolated Docker runtime is required.');
}

$source = dirname(__DIR__, 2);
require $source.'/vendor/autoload.php';
$adminPassword = getenv('DBCMS_TEST_DB_ADMIN_PASSWORD');
if ($adminPassword === false || $adminPassword === '') {
    throw new RuntimeException('Set DBCMS_TEST_DB_ADMIN_PASSWORD for the disposable MySQL server.');
}
$host = getenv('DB_HOST') ?: 'mysql';
$port = getenv('DB_PORT') ?: '3306';
$username = getenv('DB_USERNAME') ?: 'sail';
$password = getenv('DB_PASSWORD') ?: 'secret';
$database = 'dbcms_core_release_'.bin2hex(random_bytes(8));
$artifact = sys_get_temp_dir().'/'.$database;
$admin = new PDO("mysql:host={$host};port={$port}", getenv('DBCMS_TEST_DB_ADMIN_USER') ?: 'root', $adminPassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$server = null;
$created = false;
$run = static function (array $command, string $directory, array $environment = [], int $timeout = 180): void {
    $process = new Process($command, $directory, $environment);
    $process->setTimeout($timeout);
    $process->run();
    if (! $process->isSuccessful()) {
        throw new RuntimeException($process->getOutput().$process->getErrorOutput());
    }
};
$expect = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};
$token = static function (string $html): string {
    $document = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    $xpath = new DOMXPath($document);
    $value = $xpath->evaluate('string(//input[@name="_token"]/@value | //meta[@name="csrf-token"]/@content)');
    if (! is_string($value) || $value === '') {
        throw new RuntimeException('The HTTP form has no CSRF token.');
    }

    return $value;
};

try {
    mkdir($artifact, 0700, true);
    $files = new Filesystem;
    // Copy only release inputs. No .env, keys, data, Git state or optional modules.
    foreach (['app', 'bootstrap', 'config', 'database', 'routes', 'resources', 'themes', 'lang', 'block-packages', 'stubs', 'tests'] as $directory) {
        if (is_dir($source.'/'.$directory)) {
            $files->copyDirectory($source.'/'.$directory, $artifact.'/'.$directory);
        }
    }
    $files->ensureDirectoryExists($artifact.'/public');
    foreach ($files->files($source.'/public') as $file) {
        if ($file->getBasename() !== 'hot') {
            $files->copy($file->getPathname(), $artifact.'/public/'.$file->getBasename());
        }
    }
    foreach ($files->directories($source.'/public') as $directory) {
        if (! is_link($directory) && ! in_array(basename($directory), ['storage', 'uploads'], true)) {
            $files->copyDirectory($directory, $artifact.'/public/'.basename($directory));
        }
    }
    foreach (['artisan', 'composer.json', 'composer.lock', 'VERSION', '.env.example'] as $file) {
        $files->copy($source.'/'.$file, $artifact.'/'.$file);
    }
    // Dereference package symlinks and regenerate Composer maps inside this artifact.
    $run(['cp', '-aL', $source.'/vendor', $artifact.'/vendor'], $source);
    $files->delete(glob($artifact.'/bootstrap/cache/*.php') ?: []);
    foreach (['storage/framework/cache/data', 'storage/framework/views', 'storage/framework/sessions', 'storage/logs', 'storage/app/public'] as $directory) {
        $files->ensureDirectoryExists($artifact.'/'.$directory);
    }
    $files->copy($source.'/.env.example', $artifact.'/.env');
    $admin->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $created = true;
    $admin->exec("GRANT ALL PRIVILEGES ON `{$database}`.* TO ".$admin->quote($username)."@'%'");
    $environment = [
        'APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'APP_BASE_PATH' => $artifact,
        'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
        'CMS_MODULES' => '', 'CMS_SEED_DEMO' => 'false', 'CMS_BRAND' => 'dbcms', 'CMS_THEME' => 'db-minimal',
        'DB_CONNECTION' => 'mysql', 'DB_HOST' => $host, 'DB_PORT' => $port, 'DB_DATABASE' => $database, 'DB_USERNAME' => $username, 'DB_PASSWORD' => $password,
        'SESSION_DRIVER' => 'file', 'SESSION_ENCRYPT' => 'false', 'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array',
        'APP_CONFIG_CACHE' => $artifact.'/bootstrap/cache/config.php', 'APP_ROUTES_CACHE' => $artifact.'/bootstrap/cache/routes.php',
        'VIEW_COMPILED_PATH' => $artifact.'/storage/framework/views',
    ];
    $run(['composer', 'dump-autoload', '--no-scripts', '--optimize', '--no-interaction'], $artifact, $environment);
    $expect(! file_exists($artifact.'/modules'), 'Optional module files leaked into the core artifact.');
    $run([PHP_BINARY, 'artisan', 'view:cache'], $artifact, $environment);
    $identity = 'release-admin@example.invalid';
    $loginPassword = bin2hex(random_bytes(16));
    $run([PHP_BINARY, 'artisan', 'cms:install', '--without-demo', '--db-host='.$host, '--db-port='.$port, '--db-database='.$database, '--db-username='.$username, '--db-password='.$password, '--admin-name=Core Release Admin', '--admin-email='.$identity, '--admin-password='.$loginPassword, '--brand=dbcms', '--theme=db-minimal', '--no-interaction'], $artifact, $environment);
    $expect(is_file($artifact.'/storage/installed'), 'The installation did not complete.');
    $probe = new PDO("mysql:host={$host};port={$port};dbname={$database}", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $expect((int) $probe->query('SELECT COUNT(*) FROM cms_modules')->fetchColumn() === 0, 'Core installation registered optional modules.');
    $expect((int) $probe->query('SELECT COUNT(*) FROM users')->fetchColumn() === 1, 'Core installation added demo accounts.');
    $market = $probe->query('SELECT id, code, default_locale FROM markets WHERE is_default = 1')->fetch(PDO::FETCH_ASSOC);
    $expect(is_array($market), 'The core did not install its default market.');
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
    $expect(is_resource($socket), 'Unable to allocate an isolated HTTP port.');
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $expect(is_string($address), 'Unable to identify the HTTP port.');
    $base = 'http://'.$address;
    $environment['APP_URL'] = $base;
    $server = new Process([PHP_BINARY, '-S', $address, '-t', 'public', 'public/index.php'], $artifact, $environment);
    $server->start();
    $http = new Client(['base_uri' => $base, 'cookies' => new CookieJar, 'http_errors' => false, 'timeout' => 20, 'allow_redirects' => false]);
    $login = null;
    for ($attempt = 0; $attempt < 50; $attempt++) {
        try {
            $login = $http->get('/admin/login');
            break;
        } catch (ConnectException) {
            usleep(100000);
        }
    }
    $expect($login !== null && $login->getStatusCode() === 200, 'The module-free HTTP server could not render login.');
    $response = $http->post('/admin/login', ['form_params' => ['_token' => $token((string) $login->getBody()), 'email' => $identity, 'password' => $loginPassword]]);
    $expect($response->getStatusCode() === 302, 'HTTP authentication failed.');
    $dashboard = $http->get('/admin');
    $expect($dashboard->getStatusCode() === 200, 'The authenticated core dashboard failed: status '.$dashboard->getStatusCode().' location '.$dashboard->getHeaderLine('Location'));
    $response = $http->post('/admin/pages', ['form_params' => [
        '_token' => $token((string) $dashboard->getBody()), 'is_published' => '1', 'market_ids' => [$market['id']],
        'translations' => [$market['default_locale'] => ['title' => 'Core distribution publication', 'slug' => 'core-distribution-publication']],
    ]]);
    $expect($response->getStatusCode() === 302, 'Page creation failed through the HTTP form.');
    $expect((int) $probe->query('SELECT COUNT(*) FROM pages WHERE is_published = 1')->fetchColumn() === 1, 'The new page was not published.');
    $public = new Client(['base_uri' => $base, 'http_errors' => false, 'timeout' => 20]);
    $response = $public->get('/'.$market['code'].'/'.$market['default_locale'].'/core-distribution-publication');
    $expect($response->getStatusCode() === 200 && str_contains((string) $response->getBody(), 'Core distribution publication'), 'Anonymous HTTP could not read the published page.');
    $response = $public->get('/'.$market['code'].'/'.$market['default_locale'].'/sitemap.xml');
    $expect($response->getStatusCode() === 200 && str_contains((string) $response->getBody(), 'core-distribution-publication'), 'The public sitemap omitted the published core page.');
    echo "Core distribution passed: no modules directory, local Composer runtime, fresh installation, HTTP authentication, publication and anonymous delivery.\n";
} catch (Throwable $error) {
    foreach (glob($artifact.'/storage/logs/*.log') ?: [] as $log) {
        foreach (array_reverse(file($log, FILE_IGNORE_NEW_LINES) ?: []) as $line) {
            if (str_contains($line, '.ERROR:')) {
                fwrite(STDERR, substr($line, 0, 1600)."\n");
                break;
            }
        }
    }
    throw $error;
} finally {
    $server?->stop(3);
    if ($created) {
        $admin->exec("DROP DATABASE IF EXISTS `{$database}`");
    }
    if (isset($files)) {
        $files->deleteDirectory($artifact);
    }
}
