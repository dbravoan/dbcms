<?php

declare(strict_types=1);

use App\Core\Updates\FileUpdateEngine;
use Tests\Support\RuntimeHttpRecoveryFixture;

afterEach(function (): void {
    if (isset($this->httpRecovery)) {
        $this->httpRecovery->close();
    }
});

it('denies standalone recovery without its cookie or matching csrf and keeps files untouched', function (): void {
    $this->httpRecovery = new RuntimeHttpRecoveryFixture;
    expect($this->httpRecovery->request('GET', false)['status'])->toBe(403);
    expect($this->httpRecovery->request('POST', true, '', 'incorrect')['status'])->toBe(403);
    expect(file_get_contents($this->httpRecovery->root.'/app/file-0.php'))->toBe('old-0');
});

it('revalidates current permissions even while the recovery cookie remains valid', function (): void {
    $this->httpRecovery = new RuntimeHttpRecoveryFixture;
    $this->httpRecovery->pdo->exec('DELETE FROM `'.$this->httpRecovery->prefix.'permission_role` WHERE permission_id=2');
    expect($this->httpRecovery->request('POST')['status'])->toBeIn([403, 409]);
    expect(file_get_contents($this->httpRecovery->root.'/app/file-0.php'))->toBe('old-0');
});

it('requires password renewal after fifteen minutes within the recovery cookie lifetime', function (): void {
    $this->httpRecovery = new RuntimeHttpRecoveryFixture;
    $this->httpRecovery->run['recovery']['expires_at'] = time() - 1;
    $this->httpRecovery->save();
    $expired = $this->httpRecovery->request('POST');
    expect($expired['status'])->toBe(403)->and($expired['body']['reauthentication_required'])->toBeTrue();
    $renewed = $this->httpRecovery->request('POST', true, 'password=fixture-admin-password');
    expect($renewed['status'])->toBe(200);
    expect(file_get_contents($this->httpRecovery->root.'/app/file-0.php'))->toBe('old-0');
    expect($this->httpRecovery->request('POST')['status'])->toBe(200);
    expect(file_get_contents($this->httpRecovery->root.'/app/file-0.php'))->toBe('new-0');
});

it('rejects revoked sessions during recovery', function (): void {
    $this->httpRecovery = new RuntimeHttpRecoveryFixture;
    $this->httpRecovery->pdo->exec('DELETE FROM `'.$this->httpRecovery->prefix.'sessions`');
    expect($this->httpRecovery->request('POST')['status'])->toBeIn([403, 409]);
    expect(file_get_contents($this->httpRecovery->root.'/app/file-0.php'))->toBe('old-0');
});

it('rejects changed account credentials and expired recovery cookies', function (): void {
    $this->httpRecovery = new RuntimeHttpRecoveryFixture;
    $fixture = $this->httpRecovery;
    $statement = $fixture->pdo->prepare('UPDATE `'.$fixture->prefix.'users` SET password=? WHERE id=1');
    $statement->execute([password_hash('replacement-password', PASSWORD_BCRYPT, ['cost' => 4])]);
    expect($fixture->request('POST')['status'])->toBe(403);
    $fixture->run['recovery']['cookie_expires_at'] = time() - 1;
    $fixture->save();
    expect($fixture->request()['status'])->toBe(403);
    expect(file_get_contents($fixture->root.'/app/file-0.php'))->toBe('old-0');
});

it('resumes interrupted replacement batches over HTTP using only the protected snapshot', function (): void {
    $this->httpRecovery = new RuntimeHttpRecoveryFixture(42);
    $fixture = $this->httpRecovery;
    $engine = new FileUpdateEngine($fixture->root, $fixture->root.'/stage', $fixture->runDirectory.'/backup');
    expect($engine->apply($fixture->run['plan'], 1))->toBe(1);
    // Crash after the second replacement but before its completion checkpoint.
    copy($fixture->root.'/stage/app/file-1.php', $fixture->root.'/app/file-1.php');
    file_put_contents($fixture->runDirectory.'/backup/journal.ndjson', json_encode(['index' => 1, 'state' => 'started'], JSON_THROW_ON_ERROR)."\n", FILE_APPEND);
    $first = $fixture->request('POST');
    expect($first['status'])->toBe(200)->and($first['body']['status'])->toBe('files');
    $second = $fixture->request('POST');
    expect($second['status'])->toBe(200)->and($second['body']['status'])->toBe('files_applied');
    for ($index = 0; $index < 42; $index++) {
        expect(file_get_contents($fixture->root.'/app/file-'.$index.'.php'))->toBe('new-'.$index);
    }
    expect(file_get_contents($fixture->root.'/.env'))->toBe('APP_KEY=preserved-fixture-key');
    expect(is_file($fixture->root.'/storage/app/private/core-updates/active.json'))->toBeTrue();
});

it('recovers a lost browser tab through a new csrf and administrator password', function (): void {
    $this->httpRecovery = new RuntimeHttpRecoveryFixture;
    $fixture = $this->httpRecovery;
    $oldCsrf = $fixture->csrf;
    $html = $fixture->recoveryHtml();
    expect(preg_match('/const recoveryCsrf="([a-f0-9]+)"/', $html, $match))->toBe(1);
    expect($match[1])->not->toBe($oldCsrf);
    expect($fixture->request('POST', true, '', $oldCsrf)['status'])->toBe(403);
    $fixture->csrf = $match[1];
    $renewalRequired = $fixture->request('POST');
    expect($renewalRequired['status'])->toBe(403)->and($renewalRequired['body']['reauthentication_required'])->toBeTrue();
    expect($fixture->request('POST', true, 'password=fixture-admin-password')['status'])->toBe(200);
    expect($fixture->request('POST')['status'])->toBe(200);
    expect(file_get_contents($fixture->root.'/app/file-0.php'))->toBe('new-0');
});
