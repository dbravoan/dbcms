<?php

declare(strict_types=1);

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

it('dispatches container tools from the project directory without nested runtimes', function (string $script, string $command, array $arguments, int $phpStatus, array $composerArguments, int $composerStatus = 0, int $expectedExit = 0): void {
    $root = dirname(__DIR__, 2);
    $temporary = sys_get_temp_dir().'/dbcms-sail-contract-'.bin2hex(random_bytes(6));
    mkdir($temporary.'/bin', 0755, true);
    mkdir($temporary.'/mock', 0755);
    mkdir($temporary.'/vendor/bin', 0755, true);
    file_put_contents($temporary.'/vendor/autoload.php', '<?php');
    copy($root.'/bin/'.$script, $temporary.'/bin/'.$script);
    $forbidden = <<<'BASH'
#!/usr/bin/env bash
printf '%s\n' "$0" >> "$SAIL_CONTRACT_LOG.forbidden"
exit 97
BASH;
    foreach (['mock/docker', 'vendor/bin/sail', 'bin/sail-run'] as $path) {
        file_put_contents($temporary.'/'.$path, $forbidden);
        chmod($temporary.'/'.$path, 0755);
    }
    file_put_contents($temporary.'/mock/php', <<<'BASH'
#!/usr/bin/env bash
printf '%s\n' "$PWD" > "$SAIL_CONTRACT_LOG.cwd"
printf '%s\0' "$@" > "$SAIL_CONTRACT_LOG.args"
exit "$SAIL_CONTRACT_PHP_STATUS"
BASH);
    file_put_contents($temporary.'/mock/composer', <<<'BASH'
#!/usr/bin/env bash
printf '%s\n' "$PWD" > "$SAIL_CONTRACT_LOG.composer.cwd"
printf '%s\0' "$@" > "$SAIL_CONTRACT_LOG.composer.args"
exit "$SAIL_CONTRACT_COMPOSER_STATUS"
BASH);
    chmod($temporary.'/mock/php', 0755);
    chmod($temporary.'/mock/composer', 0755);
    try {
        $process = new Process(['bash', $temporary.'/bin/'.$script, ...$arguments], sys_get_temp_dir(), [
            'PATH' => $temporary.'/mock:'.getenv('PATH'),
            'SAIL_CONTRACT_LOG' => $temporary.'/calls',
            'SAIL_CONTRACT_PHP_STATUS' => (string) $phpStatus,
            'SAIL_CONTRACT_COMPOSER_STATUS' => (string) $composerStatus,
        ]);
        expect($process->run())->toBe($expectedExit, $process->getErrorOutput());
        expect(is_file($temporary.'/calls.forbidden'))->toBeFalse();
        expect(trim((string) file_get_contents($temporary.'/calls.cwd')))->toBe($temporary);
        expect(explode("\0", rtrim((string) file_get_contents($temporary.'/calls.args'), "\0")))
            ->toBe(['artisan', $command, ...$arguments]);
        if ($composerArguments === []) {
            expect(is_file($temporary.'/calls.composer.args'))->toBeFalse();
        } else {
            expect(trim((string) file_get_contents($temporary.'/calls.composer.cwd')))->toBe($temporary);
            expect(explode("\0", rtrim((string) file_get_contents($temporary.'/calls.composer.args'), "\0")))
                ->toBe($composerArguments);
        }
    } finally {
        (new Filesystem)->remove($temporary);
    }
})->with([
    'initialize' => ['project-init', 'cms:project:init', ['fixture-project', '--brand=dbcms', '--theme=db-minimal', '--deploy=none'], 0, ['dump-autoload', '--no-interaction']],
    'update' => ['project-update', 'cms:project:update', ['1.3.0', '--working-copy'], 0, ['dump-autoload', '--no-interaction']],
    'update failure propagates' => ['project-update', 'cms:project:update', ['1.3.0', '--working-copy'], 42, ['dump-autoload', '--no-interaction'], 0, 42],
    'GEO' => ['generate-llms-txt.sh', 'geo:generate-llms-txt', [], 0, []],
    'GEO failure propagates' => ['generate-llms-txt.sh', 'geo:generate-llms-txt', [], 42, [], 0, 42],
    'LTS' => ['check-lts-updates.sh', 'system:check-lts', [], 0, []],
    'LTS fallback audit' => ['check-lts-updates.sh', 'system:check-lts', [], 42, ['audit']],
    'LTS failed audit propagates' => ['check-lts-updates.sh', 'system:check-lts', [], 42, ['audit'], 55, 55],
]);
