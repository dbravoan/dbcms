<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2).'/bootstrap/RuntimeEnvironmentBootstrap.php';

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

beforeEach(function (): void {
    $this->runtimeDirectory = sys_get_temp_dir().'/dbcms-runtime-env-'.bin2hex(random_bytes(8));
    mkdir($this->runtimeDirectory, 0700);
    $this->runtimeKey = 'base64:'.base64_encode(str_repeat('r', 32));
});

afterEach(function (): void {
    (new Filesystem)->deleteDirectory($this->runtimeDirectory);
});

test('container bootstrap atomically provisions a private environment with the supplied stable key', function (): void {
    $path = $this->runtimeDirectory.'/.env';
    RuntimeEnvironmentBootstrap::ensure($path, $this->runtimeKey, 'https://web.example');
    $content = file_get_contents($path);
    RuntimeEnvironmentBootstrap::ensure($path, $this->runtimeKey, 'https://different.example');

    expect(file_get_contents($path))->toBe($content)
        ->and(Dotenv\Dotenv::parse($content))->toMatchArray(['APP_ENV' => 'production', 'APP_KEY' => $this->runtimeKey, 'APP_URL' => 'https://web.example'])
        ->and(fileperms($path) & 0777)->toBe(0600);
});

test('container bootstrap rejects a changed key or symlink without replacing persistent configuration', function (bool $link): void {
    $path = $this->runtimeDirectory.'/.env';
    $content = 'APP_KEY='.$this->runtimeKey."\n";
    file_put_contents($this->runtimeDirectory.'/original', $content);
    if ($link) {
        symlink($this->runtimeDirectory.'/original', $path);
    } else {
        file_put_contents($path, $content);
    }

    expect(fn () => RuntimeEnvironmentBootstrap::ensure($path, 'base64:'.base64_encode(str_repeat('x', 32)), 'https://web.example'))->toThrow(RuntimeException::class);
    expect(file_get_contents($path))->toBe($content)
        ->and(is_link($path))->toBe($link);
})->with([true, false]);

test('concurrent containers preserve one supplied key without overwriting each other', function (bool $sameKey): void {
    $root = dirname(__DIR__, 2);
    $script = 'require $argv[1]."/vendor/autoload.php"; require $argv[1]."/bootstrap/RuntimeEnvironmentBootstrap.php"; try { RuntimeEnvironmentBootstrap::ensure($argv[2], $argv[3], "https://web.example"); } catch (Throwable) { exit(1); }';
    $keys = [$this->runtimeKey, $sameKey ? $this->runtimeKey : 'base64:'.base64_encode(str_repeat('x', 32))];
    $processes = [];
    foreach ($keys as $key) {
        $process = new Process([PHP_BINARY, '-r', $script, $root, $this->runtimeDirectory.'/.env', $key]);
        $process->start();
        $processes[] = $process;
    }
    $statuses = array_map(static fn (Process $process): int => $process->wait(), $processes);
    sort($statuses);

    expect($statuses)->toBe($sameKey ? [0, 0] : [0, 1])
        ->and(Dotenv\Dotenv::parse(file_get_contents($this->runtimeDirectory.'/.env'))['APP_KEY'])->toBeIn($keys);
})->with([true, false]);
