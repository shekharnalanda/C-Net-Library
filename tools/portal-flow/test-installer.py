import importlib.util,json,pathlib,tempfile,shutil,unittest,subprocess
from unittest.mock import patch
spec=importlib.util.spec_from_file_location('portal_install',pathlib.Path(__file__).with_name('install.py'))
m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
class PortalInstallerTest(unittest.TestCase):
 def setUp(self):
  self.temp=pathlib.Path(tempfile.mkdtemp());self.home=self.temp/'home';self.home.mkdir();self.calls=[]
  self.roots={s:self.home/s for s in ['library','tests']};self.sources={s:self.temp/('source-'+s) for s in self.roots};self.private=self.home/'backup';self.bridge=self.home/'bridge/config.json';self.bridge.parent.mkdir();self.bridge.write_text('private existing identity');self.bridge.chmod(0o600)
  manifest={}
  for site in self.roots:
   root=self.roots[site];root.mkdir();(root/'artisan').write_text('');(root/'.env').write_text('');target=root/'app/Portal.php';target.parent.mkdir();target.write_text('original '+site)
   source=self.sources[site];(source/'app').mkdir(parents=True);data=('reviewed '+site).encode();(source/'app/Portal.php').write_bytes(data)
   manifest[site]=[{'path':'app/Portal.php','original_blobs':[m.blob(target.read_bytes())],'blob':m.blob(data),'sha256':m.hashlib.sha256(data).hexdigest()}]
  folder=self.sources['library']/'tools/portal-flow';folder.mkdir(parents=True);(folder/'manifest.json').write_text(json.dumps(manifest))
  self.patches=[patch.object(m,'HOME',self.home),patch.object(m,'ROOTS',self.roots),patch.object(m,'SOURCE',self.sources['library']),patch.object(m,'PRIVATE',self.private),patch.object(m,'BRIDGE',self.bridge),patch.object(m.os,'getuid',return_value=1),patch.object(m.pathlib.Path,'home',return_value=self.home),patch.object(m.sys,'argv',['install.py',str(self.sources['tests'])])]
  for p in self.patches:p.start()
 def tearDown(self):
  for p in reversed(self.patches):p.stop()
  shutil.rmtree(self.temp)
 def fake_run(self,command,root,log,timeout=180):
  self.calls.append((command,root))
  if 'merge-providers.php' in ' '.join(command):pathlib.Path(command[-2]).write_text("<?php return ['ExistingProvider','App\\\\Providers\\\\LibraryPortalFlowServiceProvider'];")
  if getattr(self,'fail_backup',False) and 'backup.php' in ' '.join(command):raise RuntimeError('Backup failed')
  if getattr(self,'fail_verify',False) and 'verify.php' in ' '.join(command):raise RuntimeError('Verification failed')
  if getattr(self,'fail_up',False) and command[-1]=='up' and root==self.roots['tests']:self.fail_up=False;raise RuntimeError('Up failed')
 def cron(self,command,**kwargs):
  self.assertEqual(['crontab','-l'],command)
  return subprocess.CompletedProcess(command,0,'* * * * * php '+str(self.roots['library'])+'/artisan schedule:run\n','')
 def install(self):
  with patch.object(m,'run',side_effect=self.fake_run),patch.object(m.subprocess,'run',side_effect=self.cron):m.main()
 def test_success_migrates_library_only_preserves_bridge_and_never_resets(self):
  self.install();self.assertEqual('private existing identity',self.bridge.read_text());self.assertTrue(all((r/'app/Portal.php').read_text()=='reviewed '+s for s,r in self.roots.items()))
  commands=[c for c,r in self.calls];self.assertFalse(any('reset' in ' '.join(c) for c in commands));self.assertEqual(2,sum('migrate' in c for c in commands));self.assertTrue(any('migrate' in c and r==self.roots['library'] and '--path=database/migrations/2026_10_02_083000_add_library_portal_flow.php' in c for c,r in self.calls))
 def test_unreviewed_live_file_stops_before_maintenance(self):
  (self.roots['tests']/'app/Portal.php').write_text('unreviewed')
  with self.assertRaisesRegex(RuntimeError,'Live file'):self.install()
  self.assertFalse(any('down' in c for c,r in self.calls))
 def test_failed_backup_does_not_mutate_files_or_run_migration(self):
  self.fail_backup=True
  with self.assertRaisesRegex(RuntimeError,'Backup failed'):self.install()
  self.assertFalse(any('migrate' in c for c,r in self.calls));self.assertTrue(all((r/'app/Portal.php').read_text()=='original '+s for s,r in self.roots.items()))
 def test_failed_verify_restores_both_sites_and_existing_private_identity(self):
  self.fail_verify=True
  with self.assertRaisesRegex(RuntimeError,'Verification failed'):self.install()
  self.assertTrue(all((r/'app/Portal.php').read_text()=='original '+s for s,r in self.roots.items()));self.assertEqual('private existing identity',self.bridge.read_text())
 def test_checksum_tampering_stops_before_maintenance(self):
  (self.sources['library']/'app/Portal.php').write_text('tampered')
  with self.assertRaisesRegex(RuntimeError,'checksum'):self.install()
  self.assertFalse(any('down' in c for c,r in self.calls))
 def test_rerun_is_additive(self):
  self.install();self.calls=[];self.install();self.assertFalse(any('reset' in ' '.join(c) for c,r in self.calls))
 def test_missing_scheduler_stops_without_overwriting_cron(self):
  with patch.object(m,'run',side_effect=self.fake_run),patch.object(m.subprocess,'run',return_value=subprocess.CompletedProcess(['crontab','-l'],1,'','')):
   with self.assertRaisesRegex(RuntimeError,'scheduler is missing'):m.main()
  self.assertFalse(any('down' in c for c,r in self.calls))
 def test_online_failure_restores_files_and_retries_up(self):
  self.fail_up=True
  with self.assertRaisesRegex(RuntimeError,'Up failed'):self.install()
  self.assertTrue(all((r/'app/Portal.php').read_text()=='original '+s for s,r in self.roots.items()));self.assertEqual(2,sum(c[-1]=='up' and r==self.roots['tests'] for c,r in self.calls))
 def test_run_uses_python36_compatible_arguments(self):
  self.private.mkdir();log=self.private/'install.log'
  def process(command,*,cwd,stdout,stderr,timeout,env):return subprocess.CompletedProcess(command,0)
  with patch.object(m.subprocess,'run',side_effect=process):m.run(['php','artisan','up'],self.roots['library'],log)
 def test_campus_repair_only_runs_after_verified_backup_and_is_explicit(self):
  with patch.object(m.sys,'argv',['install.py',str(self.sources['tests']),'--repair-campus-slots']):self.install()
  commands=[' '.join(c) for c,r in self.calls]
  backup=next(i for i,c in enumerate(commands) if 'backup.php' in c)
  repair=next(i for i,c in enumerate(commands) if 'repair-campus.php' in c)
  verify=next(i for i,c in enumerate(commands) if 'verify.php' in c)
  self.assertLess(backup,repair);self.assertLess(repair,verify)
 def test_silent_php_failure_still_records_step_and_exit_code(self):
  self.private.mkdir();log=self.private/'install.log'
  with patch.object(m.subprocess,'run',return_value=subprocess.CompletedProcess(['php'],1)):
   with self.assertRaisesRegex(RuntimeError,'preflight.php'):m.run([m.PHP,'/source/preflight.php','root','library'],self.roots['library'],log)
  self.assertIn('STEP | library | preflight.php',log.read_text());self.assertIn('EXIT_CODE=1',log.read_text())
if __name__=='__main__':unittest.main()
