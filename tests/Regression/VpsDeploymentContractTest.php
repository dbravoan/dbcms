<?php

declare(strict_types=1);

use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

it('deploys verified prepared runtimes over pinned SSH without rebuilding', function (): void {
    $root = __DIR__.'/../..';
    $workflow = (string) file_get_contents($root.'/.github/workflows/deploy.yml');
    expect($workflow)->toContain('bin/deploy-vps-release.sh', 'vars.DEPLOY_ENABLED', 'rsync', '"github-actions"', 'github-delivery.py stage', 'extract-runtime')
        ->not->toContain('build-release-zip', 'docker save', 'docker load', 'docker/build-push-action', 'upload-artifact', 'docker-compose.prod.yml', 'docker/login-action', 'ghcr.io');
    expect(file_get_contents($root.'/bin/deploy-vps-release.sh'))->not->toContain('tar -x', 'migrate:rollback');
    expect(file_get_contents($root.'/bin/vps-artisan.sh'))->toContain('VPS_PHP_BINARY')->not->toContain('docker run', 'VPS_CLI_IMAGE');
});

it('keeps product distributions in the upstream repository and prepares descendants through a validated PR', function (): void {
    $root = __DIR__.'/../..';
    $release = Yaml::parseFile($root.'/.github/workflows/release.yml');
    expect($release['jobs']['publish']['if'] ?? '')->toContain("github.repository == 'dbravoan/dbcms'");
    $prepare = (string) file_get_contents($root.'/.github/workflows/prepare-project.yml');
    expect($prepare)->toContain('./bin/project-init', '--brand', '--theme', 'gh pr create --draft', 'bin/prepare-delivery');
});

it('uses immutable registry images rather than archives for Docker deployments', function (): void {
    $root = __DIR__.'/../..';
    expect(is_file($root.'/.github/workflows/deploy-docker.yml'))->toBeFalse();
    expect(file_get_contents($root.'/bin/deploy-container.sh'))->toContain('pull', 'storage/installed')
        ->not->toContain('docker load', 'image.tar', 'migrate:rollback');
});

it('keeps local deployment independent of Git and containerizes Ansistrano only when selected', function (): void {
    $root = __DIR__.'/../..';
    foreach (['rsync', 'ansistrano'] as $profile) {
        expect(file_get_contents($root.'/bin/deploy-'.$profile))->toContain('deployment_build_runtime '.$profile)
            ->not->toContain('git archive', 'ghcr.io', 'GITHUB_TOKEN');
    }
    expect(file_get_contents($root.'/bin/deploy-local-common.sh'))->toContain('deployment_check_context', "--exclude='.git'", 'VPS_SSH_KEY_FILE')
        ->not->toContain('git archive', 'git rev-parse');
    $requirements = Yaml::parseFile($root.'/deploy/ansistrano/requirements.yml');
    expect($requirements['roles'][0]['version'])->toMatch('/^[a-f0-9]{40}$/');
    $playbook = (string) file_get_contents($root.'/deploy/ansistrano/deploy.yml');
    expect($playbook)->toContain('ansistrano.deploy', 'ansistrano_deploy_via: rsync', 'hooks/prepare.yml', 'hooks/finalize.yml');
});

