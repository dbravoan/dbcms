<?php

declare(strict_types=1);

use App\Core\Updates\FileUpdateEngine;
use App\Core\Updates\UpdateBarrier;

beforeEach(function (): void {
    $this->updateRoot = sys_get_temp_dir().'/dbcms-update-engine-'.bin2hex(random_bytes(8));
    mkdir($this->updateRoot.'/site', 0700, true);
    mkdir($this->updateRoot.'/stage', 0700, true);
    mkdir($this->updateRoot.'/private', 0700, true);
});

it('journals a replacement and accepts an interrupted replacement only at its verified new hash', function (): void {
    file_put_contents($this->updateRoot.'/site/core.php', 'old');
    file_put_contents($this->updateRoot.'/stage/core.php', 'new');
    $engine = new FileUpdateEngine($this->updateRoot.'/site', $this->updateRoot.'/stage', $this->updateRoot.'/private');
    $plan = [['path' => 'core.php', 'action' => 'replace', 'old_hash' => hash('sha256', 'old'), 'new_hash' => hash('sha256', 'new')]];
    expect($engine->apply($plan, 1))->toBe(1)
        ->and(file_get_contents($this->updateRoot.'/site/core.php'))->toBe('new')
        ->and($engine->apply($plan, 1))->toBe(1);
    expect($engine->restore($plan))->toBe(1)
        ->and(file_get_contents($this->updateRoot.'/site/core.php'))->toBe('old');
});

it('does not overwrite a file changed after approval', function (): void {
    file_put_contents($this->updateRoot.'/site/core.php', 'custom');
    file_put_contents($this->updateRoot.'/stage/core.php', 'new');
    $engine = new FileUpdateEngine($this->updateRoot.'/site', $this->updateRoot.'/stage', $this->updateRoot.'/private');
    expect(fn () => $engine->apply([['path' => 'core.php', 'action' => 'replace', 'old_hash' => hash('sha256', 'old'), 'new_hash' => hash('sha256', 'new')]], 1))->toThrow(RuntimeException::class);
    expect(file_get_contents($this->updateRoot.'/site/core.php'))->toBe('custom');
});

it('rejects traversal and symbolic parent paths independently of Laravel', function (): void {
    mkdir($this->updateRoot.'/outside');
    symlink($this->updateRoot.'/outside', $this->updateRoot.'/site/link');
    $engine = new FileUpdateEngine($this->updateRoot.'/site', $this->updateRoot.'/stage', $this->updateRoot.'/private');
    foreach (['../outside/payload', 'link/payload', '.env'] as $path) {
        expect(fn () => $engine->apply([['path' => $path, 'action' => 'create', 'old_hash' => null, 'new_hash' => hash('sha256', 'payload')]], 1))->toThrow(RuntimeException::class);
    }
});

it('holds admission until existing requests drain and blocks new admission during update', function (): void {
    $barrier = new UpdateBarrier($this->updateRoot.'/private');
    $lease = $barrier->admit();
    $barrier->close(['run_id' => 'test']);
    expect($barrier->drain())->toBeFalse()
        ->and($barrier->admit())->toBeNull();
    flock($lease, LOCK_UN);
    fclose($lease);
    expect($barrier->drain())->toBeTrue();
    $barrier->open();
    $nextLease = $barrier->admit();
    expect(is_resource($nextLease))->toBeTrue();
    fclose($nextLease);
});

it('backs up every affected file before replacing the first file', function (): void {
    file_put_contents($this->updateRoot.'/site/first.php', 'first-old');
    file_put_contents($this->updateRoot.'/site/last.php', 'last-old');
    file_put_contents($this->updateRoot.'/stage/first.php', 'first-new');
    file_put_contents($this->updateRoot.'/stage/last.php', 'last-new');
    $engine = new FileUpdateEngine($this->updateRoot.'/site', $this->updateRoot.'/stage', $this->updateRoot.'/private');
    $plan = array_map(fn ($name) => ['path' => $name.'.php', 'action' => 'replace', 'old_hash' => hash('sha256', $name.'-old'), 'new_hash' => hash('sha256', $name.'-new')], ['first', 'last']);
    $engine->backupPlan($plan);
    $engine->apply($plan, 1);
    expect(file_get_contents($this->updateRoot.'/private/files/last.php'))->toBe('last-old')
        ->and(file_get_contents($this->updateRoot.'/site/last.php'))->toBe('last-old')
        ->and(file_exists($this->updateRoot.'/private/file-inventory.json'))->toBeTrue();
});

it('permits public env templates but rejects runtime state and secret keys', function (): void {
    $engine = new FileUpdateEngine($this->updateRoot.'/site', $this->updateRoot.'/stage', $this->updateRoot.'/private');
    file_put_contents($this->updateRoot.'/stage/.env.installation.example', 'PUBLIC=value');
    $engine->apply([['path' => '.env.installation.example', 'action' => 'create', 'old_hash' => null, 'new_hash' => hash('sha256', 'PUBLIC=value')]], 1);
    expect(file_get_contents($this->updateRoot.'/site/.env.installation.example'))->toBe('PUBLIC=value');
    foreach (['public/uploads/test.php', 'bootstrap/cache/config.php', 'storage/installed', 'oauth-private.key'] as $path) {
        expect(fn () => $engine->apply([['path' => $path, 'action' => 'create', 'old_hash' => null, 'new_hash' => hash('sha256', 'private')]], 1))->toThrow(RuntimeException::class);
    }
});

it('recovers a torn completion record without losing a durable replacement intent', function (): void {
    file_put_contents($this->updateRoot.'/site/core.php', 'new');
    file_put_contents($this->updateRoot.'/stage/core.php', 'new');
    mkdir($this->updateRoot.'/private/files', 0700, true);
    file_put_contents($this->updateRoot.'/private/files/core.php', 'old');
    file_put_contents($this->updateRoot.'/private/journal.ndjson', "{\"index\":0,\"state\":\"started\"}\n{\"index\":0,\"sta");
    $engine = new FileUpdateEngine($this->updateRoot.'/site', $this->updateRoot.'/stage', $this->updateRoot.'/private');
    $plan = [['path' => 'core.php', 'action' => 'replace', 'old_hash' => hash('sha256', 'old'), 'new_hash' => hash('sha256', 'new')]];
    expect($engine->apply($plan, 1))->toBe(1)->and($engine->restore($plan))->toBe(1)
        ->and(file_get_contents($this->updateRoot.'/site/core.php'))->toBe('old');
});

it('rejects a corrupt completed journal line instead of discarding history', function (): void {
    file_put_contents($this->updateRoot.'/private/journal.ndjson', "not-json\n");
    $engine = new FileUpdateEngine($this->updateRoot.'/site', $this->updateRoot.'/stage', $this->updateRoot.'/private');
    expect(fn () => $engine->apply([], 1))->toThrow(JsonException::class);
});
