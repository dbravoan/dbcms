<?php

declare(strict_types=1);

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

it('builds production assets with their generator and theme sources', function (): void {
    $hasFrontendBuild = static function (string $dockerfile): bool {
        if (! preg_match('/^\h*FROM\h+[^\r\n]*\h+AS\h+frontend\h*(?:#[^\r\n]*)?\R(.*?)(?=^\h*FROM\h|\z)/ims', $dockerfile, $stage)) {
            return false;
        }

        return preg_match('/^\h*COPY\h+\.\h+\.\h*(?:#[^\r\n]*)?$/mi', $stage[1], $copy, PREG_OFFSET_CAPTURE) === 1
            && preg_match('/^\h*RUN\h+[^\r\n]*\bnpm\h+run\h+build\b/mi', $stage[1], $build, PREG_OFFSET_CAPTURE) === 1
            && $copy[0][1] < $build[0][1];
    };
    $dockerfile = (string) file_get_contents(__DIR__.'/../../docker/production/Dockerfile');
    expect($hasFrontendBuild($dockerfile))->toBeTrue();

    // Unrelated stages cannot supply either requirement for the frontend stage.
    $prefix = "FROM node:24-alpine AS node-tools\nCOPY . .\nRUN npm run build\n";
    $suffix = "FROM php:8.3 AS runtime\nCOPY . .\nRUN npm run build\n";
    expect($hasFrontendBuild($prefix."  from node-tools as frontend\n copy . .\n run npm run build\n".$suffix))->toBeTrue()
        ->and($hasFrontendBuild($prefix."FROM node-tools AS frontend\nCOPY package.json ./\nRUN npm run build\n".$suffix))->toBeFalse()
        ->and($hasFrontendBuild($prefix."FROM node-tools AS frontend\nCOPY . .\nRUN npm ci\n".$suffix))->toBeFalse();
});

it('keeps local secrets out of the Docker build context', function (): void {
    $ignore = __DIR__.'/../../.dockerignore';
    expect(is_file($ignore))->toBeTrue();
    expect(file_get_contents($ignore))->toContain('.env', 'storage/*.key');
});

it('preserves unrelated protected files when executing a project helper', function (): void {
    $temporary = sys_get_temp_dir().'/dbcms-helper-permissions-'.bin2hex(random_bytes(6));
    mkdir($temporary.'/bin', 0755, true);
    mkdir($temporary.'/mock', 0755);
    mkdir($temporary.'/storage', 0700);
    $protected = $temporary.'/storage/private-fixture';
    file_put_contents($protected, 'private fixture');
    chmod($protected, 0600);
    copy(__DIR__.'/../../bin/generate-llms-txt.sh', $temporary.'/bin/generate-llms-txt.sh');
    file_put_contents($temporary.'/mock/php', "#!/usr/bin/env bash\nexit 0\n");
    chmod($temporary.'/mock/php', 0755);
    try {
        $process = new Process(['bash', $temporary.'/bin/generate-llms-txt.sh'], sys_get_temp_dir(), [
            'PATH' => $temporary.'/mock:'.getenv('PATH'),
        ]);
        expect($process->run())->toBe(0, $process->getErrorOutput());
        clearstatcache(true, $protected);
        expect(fileperms($protected) & 0777)->toBe(0600);
        expect(file_get_contents($protected))->toBe('private fixture');
    } finally {
        (new Filesystem)->remove($temporary);
    }
});