it('rejects a mismatched profile and incompatible native PHP before changing shared data', function (string $profile, string $minimum, bool $missingRootExtension = false): void {
    $root = realpath(__DIR__.'/../..');
    $temporary = sys_get_temp_dir().'/dbcms-native-preflight-'.bin2hex(random_bytes(5));
    $release = $temporary.'/releases/release-new';
    mkdir($release.'/bin', 0755, true);
    mkdir($release.'/resources/updates', 0755, true);
    mkdir($release.'/vendor/composer', 0755, true);
    copy($root.'/bin/vps-preflight.sh', $release.'/bin/vps-preflight.sh');
    file_put_contents($release.'/cms-project.json', json_encode(['deployment' => $profile]));
    file_put_contents($release.'/resources/updates/release-policy.json', json_encode(['min_php' => $minimum, 'extensions' => []]));
    file_put_contents($release.'/composer.lock', json_encode(['packages' => [], 'platform' => $missingRootExtension ? ['ext-dbcms-missing' => '*'] : []]));
    file_put_contents($release.'/vendor/composer/platform_check.php', '<?php');
    try {
        $process = new Process(['bash', $root.'/bin/deploy-vps-release.sh', $release], $root, [
            'VPS_DIR' => $temporary, 'RELEASE_NAME' => 'release-new', 'VPS_PHP_BINARY' => PHP_BINARY,
        ]);
        expect($process->run())->not->toBe(0);
        expect(is_dir($temporary.'/shared'))->toBeFalse();
        expect(is_dir($release.'/.deployment-state'))->toBeFalse();
    } finally {
        (new Process(['rm', '-rf', $temporary]))->mustRun();
    }
})->with([
    'wrong driver' => ['docker', '8.3.0'],
    'unsupported PHP' => ['rsync', '999.0.0'],
    'missing root extension' => ['rsync', '8.3.0', true],
]);

it('refuses native deployment phases owned by another release', function (string $phase): void {
    $root = realpath(__DIR__.'/../..');
    $temporary = sys_get_temp_dir().'/dbcms-native-lock-'.bin2hex(random_bytes(5));
    $release = $temporary.'/releases/release-new';
    mkdir($release.'/bin', 0755, true);
    mkdir($release.'/resources/updates', 0755, true);
    mkdir($release.'/vendor/composer', 0755, true);
    mkdir($temporary.'/shared/.deployment-lock', 0700, true);
    file_put_contents($temporary.'/shared/.deployment-lock/owner', 'release-other');
    copy($root.'/bin/vps-preflight.sh', $release.'/bin/vps-preflight.sh');
    file_put_contents($release.'/cms-project.json', '{"deployment":"rsync"}');
    file_put_contents($release.'/resources/updates/release-policy.json', '{"min_php":"8.3.0","extensions":[]}');
    file_put_contents($release.'/composer.lock', '{"packages":[]}');
    file_put_contents($release.'/vendor/composer/platform_check.php', '<?php');
    try {
        $process = new Process(['bash', $root.'/bin/deploy-vps-release.sh', $release, $phase], $root, [
            'VPS_DIR' => $temporary, 'RELEASE_NAME' => 'release-new', 'VPS_PHP_BINARY' => PHP_BINARY,
        ]);
        expect($process->run())->not->toBe(0);
        expect($process->getErrorOutput())->toContain('.deployment-lock');
        expect(file_get_contents($temporary.'/shared/.deployment-lock/owner'))->toBe('release-other');
        expect(is_dir($release.'/.deployment-state'))->toBeFalse();
    } finally {
        (new Process(['rm', '-rf', $temporary]))->mustRun();
    }
})->with(['deploy', 'prepare', 'finalize', 'abort']);

