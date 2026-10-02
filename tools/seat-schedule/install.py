#!/usr/bin/env python3
"""Pinned file deployment; never resets the repository or removes seats."""
import argparse,datetime,hashlib,json,os,pathlib,shutil,subprocess,sys,tempfile,urllib.request,fcntl

ROOT=pathlib.Path('/home4/mcied45x/repositories/C-Net-Library')
PRIVATE=pathlib.Path('/home4/mcied45x/mci-library-backups')
PHP='/usr/local/bin/ea-php83'
SOURCE=pathlib.Path(__file__).resolve().parents[2]

def blob(data):return hashlib.sha1(b'blob '+str(len(data)).encode()+b'\0'+data).hexdigest()
def provider_merge_command(root,output):return [PHP,str(SOURCE/'tools/seat-schedule/merge-providers.php'),str(root/'bootstrap/providers.php'),str(output)]
def run(command,log,timeout=120):
    with log.open('ab') as stream:
        result=subprocess.run(command,cwd=str(ROOT),stdout=stream,stderr=subprocess.STDOUT,timeout=timeout,env=dict(os.environ,APP_DEBUG='false'))
    if result.returncode:raise RuntimeError('Command failed; private log: '+str(log))

def main():
    args=argparse.ArgumentParser();args.add_argument('--reset-enrollments',action='store_true');options=args.parse_args()
    if not options.reset_enrollments:raise RuntimeError('The requested backed-up enrollment reset flag is required.')
    if os.getuid()==0 or pathlib.Path.home()!=pathlib.Path('/home4/mcied45x'):raise RuntimeError('Use the mcied45x cPanel Terminal.')
    if not ROOT.is_dir() or not (ROOT/'artisan').is_file() or not (ROOT/'.env').is_file():raise RuntimeError('Library application was not found at the reviewed path.')
    if (ROOT/'storage/framework/down').exists():raise RuntimeError('Library is already in maintenance mode; leave its existing maintenance state untouched.')
    os.umask(0o077);PRIVATE.mkdir(mode=0o700,parents=True,exist_ok=True);os.chmod(PRIVATE,0o700)
    if PRIVATE.is_symlink():raise RuntimeError('Private backup directory cannot be a symlink.')
    with (PRIVATE/'seat-update.lock').open('a') as lock:
        fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
        return install_locked(options)

