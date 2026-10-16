<?php

declare(strict_types=1);

use App\Contracts\Services\SourceRevisionResolverInterface;
use App\Services\Projects\ProjectInitializationService;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/** Run only in an isolated CI testing database; installation intentionally migrates it. */
it('installs a physically pruned clone and renders its public and administrative HTTP surfaces', function (string $brand, string $theme, string $deployment): void {
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
        foreach (['artisan', 'composer.json', 'composer.lock', 'VERSION', '.env.example', '.env.production.example', '.env.installation.example', 'phpunit.xml', '.dockerignore', '.gitattributes', '.htaccess', 'package.json', 'package-lock.json', 'vite.config.js', 'phpstan.neon', 'pint.json', 'Makefile', 'README.md', 'SECURITY.md', 'CHANGELOG.md', 'LICENSE'] as $file) {
            if (is_file($source.'/'.$file)) {
                $files->copy($source.'/'.$file, $clone.'/'.$file);
            }
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
        (new ProjectInitializationService($files, $resolver, $clone))->initialize('clone-smoke', $brand, $theme, $deployment);
        expect(json_decode($files->get($clone.'/cms-project.json'), true)['deployment'])->toBe($deployment);
        expect(is_file($clone.'/.github/workflows/prepare-project.yml'))->toBeFalse();
        expect(array_map('basename', $files->directories($clone.'/themes')))->toBe([$theme]);
        expect(array_map('basename', $files->directories($clone.'/resources/views/themes/admin')))->toBe([$brand]);
        $files->copy($clone.'/.env.example', $clone.'/.env');
        $environment = ['APP_ENV' => 'testing', 'APP_KEY' => 'base64:'.base64_encode(str_repeat('x', 32)), 'CMS_BRAND' => $brand, 'CMS_THEME' => $theme, 'CMS_SEED_DEMO' => 'false', 'APP_CONFIG_CACHE' => $clone.'/bootstrap/cache/config.php', 'APP_ROUTES_CACHE' => $clone.'/bootstrap/cache/routes.php', 'APP_EVENTS_CACHE' => $clone.'/bootstrap/cache/events.php', 'VIEW_COMPILED_PATH' => $clone.'/storage/framework/views'];
        $reset = new Process([PHP_BINARY, 'artisan', 'migrate:fresh', '--force', '--no-interaction'], $clone, $environment);
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
        echo 'Public and login HTTP routes render; exactly one installation administrator exists.';
        PHP;
        $http = new Process([PHP_BINARY, '-r', $probe], $clone, $environment);
        $http->setTimeout(60);
        $http->run();
        expect($http->isSuccessful())->toBeTrue($http->getOutput().$http->getErrorOutput());
        if (getenv('RUN_DERIVED_PROFILE_SUITE') === '1') {
            $suite = new Process(['bash', 'bin/ci-derived-tests'], $clone, $environment + ['APP_BASE_PATH' => $clone, 'RUN_DERIVED_CLONE_SMOKE' => '0', 'RUN_DERIVED_PROFILE_SUITE' => '0']);
            $suite->setTimeout(300);
            $suite->run();
            expect($suite->isSuccessful())->toBeTrue($suite->getOutput().$suite->getErrorOutput());
            expect(is_dir($clone.'/.git'))->toBeFalse();
        }
    } finally {
        $files->deleteDirectory($clone);
    }
})->with(function (): array {
    $manifest = dirname(__DIR__, 2).'/cms-project.json';
    if (is_file($manifest)) {
        $profile = json_decode((string) file_get_contents($manifest), true, 512, JSON_THROW_ON_ERROR);

        return [[$profile['brand'], $profile['default_theme'], $profile['deployment']]];
    }

    return [['dbcms', 'db-minimal', 'github-actions'], ['termocms', 'db-editorial', 'docker']];
})->group('derived-installation');