it('preserves first installation and fails closed after migrations start', function (bool $installed, string $failure, bool $successful, bool $derived = false, bool $alreadyDown = false): void {
    $root = realpath(__DIR__.'/../..');
    $temporary = sys_get_temp_dir().'/dbcms-vps-'.bin2hex(random_bytes(5));
    $application = $temporary.'/app';
    $old = $application.'/releases/release-old';
    $release = $application.'/releases/release-new';
    mkdir($old, 0755, true);
    mkdir($release.'/bin', 0755, true);
    mkdir($release.'/vendor', 0755, true);
    mkdir($release.'/public/build', 0755, true);
    mkdir($application.'/shared/storage', 0755, true);
    mkdir($temporary.'/mock', 0755);
    file_put_contents($old.'/artisan', 'fixture');
    file_put_contents($release.'/artisan', 'fixture');
    file_put_contents($release.'/cms-project.json', '{"schema":1,"deployment":"rsync"}');
    file_put_contents($release.'/vendor/autoload.php', '<?php');
    file_put_contents($release.'/public/build/manifest.json', '{}');
    copy($root.'/bin/vps-artisan.sh', $release.'/bin/vps-artisan.sh');
    copy($root.'/bin/runtime-safety.php', $release.'/bin/runtime-safety.php');
    copy($root.'/bin/vps-preflight.sh', $release.'/bin/vps-preflight.sh');
    file_put_contents($application.'/shared/.env', "APP_KEY=fixture-stable-key\nAPP_URL=https://fixture.invalid\n");
    if ($installed) {
        touch($application.'/shared/storage/installed');
        symlink($old, $application.'/current');
    }
    file_put_contents($temporary.'/mock/php', <<<'BASH'
#!/usr/bin/env bash
printf '%s\n' "$*" >> "$MOCK_LOG"
if [[ "$*" == *'runtime-safety.php maintenance'* ]]; then if [ "$MOCK_DOWN" = true ]; then printf active; else printf inactive; fi; exit 0; fi
if [[ "$*" == *'migrate --force'* ]] && [ "$MOCK_FAILURE" = interrupt ]; then kill -KILL "$PPID"; exit 0; fi
if [[ "$*" == *'migrate --force'* ]] && [ "$MOCK_FAILURE" = migrate ]; then exit 1; fi
exit 0
BASH);
    file_put_contents($temporary.'/mock/sudo', "#!/usr/bin/env bash\n[ \"\$MOCK_FAILURE\" != reload ]\n");
    file_put_contents($temporary.'/mock/curl', "#!/usr/bin/env bash\nif [ \"\$MOCK_FAILURE\" = health ]; then printf 500; else printf 200; fi\n");
    foreach (['php', 'sudo', 'curl'] as $binary) {
        chmod($temporary.'/mock/'.$binary, 0755);
    }
    try {
        $process = new Process(['bash', $root.'/bin/deploy-vps-release.sh', $release], $root, [
            'VPS_DIR' => $application, 'RELEASE_NAME' => 'release-new', 'VPS_PHP_BINARY' => $temporary.'/mock/php',
            'VPS_HEALTH_URL' => 'https://fixture.invalid', 'VPS_FPM_SERVICE' => 'fixture-fpm',
            'PATH' => $temporary.'/mock:'.getenv('PATH'), 'MOCK_LOG' => $temporary.'/docker.log', 'MOCK_FAILURE' => $failure, 'MOCK_DOWN' => $alreadyDown ? 'true' : 'false',
        ]);
        try {
            $succeeded = $process->run() === 0;
        } catch (ProcessSignaledException) {
            $succeeded = false;
        }
        expect($succeeded)->toBe($successful, $process->getErrorOutput());
        $calls = file_get_contents($temporary.'/docker.log');
        expect($calls)->not->toContain('migrate:rollback', 'migrate:fresh');
        expect(readlink($release.'/storage'))->toBe($application.'/shared/storage');
        expect(file_get_contents($application.'/shared/.env'))->toContain('APP_KEY=fixture-stable-key');
        if (! $installed) {
            expect($calls)->not->toContain('migrate --force', 'passport:keys', 'cms:install');
        } else {
            expect($calls)->toContain('migrate --force');
            expect($calls)->toContain('cms:project:migrate --force')->not->toContain('artisan migrate --force');
        }
        if ($installed && ! $successful) {
            expect(is_file($application.'/shared/deployment-recovery-required'))->toBeTrue();
            expect(file_get_contents($application.'/shared/.deployment-lock/owner'))->toBe('release-new'.PHP_EOL);
            expect($calls)->not->toContain('up --quiet');
            if ($alreadyDown) {
                expect($calls)->not->toContain('down --retry=15');
            } else {
                expect($calls)->toContain('down --retry=15');
            }
            expect(readlink($application.'/current'))->toBe(in_array($failure, ['migrate', 'interrupt'], true) ? $old : $release);
            expect($process->run())->not->toBe(0);
            expect(file_get_contents($temporary.'/docker.log'))->toBe($calls);
        } elseif ($successful) {
            expect(readlink($application.'/current'))->toBe($release);
            expect(is_file($application.'/shared/deployment-recovery-required'))->toBeFalse();
            expect(is_dir($application.'/shared/.deployment-lock'))->toBeFalse();
            if ($alreadyDown) {
                expect($calls)->not->toContain('artisan down', 'artisan up');
            }
        } else {
            expect(is_link($application.'/current'))->toBeFalse();
            expect(is_dir($application.'/shared/.deployment-lock'))->toBeFalse();
        }
    } finally {
        (new Process(['rm', '-rf', $temporary]))->mustRun();
    }
})->with([
    'initial web installation' => [false, '', true],
    'healthy upgrade' => [true, '', true],
    'operator maintenance preserved' => [true, '', true, false, true],
    'operator maintenance after failed migration' => [true, 'migrate', false, false, true],
    'uncertain migration' => [true, 'migrate', false],
    'interrupted migration' => [true, 'interrupt', false],
    'failed FPM after migration' => [true, 'reload', false],
    'unhealthy after migration' => [true, 'health', false],
    'failed first promotion' => [false, 'reload', false],
    'derived installed upgrade' => [true, '', true, true],
    'derived failed migration' => [true, 'migrate', false, true],
]);

