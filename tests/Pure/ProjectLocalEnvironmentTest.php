<?php

declare(strict_types=1);

use App\DTOs\ProjectProfile;
use App\Services\Projects\ProjectLocalEnvironmentService;
use Dotenv\Dotenv;

beforeEach(function (): void {
    $this->root = sys_get_temp_dir().'/dbcms-local-'.bin2hex(random_bytes(8));
    mkdir($this->root);
    file_put_contents($this->root.'/.env.example', "APP_KEY=\nCMS_BRAND=dbcms\nCMS_THEME=db-cyber\nCACHE_PREFIX=\nCMS_QUICK_LOGIN=true\nCMS_SEED_DEMO=true\n");
    $this->profile = new ProjectProfile('My $ite', 'termocms', ['db-cyber'], 'db-cyber', '1.2.0', str_repeat('a', 40), 'none');
    $this->local = new ProjectLocalEnvironmentService;
    $this->hostDirectory = getenv('DBCMS_PROJECT_DIRECTORY');
    putenv('DBCMS_PROJECT_DIRECTORY');
});

afterEach(function (): void {
    foreach (scandir($this->root) ?: [] as $name) {
        $path = $this->root.'/'.$name;
        if (is_file($path)) {
            unlink($path);
        }
    }
    rmdir($this->root);
    $this->hostDirectory === false ? putenv('DBCMS_PROJECT_DIRECTORY') : putenv('DBCMS_PROJECT_DIRECTORY='.$this->hostDirectory);
});

it('previews without writes then persists isolated local identity ports and safe defaults', function (): void {
    $plan = $this->local->plan($this->root, $this->profile);
    expect(file_exists($this->root.'/.env'))->toBeFalse();
    $this->local->apply($plan, $this->profile);
    $bytes = file_get_contents($this->root.'/.env');
    $env = Dotenv::parse($bytes);
    expect($env['APP_NAME'])->toBe('My $ite')
        ->and($env['CMS_BRAND'])->toBe('termocms')
        ->and($env['CMS_SEED_DEMO'])->toBe('false')
        ->and($env['CMS_QUICK_LOGIN'])->toBe('false')
        ->and($env['SESSION_COOKIE'])->toBe($plan->projectId.'_session')
        ->and($env['CACHE_PREFIX'])->toBe($plan->projectId.'_cache_')
        ->and($env['REDIS_PREFIX'])->toBe($plan->projectId.'_redis_')
        ->and($env['APP_URL'])->toBe($plan->urls['Application'])
        ->and($env['APP_PORT'])->toBe((string) $plan->ports['APP_PORT'])
        ->and(strlen(base64_decode(substr($env['APP_KEY'], 7), true)))->toBe(32);
    $this->local->apply($this->local->plan($this->root, $this->profile), $this->profile);
    expect(file_get_contents($this->root.'/.env'))->toBe($bytes);
});

it('preserves existing environment and OAuth keys while generating only an absent application key', function (): void {
    $original = "APP_PORT=23456\nDB_PASSWORD='secret value'\nPASSPORT_PRIVATE_KEY='keep-private'\nPASSPORT_PUBLIC_KEY='keep-public'\nCMS_QUICK_LOGIN=true\nCUSTOM='\$(touch never-run)'\n";
    file_put_contents($this->root.'/.env', $original);
    file_put_contents($this->root.'/installed.lock', 'preserve');
    $plan = $this->local->plan($this->root, $this->profile);
    expect($plan->createsEnvironment)->toBeFalse()->and($plan->urls['Installer'])->toBe('http://localhost:23456/install');
    $this->local->apply($plan, $this->profile);
    $bytes = file_get_contents($this->root.'/.env');
    expect($bytes)->toStartWith($original)->and(file_get_contents($this->root.'/installed.lock'))->toBe('preserve');
    $this->local->apply($this->local->plan($this->root, $this->profile), $this->profile);
    expect(file_get_contents($this->root.'/.env'))->toBe($bytes);
});

it('rejects environment changes after confirmation without overwriting them', function (): void {
    $plan = $this->local->plan($this->root, $this->profile);
    file_put_contents($this->root.'/.env', 'APP_KEY=concurrently-created');
    expect(fn () => $this->local->apply($plan, $this->profile))->toThrow(RuntimeException::class, 'changed after preview');
    expect(file_get_contents($this->root.'/.env'))->toBe('APP_KEY=concurrently-created');
});

it('rejects a host port occupied since preview and can plan a retry', function (): void {
    $plan = $this->local->plan($this->root, $this->profile);
    $socket = stream_socket_server('tcp://0.0.0.0:'.$plan->ports['APP_PORT']);
    try {
        expect(fn () => $this->local->apply($plan, $this->profile))->toThrow(RuntimeException::class, 'occupied');
        expect(file_exists($this->root.'/.env'))->toBeFalse();
        $retry = $this->local->plan($this->root, $this->profile);
        expect($retry->ports['APP_PORT'])->not->toBe($plan->ports['APP_PORT']);
        $this->local->apply($retry, $this->profile);
    } finally {
        fclose($socket);
    }
});

it('distinguishes host directories even though bootstrap mount paths are identical', function (): void {
    putenv('DBCMS_PROJECT_DIRECTORY=/projects/first');
    $first = $this->local->plan($this->root, $this->profile);
    putenv('DBCMS_PROJECT_DIRECTORY=/projects/second');
    $second = $this->local->plan($this->root, $this->profile);
    expect($first->projectId)->not->toBe($second->projectId);
});

it('rejects an environment symlink without reading or changing its target', function (): void {
    $target = $this->root.'/private-env';
    file_put_contents($target, 'PRIVATE=preserve');
    symlink($target, $this->root.'/.env');
    expect(fn () => $this->local->plan($this->root, $this->profile))->toThrow(RuntimeException::class, 'symbolic link');
    expect(file_get_contents($target))->toBe('PRIVATE=preserve');
});

it('rejects an environment replaced by a symlink after preview', function (): void {
    file_put_contents($this->root.'/.env', 'PRIVATE=preserve');
    $plan = $this->local->plan($this->root, $this->profile);
    rename($this->root.'/.env', $this->root.'/private-env');
    symlink($this->root.'/private-env', $this->root.'/.env');
    expect(fn () => $this->local->apply($plan, $this->profile))->toThrow(RuntimeException::class, 'symbolic link');
    expect(file_get_contents($this->root.'/private-env'))->toBe('PRIVATE=preserve');
});
