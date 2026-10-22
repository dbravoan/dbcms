<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3).'/bootstrap/InstallerBootstrap.php';

use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->root = sys_get_temp_dir().'/dbcms-bootstrap-'.bin2hex(random_bytes(8));
    File::ensureDirectoryExists($this->root.'/vendor', 0700);
    File::ensureDirectoryExists($this->root.'/storage', 0700);
    File::put($this->root.'/vendor/autoload.php', '<?php');
    File::put($this->root.'/.env.installation.example', "APP_ENV=production\nAPP_KEY=\nAPP_URL=http://localhost\nSESSION_DRIVER=file\nCACHE_STORE=file\nQUEUE_CONNECTION=sync\n");
});

afterEach(function (): void {
    File::deleteDirectory($this->root);
});

test('clean zip bootstrap atomically creates a protected environment and stable key', function (): void {
    $bootstrap = new InstallerBootstrap($this->root, ['HTTP_HOST' => 'cms.example', 'HTTPS' => 'on']);

    expect($bootstrap->prepare())->toBeNull();
    $first = File::get($this->root.'/.env');
    expect($first)->toContain('APP_URL="https://cms.example"')
        ->and($first)->toMatch('/^APP_KEY="base64:[A-Za-z0-9+\/=]+"$/m')
        ->and(fileperms($this->root.'/.env') & 0777)->toBe(0600);

    expect($bootstrap->prepare())->toBeNull()
        ->and(File::get($this->root.'/.env'))->toBe($first);
});

test('bootstrap preserves an existing valid environment byte for byte', function (): void {
    $existing = 'APP_KEY=base64:'.base64_encode(str_repeat('k', 32))."\nAPP_URL=https://configured.example/subdirectory\nCUSTOM_VALUE=keep-this-byte-for-byte\n";
    File::put($this->root.'/.env', $existing);
    chmod($this->root.'/.env', 0640);

    $bootstrap = new InstallerBootstrap($this->root, ['HTTP_HOST' => 'different.example', 'HTTPS' => 'on']);

    expect($bootstrap->prepare())->toBeNull()
        ->and(File::get($this->root.'/.env'))->toBe($existing)
        ->and(fileperms($this->root.'/.env') & 0777)->toBe(0640);
});

test('first deployment accepts a provisioned shared environment without replacing its link', function (): void {
    File::ensureDirectoryExists($this->root.'/shared');
    $existing = 'APP_KEY=base64:'.base64_encode(str_repeat('k', 32))."\nAPP_URL=https://configured.example\n";
    File::put($this->root.'/shared/.env', $existing);
    symlink($this->root.'/shared/.env', $this->root.'/.env');

    expect((new InstallerBootstrap($this->root, []))->prepare())->toBeNull()
        ->and(is_link($this->root.'/.env'))->toBeTrue()
        ->and(File::get($this->root.'/shared/.env'))->toBe($existing)
        ->and(file_exists($this->root.'/storage/install-bootstrap.lock'))->toBeFalse();
});

test('first deployment never generates a key through an invalid or dangling environment link', function (bool $exists): void {
    File::ensureDirectoryExists($this->root.'/shared');
    if ($exists) {
        File::put($this->root.'/shared/.env', "APP_KEY=\n");
    }
    symlink($this->root.'/shared/.env', $this->root.'/.env');

    expect((new InstallerBootstrap($this->root, []))->prepare())->not->toBeNull()
        ->and(is_link($this->root.'/.env'))->toBeTrue()
        ->and(is_file($this->root.'/shared/.env'))->toBe($exists);
    if ($exists) {
        expect(File::get($this->root.'/shared/.env'))->toBe("APP_KEY=\n");
    }
})->with([true, false]);

test('concurrent first requests persist one complete application key', function (): void {
    $gate = $this->root.'/start-concurrent-bootstrap';
    $runner = $this->root.'/run-bootstrap.php';
    File::put($runner, <<<'PHP'
<?php
require $argv[1];
while (! is_file($argv[3])) {
    usleep(1000);
}
$failure = (new InstallerBootstrap($argv[2], ['HTTP_HOST' => 'cms.example']))->prepare();
exit($failure === null ? 0 : 1);
PHP);
    $processes = [];
    for ($index = 0; $index < 2; $index++) {
        $process = proc_open(
            [PHP_BINARY, $runner, dirname(__DIR__, 3).'/bootstrap/InstallerBootstrap.php', $this->root, $gate],
            [['file', '/dev/null', 'r'], ['file', '/dev/null', 'a'], ['file', '/dev/null', 'a']],
            $pipes,
        );
        expect($process)->toBeResource();
        $processes[] = $process;
    }

    File::put($gate, 'go');
    foreach ($processes as $process) {
        expect(proc_close($process))->toBe(0);
    }

    $environment = File::get($this->root.'/.env');
    expect(substr_count($environment, 'APP_KEY='))->toBe(1)
        ->and($environment)->toMatch('/^APP_KEY="base64:[A-Za-z0-9+\/=]{44}"$/m')
        ->and(fileperms($this->root.'/.env') & 0777)->toBe(0600);
});

test('clean zip bootstrap ignores an untrusted host header', function (): void {
    $bootstrap = new InstallerBootstrap($this->root, ['HTTP_HOST' => "bad.example\r\nX-Injected: yes"]);

    expect($bootstrap->prepare())->toBeNull()
        ->and(File::get($this->root.'/.env'))->toContain('APP_URL="http://localhost"');
});

test('clean zip bootstrap does not trust a forwarded protocol header by itself', function (): void {
    $bootstrap = new InstallerBootstrap($this->root, ['HTTP_HOST' => 'cms.example', 'HTTP_X_FORWARDED_PROTO' => 'https']);

    expect($bootstrap->prepare())->toBeNull()
        ->and(File::get($this->root.'/.env'))->toContain('APP_URL="http://cms.example"');
});

test('sealed installation without its original key returns recovery guidance and never rotates it', function (): void {
    File::put($this->root.'/storage/installed', '{}');
    File::put($this->root.'/.env', "APP_KEY=\n");

    $failure = (new InstallerBootstrap($this->root, ['HTTP_HOST' => 'cms.example']))->prepare();

    expect($failure)->not->toBeNull()
        ->and($failure['title'])->toBe('La instalación necesita recuperación')
        ->and(File::get($this->root.'/.env'))->toBe("APP_KEY=\n");
});

test('bootstrap explains when the github source archive has no dependencies', function (): void {
    File::delete($this->root.'/vendor/autoload.php');

    $failure = (new InstallerBootstrap($this->root, []))->prepare();

    expect($failure)->not->toBeNull()
        ->and($failure['title'])->toBe('El paquete está incompleto')
        ->and(InstallerBootstrap::render($failure))->toContain('Source code (zip)');
});

test('bootstrap reports an unsupported php runtime without exposing a trace', function (): void {
    $failure = (new InstallerBootstrap($this->root, [], '8.2.20'))->prepare();

    expect($failure)->not->toBeNull()
        ->and($failure['title'])->toBe('PHP no es compatible')
        ->and(InstallerBootstrap::render($failure))->not->toContain($this->root);
});