it('bootstraps configuration once and preserves the original key', function (): void {
    $root = realpath(__DIR__.'/../..');
    $temporary = sys_get_temp_dir().'/dbcms-bootstrap-'.bin2hex(random_bytes(5));
    try {
        $bootstrap = new Process(['bash', $root.'/bin/vps-bootstrap.sh'], $root, ['VPS_DIR' => $temporary]);
        $bootstrap->setInput("APP_KEY=original-key\nAPP_URL=https://fixture.invalid\n");
        $bootstrap->mustRun();
        $bootstrap->setInput("APP_KEY=replacement-key\nAPP_URL=https://other.invalid\n");
        $bootstrap->mustRun();
        expect(file_get_contents($temporary.'/shared/.env'))->toContain('original-key')->not->toContain('replacement-key');
        expect(fileperms($temporary.'/shared/.env') & 0777)->toBe(0600);
    } finally {
        (new Process(['rm', '-rf', $temporary]))->mustRun();
    }
});

it('keeps fresh Docker installations unmigrated and stops failed migrated applications', function (bool $installed, string $failure, bool $successful, bool $derived = false, bool $alreadyDown = false): void {
    $root = realpath(__DIR__.'/../..');
    $temporary = sys_get_temp_dir().'/dbcms-docker-'.bin2hex(random_bytes(5));
    mkdir($temporary.'/shared', 0755, true);
    mkdir($temporary.'/mock', 0755);
    file_put_contents($temporary.'/shared/.env', "APP_KEY=fixture-stable-key\n");
    file_put_contents($temporary.'/docker-compose.prod.yml', 'services: {}');
    if ($failure === 'locked') {
        mkdir($temporary.'/shared/.deployment-lock', 0700);
        file_put_contents($temporary.'/shared/.deployment-lock/owner', 'other-image');
    }
    file_put_contents($temporary.'/mock/docker', <<<'BASH'
#!/usr/bin/env bash
printf '%s\n' "$*" >> "$MOCK_LOG"
case "$*" in
    *'runtime-safety.php maintenance'*) if [ "$MOCK_DOWN" = true ]; then printf active; else printf inactive; fi ;;
    *'.Config.Image'*) printf 'fixture@sha256:%064d' 0 ;;
    *'.State.Health.Status'*) if [ "$MOCK_FAILURE" = health ]; then printf unhealthy; else printf healthy; fi ;;
    *' ps -q app') printf fixture-container ;;
    *'test -f storage/installed'*) if [ "$MOCK_FAILURE" = probe ]; then exit 125; fi; [ "$MOCK_INSTALLED" = true ] ;;
    *'migrate --force'*) if [ "$MOCK_FAILURE" = interrupt ]; then kill -KILL "$PPID"; exit 0; fi; [ "$MOCK_FAILURE" != migrate ] ;;
    *'test -f cms-project.json &&'*) exit 1 ;;
    *'test -f cms-project.json'*) [ "$MOCK_DERIVED" = true ] ;;
    *) exit 0 ;;
