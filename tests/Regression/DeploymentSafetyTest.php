<?php

declare(strict_types=1);

it('builds production assets with their generator and theme sources', function (): void {
    $dockerfile = file_get_contents(__DIR__.'/../../docker/production/Dockerfile');
    expect($dockerfile)->toContain('COPY bin ./bin', 'COPY themes ./themes');
});

it('keeps local secrets out of the Docker build context and wrappers from changing permissions', function (): void {
    $ignore = __DIR__.'/../../.dockerignore';
    expect(is_file($ignore))->toBeTrue();
    expect(file_get_contents($ignore))->toContain('.env', 'storage/*.key');
    expect(file_get_contents(__DIR__.'/../../bin/sail-run'))->not->toContain('chmod -R 777');
});
