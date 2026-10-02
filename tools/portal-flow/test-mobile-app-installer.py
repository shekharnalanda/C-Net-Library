import importlib.util
import json
from pathlib import Path
import subprocess
import tempfile
import unittest
from unittest.mock import patch
import zipfile

spec = importlib.util.spec_from_file_location('app_installer', str(Path(__file__).with_name('mobile-app-install.py')))
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)


class InstallerTest(unittest.TestCase):
    def fixture(self, parent):
        root, source, home = parent/'live', parent/'source', parent/'home'
        for directory in [root/'bootstrap/cache', root/'storage/framework', source/'tools/portal-flow', home]:
            directory.mkdir(parents=True)
        (root/'artisan').write_text('fake')
        (root/'old.php').write_text('old')
        (source/'old.php').write_text('new')
        (source/'new.js').write_text('new script')
        entries = [{'path':'old.php', 'blob':module.digest(b'new'), 'original_blobs':[module.digest(b'old')]}, {'path':'new.js','blob':module.digest(b'new script'),'original_blobs':[None]}]
        (source/'tools/portal-flow/mobile-app-manifest.json').write_text(json.dumps(entries))
        return root, source, home

    def test_deployment_then_repeat_changes_only_reviewed_files(self):
        with tempfile.TemporaryDirectory() as folder:
            root, source, home = self.fixture(Path(folder))
            (root/'student-data.txt').write_text('preserve')
            with patch.multiple(module, ROOT=root, SOURCE=source, HOME=home), patch.object(module.subprocess, 'run', return_value=subprocess.CompletedProcess([], 0)), patch.object(module, 'public_check'), patch.object(module, 'apk_check', return_value={'present':False}):
                module.main(); module.main()
            self.assertEqual((root/'old.php').read_text(), 'new')
            self.assertEqual((root/'new.js').read_text(), 'new script')
            self.assertEqual((root/'student-data.txt').read_text(), 'preserve')
            self.assertEqual(len(list((home/'mci-library-app-backups').glob('*/summary.json'))), 2)

    def test_live_edits_are_blocked_before_maintenance(self):
        with tempfile.TemporaryDirectory() as folder:
            root, source, home = self.fixture(Path(folder))
            (root/'old.php').write_text('owner edit')
            with patch.multiple(module, ROOT=root, SOURCE=source, HOME=home), patch.object(module.subprocess,'run') as run:
                with self.assertRaisesRegex(RuntimeError, 'Live app file requires review'):
                    module.main()
                run.assert_not_called()

    def test_route_cache_and_files_are_restored_on_failed_public_check(self):
        for cached in [True, False]:
            with self.subTest(cached=cached), tempfile.TemporaryDirectory() as folder:
                root, source, home = self.fixture(Path(folder))
                cache = root/'bootstrap/cache/routes-v7.php'
                if cached: cache.write_text('old cached routes')
                def run(args, **kwargs):
                    if 'route:cache' in args: cache.write_text('new routes')
                    if 'route:clear' in args and cache.exists(): cache.unlink()
                    return subprocess.CompletedProcess(args, 0)
                with patch.multiple(module, ROOT=root, SOURCE=source, HOME=home), patch.object(module.subprocess, 'run', side_effect=run), patch.object(module,'public_check', side_effect=RuntimeError('HTTP check failed')):
                    with self.assertRaisesRegex(RuntimeError, 'HTTP check failed'): module.main()
                self.assertEqual((root/'old.php').read_text(), 'old')
                self.assertFalse((root/'new.js').exists())
                self.assertEqual(cache.exists(), cached)
                if cached: self.assertEqual(cache.read_text(), 'old cached routes')

    def test_apk_reports_file_integrity_without_claiming_phone_compatibility(self):
        with tempfile.TemporaryDirectory() as folder:
            root=Path(folder); apk=root/'public/downloads/C-Net-Library.apk'; apk.parent.mkdir(parents=True)
            with zipfile.ZipFile(str(apk), 'w') as archive:
                archive.writestr('AndroidManifest.xml', b'fixture')
                archive.writestr('classes.dex', b'fixture')
                archive.writestr('META-INF/CERT.RSA', b'fixture')
            result=module.apk_check(root)
            self.assertTrue(result['android_manifest']); self.assertTrue(result['dex_files'])
            self.assertEqual(result['zip_integrity'], 'ok')
            self.assertEqual(result['android_device_install'], 'not_verified')
            apk.write_bytes(b'not apk')
            self.assertEqual(module.apk_check(root)['zip_integrity'], 'failed')


if __name__ == '__main__':
    unittest.main()
