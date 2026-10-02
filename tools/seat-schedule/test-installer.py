import importlib.util,pathlib,tempfile,unittest,shutil,subprocess,json,hashlib
from unittest.mock import patch
spec=importlib.util.spec_from_file_location('seat_install',pathlib.Path(__file__).with_name('install.py'))
module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)

class InstallerSafetyTest(unittest.TestCase):
    def setUp(self):
        self.temp=pathlib.Path(tempfile.mkdtemp());self.root=self.temp/'app';self.source=self.temp/'source';self.private=self.temp/'private';self.root.mkdir();(self.root/'artisan').write_text('');(self.root/'.env').write_text('');(self.source/'tools/seat-schedule').mkdir(parents=True)
        self.target=self.root/'app/Services/Example.php';self.target.parent.mkdir(parents=True);self.target.write_text('<?php /* original */');source=self.source/'app/Services/Example.php';source.parent.mkdir(parents=True);source.write_text('<?php /* reviewed update */')
        b=source.read_bytes();self.entry={'path':'app/Services/Example.php','original_blob':module.blob(self.target.read_bytes()),'blob':module.blob(b),'sha256':hashlib.sha256(b).hexdigest()}
        (self.source/'tools/seat-schedule/manifest.json').write_text(json.dumps([self.entry]));self.calls=[]
        self.patches=[patch.object(module,'ROOT',self.root),patch.object(module,'PRIVATE',self.private),patch.object(module,'SOURCE',self.source),patch.object(module.os,'getuid',return_value=1),patch.object(module.pathlib.Path,'home',return_value=pathlib.Path('/home4/mcied45x')),patch.object(module.sys,'argv',['install.py','--reset-enrollments'])]
        for p in self.patches:p.start()
    def tearDown(self):
        for p in reversed(self.patches):p.stop()
        shutil.rmtree(self.temp)
    def run_command(self,command,log,timeout=120):
        self.calls.append(command)
        if 'merge-providers.php' in ' '.join(command):pathlib.Path(command[-1]).write_text('<?php return [];')
        if 'maintenance:reset-enrollments' in command and getattr(self,'fail_reset',False):raise RuntimeError('Injected reset failure')
    # Match Python 3.6's supported keyword surface instead of accepting arbitrary
    # modern arguments; deployment tests must catch compatibility regressions.
    def process(self,command,*,input=None,stdout=None,stderr=None,universal_newlines=False):
        self.assertEqual(subprocess.PIPE,stdout)
        self.assertEqual(subprocess.PIPE,stderr)
        self.assertTrue(universal_newlines)
        if command==['crontab','-l']:return subprocess.CompletedProcess(command,0,'# existing cron\n','')
        return subprocess.CompletedProcess(command,0,'','')
    def run_install(self):
        with patch.object(module,'run',side_effect=self.run_command),patch.object(module.subprocess,'run',side_effect=self.process),patch.object(module.urllib.request,'urlopen',side_effect=OSError('Offline test')):module.main()
    def test_changed_live_source_stops_before_reset(self):
        self.target.write_text('Unreviewed live customization')
        with self.assertRaisesRegex(RuntimeError,'differs'):self.run_install()
        self.assertFalse(any('maintenance:reset-enrollments' in c for c in self.calls))
    def test_reset_failure_restores_original_files_and_returns_online(self):
        self.fail_reset=True
        with self.assertRaisesRegex(RuntimeError,'reset failure'):self.run_install()
        self.assertEqual('<?php /* original */',self.target.read_text());self.assertTrue(any('up' in c for c in self.calls));self.assertFalse((self.private/'seat-schedule-enrollment-reset-completed.json').exists())
    def test_recorded_reset_preserves_new_enrollments_on_rerun(self):
        self.private.mkdir();(self.private/'seat-schedule-enrollment-reset-completed.json').write_text(json.dumps({'status':'RESET_COMPLETED'}))
        self.run_install();self.assertFalse(any('maintenance:reset-enrollments' in c for c in self.calls));self.assertIn('reviewed update',self.target.read_text())
    def test_success_records_one_time_reset(self):
        self.run_install();marker=json.loads((self.private/'seat-schedule-enrollment-reset-completed.json').read_text());self.assertEqual('RESET_COMPLETED',marker['status']);self.assertEqual(1,sum('maintenance:reset-enrollments' in c for c in self.calls))

    def test_python36_style_pipe_capture_handles_real_process_input_and_output(self):
        # Execute a real subprocess with precisely the options used for cron.
        result=subprocess.run([module.sys.executable,'-c','import sys; print(sys.stdin.read()); sys.stderr.write("diagnostic")'],input='saved cron',stdout=subprocess.PIPE,stderr=subprocess.PIPE,universal_newlines=True)
        self.assertEqual(0,result.returncode)
        self.assertEqual('saved cron\n',result.stdout)
        self.assertEqual('diagnostic',result.stderr)

    def test_cron_install_failure_occurs_after_reset_and_keeps_reset_marker(self):
        def fail_write(command,*,input=None,stdout=None,stderr=None,universal_newlines=False):
            result=self.process(command,input=input,stdout=stdout,stderr=stderr,universal_newlines=universal_newlines)
            if command==['crontab','-']:return subprocess.CompletedProcess(command,1,'','write denied')
            return result
        with patch.object(module,'run',side_effect=self.run_command),patch.object(module.subprocess,'run',side_effect=fail_write):
            with self.assertRaisesRegex(RuntimeError,'scheduler installation failed'):module.main()
        self.assertTrue((self.private/'seat-schedule-enrollment-reset-completed.json').exists())
        self.assertIn('reviewed update',self.target.read_text())
        self.assertTrue(any('up' in c for c in self.calls))

if __name__=='__main__':unittest.main()