def install_locked(options):
    backup=pathlib.Path(tempfile.mkdtemp(prefix=datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%S-'),dir=str(PRIVATE)))
    log=backup/'install.log';manifest=json.loads((SOURCE/'tools/seat-schedule/manifest.json').read_text());entries=[]
    marker=PRIVATE/'seat-schedule-enrollment-reset-completed.json';reset_done=False;maintenance=False;copied=[];originals={};cron_changed=False
    print('C-NET LIBRARY SEAT UPDATE',flush=True)
    print('Checking library identity, campus structure and reviewed files ...',flush=True)
    run([PHP,str(SOURCE/'tools/seat-schedule/audit.php'),str(ROOT),'preflight'],log)
    for entry in manifest:
        name=entry['path']
        if name.startswith('tests/') or name=='bootstrap/providers.php':continue
        if not name.startswith(('app/','resources/','routes/')):raise RuntimeError('Unreviewed manifest path.')
        source=SOURCE/name;target=ROOT/name
        if not source.is_file() or hashlib.sha256(source.read_bytes()).hexdigest()!=entry['sha256']:raise RuntimeError('Source checksum mismatch: '+name)
        if target.is_symlink():raise RuntimeError('Unreviewed target symlink: '+name)
        if target.exists() and blob(target.read_bytes()) not in [entry['blob'],entry.get('original_blob'),entry.get('normalized_original_blob')]:raise RuntimeError('Live file differs from reviewed versions: '+name)
        if not target.exists() and entry.get('original_blob'):raise RuntimeError('Required live file missing: '+name)
        entries.append((name,source,target));originals[name]=target.read_bytes() if target.exists() else None
        if name.endswith('.php'):run([PHP,'-l',str(source)],log)
    provider_target=ROOT/'bootstrap/providers.php'
    if provider_target.is_symlink():raise RuntimeError('Provider target cannot be a symlink.')
    originals['bootstrap/providers.php']=provider_target.read_bytes() if provider_target.exists() else None
    provider_output=backup/'merged-providers.php';run(provider_merge_command(ROOT,provider_output),log)
    run([PHP,'-l',str(provider_output)],log);entries.append(('bootstrap/providers.php',provider_output,provider_target))
    for name,data in originals.items():
        if data is not None:
            destination=backup/'files'/name;destination.parent.mkdir(parents=True,exist_ok=True);destination.write_bytes(data)
            if destination.read_bytes()!=data:raise RuntimeError('File backup verification failed.')
    cron=subprocess.run(['crontab','-l'],capture_output=True,text=True)
    if cron.returncode not in [0,1] or cron.returncode==1 and cron.stdout.strip():raise RuntimeError('Cron inventory unavailable.')
    old_cron=cron.stdout if cron.returncode==0 else ''
    (backup/'crontab.txt').write_text(old_cron)
    cron_line='* * * * * /usr/local/bin/ea-php83 /home4/mcied45x/repositories/C-Net-Library/artisan schedule:run >> /home4/mcied45x/mci-library-backups/scheduler.log 2>&1'
    matching=[line for line in old_cron.splitlines() if str(ROOT)+'/artisan' in line and 'schedule:run' in line and not line.lstrip().startswith('#')]
    if matching and not any(line.split()[:5]==['*']*5 for line in matching):raise RuntimeError('Existing library scheduler frequency requires review.')
    new_cron=old_cron if matching else old_cron.rstrip('\n')+'\n'+cron_line+'\n'
    try:
        print('Backing up current files; enabling short maintenance window ...',flush=True)
        run([PHP,'artisan','down','--retry=60'],log);maintenance=True
        for name,source,target in entries:
            target.parent.mkdir(parents=True,exist_ok=True)
            stage=target.with_name(target.name+'.mci-stage');stage.write_bytes(source.read_bytes());os.chmod(stage,0o644);os.replace(stage,target);copied.append(name)
        run([PHP,'artisan','optimize:clear'],log)
        run([PHP,str(SOURCE/'tools/seat-schedule/audit.php'),str(ROOT),'installed'],log)
        run([PHP,'artisan','view:cache'],log)
        # A successful reset marker prevents reruns from deleting newly entered students.
        if marker.exists():
            previous=json.loads(marker.read_text())
            if previous.get('status')!='RESET_COMPLETED':raise RuntimeError('Previous reset marker is unrecognized.')
            print('Prior enrollment reset recorded; preserving any new student entries.',flush=True)
        else:
            print('Saving verified private database backup; resetting old enrollments only ...',flush=True)
            run([PHP,'artisan','maintenance:reset-enrollments','--confirm=RESET-CNET-LIBRARY-ENROLLMENTS','--backup-directory='+str(backup/'database')],log,timeout=1800)
            reset_done=True
            marker.write_text(json.dumps({'status':'RESET_COMPLETED','backup':str(backup),'created_at':datetime.datetime.now(datetime.timezone.utc).isoformat()}));os.chmod(marker,0o600)
        print('Enabling time-based seat allocation and monthly fee release ...',flush=True)
        run([PHP,'artisan','seats:configure-monthly-cutoff'],log)
        run([PHP,'artisan','memberships:release-unpaid-seats'],log)
        if new_cron!=old_cron:
            result=subprocess.run(['crontab','-'],input=new_cron,text=True,capture_output=True)
            if result.returncode:raise RuntimeError('Library scheduler installation failed.')
            cron_changed=True
        run([PHP,'artisan','route:cache'],log)
        run([PHP,str(SOURCE/'tools/seat-schedule/audit.php'),str(ROOT),'installed'],log)
        run([PHP,'artisan','up'],log);maintenance=False
        print('APPLIED | time-based seats | public/admin report | fee cutoff=10 | scheduler=ready',flush=True)
        print('PRIVATE_BACKUP='+str(backup),flush=True)
        if reset_done:print('RESET_COMPLETED | students=0 | existing campus seats preserved',flush=True)
        req=urllib.request.Request('https://cnetlibrary.mciedu.com/seat-report',headers={'User-Agent':'MCI deployment verification'})
        try:
            with urllib.request.urlopen(req,timeout=30) as response:
                body=response.read().decode('utf-8','replace');verified=response.status==200 and 'Seat & Time Report' in body
            print('PUBLIC_REPORT='+('VERIFIED' if verified else 'HTTP_REVIEW_REQUIRED'),flush=True)
        except Exception:print('PUBLIC_REPORT=HTTP_REVIEW_REQUIRED (local report render passed)',flush=True)
        print('REPORT_URL=https://cnetlibrary.mciedu.com/seat-report',flush=True)
    except Exception:
        if not reset_done:
            for name in reversed(copied):
                target=ROOT/name;data=originals[name]
                if data is None:
                    if target.exists():target.unlink()
                else:target.write_bytes(data)
            if copied:
                try:run([PHP,'artisan','optimize:clear'],log)
                except Exception:pass
            if cron_changed:subprocess.run(['crontab','-'],input=old_cron,text=True,capture_output=True)
            print('STOPPED | files restored; enrollment reset did not complete. Private report: '+str(log),flush=True)
        else:
            print('STOPPED | enrollment reset completed and is backed up. New seat code retained; inspect private report: '+str(log),flush=True)
        raise
    finally:
        if maintenance:
            try:run([PHP,'artisan','up'],log)
            except Exception:print('MAINTENANCE_REVIEW_REQUIRED: '+str(log),flush=True)

if __name__=='__main__':
    try:main()
    except Exception as error:print('STOPPED: '+str(error),flush=True);sys.exit(1)
