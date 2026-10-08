<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

it('keeps VPS hosting on current/shared releases and invokes PHP only through Docker tooling', function (): void {
    $root = __DIR__.'/../..';
    expect(file_get_contents($root.'/.github/workflows/deploy.yml'))
        ->toContain('bin/deploy-vps-release.sh', 'vars.VPS_DEPLOY_ENABLED')
        ->not->toContain('docker-compose.prod.yml');
    expect(file_get_contents($root.'/bin/vps-install.sh'))->toContain('vps-artisan.sh')->not->toContain('VPS_DEPLOYMENT', 'composer install');
});

it('promotes healthy releases and restores unhealthy ones without rolling back the database', function (string $httpStatus, bool $successful): void {
    $root = realpath(__DIR__.'/../..');
    expect(is_file($root.'/bin/deploy-vps-release.sh'))->toBeTrue();
    $temporary = sys_get_temp_dir().'/dbcms-vps-'.bin2hex(random_bytes(5));
    $application = $temporary.'/app';
    $old = $application.'/releases/release-old';
    $package = $temporary.'/package';
    mkdir($old, 0755, true);
    mkdir($package.'/bin', 0755, true);
    mkdir($package.'/vendor', 0755, true);
    mkdir($package.'/public/build', 0755, true);
    mkdir($temporary.'/mock', 0755);
    file_put_contents($old.'/artisan', 'fixture');
    file_put_contents($package.'/artisan', 'fixture');
    file_put_contents($package.'/vendor/autoload.php', '<?php');
    file_put_contents($package.'/public/build/manifest.json', '{}');
    copy($root.'/bin/vps-artisan.sh', $package.'/bin/vps-artisan.sh');
    file_put_contents($application.'/.env', "APP_KEY=fixture-stable-key\nAPP_URL=https://fixture.invalid\n");
    symlink($old, $application.'/current');
    $archive = $temporary.'/release-new.tar.gz';
    $tar = new Process(['tar', '-czf', $archive, '-C', $package, '.']);
    $tar->mustRun();
    file_put_contents($temporary.'/mock/docker', "#!/usr/bin/env bash\nprintf '%s\\n' \"\$*\" >> \"\$MOCK_LOG\"\nexit 0\n");
    file_put_contents($temporary.'/mock/sudo', "#!/usr/bin/env bash\nexit 0\n");
    chmod($temporary.'/mock/sudo', 0755);
    file_put_contents($temporary.'/mock/curl', "#!/usr/bin/env bash\nprintf {$httpStatus}\n");
    chmod($temporary.'/mock/docker', 0755);
    chmod($temporary.'/mock/curl', 0755);
    try {
        $process = new Process(['bash', $root.'/bin/deploy-vps-release.sh', $archive], $root, [
            'VPS_DIR' => $application, 'RELEASE_NAME' => 'release-new', 'VPS_CLI_IMAGE' => 'fixture-cli:sha',
            'VPS_HEALTH_URL' => 'https://fixture.invalid', 'VPS_FPM_SERVICE' => '',
            'PATH' => $temporary.'/mock:'.getenv('PATH'), 'MOCK_LOG' => $temporary.'/docker.log',
        ]);
        expect($process->run() === 0)->toBe($successful);
        expect(readlink($application.'/current'))->toBe($successful ? $application.'/releases/release-new' : $old);
        $calls = file_get_contents($temporary.'/docker.log');
        expect($calls)->toContain('migrate --force')->not->toContain('migrate:rollback', 'migrate:fresh');
        expect(readlink($application.'/releases/release-new/storage'))->toBe($application.'/shared/storage');
        expect(file_get_contents($application.'/.env'))->toContain('APP_KEY=fixture-stable-key');
    } finally {
        (new Process(['rm', '-rf', $temporary]))->mustRun();
    }
})->with(['unhealthy' => ['500', false], 'healthy' => ['200', true]]);
