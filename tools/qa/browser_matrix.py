#!/usr/bin/env python3
"""Run existing browser suites sequentially against a guarded tg_test server; no staging/prod target."""
import argparse
import json
import os
from pathlib import Path
import subprocess
import time
import urllib.request
import urllib.error
from urllib.parse import urlparse
import uuid

ROOT = Path(__file__).resolve().parents[2]
CASES = {
    'feedback': ('feedback.cjs', 'tg009-browser', False),
    'layout': ('layout.cjs', 'tg008-browser', False),
    'products': ('products.cjs', 'tg011-browser', False),
    'catalog': ('catalog.cjs', 'tg012-browser', False),
    'checkout': ('checkout.cjs', 'tg013-15-browser', True),
    'fulfillment': ('fulfillment.cjs', 'tg016-18-browser', True),
    'licenses': ('license-operations.cjs', 'tg019-22-browser', True),
    'rebates': ('rebates.cjs', 'phase3-browser', False),
    'content': ('content.cjs', 'phase4-browser', False),
}


def run(backend, output, selected, base):
    url = urlparse(base)
    if url.scheme != 'http' or url.hostname != '127.0.0.1' or not url.port or url.port < 1024 or url.path not in ('', '/') or url.query or url.fragment or url.username is not None or url.password is not None:
        raise ValueError('QA matrix requires an explicit unprivileged 127.0.0.1 HTTP origin.')
    output.mkdir(parents=True, exist_ok=True)
    docker = ['docker', '--host=unix:///var/run/docker.sock']
    results = []
    for name in selected:
        script, folder, sales = CASES[name]
        env = os.environ.copy()
        env.update(APP_ENV='e2e', APP_URL=base, DB_DATABASE='tg_test', DB_URL='', TG_BROKER_REFERRAL_HOSTS='broker.example.invalid', QA_ENABLE_SALES_FIXTURES='1' if sales else '0')
        storage = f'/app/storage/framework/testing/matrix-{name}' if backend == 'docker' else str(ROOT / 'storage/framework/testing' / f'matrix-{name}')
        env['TG_PRIVATE_STORAGE_PATH'] = storage
        options = []
        for key in ['APP_ENV', 'APP_URL', 'DB_DATABASE', 'DB_URL', 'TG_PRIVATE_STORAGE_PATH', 'TG_BROKER_REFERRAL_HOSTS', 'QA_ENABLE_SALES_FIXTURES']:
            options += ['-e', f'{key}={env[key]}']
        seed = docker + ['compose', 'run', '--rm', '--no-deps'] + options + ['app', 'php', 'tools/qa/seed-browser.php', name] if backend == 'docker' else ['php', 'tools/qa/seed-browser.php', name]
        with (output / f'{name}-seed.log').open('w') as log:
            subprocess.run(seed, cwd=ROOT, env=env, stdout=log, stderr=subprocess.STDOUT, check=True, timeout=60)
        container = 'tg-qa-browser-' + uuid.uuid4().hex[:12]
        command = docker + ['compose', 'run', '--rm', '--no-deps', '--name', container, '-p', f'127.0.0.1:{url.port}:8000'] + options + ['app', 'php', '-S', '0.0.0.0:8000', '-t', 'public', 'tools/qa/router.php'] if backend == 'docker' else ['php', '-S', f'127.0.0.1:{url.port}', '-t', 'public', 'tools/qa/router.php']
        server = None
        try:
            with (output / f'{name}-server.log').open('w') as server_log:
                server = subprocess.Popen(command, cwd=ROOT, env=env, stdout=server_log, stderr=subprocess.STDOUT)
                deadline = time.monotonic() + 25
                opener = urllib.request.build_opener(urllib.request.ProxyHandler({}))
                while True:
                    if server.poll() is not None:
                        raise RuntimeError(f'{name} test server exited; inspect local server log.')
                    try:
                        with opener.open(base + '/login', timeout=2) as response:
                            if response.status == 200:
                                break
                    except (OSError, urllib.error.URLError):
                        pass
                    if time.monotonic() > deadline:
                        raise RuntimeError('Loopback e2e server did not become ready.')
                    time.sleep(.1)
                browser_env = os.environ.copy()
                browser_env.update(QA_BASE_URL=base, QA_ARTIFACT_DIR=str(output), LAYOUT_BASE_URL=base, LAYOUT_ARTIFACT_DIR=str(output / folder))
                with (output / f'{name}-browser.log').open('w') as log:
                    subprocess.run(['node', str(ROOT / 'tests/Browser' / script)], cwd=ROOT, env=browser_env, stdout=log, stderr=subprocess.STDOUT, check=True, timeout=300)
                summary = json.loads((output / folder / 'summary.json').read_text())
                if summary.get('failed', 0) or summary.get('javascriptErrors', []) or summary.get('browserErrors', []):
                    raise RuntimeError(f'{name} reported failed checks or browser errors.')
                results.append({'suite': name, 'passed': summary['passed'], 'summary': folder + '/summary.json'})
                print(f"PASS {name}: {summary['passed']} checks", flush=True)
        finally:
            if server:
                if backend == 'docker':
                    subprocess.run(docker + ['stop', '-t', '2', container], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, timeout=15)
                if server.poll() is None:
                    server.terminate()
                server.wait(timeout=15)
    summary = {'environment': 'local isolated e2e/MySQL8.4 tg_test; not staging', 'passed': sum(item['passed'] for item in results), 'suites': results}
    (output / 'browser-matrix.json').write_text(json.dumps(summary, indent=2) + '\n')
    return summary


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--backend', choices=['docker', 'local'], default='docker')
    parser.add_argument('--output', type=Path, required=True)
    parser.add_argument('--cases', nargs='+', choices=list(CASES), default=list(CASES))
    parser.add_argument('--base-url', default='http://127.0.0.1:8081')
    args = parser.parse_args()
    run(args.backend, args.output.resolve(), args.cases, args.base_url)