esac
BASH);
    chmod($temporary.'/mock/docker', 0755);
    try {
        $process = new Process(['bash', $root.'/bin/deploy-container.sh'], $root, [
            'VPS_DIR' => $temporary, 'VPS_RELEASE_DIR' => $temporary, 'VPS_COMPOSE_PROJECT' => 'fixture',
            'CMS_IMAGE' => 'ghcr.io/fixture/project@sha256:'.str_repeat('a', 64),
            'PATH' => $temporary.'/mock:'.getenv('PATH'), 'MOCK_LOG' => $temporary.'/docker.log',
            'MOCK_INSTALLED' => $installed ? 'true' : 'false', 'MOCK_FAILURE' => $failure,
            'MOCK_DERIVED' => $derived ? 'true' : 'false', 'MOCK_DOWN' => $alreadyDown ? 'true' : 'false',
        ]);
        try {
            $succeeded = $process->run() === 0;
        } catch (ProcessSignaledException) {
            $succeeded = false;
        }
        expect($succeeded)->toBe($successful, $process->getErrorOutput());
        $calls = file_get_contents($temporary.'/docker.log');
        if ($installed) {
            expect($calls)->toContain('migrate --force');
            if ($derived) {
                expect($calls)->toContain('cms:project:migrate --force')->not->toContain('artisan migrate --force');
            }
        } else {
            expect($calls)->not->toContain('migrate --force', 'cms:install');
        }
        if ($installed && ! $successful) {
            expect(is_file($temporary.'/shared/deployment-recovery-required'))->toBeTrue();
            expect(file_get_contents($temporary.'/shared/.deployment-lock/owner'))->toContain('ghcr.io/fixture/project@sha256:');
            expect($calls)->not->toContain('up --quiet');
            if ($alreadyDown) {
                expect($calls)->not->toContain('down --retry=15');
            } else {
                expect($calls)->toContain('down --retry=15');
            }
            if ($failure !== 'interrupt') {
                expect($calls)->toContain('stop app');
            }
            expect($process->run())->not->toBe(0);
            expect(file_get_contents($temporary.'/docker.log'))->toBe($calls);
        } elseif ($successful) {
            expect(is_file($temporary.'/shared/current-image'))->toBeTrue();
            expect(is_dir($temporary.'/shared/.deployment-lock'))->toBeFalse();
            if ($alreadyDown) {
                expect($calls)->not->toContain('artisan down', 'artisan up');
            }
        }
        if (in_array($failure, ['probe', 'locked'], true)) {
            expect($calls)->not->toContain('migrate --force', 'up -d --no-build', 'artisan up');
            expect(is_file($temporary.'/shared/current-image'))->toBeFalse();
        }
        if ($failure === 'locked') {
            expect(file_get_contents($temporary.'/shared/.deployment-lock/owner'))->toBe('other-image');
        }
    } finally {
        (new Process(['rm', '-rf', $temporary]))->mustRun();
    }
})->with([
    'first Docker deployment' => [false, '', true],
    'Docker installation probe error' => [false, 'probe', false],
    'concurrent Docker deployment' => [false, 'locked', false],
    'healthy Docker upgrade' => [true, '', true],
    'Docker operator maintenance preserved' => [true, '', true, false, true],
    'Docker operator maintenance after failed migration' => [true, 'migrate', false, false, true],
    'failed Docker migration' => [true, 'migrate', false],
    'interrupted Docker migration' => [true, 'interrupt', false],
    'failed Docker health' => [true, 'health', false],
    'derived Docker upgrade' => [true, '', true, true],
    'derived Docker failure' => [true, 'migrate', false, true],
]);
