#!/usr/bin/env python3
"""Consume locally signed deliveries; GitHub never rebuilds published bytes."""
import argparse
from datetime import datetime, timedelta, timezone
import json
import os
from pathlib import Path
import re
import subprocess
import sys


class DeliveryError(ValueError):
    pass


def require(condition, message):
    if not condition:
        raise DeliveryError(message)


def command(*args, **kwargs):
    return subprocess.run(args, check=True, text=True, capture_output=True, **kwargs).stdout.strip()


def api(repository, path, payload=None):
    args = ['gh', 'api', f'repos/{repository}/{path}']
    if payload is not None:
        args += ['--method', 'PATCH', '--input', '-']
    return json.loads(command(*args, input=json.dumps(payload) if payload is not None else None))


def verify_ci(response, repository, sha):
    require(isinstance(response, dict) and isinstance(response.get('workflow_runs'), list), 'Missing CI response')
    for run in response['workflow_runs']:
        if (isinstance(run, dict) and run.get('head_sha') == sha
                and run.get('path') == '.github/workflows/ci.yml'
                and run.get('head_branch') == 'main' and run.get('event') == 'push'
                and run.get('status') == 'completed' and run.get('conclusion') == 'success'
                and run.get('repository', {}).get('full_name') == repository
                and run.get('head_repository', {}).get('full_name') == repository):
            return run['id']
    raise DeliveryError('Exact successful main push CI is required')


