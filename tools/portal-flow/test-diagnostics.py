import importlib.util, json, pathlib, shutil, subprocess, tempfile, unittest
from unittest.mock import patch

spec=importlib.util.spec_from_file_location('portal_diagnostics',pathlib.Path(__file__).with_name('diagnose.py'))
m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)

class DiagnosticsTest(unittest.TestCase):
    def setUp(self):
        self.home=pathlib.Path(tempfile.mkdtemp());self.calls=[];self.current_ok=False;self.fallback_ok=True;self.recipient='owner@example.test'
        self.row={'email':'no-reply@mciedu.com','suspended_outgoing':0,'suspended_login':0,'hold_outgoing':0}
        self.roots={site:self.home/'repositories'/name for site,name in [('library','C-Net-Library'),('tests','MCI-Test-Series')]}
        for root in list(self.roots.values())+[self.home/'repositories/Salary-Book']:
            root.mkdir(parents=True);(root/'.env').write_text('original SMTP credentials preserved')
        (self.home/'mci-library-test-bridge').mkdir(mode=0o700)
        self.patches=[patch.object(m,'HOME',self.home),patch.object(m,'ROOTS',self.roots),patch.object(m.pathlib.Path,'home',return_value=self.home),patch.object(m.os,'getuid',return_value=1),patch.object(m.subprocess,'run',side_effect=self.process),patch.object(m.urllib.request,'urlopen',side_effect=OSError('offline'))]
        for p in self.patches:p.start()
    def tearDown(self):
        for p in reversed(self.patches):p.stop()
        shutil.rmtree(self.home)
    def process(self,command,**kwargs):
        self.calls.append(command)
        if command[0]=='/usr/local/cpanel/bin/uapi':
            data=[self.row] if command[2]=='Email' else [{'state':'VALID'}]
            return subprocess.CompletedProcess(command,0,json.dumps({'result':{'status':1,'data':data}}),'')
        helper=pathlib.Path(command[2]).name;args=command[3:]
        if helper=='mail-inspect.php':
            root=pathlib.Path(args[0]);data={'sender':'no-reply@mciedu.com' if root.name=='Salary-Book' else 'old@example.test','recipient':self.recipient if root.name=='Micro-Computer-Institute' else None,'driver':'smtp'}
            pathlib.Path(args[1]).write_text(json.dumps(data))
        elif helper=='mail-probe.php':
            ok=self.fallback_ok if len(args)==4 else self.current_ok
            pathlib.Path(args[1]).write_text(json.dumps({'accepted':ok,'code':'TRANSPORT_ACCEPTED' if ok else 'MAIL_AUTH_FAILED'}))
        elif helper=='portal-check.php':pathlib.Path(args[2]).write_text(json.dumps({'bridge_database_ms':2}))
        return subprocess.CompletedProcess(command,0)
    def test_failed_smtp_uses_only_verified_existing_sender_and_preserves_env(self):
        m.main();config=self.home/'mci-library-test-bridge/library-mail.json'
        self.assertEqual({'sender':'no-reply@mciedu.com'},json.loads(config.read_text()))
        self.assertEqual(0o600,config.stat().st_mode&0o777)
        self.assertTrue(all((root/'.env').read_text()=='original SMTP credentials preserved' for root in self.roots.values()))
        self.assertTrue(any('retry-welcome.php' in ' '.join(c) for c in self.calls))
    def test_unknown_or_suspended_mailbox_never_becomes_sender(self):
        for flag in [None,True,'unknown',1]:
            self.row['suspended_outgoing']=flag;m.main()
            self.assertFalse((self.home/'mci-library-test-bridge/library-mail.json').exists())
        self.assertFalse(any('retry-welcome.php' in ' '.join(c) for c in self.calls))
    def test_unrelated_active_mailbox_is_not_silently_selected(self):
        self.row['email']='another@mciedu.com';m.main()
        self.assertFalse((self.home/'mci-library-test-bridge/library-mail.json').exists())
    def test_failed_hosted_transport_does_not_persist_fallback(self):
        self.fallback_ok=False;m.main()
        self.assertFalse((self.home/'mci-library-test-bridge/library-mail.json').exists())
    def test_unresolved_owner_does_not_send_test_email(self):
        self.recipient=None
        with self.assertRaisesRegex(RuntimeError,'owner email'):m.main()
        self.assertFalse(any('mail-probe.php' in ' '.join(c) for c in self.calls))

if __name__=='__main__':unittest.main()
