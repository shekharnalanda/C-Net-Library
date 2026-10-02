import importlib.util,json,pathlib,tempfile,shutil,unittest,subprocess
from unittest.mock import patch

spec=importlib.util.spec_from_file_location('benefit_install',pathlib.Path(__file__).with_name('install.py'))
module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)

class BenefitInstallerTest(unittest.TestCase):
    def setUp(self):
        self.temp=pathlib.Path(tempfile.mkdtemp());self.home=self.temp/'home';self.home.mkdir()
        self.roots={site:self.home/site for site in ['library','tests']}
        self.sources={site:self.temp/('source-'+site) for site in self.roots}
        self.bridge=self.home/'bridge/config.json';self.private=self.home/'backup';self.calls=[]
        manifest={}
        for site in self.roots:
            root=self.roots[site];root.mkdir();(root/'artisan').write_text('');(root/'.env').write_text('')
            target=root/'app/Benefit.php';target.parent.mkdir();target.write_text('original '+site)
            source=self.sources[site];(source/'app').mkdir(parents=True);(source/'app/Benefit.php').write_text('reviewed '+site)
            data=(source/'app/Benefit.php').read_bytes()
            manifest[site]=[{'path':'app/Benefit.php','original_blobs':[module.blob(target.read_bytes())],'blob':module.blob(data),'sha256':module.hashlib.sha256(data).hexdigest()}]
        folder=self.sources['library']/'tools/library-practice';folder.mkdir(parents=True);(folder/'manifest.json').write_text(json.dumps(manifest))
        self.patches=[patch.object(module,'HOME',self.home),patch.object(module,'ROOTS',self.roots),patch.object(module,'SOURCE',self.sources['library']),patch.object(module,'PRIVATE',self.private),patch.object(module,'BRIDGE',self.bridge),patch.object(module.os,'getuid',return_value=1),patch.object(module.pathlib.Path,'home',return_value=self.home),patch.object(module.sys,'argv',['install.py',str(self.sources['tests'])])]
        for p in self.patches:p.start()
    def tearDown(self):
        for p in reversed(self.patches):p.stop()
        shutil.rmtree(self.temp)
    def fake_run(self,command,root,log,timeout=180):
        self.calls.append((command,root))
        if 'merge-providers.php' in ' '.join(command):
            pathlib.Path(command[-1]).write_text("<?php return ['ExistingProvider','App\\\\Providers\\\\LibraryPracticeServiceProvider'];")
        if 'configure.php' in ' '.join(command):
            self.bridge.parent.mkdir(exist_ok=True);self.bridge.write_text('private config');self.bridge.chmod(0o600)
        if getattr(self,'fail_verify',False) and 'verify.php' in ' '.join(command):
            raise RuntimeError('Injected verification failure')
        if getattr(self,'fail_up',False) and command[-1]=='up' and root==self.roots['tests']:
            self.fail_up=False;raise RuntimeError('Injected online failure')
    def install(self):
        with patch.object(module,'run',side_effect=self.fake_run):module.main()
    def test_success_updates_both_sites_and_never_runs_reset_or_changes_existing_student_tables(self):
        self.install()
        for site,root in self.roots.items():self.assertEqual('reviewed '+site,(root/'app/Benefit.php').read_text())
        commands=[c for c,r in self.calls]
        self.assertFalse(any('reset' in ' '.join(c) for c in commands))
        migrations=[c for c in commands if 'migrate' in c]
        self.assertEqual(1,len(migrations));self.assertIn('--path=database/migrations/2026_10_02_071244_create_library_practice_tables.php',migrations[0])
        self.assertTrue(all(any(c[-1]=='up' and r==root for c,r in self.calls) for root in self.roots.values()))
    def test_live_customization_stops_before_maintenance(self):
        (self.roots['tests']/'app/Benefit.php').write_text('unreviewed edit')
        with self.assertRaisesRegex(RuntimeError,'Live file'):self.install()
        self.assertFalse(any('down' in c for c,r in self.calls))
        self.assertEqual('original library',(self.roots['library']/'app/Benefit.php').read_text())
    def test_failed_verification_restores_both_sites_and_private_configuration(self):
        self.bridge.parent.mkdir();self.bridge.write_text('original config');self.bridge.chmod(0o600);self.fail_verify=True
        with self.assertRaisesRegex(RuntimeError,'verification failure'):self.install()
        for site,root in self.roots.items():self.assertEqual('original '+site,(root/'app/Benefit.php').read_text())
        self.assertEqual('original config',self.bridge.read_text())
        self.assertTrue(all(any(c[-1]=='up' and r==root for c,r in self.calls) for root in self.roots.values()))
    def test_new_configuration_is_removed_on_failed_install(self):
        self.fail_verify=True
        with self.assertRaisesRegex(RuntimeError,'verification failure'):self.install()
        self.assertFalse(self.bridge.exists())
    def test_online_failure_is_reported_and_restores_files_then_retries_return_online(self):
        self.fail_up=True
        with self.assertRaisesRegex(RuntimeError,'online failure'):self.install()
        self.assertEqual('original tests',(self.roots['tests']/'app/Benefit.php').read_text())
        self.assertEqual(2,sum(c[-1]=='up' and r==self.roots['tests'] for c,r in self.calls))
    def test_checksum_mismatch_stops_before_maintenance(self):
        (self.sources['tests']/'app/Benefit.php').write_text('modified staged source')
        with self.assertRaisesRegex(RuntimeError,'checksum'):self.install()
        self.assertFalse(any('down' in c for c,r in self.calls))
    def test_rerun_accepts_already_applied_files(self):
        self.install();self.calls=[];self.install()
        self.assertFalse(any('reset' in ' '.join(c) for c,r in self.calls))
    def test_run_uses_python36_supported_subprocess_arguments(self):
        self.private.mkdir();log=self.private/'test.log'
        def process(command,*,cwd,stdout,stderr,timeout,env):
            self.assertEqual(subprocess.STDOUT,stderr);self.assertEqual('false',env['APP_DEBUG'])
            return subprocess.CompletedProcess(command,0)
        with patch.object(module.subprocess,'run',side_effect=process):module.run(['php','artisan','up'],self.roots['library'],log)

if __name__=='__main__':unittest.main()
