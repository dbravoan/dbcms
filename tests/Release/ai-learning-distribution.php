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
$password = getenv('DBCMS_TEST_DB_ADMIN_PASSWORD');
if (! is_string($password) || $password === '') {
    throw new RuntimeException('Disposable MySQL administrator credentials are required.');
}
$host = getenv('DB_HOST') ?: 'mysql';
$port = getenv('DB_PORT') ?: '3306';
$username = getenv('DB_USERNAME') ?: 'sail';
$dbPassword = getenv('DB_PASSWORD') ?: 'secret';
$database = 'dbcms_ai_release_'.bin2hex(random_bytes(8));
$artifact = sys_get_temp_dir().'/'.$database;
$admin = new PDO("mysql:host={$host};port={$port}", getenv('DBCMS_TEST_DB_ADMIN_USER') ?: 'root', $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$files = new Filesystem;
$server = null;
$created = false;
$run = static function (array $command, array $environment = []) use ($artifact): void {
    $process = new Process($command, $artifact, $environment);
    $process->setTimeout(180);
    $process->mustRun();
};
$expect = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};
$document = static function (string $html): DOMXPath {
    $dom = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    return new DOMXPath($dom);
};
$token = static fn (string $html): string => (string) $document($html)->evaluate('string(//meta[@name="csrf-token"]/@content | //input[@name="_token"]/@value)');

