<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

it('publishes a local Docker project only after validation and deploys the registry digest', function (string $failure, bool $success): void {
    $files = new Filesystem;
    $sandbox = sys_get_temp_dir().'/dbcms-local-docker-'.bin2hex(random_bytes(8));
    $files->ensureDirectoryExists($sandbox.'/bin');
    $files->ensureDirectoryExists($sandbox.'/mock');
    copy(dirname(__DIR__, 2).'/bin/deploy-docker', $sandbox.'/bin/deploy-docker');
    file_put_contents($sandbox.'/bin/deploy-local-common.sh', <<<'BASH'
    deployment_validate_local() {
        DEPLOY_TEMP="$FIXTURE_ROOT/work"
        mkdir -p "$DEPLOY_TEMP/context/bin"
        touch "$DEPLOY_TEMP/context/docker-compose.prod.yml" "$DEPLOY_TEMP/context/bin/deploy-container.sh" "$DEPLOY_TEMP/context/bin/vps-bootstrap.sh"
        DEPLOY_SSH=(ssh)
        DEPLOY_RSYNC_SSH=ssh
        DEPLOY_DESTINATION=deploy@example.invalid
        RELEASE_NAME=release-fixture
    }
    deployment_build_context() { :; }
    deployment_check_context() {
        printf 'quality\n' >> "$MOCK_LOG"
        [ "$MOCK_FAILURE" != quality ]
    }
    deployment_remote_bootstrap() { :; }
    BASH);
    file_put_contents($sandbox.'/mock/docker', <<<'BASH'
    #!/usr/bin/env bash
    printf 'docker %s\n' "$*" >> "$MOCK_LOG"
    if [ "$1" = push ] && [ "$MOCK_FAILURE" = push ]; then exit 1; fi
    if [ "$1" = run ] && [ "$MOCK_FAILURE" = profile ]; then exit 1; fi
    if [ "$1 $2" = 'image inspect' ]; then
        if [ "$MOCK_FAILURE" = digest ]; then printf 'other.invalid/app@sha256:bad\n';
        else printf '%s@sha256:%s\n' "$CMS_IMAGE_REPOSITORY" "$MOCK_DIGEST"; fi
    fi
    BASH);
    foreach (['ssh', 'rsync'] as $binary) {
        file_put_contents($sandbox.'/mock/'.$binary, '#!/usr/bin/env bash'."\n".'printf "'.$binary.' %s\n" "$*" >> "$MOCK_LOG"'."\n");
    }
    foreach (['docker', 'ssh', 'rsync'] as $binary) {
        chmod($sandbox.'/mock/'.$binary, 0755);
    }
    try {
        $digest = str_repeat('a', 64);
        $process = new Process(['bash', $sandbox.'/bin/deploy-docker'], $sandbox, [
            'FIXTURE_ROOT' => $sandbox, 'MOCK_LOG' => $sandbox.'/calls', 'MOCK_FAILURE' => $failure,
            'MOCK_DIGEST' => $digest, 'CMS_IMAGE_REPOSITORY' => 'registry.invalid:5050/team/web',
            'VPS_DIR' => '/srv/web', 'PATH' => $sandbox.'/mock:'.getenv('PATH'),
        ]);
        expect($process->run() === 0)->toBe($success, $process->getErrorOutput());
        $calls = file_get_contents($sandbox.'/calls');
        expect(file_exists($sandbox.'/.git'))->toBeFalse();
        expect($calls)->toStartWith("quality\n")->not->toContain('docker save', 'docker load', 'github');
        if ($success) {
            expect($calls)->toContain('CMS_IMAGE=registry.invalid:5050/team/web@sha256:'.$digest, 'deploy-container.sh');
        } else {
            expect($calls)->not->toContain('ssh ', 'rsync ');
        }
        if (in_array($failure, ['quality', 'profile'], true)) {
            expect($calls)->not->toContain('docker push');
        }
    } finally {
        $files->deleteDirectory($sandbox);
    }
})->with(['success' => ['', true], 'quality failed' => ['quality', false], 'wrong profile' => ['profile', false], 'push failed' => ['push', false], 'invalid registry digest' => ['digest', false]]);
