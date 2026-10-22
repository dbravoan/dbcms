<?php

declare(strict_types=1);

use App\Contracts\Services\ProjectSourceRepositoryInterface;
use App\Contracts\Services\SourceRevisionResolverInterface;
use App\Services\Projects\ProjectInitializationService;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/** Run only in an isolated CI testing database; installation intentionally migrates it. */
it('installs a physically pruned clone and renders its public and administrative HTTP surfaces', function (string $brand, array $themes, string $theme, string $deployment, string $modules, string $packages): void {
    if (getenv('RUN_DERIVED_CLONE_SMOKE') !== '1') {
        $this->markTestSkipped('Enable RUN_DERIVED_CLONE_SMOKE in an isolated CI testing database.');
    }

    $source = dirname(__DIR__, 2);
    $files = new Filesystem;
    $clone = sys_get_temp_dir().'/dbcms-derived-clone-'.bin2hex(random_bytes(8));
    $files->makeDirectory($clone, 0755, true);
    try {
        foreach (['app', 'bootstrap', 'config', 'database', 'routes', 'resources', 'themes', 'block-packages', 'modules', 'lang', 'tests', 'bin', 'stubs', '.github', 'docker', 'specs', 'docs'] as $directory) {
            if (is_dir($source.'/'.$directory)) {
                $files->copyDirectory($source.'/'.$directory, $clone.'/'.$directory);
            }
        }
        $files->ensureDirectoryExists($clone.'/public');
        foreach ($files->files($source.'/public') as $file) {
            $files->copy($file->getPathname(), $clone.'/public/'.$file->getBasename());
        }
        foreach ($files->directories($source.'/public') as $directory) {
            if (! in_array(basename($directory), ['storage', 'uploads'], true) && ! is_link($directory)) {
                $files->copyDirectory($directory, $clone.'/public/'.basename($directory));
            }
        }
        foreach (['artisan', 'composer.json', 'composer.lock', 'VERSION', '.env.example', '.env.production.example', '.env.installation.example', 'phpunit.xml', 'phpunit.fast.xml', '.dockerignore', '.gitattributes', '.htaccess', 'package.json', 'package-lock.json', 'vite.config.js', 'phpstan.neon', 'pint.json', 'Makefile', 'AGENTS.md', 'docker-compose.yml', 'README.md', 'SECURITY.md', 'CHANGELOG.md', 'LICENSE'] as $file) {
            if (is_file($source.'/'.$file)) {
                $files->copy($source.'/'.$file, $clone.'/'.$file);
            }
        }
        foreach ($files->allFiles($source.'/bin') as $file) {
            chmod($clone.'/bin/'.$file->getRelativePathname(), $file->getPerms() & 0777);
        }
        // No installed .env, keys, uploads, storage or database data are copied.
        $files->delete(glob($clone.'/bootstrap/cache/*.php') ?: []);
        foreach (['storage/framework/cache/data', 'storage/framework/views', 'storage/framework/sessions', 'storage/logs', 'storage/app/public'] as $directory) {
            $files->ensureDirectoryExists($clone.'/'.$directory);
        }
        // Keep Composer/Pest bootstrap paths local so application and test discovery
        // use the clone. Immutable third-party package payloads can be shared.
        $files->ensureDirectoryExists($clone.'/vendor/pestphp');
        foreach ($files->files($source.'/vendor') as $file) {
            $files->copy($file->getPathname(), $clone.'/vendor/'.$file->getBasename());
        }
        foreach ($files->directories($source.'/vendor') as $directory) {
            $name = basename($directory);
            if ($name === 'pestphp') {
                foreach ($files->directories($directory) as $package) {
                    if (basename($package) === 'pest') {
                        $files->copyDirectory($package, $clone.'/vendor/pestphp/pest');
                    } else {
                        symlink($package, $clone.'/vendor/pestphp/'.basename($package));
                    }
                }
            } elseif (in_array($name, ['composer', 'bin'], true)) {
                $files->copyDirectory($directory, $clone.'/vendor/'.$name);
            } else {
                symlink($directory, $clone.'/vendor/'.$name);
            }
        }
        $resolver = Mockery::mock(SourceRevisionResolverInterface::class);
        $resolver->shouldReceive('resolve')->andReturn(str_repeat('a', 40));
        $repository = Mockery::mock(ProjectSourceRepositoryInterface::class);
        $repository->shouldReceive('fetch')->andReturnNull();
        $repository->shouldReceive('read')->andReturnUsing(static fn (string $directory, string $sha, string $path): string => (string) file_get_contents($source.'/'.$path));
        (new ProjectInitializationService($files, $resolver, $clone, $repository))->initialize('clone-smoke', $brand, implode(',', $themes), $deployment, $modules, $packages);
        $prepared = json_decode($files->get($clone.'/cms-project.json'), true, flags: JSON_THROW_ON_ERROR);
        expect($prepared['source_selection']['module_directories'])->toEqualCanonicalizing(array_map('basename', $files->directories($clone.'/modules')));
        expect($prepared['source_selection']['block_package_directories'])->toEqualCanonicalizing(array_map('basename', $files->directories($clone.'/block-packages')));
        expect($prepared['source_selection']['include_showcase'])->toBeFalse();
        expect(json_decode($files->get($clone.'/cms-project.json'), true)['deployment'])->toBe($deployment);
        expect(is_file($clone.'/.github/workflows/prepare-project.yml'))->toBeFalse();
        expect(array_map('basename', $files->directories($clone.'/themes')))->toEqualCanonicalizing($themes);
        expect(array_map('basename', $files->directories($clone.'/resources/views/themes/admin')))->toBe([$brand]);
        $files->copy($clone.'/.env.example', $clone.'/.env');
        $environment = ['APP_ENV' => 'testing', 'APP_KEY' => 'base64:'.base64_encode(str_repeat('x', 32)), 'CMS_BRAND' => $brand, 'CMS_THEME' => $theme, 'CMS_SEED_DEMO' => 'false', 'APP_CONFIG_CACHE' => $clone.'/bootstrap/cache/config.php', 'APP_ROUTES_CACHE' => $clone.'/bootstrap/cache/routes.php', 'APP_EVENTS_CACHE' => $clone.'/bootstrap/cache/events.php', 'VIEW_COMPILED_PATH' => $clone.'/storage/framework/views'];
        $resetScript = <<<'PHP'
        require 'vendor/autoload.php';
        $app = require 'bootstrap/app.php';
        $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
        $kernel->bootstrap();
        Tests\Support\IsolatedTestDatabase::assertConnection();
        exit($kernel->call('migrate:fresh', ['--force' => true, '--no-interaction' => true]));
        PHP;
        $reset = new Process([PHP_BINARY, '-r', $resetScript], $clone, $environment);
        $reset->setTimeout(120);
        $reset->mustRun();
        $install = new Process([PHP_BINARY, 'artisan', 'cms:install', '--without-demo', '--db-host='.(getenv('DB_HOST') ?: 'mysql'), '--db-port='.(getenv('DB_PORT') ?: '3306'), '--db-database='.(getenv('DB_DATABASE') ?: 'testing'), '--db-username='.(getenv('DB_USERNAME') ?: 'sail'), '--db-password='.(getenv('DB_PASSWORD') ?: 'secret'), '--admin-name=Clone Admin', '--admin-email=clone-admin@example.invalid', '--admin-password=clone-ci-password-9281', '--brand='.$brand, '--theme='.$theme, '--force', '--no-interaction'], $clone, $environment);
        $install->setTimeout(180);
        $install->run();
        expect($install->isSuccessful())->toBeTrue($install->getOutput().$install->getErrorOutput());
        expect(is_file($clone.'/storage/installed'))->toBeTrue();
        $probe = <<<'PHP'
        require 'vendor/autoload.php';
        $app = require 'bootstrap/app.php';
        $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
        foreach (['/', '/admin/login'] as $uri) {
            $request = Illuminate\Http\Request::create($uri);
            $response = $kernel->handle($request);
            for ($redirect = 0; $response->isRedirect() && $redirect < 4; $redirect++) {
                $kernel->terminate($request, $response);
                $request = Illuminate\Http\Request::create($response->headers->get('Location'));
                $response = $kernel->handle($request);
            }
            if ($response->getStatusCode() !== 200) {
                fwrite(STDERR, $uri.' status '.$response->getStatusCode()."\n".$response->getContent());
                exit(1);
            }
            $kernel->terminate($request, $response);
        }
        $admin = App\Models\User::where('email', 'clone-admin@example.invalid')->firstOrFail();
        $app['auth']->guard('web')->setUser($admin);
        $request = Illuminate\Http\Request::create('/admin');
        $response = $kernel->handle($request);
        if ($response->getStatusCode() !== 200) {
            fwrite(STDERR, 'Authenticated dashboard status '.$response->getStatusCode()."\n".$response->getContent());
            exit(1);
        }
        $kernel->terminate($request, $response);
        if (App\Models\User::count() !== 1) { throw new RuntimeException('Demo accounts were installed'); }
        $market = App\Models\Market::where('is_active', true)->where('is_default', true)->firstOrFail();
        $locale = $market->default_locale;
        $blocks = $app->make(App\Core\Blocks\Contracts\BlockRegistryInterface::class)->isAvailable('text');
        $data = [
            'is_published' => true, 'market_ids' => [$market->id],
            'translations' => [$locale => ['title' => 'Derived semantic publication', 'slug' => 'derived-publication', 'meta_description' => 'A published page from the selected child source.']],
        ];
        if ($blocks) {
            $data['sections_json'] = json_encode([$locale => [[
                'type' => 'text', 'title' => 'Derived semantic publication',
                'content' => 'A verifiable published body. [Verification link](https://example.invalid/verify)', 'format' => 'markdown',
            ]]], JSON_THROW_ON_ERROR);
        }
        $page = $app->make(App\Content\Pages\Contracts\PageDomainServiceInterface::class)->createPage($data, $admin);
        if (!$page->is_published) { throw new RuntimeException('Core mutation did not publish the page'); }
        $app['auth']->guard('web')->forgetUser();
        $publicPath = '/'.$market->code.'/'.$locale.'/derived-publication';
        $request = Illuminate\Http\Request::create($publicPath);
        $response = $kernel->handle($request);
        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new DOMXPath($document);
        if ($response->getStatusCode() !== 200 || !str_contains($document->getElementsByTagName('title')->item(0)?->textContent ?? '', 'Derived semantic publication')) {
            throw new RuntimeException('Anonymous publication did not deliver the page title: '.$response->getContent());
        }
        if ($xpath->query('//meta[@name="description" and @content="A published page from the selected child source."]')->length !== 1
            || !str_contains($xpath->query('//link[@rel="canonical"]')->item(0)?->getAttribute('href') ?? '', $publicPath)) {
            throw new RuntimeException('Published metadata or canonical link was not delivered');
        }
        if ($blocks && (!str_contains($response->getContent(), 'A verifiable published body.') || $xpath->query('//a[@href="https://example.invalid/verify"]')->length !== 1)) {
            throw new RuntimeException('Published section text or verification link was lost');
        }
        $kernel->terminate($request, $response);
        $request = Illuminate\Http\Request::create('/'.$market->code.'/'.$locale.'/sitemap.xml');
        $response = $kernel->handle($request);
        if ($response->getStatusCode() !== 200 || !str_contains($response->getContent(), 'derived-publication')) {
            throw new RuntimeException('The selected child sitemap omitted the published page');
        }
        $kernel->terminate($request, $response);
        echo 'Selected child installed, authenticated, published semantic content and delivered it anonymously with canonical metadata and sitemap.';
        PHP;
        $http = new Process([PHP_BINARY, '-r', $probe], $clone, $environment);
        $http->setTimeout(60);
        $http->run();
        expect($http->isSuccessful())->toBeTrue($http->getOutput().$http->getErrorOutput());
        if (getenv('RUN_DERIVED_PROFILE_SUITE') === '1') {
            if (is_dir($source.'/node_modules')) {
                symlink($source.'/node_modules', $clone.'/node_modules');
            }
            $evidence = sys_get_temp_dir().'/dbcms-derived-'.$brand.'-'.$theme.'-'.$modules.'-'.$packages;
            foreach (['fast', 'integration'] as $lane) {
                $suite = new Process(['bash', 'bin/ci-derived-tests', $lane], $clone, $environment + [
                    'APP_BASE_PATH' => $clone, 'RUN_DERIVED_CLONE_SMOKE' => '0', 'RUN_DERIVED_PROFILE_SUITE' => '0',
                    'DBCMS_TEST_ISOLATED' => $lane === 'fast' ? '0' : '1',
                ]);
                $suite->setTimeout(1800);
                $suite->run();
                file_put_contents($evidence.'-'.$lane.'.log', $suite->getOutput().$suite->getErrorOutput());
                expect($suite->isSuccessful())->toBeTrue($suite->getOutput().$suite->getErrorOutput());
            }
            $frontend = new Process(['node', 'bin/test-frontend.mjs'], $clone, $environment);
            $frontend->setTimeout(180);
            $frontend->run();
            file_put_contents($evidence.'-frontend.log', $frontend->getOutput().$frontend->getErrorOutput());
            expect($frontend->isSuccessful())->toBeTrue($frontend->getOutput().$frontend->getErrorOutput());
            expect(is_dir($clone.'/.git'))->toBeFalse();
        }
    } finally {
        $files->deleteDirectory($clone);
    }
})->with(function (): array {
    $manifest = dirname(__DIR__, 2).'/cms-project.json';
    if (is_file($manifest)) {
        $profile = json_decode((string) file_get_contents($manifest), true, 512, JSON_THROW_ON_ERROR);

        return [[$profile['brand'], $profile['themes'], $profile['default_theme'], $profile['deployment'], 'all', 'all']];
    }

    $themes = ['db-cyber', ...array_values(array_diff(array_map('basename', glob(dirname(__DIR__, 2).'/themes/*', GLOB_ONLYDIR) ?: []), ['db-cyber']))];

    return [
        'core only' => ['dbcms', ['db-minimal'], 'db-minimal', 'github-actions', 'none', 'none'],
        'core ansistrano' => ['termocms', ['db-minimal'], 'db-minimal', 'ansistrano', 'none', 'none'],
        'single content module' => ['termocms', ['db-editorial'], 'db-editorial', 'rsync', 'blog', 'basic'],
        'dependency closure' => ['termocms', ['db-editorial'], 'db-editorial', 'docker', 'orchestration', 'basic'],
        'complete source selection' => ['dbcms', $themes, 'db-cyber', 'none', 'all', 'all'],
    ];
})->group('derived-installation');
