"""Meaningful artifact safety/reproducibility checks using an isolated synthetic git repository."""
import hashlib
import io
import json
from pathlib import Path
import subprocess
import tarfile
import tempfile
import unittest
from release_bundle import build, verify


class ReleaseBundleTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix='tg-release-test-')
        self.root = Path(self.temp.name) / 'source'; self.root.mkdir()
        for name in ['artisan', 'composer.json', 'composer.lock', 'bootstrap/app.php', 'public/index.php', '.env.example']:
            path = self.root / name; path.parent.mkdir(parents=True, exist_ok=True); path.write_text('APP_KEY=\nDB_PASSWORD=\nDB_ROOT_PASSWORD=\nTG_HEALTH_TOKEN=\nTG_LICENSE_ED25519_PRIVATE_KEY_PATH=\n' if name == '.env.example' else 'synthetic fixture\n')
        path = self.root / 'public/build'; path.mkdir()
        (path / 'manifest.json').write_text(json.dumps({'entry': {'file': 'app.js', 'isEntry': True}})); (path / 'app.js').write_text('/* synthetic */')
        subprocess.run(['git', 'init', '-q', str(self.root)], check=True)
        subprocess.run(['git', '-C', str(self.root), 'add', '.'], check=True)
        subprocess.run(['git', '-C', str(self.root), '-c', 'user.name=Synthetic QA', '-c', 'user.email=qa@example.invalid', 'commit', '-qm', 'Synthetic fixture'], check=True)

    def tearDown(self):
        self.temp.cleanup()

    def test_reproducible_bytes_and_secrets_data_dependencies_excluded(self):
        for name in ['.env', 'storage/app/private/client.ex5', 'bootstrap/cache/config.php', 'vendor/auth.json', 'tests/fake-business.php']:
            path = self.root / name; path.parent.mkdir(parents=True, exist_ok=True); path.write_text('SYNTHETIC-EXCLUDED-MATERIAL')
        first = build(self.root, Path(self.temp.name) / 'first'); second = build(self.root, Path(self.temp.name) / 'second')
        self.assertEqual(first['sha256'], second['sha256'])
        with tarfile.open(Path(self.temp.name) / 'first/trader-garuda-candidate.tar.gz') as tar:
            self.assertFalse(any('SYNTHETIC-EXCLUDED-MATERIAL' in tar.extractfile(item).read().decode() for item in tar.getmembers() if item.isfile()))

    def test_symlink_and_missing_asset_are_rejected(self):
        (self.root / 'app').mkdir(); (self.root / 'app/link.php').symlink_to(self.root / '.env.example')
        with self.assertRaisesRegex(ValueError, 'Symlink'):
            build(self.root, Path(self.temp.name) / 'out')
        (self.root / 'app/link.php').unlink(); (self.root / 'public/build/app.js').unlink()
        with self.assertRaisesRegex(ValueError, 'asset'):
            build(self.root, Path(self.temp.name) / 'out')

    def test_populated_env_example_and_private_key_source_are_rejected(self):
        (self.root / '.env.example').write_text('APP_KEY=synthetic-not-empty\n')
        with self.assertRaisesRegex(ValueError, 'empty'):
            build(self.root, Path(self.temp.name) / 'out')
        (self.root / '.env.example').write_text('APP_KEY=\n'); (self.root / 'app').mkdir(); (self.root / 'app/bad.php').write_text('-----BEGIN PRIVATE KEY-----\nSYNTHETIC')
        with self.assertRaisesRegex(ValueError, 'Private key'):
            build(self.root, Path(self.temp.name) / 'out')

    def test_tampered_archive_content_fails_manifest_verification(self):
        out = Path(self.temp.name) / 'out'; build(self.root, out)
        target = out / 'tampered.tar.gz'
        with tarfile.open(out / 'trader-garuda-candidate.tar.gz') as source, tarfile.open(target, 'w:gz') as dest:
            for item in source.getmembers():
                if item.isfile():
                    data = source.extractfile(item).read()
                    if item.name == 'public/build/app.js': data += b'changed'
                    item.size = len(data); dest.addfile(item, io.BytesIO(data))
                else: dest.addfile(item)
        with self.assertRaisesRegex(ValueError, 'checksum'):
            verify(target)


if __name__ == '__main__':
    unittest.main()
