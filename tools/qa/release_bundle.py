#!/usr/bin/env python3
"""Build/verify a deterministic source+frontend candidate; never package credentials, data or vendors."""
import argparse
import gzip
import hashlib
import io
import json
import os
from pathlib import Path, PurePosixPath
import re
import subprocess
import tarfile

ROOT = Path(__file__).resolve().parents[2]
SCAFFOLD = ['bootstrap/cache', 'storage/app/private', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs']
ROOT_FILES = {'.env.example', 'artisan', 'composer.json', 'composer.lock', 'package.json', 'package-lock.json', 'vite.config.js', 'RELEASE_HANDOFF.md'}
MANIFEST = '.release-manifest.json'


def allowed(name):
    p = PurePosixPath(name)
    if p.is_absolute() or '\\' in name or any(part in ('..', '.') for part in p.parts):
        return False
    if p.name.startswith('.env') and name != '.env.example':
        return False
    if p.name in ('auth.json', 'hot') or p.suffix.lower() in ('.pem', '.key', '.ex5', '.log'):
        return False
    if name.startswith(('bootstrap/cache/', 'public/storage/', 'storage/', 'vendor/', 'node_modules/', 'tests/')):
        return False
    return name in ROOT_FILES or name.startswith(('app/', 'config/', 'resources/', 'routes/', 'database/migrations/')) or name in ('bootstrap/app.php', 'bootstrap/providers.php', 'database/seeders/DatabaseSeeder.php', 'public/index.php', 'public/favicon.ico') or name.startswith('public/build/')


def canonical(value):
    return json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(',', ':')).encode()


def check_assets(root):
    directory = root / 'public/build'
    manifest = json.loads((directory / 'manifest.json').read_text())
    if not manifest or not any(item.get('isEntry') for item in manifest.values()):
        raise ValueError('Built frontend manifest has no entries.')
    for entry in manifest.values():
        for value in [entry.get('file'), *entry.get('css', []), *entry.get('assets', [])]:
            if not value:
                continue
            path = PurePosixPath(value)
            if path.is_absolute() or '..' in path.parts or '\\' in value:
                raise ValueError('Invalid frontend asset path.')
            candidate = directory / path
            if candidate.is_symlink() or not candidate.is_file() or not candidate.resolve().is_relative_to(directory.resolve()):
                raise ValueError('Missing or unsafe built frontend asset.')


def build(root, output):
    root = root.resolve()
    output = output.resolve()
    if output == root or (output.is_relative_to(root) and allowed(output.relative_to(root).as_posix() + '/artifact')):
        raise ValueError('Output must be outside runtime source directories.')
    check_assets(root)
    files = {}
    for path in root.rglob('*'):
        # Skip data/dependencies early; never follow symlinks into excluded directories.
        name = path.relative_to(root).as_posix()
        if not allowed(name):
            continue
        if path.is_symlink():
            raise ValueError('Symlink in runtime source: ' + name)
        if path.is_file():
            data = path.read_bytes()
            if re.search(rb'-----BEGIN (?:RSA |EC |OPENSSH |ENCRYPTED )?PRIVATE KEY-----', data):
                raise ValueError('Private key material in runtime source.')
            if name == '.env.example':
                for key in ['APP_KEY', 'DB_PASSWORD', 'DB_ROOT_PASSWORD', 'TG_HEALTH_TOKEN', 'TG_LICENSE_ED25519_PRIVATE_KEY_PATH']:
                    if re.search(r'^' + key + r'[ \t]*=[ \t]*[^\s#]+', data.decode(), re.M):
                        raise ValueError('Secret example value must be empty: ' + key)
            files[name] = data
    for required in ['artisan', 'composer.json', 'composer.lock', '.env.example', 'bootstrap/app.php', 'public/index.php', 'public/build/manifest.json']:
        if required not in files:
            raise ValueError('Missing runtime file: ' + required)
    def git(*args):
        return subprocess.check_output(['git', '-C', str(root), *args], text=True).strip()
    revision = git('rev-parse', 'HEAD')
    timestamp = int(git('show', '-s', '--format=%ct', 'HEAD'))
    records = {name: {'sha256': hashlib.sha256(data).hexdigest(), 'bytes': len(data)} for name, data in sorted(files.items())}
    manifest = {'schema': 1, 'candidate_only': True, 'source_commit': revision, 'worktree_dirty': bool(git('status', '--porcelain', '--untracked-files=normal')), 'source_tree_sha256': hashlib.sha256(canonical(records)).hexdigest(), 'dependencies_included': False, 'files': records}
    files[MANIFEST] = canonical(manifest) + b'\n'
    output.mkdir(parents=True, exist_ok=True)
    archive = output / 'trader-garuda-candidate.tar.gz'
    with archive.open('wb') as raw:
        with gzip.GzipFile(fileobj=raw, mode='wb', filename='', mtime=0) as compressed:
            with tarfile.open(fileobj=compressed, mode='w') as tar:
                for directory in SCAFFOLD:
                    item = tarfile.TarInfo(directory + '/'); item.type = tarfile.DIRTYPE; item.mode = 0o755; item.mtime = timestamp; tar.addfile(item)
                for name, data in sorted(files.items()):
                    item = tarfile.TarInfo(name); item.mode = 0o755 if name == 'artisan' else 0o644; item.mtime = timestamp; item.size = len(data); tar.addfile(item, io.BytesIO(data))
    digest = hashlib.sha256(archive.read_bytes()).hexdigest()
    (output / 'manifest.json').write_text(json.dumps(manifest, indent=2, ensure_ascii=False, sort_keys=True) + '\n')
    (output / (archive.name + '.sha256')).write_text(digest + '  ' + archive.name + '\n')
    verify(archive)
    return {'archive': archive.name, 'sha256': digest, 'source_commit': revision, 'source_tree_sha256': manifest['source_tree_sha256'], 'files': len(records), 'bytes': archive.stat().st_size, 'worktree_dirty': manifest['worktree_dirty']}


def verify(archive):
    with tarfile.open(archive, 'r:gz') as tar:
        members = tar.getmembers()
        seen = set()
        for member in members:
            if member.name in seen:
                raise ValueError('Duplicate archive member.')
            seen.add(member.name)
            if member.isdir():
                if member.name.rstrip('/') not in SCAFFOLD:
                    raise ValueError('Unexpected runtime directory.')
            elif not member.isfile() or (member.name != MANIFEST and not allowed(member.name)):
                raise ValueError('Unsafe archive entry.')
        manifest = json.load(tar.extractfile(MANIFEST))
        regular = {m.name for m in members if m.isfile() and m.name != MANIFEST}
        if regular != set(manifest['files']):
            raise ValueError('Archive and manifest inventory differ.')
        for name, record in manifest['files'].items():
            data = tar.extractfile(name).read()
            if len(data) != record['bytes'] or hashlib.sha256(data).hexdigest() != record['sha256']:
                raise ValueError('Archive checksum mismatch.')
        if hashlib.sha256(canonical(manifest['files'])).hexdigest() != manifest['source_tree_sha256']:
            raise ValueError('Source tree checksum mismatch.')
        return manifest


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--root', type=Path, default=ROOT)
    parser.add_argument('--output', type=Path)
    parser.add_argument('--verify', type=Path)
    args = parser.parse_args()
    if args.verify:
        result = verify(args.verify)
        print(json.dumps({'verified': True, 'source_commit': result['source_commit'], 'files': len(result['files'])}))
    elif args.output:
        print(json.dumps(build(args.root, args.output), indent=2))
    else:
        parser.error('--output or --verify is required')