def verify_stage(release, sha, version=None):
    require(isinstance(release, dict) and release.get('prerelease') is False
            and ((release.get('tag_name') == f'delivery-{sha}' and release.get('draft') is True)
                 or (version and release.get('tag_name') == f'v{version}' and release.get('draft') is False)),
            'Delivery must be an exact staging draft or an explicitly accepted stable release')
    assets = release.get('assets')
    require(isinstance(assets, list) and assets, 'Missing staged assets')
    names = [a.get('name', '') for a in assets]
    require(len(names) == len(set(names)), 'Duplicate staged names')
    require(all(re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9._-]*', name) for name in names), 'Unsafe staged name')
    require({'delivery.json', 'delivery.sig', 'runtime.tar.gz'} <= set(names), 'Incomplete staged delivery')
    require(all(a.get('state') == 'uploaded' and isinstance(a.get('size'), int)
                and a['size'] > 0 for a in assets), 'Incomplete staged upload')
    return release['id']


def find_stage(repository, sha):
    """Drafts are only supported by authenticated release listings, not tag lookup."""
    matches = []
    page = 1
    while True:
        releases = api(repository, f'releases?per_page=100&page={page}')
        require(isinstance(releases, list), 'Invalid authenticated release listing')
        matches.extend(release for release in releases
                       if isinstance(release, dict) and release.get('tag_name') == f'delivery-{sha}')
        require(len(matches) <= 1, 'Ambiguous staging release')
        if len(releases) < 100:
            return matches[0] if matches else None
        page += 1


def verify_source(repository, sha, root):
    require(re.fullmatch(r'[0-9a-f]{40}', sha) is not None, 'Expected exact 40-character SHA')
    comparison = api(repository, f'compare/main...{sha}')
    require(comparison.get('status') in ('behind', 'identical')
            and comparison.get('merge_base_commit', {}).get('sha') == sha,
            'Candidate must be committed on main')
    response = api(repository, f'actions/workflows/ci.yml/runs?branch=main&event=push&status=success&head_sha={sha}&per_page=100')
    verify_ci(response, repository, sha)
    version = command('git', '-C', str(root), 'show', f'{sha}:VERSION').strip()
    require(re.fullmatch(r'(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)', version) is not None, 'Invalid source version')
    return version


def verify_bundle(root, directory, sha, kind, version):
    command(sys.executable, str(root / 'bin/delivery.py'), 'verify', '--directory', str(directory),
            '--sha', sha, '--kind', kind, '--version', version)


def stage(args, repository, root):
    version = verify_source(repository, args.sha, root)
    require(not args.version or args.version == version, 'Requested version differs from source')
    release = find_stage(repository, args.sha)
    if release is None:
        require(args.allow_published, 'Missing signed staging draft')
        release = api(repository, f'releases/tags/v{version}')
    verify_stage(release, args.sha, version if args.allow_published else None)
    directory = args.directory.resolve()
    require(not directory.exists(), 'Delivery download directory must not exist')
    directory.mkdir(parents=True)
    for asset in release['assets']:
        with (directory / asset['name']).open('wb') as output:
            subprocess.run(['gh', 'api', '--header', 'Accept: application/octet-stream',
                            f"repos/{repository}/releases/assets/{asset['id']}"], check=True, stdout=output)
    verify_bundle(root, directory, args.sha, args.kind, version)
    directory.with_suffix('.stage.json').write_text(json.dumps(release))
    if args.output:
        with open(args.output, 'a', encoding='utf-8') as output:
            output.write(f'target_sha={args.sha}\nversion={version}\n')
    print(f'Verified {args.kind} delivery {version} at {args.sha}')


def publish(args, repository, root):
    version = verify_source(repository, args.sha, root)
    verify_bundle(root, args.directory, args.sha, 'release', version)
    tag = f'v{version}'
    require(command('git', '-C', str(root), 'cat-file', '-t', f'refs/tags/{tag}') == 'tag', 'Stable release requires an annotated tag')
    require(command('git', '-C', str(root), 'rev-parse', f'refs/tags/{tag}^{{commit}}') == args.sha, 'Stable tag points to another commit')
    # GET errors other than an authenticated 404 are fatal, including rate limits.
    result = subprocess.run(['gh', 'api', '--include', f'repos/{repository}/releases/tags/{tag}'], capture_output=True, text=True)
    require(result.returncode != 0 and re.search(r'^HTTP/\S+ 404\b', result.stdout, re.MULTILINE) is not None,
            'Stable releases are immutable; existing release or unavailable API')
    before = json.loads(args.directory.resolve().with_suffix('.stage.json').read_text())
    current = find_stage(repository, args.sha)
    release_id = verify_stage(current, args.sha)
    projection = lambda release: [(a['id'], a['name'], a['size'], a.get('updated_at')) for a in release['assets']]
    require(current['id'] == before['id'] and projection(current) == projection(before), 'Staging assets changed after verification')
    body = (f'## Descarga este archivo\n\nPara instalar desde Plesk o cPanel, descarga **dbcms-installer-v{version}.zip**, '
            'extráelo y abre el dominio.\n\nEl TAR.GZ está dirigido a administradores. Los enlaces automáticos '
            '**Source code** contienen fuentes para desarrolladores, sin el runtime del instalador.\n\n'
            f'Preparación y validación completas realizadas localmente sobre `{args.sha}`. '
            'Las imágenes Docker se publican en un workflow independiente.')
    published = api(repository, f'releases/{release_id}', {'tag_name': tag, 'target_commitish': args.sha,
                    'name': f'dbCMS {version}', 'body': body, 'draft': False, 'prerelease': False, 'make_latest': 'true'})
    require(published.get('tag_name') == tag and published.get('draft') is False, 'Release publication was not confirmed')
    print(published['html_url'])


def prune(repository):
    threshold = datetime.now(timezone.utc) - timedelta(days=7)
    # First page is bounded; old drafts beyond it are pruned by subsequent deliveries.
    for release in api(repository, 'releases?per_page=100'):
        if (release.get('draft') is True and re.fullmatch(r'delivery-[0-9a-f]{40}', release.get('tag_name', ''))
                and datetime.fromisoformat(release['created_at'].replace('Z', '+00:00')) < threshold):
            command('gh', 'api', '--method', 'DELETE', f"repos/{repository}/releases/{release['id']}")


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('action', choices=['stage', 'publish', 'prune'])
    parser.add_argument('--root', type=Path, default=Path('.'))
    parser.add_argument('--sha')
    parser.add_argument('--kind', choices=['deploy', 'release'], default='release')
    parser.add_argument('--version')
    parser.add_argument('--directory', type=Path)
    parser.add_argument('--output')
    parser.add_argument('--allow-published', action='store_true', help='Also accept an already published stable release (Docker only)')
    args = parser.parse_args()
    repository = os.environ.get('GITHUB_REPOSITORY', '')
    require(re.fullmatch(r'[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+', repository) is not None, 'Invalid repository')
    root = args.root.resolve()
    if args.action == 'prune':
        prune(repository)
    else:
        require(args.sha is not None and args.directory is not None, 'SHA and directory are required')
        (stage if args.action == 'stage' else publish)(args, repository, root)


if __name__ == '__main__':
    try:
        main()
    except (DeliveryError, subprocess.CalledProcessError, KeyError, OSError, json.JSONDecodeError) as error:
        print(f'Delivery refused: {error}', file=sys.stderr)
        sys.exit(1)
