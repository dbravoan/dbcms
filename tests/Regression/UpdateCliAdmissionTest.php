<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;

beforeEach(function (): void {
    $this->cliFixture = sys_get_temp_dir().'/update-cli-'.bin2hex(random_bytes(8));
    mkdir($this->cliFixture.'/storage/app/private/core-updates', 0700, true);
    mkdir($this->cliFixture.'/bootstrap', 0700);
    mkdir($this->cliFixture.'/vendor', 0700);
    copy(dirname(__DIR__, 2).'/artisan', $this->cliFixture.'/artisan');
    file_put_contents($this->cliFixture.'/storage/app/private/core-updates/active.json', '{"run_id":"fixture"}');
    file_put_contents($this->cliFixture.'/bootstrap/UpdateRuntime.php', '<?php class UpdateRuntime { public static function admit(string $root): bool { throw new RuntimeException("Recovery must not wait on its own barrier"); } }');
    file_put_contents($this->cliFixture.'/vendor/autoload.php', '<?php namespace Symfony\\Component\\Console\\Input; class ArgvInput {}');
    file_put_contents($this->cliFixture.'/bootstrap/app.php', '<?php return new class { public function handleCommand($input): int { echo "RECOVERY_ENTRYPOINT_OK"; return 0; } };');
});

afterEach(function (): void {
    (new Filesystem)->deleteDirectory($this->cliFixture);
});

it('admits the actual update recovery command before Laravel during maintenance', function (string $operation): void {
    $process = proc_open([PHP_BINARY, $this->cliFixture.'/artisan', 'cms:updates', $operation, 'execution-id', '--actor=1'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    expect(is_resource($process))->toBeTrue();
    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    expect(proc_close($process))->toBe(0)->and($output)->toBe('RECOVERY_ENTRYPOINT_OK')->and($errors)->toBe('');
})->with(['advance', 'status', 'cancel']);

it('blocks ordinary commands and new approvals before loading framework code', function (array $arguments): void {
    $process = proc_open([PHP_BINARY, $this->cliFixture.'/artisan', ...$arguments], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    expect(is_resource($process))->toBeTrue();
    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    expect(proc_close($process))->toBe(75)->and($output)->toBe('')->and($errors)->toContain('Actualización del núcleo en curso');
})->with([
    'migration' => [['migrate']],
    'worker' => [['queue:work']],
    'new approval' => [['cms:updates', 'approve']],
    'obsolete command name' => [['cms:updates:advance']],
]);
