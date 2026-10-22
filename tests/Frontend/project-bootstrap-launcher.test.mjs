import test from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, mkdirSync, readFileSync, writeFileSync, copyFileSync, chmodSync, rmSync, symlinkSync, existsSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { spawnSync } from 'node:child_process';

function fixture(t) {
    const root = mkdtempSync(join(tmpdir(), 'dbcms-bootstrap-'));
    t.after(() => rmSync(root, { recursive: true, force: true }));
    for (const dir of ['bin', 'vendor/bin', 'tools']) mkdirSync(join(root, dir), { recursive: true });
    for (const script of ['project-init', 'project-local-start']) copyFileSync(new URL(`../../bin/${script}`, import.meta.url), join(root, 'bin', script));
    writeFileSync(join(root, '.env'), 'APP_KEY=preserved\nDANGEROUS=$(touch should-never-exist)\n');
    writeFileSync(join(root, 'shell-env'), `test() { if [[ "$*" == '-f /.dockerenv' ]]; then return 1; fi; builtin test "$@"; }\n`);
    const tool = (name, source) => {
        writeFileSync(join(root, name), `#!/usr/bin/env bash\nset -e\n${source}\n`);
        chmodSync(join(root, name), 0o755);
    };
    tool('tools/docker', `printf 'docker %s\\n' "$*" >> "$TRACE"
if [[ "$1" == run && "$FAIL" == cancelled ]]; then exit 130; fi
if [[ "$1" == run && "$FAIL" == stdin ]]; then cat > "$TRACE.stdin"; fi
[[ "$1" != run || "$FAIL" != bootstrap ]]`);
    tool('tools/sleep', ':');
    tool('vendor/bin/sail', `printf 'sail %s\\n' "$*" >> "$TRACE"
if [[ "$*" == 'npm run build' && "$FAIL" == build ]]; then exit 7; fi
if [[ "$1" == port ]]; then
 case "$2" in laravel.test) echo '0.0.0.0:23451';; mailpit) echo '0.0.0.0:23452';; adminer) echo '0.0.0.0:23453';; esac
fi`);
    const run = (args = [], fail = '', input = undefined) => spawnSync('bash', ['bin/project-init', ...args], {
        cwd: root, encoding: 'utf8', input, env: { ...process.env, PATH: `${root}/tools:${process.env.PATH}`, BASH_ENV: `${root}/shell-env`, TRACE: `${root}/trace`, FAIL: fail },
    });
    return { root, run, trace: () => readFileSync(join(root, 'trace'), 'utf8') };
}

test('guided launcher starts official Sail after bootstrap and reports actual published ports', t => {
    const f = fixture(t);
    const result = f.run();
    assert.equal(result.status, 0, result.stderr);
    assert.match(f.trace(), /docker run .*--network host/);
    assert.match(f.trace(), /sail up -d --build[\s\S]*sail exec -T laravel.test curl[\s\S]*sail npm ci[\s\S]*sail npm run build/);
    assert.match(result.stdout, /Installer: http:\/\/localhost:23451\/install/);
    assert.equal(readFileSync(join(f.root, '.env'), 'utf8'), 'APP_KEY=preserved\nDANGEROUS=$(touch should-never-exist)\n');
});

test('failed bootstrap cannot start Sail and prepare-only stays source-only', t => {
    const f = fixture(t);
    assert.notEqual(f.run([], 'bootstrap').status, 0);
    assert.doesNotMatch(f.trace(), /sail /);
    assert.equal(f.run(['--prepare-only', '--no-interaction']).status, 0);
    assert.doesNotMatch(f.trace(), /sail /);
});

test('build failure is actionable and rerunning succeeds without changing environment', t => {
    const f = fixture(t);
    const first = f.run([], 'build');
    assert.notEqual(first.status, 0);
    assert.match(first.stderr, /rerun .\/bin\/project-init/);
    assert.equal(f.run().status, 0);
    assert.equal(readFileSync(join(f.root, '.env'), 'utf8'), 'APP_KEY=preserved\nDANGEROUS=$(touch should-never-exist)\n');
});


test('cancelled source confirmation propagates 130 and cannot start local services', t => {
    const f = fixture(t);
    assert.equal(f.run([], 'cancelled').status, 130);
    assert.doesNotMatch(f.trace(), /sail /);
    assert.equal(readFileSync(join(f.root, '.env'), 'utf8'), 'APP_KEY=preserved\nDANGEROUS=$(touch should-never-exist)\n');
});

