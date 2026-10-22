<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;
use Tests\Support\DerivedTestSelection;

require dirname(__DIR__).'/vendor/autoload.php';

try {
    $root = dirname(__DIR__);
    $arguments = array_slice($argv, 1);
    $lane = $arguments !== [] && ! str_starts_with($arguments[0], '-') ? array_shift($arguments) : 'integration';
    if (! in_array($lane, ['fast', 'integration', 'release'], true) || (in_array('--list', $arguments, true) && $arguments !== ['--list'])) {
        throw new DomainException('Usage: bin/ci-derived-tests [fast|integration|release] [--list]');
    }
    $requirements = json_decode((string) file_get_contents($root.'/tests/Support/derived-test-requirements.json'), true, flags: JSON_THROW_ON_ERROR);
    $plan = (new DerivedTestSelection($root, $requirements))->plan($lane);
    if ($arguments === ['--list']) {
        echo implode("\n", $plan['files'])."\n";
        exit;
    }
    if ($lane !== 'fast' && getenv('DBCMS_TEST_ISOLATED') !== '1') {
        throw new DomainException('Derived quality tests require an isolated Docker filesystem and database.');
    }
    $process = new Process([
        PHP_BINARY, '-d', 'memory_limit=2G', 'vendor/bin/pest', '--colors=always',
        '--configuration='.($lane === 'fast' ? 'phpunit.fast.xml' : 'phpunit.xml'),
        '--exclude-group=source-catalogue', '--exclude-group=derived-installation', '--compact', ...$plan['files'], ...$arguments,
    ], $root, ['CMS_BRAND' => $plan['profile']['brand'], 'CMS_THEME' => $plan['profile']['default_theme']]);
    $process->setTimeout(null);
    exit($process->run(static function (string $type, string $buffer): void {
        fwrite($type === Process::ERR ? STDERR : STDOUT, $buffer);
    }));
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage()."\n");
    exit(2);
}