try {
    $files->makeDirectory($artifact, 0700, true);
    foreach (['app', 'bootstrap', 'config', 'database', 'routes', 'resources', 'lang', 'stubs'] as $directory) {
        $files->copyDirectory($source.'/'.$directory, $artifact.'/'.$directory);
    }
    $files->delete($artifact.'/database/seeders/PlatformShowcaseSeeder.php');
    // This is a physically minimal distribution, not merely disabled modules.
    $files->deleteDirectory($artifact.'/resources/views/themes/admin/termocms');
    foreach (['themes/ia-practica', 'modules/Blog', 'block-packages/Basic'] as $directory) {
        $files->copyDirectory($source.'/'.$directory, $artifact.'/'.$directory);
    }
    $files->ensureDirectoryExists($artifact.'/public');
    foreach ($files->files($source.'/public') as $file) {
        if ($file->getBasename() !== 'hot') {
            $files->copy($file->getPathname(), $artifact.'/public/'.$file->getBasename());
        }
    }
    foreach (['build', 'fonts', 'images'] as $directory) {
        $files->copyDirectory($source.'/public/'.$directory, $artifact.'/public/'.$directory);
    }
    foreach (['artisan', 'composer.json', 'composer.lock', 'VERSION', '.env.example'] as $file) {
        $files->copy($source.'/'.$file, $artifact.'/'.$file);
    }
    $run(['cp', '-aL', $source.'/vendor', $artifact.'/vendor']);
    $files->delete(glob($artifact.'/bootstrap/cache/*.php') ?: []);
    foreach (['storage/framework/cache/data', 'storage/framework/views', 'storage/framework/sessions', 'storage/logs', 'storage/app/public'] as $directory) {
        $files->ensureDirectoryExists($artifact.'/'.$directory);
    }
    $files->copy($artifact.'/.env.example', $artifact.'/.env');
    $admin->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $created = true;
    $admin->exec("GRANT ALL PRIVILEGES ON `{$database}`.* TO ".$admin->quote($username)."@'%'");
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
    $expect(is_resource($socket), 'Cannot allocate an HTTP port.');
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $expect(is_string($address), 'Cannot resolve the HTTP address.');
    $base = 'http://'.$address;
    $environment = [
        'APP_ENV' => 'production', 'APP_DEBUG' => 'false', 'APP_BASE_PATH' => $artifact, 'APP_URL' => $base,
        'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)), 'APP_NAME' => 'IA en práctica',
        'CMS_MODULES' => '', 'CMS_SEED_DEMO' => 'false', 'CMS_BRAND' => 'dbcms', 'CMS_THEME' => 'ia-practica',
        'DB_CONNECTION' => 'mysql', 'DB_HOST' => $host, 'DB_PORT' => $port, 'DB_DATABASE' => $database, 'DB_USERNAME' => $username, 'DB_PASSWORD' => $dbPassword,
        'SESSION_DRIVER' => 'file', 'SESSION_SECURE_COOKIE' => 'false', 'CACHE_STORE' => 'file', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array',
        'APP_CONFIG_CACHE' => $artifact.'/bootstrap/cache/config.php', 'APP_ROUTES_CACHE' => $artifact.'/bootstrap/cache/routes.php',
        'VIEW_COMPILED_PATH' => $artifact.'/storage/framework/views',
    ];
    $run(['composer', 'dump-autoload', '--no-scripts', '--optimize', '--no-interaction'], $environment);
    $demoPreflight = <<<'PHP'
    require 'vendor/autoload.php';
    $app = require 'bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $result = $app->make(App\Contracts\Services\InstallationProvisioningServiceInterface::class)->provision(true);
    if ($result->success || $result->logs !== []) {
        throw new RuntimeException('Missing showcase was not rejected before provisioning.');
    }
    PHP;
    $run([PHP_BINARY, '-r', $demoPreflight], $environment);
    $probe = new PDO("mysql:host={$host};port={$port};dbname={$database}", $username, $dbPassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $expect($probe->query('SHOW TABLES')->fetchAll() === [], 'Rejected demo provisioning migrated the empty database.');
    $identity = 'learning-admin@example.invalid';
    $loginPassword = bin2hex(random_bytes(16));
    $run([PHP_BINARY, 'artisan', 'cms:install', '--without-demo', '--db-host='.$host, '--db-port='.$port, '--db-database='.$database, '--db-username='.$username, '--db-password='.$dbPassword, '--admin-name=Learning Editor', '--admin-email='.$identity, '--admin-password='.$loginPassword, '--site-name=IA en práctica', '--brand=dbcms', '--theme=ia-practica', '--no-interaction'], $environment);
    $expect(is_file($artifact.'/storage/installed'), 'The minimal installation did not complete.');
    $run([PHP_BINARY, 'artisan', 'cms:starter:ai-learning', '--author='.$identity, '--market=es', '--no-interaction'], $environment);
    $expect($probe->query('SELECT identifier FROM cms_modules WHERE is_active = 1')->fetchAll(PDO::FETCH_COLUMN) === ['blog'], 'The starter activated unrelated modules.');
    foreach (['config:cache', 'route:cache', 'view:cache'] as $command) {
        $run([PHP_BINARY, 'artisan', $command], $environment);
    }
    $files->put($artifact.'/http-router.php', <<<'PHP'
    <?php
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_string($path) && is_file(__DIR__.'/public'.$path)) {
        return false;
    }
    require __DIR__.'/public/index.php';
    PHP);
    $server = new Process([PHP_BINARY, '-S', $address, '-t', 'public', 'http-router.php'], $artifact, $environment);
    $server->start();
    $http = new Client(['base_uri' => $base, 'cookies' => new CookieJar, 'http_errors' => false, 'timeout' => 30, 'allow_redirects' => false]);
    $login = null;
    for ($attempt = 0; $attempt < 50; $attempt++) {
        try {
            $login = $http->get('/admin/login');
            break;
        } catch (ConnectException) {
            usleep(100000);
        }
    }
    $expect($login !== null && $login->getStatusCode() === 200, 'The minimal login screen failed.');
    $response = $http->post('/admin/login', ['form_params' => ['_token' => $token((string) $login->getBody()), 'email' => $identity, 'password' => $loginPassword]]);
    $expect($response->getStatusCode() === 302, 'Real HTTP authentication failed.');
    foreach (['/admin', '/admin/pages', '/admin/blog/posts', '/admin/blog/posts/create', '/admin/settings'] as $path) {
        $expect($http->get($path)->getStatusCode() === 200, 'Minimal administration failed at '.$path);
    }
    $public = new Client(['base_uri' => $base, 'http_errors' => false, 'timeout' => 30]);
    $home = (string) $public->get('/es/es')->getBody();
    $xpath = $document($home);
    $expect($xpath->query('//main')->length === 1 && $xpath->query('//h1')->length === 1, 'The home lacks a unique main heading/landmark.');
    $expect($xpath->query('//title')->length === 1, 'Duplicate document titles.');
    $expect((string) $xpath->evaluate('string(//html/@lang)') === 'es', 'Wrong document language.');
    $expect((string) $xpath->evaluate('string(//meta[@name="description"]/@content)') !== '', 'Missing home description.');
    $social = (string) $xpath->evaluate('string(//meta[@property="og:image"]/@content)');
    $expect($social === $base.'/images/ia-practica-social.png', 'The publication has no canonical social image.');
    $expect($public->get($social)->getStatusCode() === 200, 'The social image is not served.');
    $favicon = (string) $xpath->evaluate('string(//link[@rel="icon"]/@href)');
    $expect($favicon !== '' && $public->get($favicon)->getStatusCode() === 200, 'The publication favicon is not served.');
    $expect(rtrim((string) $xpath->evaluate('string(//link[@rel="canonical"]/@href)'), '/') === $base.'/es/es', 'The home canonical is not its public URL.');
    $expect($xpath->query('//script[@type="application/ld+json"]')->length > 0, 'Missing structured data.');
    $expect(! str_contains((string) $xpath->evaluate('string(//meta[@name="robots"]/@content)'), 'noindex'), 'Production publication is marked noindex.');
    foreach ($xpath->query('//script[@type="application/ld+json"]') as $schema) {
        $data = json_decode($schema->textContent, true, 512, JSON_THROW_ON_ERROR);
        $expect(is_array($data) && ($data['@context'] ?? null) === 'https://schema.org', 'Invalid semantic schema.');
    }
    // Traverse all stored public editorial entities, not a hardcoded sample URL.
    $slugs = $probe->query("SELECT slug FROM page_translations WHERE locale = 'es'")->fetchAll(PDO::FETCH_COLUMN);
    $posts = $probe->query("SELECT slug FROM post_translations WHERE locale = 'es'")->fetchAll(PDO::FETCH_COLUMN);
    $expect($posts !== [], 'The learning guides were not provisioned.');
    $urls = array_map(static fn (string $slug): string => '/es/es/'.($slug === 'home' ? '' : $slug), $slugs);
    foreach ($posts as $slug) {
        $urls[] = '/es/es/blog/'.$slug;
    }
    $urls[] = '/es/es/blog';
    foreach ($probe->query("SELECT slug FROM category_translations WHERE locale = 'es'")->fetchAll(PDO::FETCH_COLUMN) as $slug) {
        $urls[] = '/es/es/blog/'.$slug;
    }
    foreach ($urls as $path) {
        $response = $public->get($path);
        $expect($response->getStatusCode() === 200, 'A provisioned route failed: '.$path);
        $page = $document((string) $response->getBody());
        $expect($page->query('//h1')->length === 1, 'An editorial route has no unique H1: '.$path);
        $expect((string) $page->evaluate('string(//meta[@name="description"]/@content)') !== '', 'Missing editorial description: '.$path);
        $expect(rtrim((string) $page->evaluate('string(//link[@rel="canonical"]/@href)'), '/') === rtrim($base.$path, '/'), 'Wrong editorial canonical: '.$path);
        foreach ($page->query('//main//a[@href]') as $link) {
            $url = $link->getAttribute('href');
            if (str_starts_with($url, $base.'/') || str_starts_with($url, '/es/')) {
                $expect($public->get($url)->getStatusCode() === 200, 'Broken editorial link: '.$url);
            }
        }
    }
    foreach (['/robots.txt', '/sitemap.xml', '/es/es/sitemap.xml', '/es/es/llms.txt', '/es/es/llms-full.txt'] as $path) {
        $response = $public->get($path);
        $expect($response->getStatusCode() === 200 && (string) $response->getBody() !== '', 'Discovery failed: '.$path);
    }
    $corpus = (string) $public->get('/es/es/llms-full.txt')->getBody();
    $expect(str_contains($corpus, $posts[0]), 'The public corpus omitted the guides.');
    $sitemap = (string) $public->get('/es/es/sitemap.xml')->getBody();
    $expect(simplexml_load_string($sitemap) !== false, 'The regional sitemap is not valid XML.');
    foreach ($posts as $slug) {
        $expect(str_contains($sitemap, '/blog/'.$slug), 'The sitemap omitted a guide: '.$slug);
        $expect(str_contains($corpus, '/blog/'.$slug), 'The semantic corpus omitted a guide: '.$slug);
    }
    // Publish a new page through the actual admin form with CSRF/session cookies.
    $dashboard = (string) $http->get('/admin')->getBody();
    $marketId = $probe->query("SELECT id FROM markets WHERE code = 'es'")->fetchColumn();
    $response = $http->post('/admin/pages', ['form_params' => [
        '_token' => $token($dashboard), 'is_published' => '1', 'market_ids' => [$marketId],
        'translations' => ['es' => ['title' => 'Learning publication proof', 'slug' => 'learning-publication-proof', 'meta_description' => 'A real editorial publication.']],
    ]]);
    $expect($response->getStatusCode() === 302, 'Minimal HTTP publication failed.');
    $expect($public->get('/es/es/learning-publication-proof')->getStatusCode() === 200, 'Published content is not public.');
    // A rerun is a preservation operation, never a destructive reseed.
    $run([PHP_BINARY, 'artisan', 'cms:starter:ai-learning', '--author='.$identity, '--market=es', '--no-interaction'], $environment);
    $expect($public->get('/es/es/learning-publication-proof')->getStatusCode() === 200, 'Starter rerun removed editor work.');
    $toggle = <<<'PHP'
    require 'vendor/autoload.php';
    $app = require 'bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $app->make(App\Core\Modules\ModuleManager::class)->%s('blog');
    PHP;
    $needle = 'Con estas notas, prepara una agenda y cinco preguntas abiertas.';
    $expect(str_contains($corpus, $needle), 'The semantic corpus lost a practical prompt.');
    $run([PHP_BINARY, '-r', sprintf($toggle, 'deactivate')], $environment);
    $expect($public->get('/es/es/blog/'.$posts[0])->getStatusCode() === 404, 'Disabled Blog remained executable.');
    $expect(! str_contains((string) $public->get('/es/es/llms-full.txt')->getBody(), $needle), 'Disabled Blog text remained discoverable.');
    $expect($public->get('/es/es/learning-publication-proof')->getStatusCode() === 200, 'Disabling Blog broke core publication.');
    $run([PHP_BINARY, '-r', sprintf($toggle, 'activate')], $environment);
    $expect($public->get('/es/es/blog/'.$posts[0])->getStatusCode() === 200, 'Reactivating Blog did not restore articles.');
    $expect(str_contains((string) $public->get('/es/es/llms-full.txt')->getBody(), $needle), 'Reactivating Blog did not restore semantic content.');
    echo "AI learning distribution passed: physically only Blog + Basic, fresh installation, production caches, authenticated administration, all editorial routes/links, semantic discovery and HTTP publication.\n";
} catch (Throwable $error) {
    foreach (glob($artifact.'/storage/logs/*.log') ?: [] as $log) {
        foreach (array_reverse(file($log, FILE_IGNORE_NEW_LINES) ?: []) as $line) {
            if (str_contains($line, '.ERROR:')) {
                fwrite(STDERR, substr($line, 0, 1800)."\n");
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
    $files->deleteDirectory($artifact);
}
