<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

test('zero-host guard script exists and is executable', function (): void {
    $guardScript = dirname(__DIR__, 2).'/bin/guard-host-php.sh';

    expect(file_exists($guardScript))->toBeTrue()
        ->and(is_executable($guardScript))->toBeTrue();
});

test('the host policy gives actionable official Sail examples', function (): void {
    $guard = (string) file_get_contents(dirname(__DIR__, 2).'/bin/guard-host-php.sh');
    preg_match_all('~\./vendor/bin/sail\h+(artisan|pest)\b~', $guard, $matches);

    expect(array_unique($matches[1]))->toContain('artisan', 'pest');
});

test('the Composer guard permits execution inside the current container', function (): void {
    $root = dirname(__DIR__, 2);
    $process = new Process(['bash', $root.'/bin/guard-host-php.sh'], $root);

    expect($process->run())->toBe(0, $process->getErrorOutput());
});

test('docker-compose contains all required containerized services', function (): void {
    $dockerComposePath = dirname(__DIR__, 2).'/docker-compose.yml';
    expect(file_exists($dockerComposePath))->toBeTrue();

    $content = (string) file_get_contents($dockerComposePath);
    expect($content)
        ->toContain('laravel.test')
        ->toContain('mysql')
        ->toContain('redis')
        ->toContain('mailpit');
});

test('composer scripts enforce the zero-host guard', function (): void {
    $composerJson = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/composer.json'), true);

    expect($composerJson['scripts']['pre-install-cmd'])
        ->toContain('bash bin/guard-host-php.sh');

    expect($composerJson['scripts']['pre-update-cmd'])
        ->toContain('bash bin/guard-host-php.sh');
});
