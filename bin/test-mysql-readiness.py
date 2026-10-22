#!/usr/bin/env python3
"""Prove Compose readiness on fresh disposable MySQL; requires host Docker.

No host PHP, application volumes, source mounts or existing services are used.
Unlike the in-container release lane, this check controls independent containers.
"""
import json
import os
from pathlib import Path
import subprocess
import tempfile
import time
import uuid

ROOT = Path(__file__).resolve().parents[1]
CONFIGURATIONS = ('docker/ci/compose.yml', 'docker-compose.prod.yml', 'docker-compose.yml')


def docker(*arguments, check=True, environment=None):
    return subprocess.run(['docker', *arguments], check=check, text=True,
                          capture_output=True, env=environment)


def wait_for(predicate, description):
    deadline = time.monotonic() + 60
    while time.monotonic() < deadline:
        if predicate():
            return
        time.sleep(0.2)
    raise RuntimeError(f'Timed out waiting for {description}')


def verify(configuration, temporary):
    env_file = temporary / 'compose.env'
    env_file.write_text('APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=\n'
                        'DB_PASSWORD=readiness-fixture-only\nDB_ROOT_PASSWORD=readiness-root-only\n'
                        'DB_DATABASE=readiness\nDB_USERNAME=readiness\n')
    environment = {**os.environ, 'APP_KEY': 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
                   'DB_PASSWORD': 'readiness-fixture-only', 'DB_ROOT_PASSWORD': 'readiness-root-only',
                   'DB_DATABASE': 'readiness', 'DB_USERNAME': 'readiness'}
    model = json.loads(docker('compose', '--env-file', str(env_file), '-f', str(ROOT / configuration),
                             'config', '--format', 'json', environment=environment).stdout)
    service = model['services']['mysql']
    name = 'dbcms-readiness-' + uuid.uuid4().hex[:12]
    # Keep the entrypoint's socket-only temporary server alive at an observable
    # init boundary. Release it only after asserting that TCP readiness rejects it.
    delay = temporary / '99-readiness-delay.sh'
    delay.write_text('#!/bin/sh\ntouch /tmp/dbcms-init-delayed\n'
                     'while [ ! -f /tmp/dbcms-release-init ]; do sleep 0.1; done\n')
    delay.chmod(0o755)
    try:
        fixture = temporary / 'compose.json'
        fixture.write_text(json.dumps({'services': {'mysql': {
            'image': service['image'], 'network_mode': 'none', 'tmpfs': ['/var/lib/mysql'],
            'environment': {'MYSQL_ROOT_PASSWORD': 'readiness-root-only', 'MYSQL_DATABASE': 'readiness',
                            'MYSQL_USER': 'readiness', 'MYSQL_PASSWORD': 'readiness-fixture-only'},
            'volumes': [f'{delay}:/docker-entrypoint-initdb.d/99-readiness-delay.sh:ro'],
            'healthcheck': {**service['healthcheck'], 'interval': '1s', 'timeout': '3s', 'retries': 60},
        }}}))
        compose = ('compose', '-p', name, '-f', str(fixture))
        docker(*compose, 'up', '-d')
        container_id = docker(*compose, 'ps', '-q', 'mysql').stdout.strip()
        health = json.loads(docker('inspect', '--format', '{{json .Config.Healthcheck.Test}}', container_id).stdout)
        if health[0] != 'CMD-SHELL':
            raise RuntimeError('Expected an executable application readiness probe')
        health_command = health[1]
        # Use the actual engine command after Compose performs its escaping.
        name = container_id
        wait_for(lambda: docker('exec', name, 'test', '-f', '/tmp/dbcms-init-delayed', check=False).returncode == 0,
                 f'{configuration} delayed initialization')
        # The previous probe falsely passed on this actual temporary socket server.
        previous = docker('exec', name, 'mysqladmin', 'ping', '-h', 'localhost', check=False)
        if previous.returncode != 0:
            raise RuntimeError('Fixture did not reproduce the previous false readiness')
        def probe(*overrides):
            return docker('exec', *overrides, name, 'sh', '-c', health_command, check=False)
        if probe().returncode == 0:
            raise RuntimeError('Readiness accepted a socket-only initialization server')
        status = json.loads(docker('inspect', '--format', '{{json .State.Health}}', name).stdout)
        if status['Status'] == 'healthy':
            raise RuntimeError('Container became healthy before TCP startup')
        docker('exec', name, 'touch', '/tmp/dbcms-release-init')
        wait_for(lambda: probe().returncode == 0, f'{configuration} authenticated application database')
        wait_for(lambda: docker('inspect', '--format', '{{.State.Health.Status}}', name).stdout.strip() == 'healthy',
                 f'{configuration} Docker readiness')
        if probe('-e', 'MYSQL_PASSWORD=incorrect-fixture-password').returncode == 0:
            raise RuntimeError('Readiness accepted invalid application credentials')
        if probe('-e', 'MYSQL_DATABASE=absent_fixture_database').returncode == 0:
            raise RuntimeError('Readiness accepted a missing application database')
        print(f'PASS {configuration}: temporary socket rejected; TCP/app auth/database required', flush=True)
    finally:
        if 'compose' in locals():
            docker(*compose, 'down', '--volumes', '--remove-orphans', check=False)


def main():
    for configuration in CONFIGURATIONS:
        with tempfile.TemporaryDirectory(prefix='dbcms-readiness-') as path:
            verify(configuration, Path(path))


if __name__ == '__main__':
    main()