test('piped interactive answers are forwarded without allocating a terminal', t => {
    const f = fixture(t);
    const answers = 'my-project\ndbcms\nno\n';
    const result = f.run([], 'stdin', answers);
    assert.equal(result.status, 0, result.stderr);
    assert.match(f.trace(), /docker run --rm -i --network host/);
    assert.doesNotMatch(f.trace(), /docker run .* -it /);
    assert.equal(readFileSync(join(f.root, 'trace.stdin'), 'utf8'), answers);
});

function containerFixture(t) {
    const root = mkdtempSync(join(tmpdir(), 'dbcms-bootstrap-container-'));
    t.after(() => rmSync(root, { recursive: true, force: true }));
    mkdirSync(join(root, 'bin'));
    mkdirSync(join(root, 'tools'));
    mkdirSync(join(root, 'bootstrap/cache'), { recursive: true });
    writeFileSync(join(root, 'bootstrap/cache/config.php'), 'preserved installation cache');
    copyFileSync(new URL('../../bin/project-init', import.meta.url), join(root, 'bin/project-init'));
    writeFileSync(join(root, 'composer.lock'), 'initial-lock');
    writeFileSync(join(root, 'package-lock.json'), '{}');
    writeFileSync(join(root, 'shell-env'), `test() { if [[ "$*" == '-f /.dockerenv' ]]; then return 0; fi; builtin test "$@"; }\n`);
    const tool = (name, source) => {
        const path = join(root, 'tools', name);
        writeFileSync(path, `#!/usr/bin/env bash\nset -e\n${source}\n`);
        chmodSync(path, 0o755);
    };
    tool('php', `[[ ! -e "$APP_CONFIG_CACHE" ]] || exit 99
printf '%s' "$APP_CONFIG_CACHE" > "$TRACE.config-path"
printf 'php %s\\n' "$*" >> "$TRACE"; [[ "$FAIL" != cancelled ]] || exit 130; printf final-lock > composer.lock`);
    tool('composer', `printf 'bootstrap-runtime %s %s %s %s\\n' "$DB_CONNECTION" "$DB_DATABASE" "$CACHE_STORE" "$QUEUE_CONNECTION" >> "$TRACE"
printf 'composer %s %s\\n' "$*" "$(cat composer.lock)" >> "$TRACE"
if [[ "$1" == install && "$(cat composer.lock)" == final-lock && "$FAIL" == dependency ]]; then exit 23; fi`);
    tool('docker', 'exit 98');
    const run = (fail = '') => spawnSync('bash', ['bin/project-init', 'example', '--prepare-only', '--no-interaction'], {
        cwd: root, encoding: 'utf8', env: { ...process.env, PATH: `${root}/tools:${process.env.PATH}`, BASH_ENV: `${root}/shell-env`, TRACE: `${root}/trace`, FAIL: fail },
    });
    return { root, run, trace: () => readFileSync(join(root, 'trace'), 'utf8') };
}

test('bootstrap converges Composer to final confirmed source lock before returning', t => {
    const f = containerFixture(t);
    const result = f.run();
    assert.equal(result.status, 0, result.stderr);
    assert.match(f.trace(), /bootstrap-runtime sqlite :memory: array sync/);
    assert.equal(readFileSync(join(f.root, 'bootstrap/cache/config.php'), 'utf8'), 'preserved installation cache');
    const temporaryCache = readFileSync(join(f.root, 'trace.config-path'), 'utf8');
    assert.notEqual(temporaryCache, join(f.root, 'bootstrap/cache/config.php'));
    assert.equal(existsSync(temporaryCache.slice(0, temporaryCache.lastIndexOf('/'))), false);
    assert.match(f.trace(), /composer install .*initial-lock[\s\S]*php artisan cms:project:init[\s\S]*composer install .*final-lock[\s\S]*composer dump-autoload .*final-lock/);
});

test('final dependency failure reports resume and cancellation skips final installation', t => {
    const f = containerFixture(t);
    const failed = f.run('dependency');
    assert.equal(failed.status, 23);
    assert.match(failed.stderr, /Rerun .\/bin\/project-init to resume/);
    assert.equal(f.run().status, 0);
    const cancelled = containerFixture(t);
    assert.equal(cancelled.run('cancelled').status, 130);
    assert.doesNotMatch(cancelled.trace(), /composer install .*final-lock/);
});


test('launcher rejects environment symlinks before invoking Docker or reading configuration', t => {
    const f = fixture(t);
    const target = join(f.root, 'private-env');
    writeFileSync(target, 'PRIVATE=preserve');
    rmSync(join(f.root, '.env'));
    symlinkSync(target, join(f.root, '.env'));
    const result = f.run();
    assert.notEqual(result.status, 0);
    assert.match(result.stderr, /symbolic link/);
    assert.equal(existsSync(join(f.root, 'trace')), false);
    assert.equal(readFileSync(target, 'utf8'), 'PRIVATE=preserve');
});
