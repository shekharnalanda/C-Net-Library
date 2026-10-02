#!/usr/bin/env python3
"""Private owner email check, reviewed hosted fallback and non-mutating timing checks."""
import json, os, pathlib, re, subprocess, sys, tempfile, time, urllib.request

HOME=pathlib.Path('/home4/mcied45x')
PHP='/usr/local/bin/ea-php83'
HERE=pathlib.Path(__file__).resolve().parent
ROOTS={'library':HOME/'repositories/C-Net-Library','tests':HOME/'repositories/MCI-Test-Series'}

def allowed_mailbox(row):
    def clear(value):
        return (type(value) is bool and value is False) or (type(value) is int and value==0) or value=='0'
    return clear(row.get('suspended_outgoing')) and clear(row.get('suspended_login')) and ('hold_outgoing' not in row or clear(row['hold_outgoing']))

def hosted(sender):
    return isinstance(sender,str) and re.fullmatch(r'[A-Za-z0-9._+%-]+@(mciedu\.com|mciedu\.in)',sender,re.I) is not None

def main():
    if pathlib.Path.home()!=HOME or os.getuid()==0:raise RuntimeError('Use mcied45x cPanel Terminal.')
    os.umask(0o077)
    parent=HOME/'mci-library-portal-backups'
    if parent.is_symlink():raise RuntimeError('Private report path requires review.')
    parent.mkdir(mode=0o700,exist_ok=True)
    report=pathlib.Path(tempfile.mkdtemp(prefix='mail-speed-',dir=str(parent)))
    log=report/'check.log'
    def run(helper,args,timeout=60):
        with log.open('ab') as stream:
            result=subprocess.run([PHP,str(HERE/'run-helper.php'),str(HERE/helper)]+list(map(str,args)),stdout=stream,stderr=subprocess.STDOUT,timeout=timeout,env=dict(os.environ,APP_DEBUG='false'))
        if result.returncode:raise RuntimeError('Private diagnostic failed: '+helper)
    owner_path=report/'owner-config.json'
    run('mail-inspect.php',[HOME/'repositories/Micro-Computer-Institute',owner_path])
    owner=json.loads(owner_path.read_text()).get('recipient')
    if not isinstance(owner,str) or not re.fullmatch(r'[^\s@]+@[^\s@]+\.[^\s@]+',owner):raise RuntimeError('Verified owner email unavailable; no test message sent.')
    configured=report/'library-config.json'
    run('mail-inspect.php',[ROOTS['library'],configured])
    source=json.loads(configured.read_text())
    probe=report/'current-mail.json'
    run('mail-probe.php',[ROOTS['library'],probe,owner],timeout=45)
    result=json.loads(probe.read_text())
    print('CURRENT_MAIL | '+result['code'],flush=True)
    def inventory_rows():
        inventory=subprocess.run(['/usr/local/cpanel/bin/uapi','--output=json','Email','list_pops_with_disk','get_restrictions=1'],stdout=subprocess.PIPE,stderr=subprocess.PIPE,universal_newlines=True,timeout=30)
        data=json.loads(inventory.stdout).get('result',{}) if inventory.returncode==0 else {}
        return data.get('data',[]) if data.get('status')==1 and isinstance(data.get('data'),list) else []
    try:rows=inventory_rows()
    except (ValueError,OSError,subprocess.SubprocessError):rows=[]
    sender=source.get('sender')
    matches=[row for row in rows if isinstance(row,dict) and str(row.get('email','')).lower()==str(sender).lower()]
    current_hosted=hosted(sender) and len(matches)==1 and allowed_mailbox(matches[0])
    print('SENDER_CHECK | hosted_mailbox_outgoing_verified='+str(current_hosted),flush=True)
    if not result['accepted'] or not current_hosted:
        senders=[source.get('sender')]
        salary=HOME/'repositories/Salary-Book'
        if salary.is_dir() and not salary.is_symlink():
            try:
                out=report/'salary-config.json';run('mail-inspect.php',[salary,out]);senders.append(json.loads(out.read_text()).get('sender'))
            except (RuntimeError,subprocess.SubprocessError):pass
        selected=None
        for sender in senders:
            matches=[row for row in rows if isinstance(row,dict) and str(row.get('email','')).lower()==str(sender).lower()]
            if hosted(sender) and len(matches)==1 and allowed_mailbox(matches[0]):selected=sender;break
        if selected:
            fallback=report/'hosted-mail.json';run('mail-probe.php',[ROOTS['library'],fallback,owner,selected],timeout=45)
            result=json.loads(fallback.read_text())
            if result['accepted']:
                config=HOME/'mci-library-test-bridge/library-mail.json'
                if config.parent.is_symlink() or config.is_symlink() or (config.parent.stat().st_mode&0o077):raise RuntimeError('Private sender path requires review.')
                if config.exists():(report/'previous-library-mail.json').write_bytes(config.read_bytes())
                temp=config.with_name('library-mail-'+os.urandom(6).hex()+'.json')
                temp.write_text(json.dumps({'sender':selected}));temp.chmod(0o600);os.replace(str(temp),str(config))
                sender=selected
                print('MAIL_REPAIR | VERIFIED_HOSTED_SENDMAIL_CONFIGURED',flush=True)
            else:print('MAIL_REPAIR | '+result['code'],flush=True)
        else:print('MAIL_REPAIR | ACTIVE_CONFIGURED_HOSTED_SENDER_NOT_VERIFIED',flush=True)
    if hosted(sender):
        domain=sender.rsplit('@',1)[1].lower()
        for kind in ['spfs','dkims']:
            try:
                check=subprocess.run(['/usr/local/cpanel/bin/uapi','--output=json','EmailAuth','validate_current_'+kind,'domain='+domain],stdout=subprocess.PIPE,stderr=subprocess.PIPE,universal_newlines=True,timeout=30)
                decoded=json.loads(check.stdout).get('result',{}) if check.returncode==0 else {}
                values=decoded.get('data',[]) if decoded.get('status')==1 else []
                states=[str(row.get('state','UNKNOWN')) for row in values if isinstance(row,dict)]
                (report/(kind+'-validation.json')).write_text(check.stdout)
                print('EMAIL_DNS | '+kind.upper()+' | '+','.join(states or ['CHECK_UNAVAILABLE']),flush=True)
            except (ValueError,OSError,subprocess.SubprocessError):print('EMAIL_DNS | '+kind.upper()+' | CHECK_UNAVAILABLE',flush=True)
    if result['accepted']:
        run('retry-welcome.php',[ROOTS['library']],timeout=240)
        lines=log.read_text(encoding='utf-8',errors='replace').splitlines()
        for line in lines:
            if line.startswith('WELCOME_RETRY |'):print(line,flush=True)
        print('OWNER_TEST_EMAIL | TRANSPORT_ACCEPTED | inbox/spam confirmation pending',flush=True)
    for site,root in ROOTS.items():
        out=report/(site+'-timing.json');run('portal-check.php',[root,site,out])
        print('CHECK | '+site+' | '+json.dumps(json.loads(out.read_text()),sort_keys=True),flush=True)
    for url in ['https://cnetlibrary.mciedu.com/admission','https://test.mciedu.com']:
        start=time.monotonic()
        try:
            with urllib.request.urlopen(urllib.request.Request(url,headers={'User-Agent':'MCI-Portal-Check/1.0'}),timeout=12) as response:
                response.read(1024);code=response.status
            print('PUBLIC_PAGE | '+url+' | status='+str(code)+' | first_response_ms='+str(round((time.monotonic()-start)*1000)),flush=True)
        except Exception:print('PUBLIC_PAGE | '+url+' | NETWORK_CHECK_INCOMPLETE',flush=True)
    print('PRIVATE_DIAGNOSTIC='+str(report),flush=True)
    print('No passwords or student enrollments were reset. Authenticated test opening still needs browser verification.',flush=True)

if __name__=='__main__':
    try:main()
    except Exception as error:
        print('DIAGNOSTIC_PENDING | '+str(error),flush=True);sys.exit(1)
