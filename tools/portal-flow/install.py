#!/usr/bin/env python3
"""Update library admissions and enforce a single student session on both portals."""
import datetime,fcntl,hashlib,json,os,pathlib,subprocess,sys,tempfile

HOME=pathlib.Path('/home4/mcied45x')
PHP='/usr/local/bin/ea-php83'
SOURCE=pathlib.Path(__file__).resolve().parents[2]
BRIDGE=HOME/'mci-library-test-bridge/config.json'
PRIVATE=HOME/'mci-library-portal-backups'
ROOTS={'library':HOME/'repositories/C-Net-Library','tests':HOME/'repositories/MCI-Test-Series'}

def blob(data):
    return hashlib.sha1(b'blob '+str(len(data)).encode()+b'\0'+data).hexdigest()

def run(command,root,log,timeout=180):
    with log.open('ab') as stream:
        result=subprocess.run(command,cwd=str(root),stdout=stream,stderr=subprocess.STDOUT,timeout=timeout,env=dict(os.environ,APP_DEBUG='false'))
    if result.returncode:
        raise RuntimeError('Deployment step failed. Private log: '+str(log))

def check_entries(source,root,entries):
    result=[]
    for entry in entries:
        name=entry['path'];relative=pathlib.PurePosixPath(name)
        if relative.is_absolute() or '..' in relative.parts:
            raise RuntimeError('Invalid deployment path.')
        staged=source/name;target=root/name
        data=staged.read_bytes()
        if hashlib.sha256(data).hexdigest()!=entry['sha256'] or blob(data)!=entry['blob']:
            raise RuntimeError('Staged checksum mismatch: '+name)
        if target.is_symlink() or any(parent.is_symlink() for parent in target.parents if parent!=root.parent):
            raise RuntimeError('Deployment path is a symlink: '+name)
        current=target.read_bytes() if target.exists() else None
        allowed=[entry['blob']]+entry['original_blobs']
        if (blob(current) if current is not None else None) not in allowed:
            raise RuntimeError('Live file requires review: '+name)
        result.append((name,data,current))
    return result

def main():
    if os.getuid()==0 or pathlib.Path.home()!=HOME:
        raise RuntimeError('Use the mcied45x cPanel Terminal.')
    if len(sys.argv)!=2:
        raise RuntimeError('The pinned Test Series source directory is required.')
    sources={'library':SOURCE,'tests':pathlib.Path(sys.argv[1]).resolve()}
    for site,root in ROOTS.items():
        if not (root/'artisan').is_file() or not (root/'.env').is_file():
            raise RuntimeError('Application is missing: '+site)
        if (root/'storage/framework/down').exists():
            raise RuntimeError('Application is already in maintenance mode: '+site)
    if PRIVATE.is_symlink() or BRIDGE.parent.is_symlink():
        raise RuntimeError('Private deployment directory is a symlink.')
    os.umask(0o077)
    PRIVATE.mkdir(mode=0o700,parents=True,exist_ok=True)
    os.chmod(str(PRIVATE),0o700)
    with (PRIVATE/'install.lock').open('a') as lock:
        fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
        install(sources)

def install(sources):
    backup=pathlib.Path(tempfile.mkdtemp(prefix=datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%S-'),dir=str(PRIVATE)))
    log=backup/'install.log'
    manifest=json.loads((SOURCE/'tools/portal-flow/manifest.json').read_text())
    staged={};merged={};originals={};maintenance=[];written=[];config_written=False
    helpers=SOURCE/'tools/portal-flow'
    print('C-NET LIBRARY ADMISSION AND SINGLE-DEVICE UPDATE',flush=True)
    print('Checking both sites, available tests and reviewed files ...',flush=True)
    for site,root in ROOTS.items():
        staged[site]=check_entries(sources[site],root,manifest[site])
        run([PHP,str(helpers/'preflight.php'),str(root),site],root,log)
        provider=root/'bootstrap/providers.php'
        if provider.is_symlink():raise RuntimeError('Provider file is a symlink.')
        original=provider.read_bytes() if provider.exists() else None
        output=backup/(site+'-providers.php')
        run([PHP,str(helpers/'merge-providers.php'),str(root),str(output),site],root,log)
        merged[site]=(output.read_bytes(),original)
        run([PHP,'-l',str(output)],root,log)
        for name,data,current in staged[site]+[('bootstrap/providers.php',merged[site][0],original)]:
            originals[(site,name)]=current
            if current is not None:
                saved=backup/'files'/site/name;saved.parent.mkdir(parents=True,exist_ok=True);saved.write_bytes(current)
                if saved.read_bytes()!=current:raise RuntimeError('Private file backup failed.')
    cron=subprocess.run(['crontab','-l'],stdout=subprocess.PIPE,stderr=subprocess.PIPE,universal_newlines=True)
    if cron.returncode not in [0,1]:raise RuntimeError('Scheduler inventory is unavailable.')
    rows=[line for line in cron.stdout.splitlines() if str(ROOTS['library'])+'/artisan' in line and 'schedule:run' in line and not line.lstrip().startswith('#')]
    if not any(line.split()[:5]==['*']*5 for line in rows):raise RuntimeError('Existing every-minute library scheduler is missing; no cron entries were changed.')
    (backup/'crontab.txt').write_text(cron.stdout)
    old_config=BRIDGE.read_bytes() if BRIDGE.exists() else None
    if old_config is None:raise RuntimeError('Existing private practice bridge is missing.')
    if BRIDGE.is_symlink() or (old_config is not None and BRIDGE.stat().st_mode&0o077):
        raise RuntimeError('Bridge configuration requires review.')
    if old_config is not None:(backup/'bridge-config.json').write_bytes(old_config)
    try:
        print('Private file backups ready; saving library database backup ...',flush=True)
        for site,root in ROOTS.items():
            run([PHP,'artisan','down','--retry=60'],root,log)
            maintenance.append(site)
        run([PHP,str(helpers/'backup.php'),str(ROOTS['library']),str(backup/'database')],ROOTS['library'],log,timeout=300)
        for site,root in ROOTS.items():
            entries=staged[site]+[('bootstrap/providers.php',merged[site][0],merged[site][1])]
            for name,data,current in entries:
                target=root/name;target.parent.mkdir(parents=True,exist_ok=True)
                target.write_bytes(data);os.chmod(str(target),0o644);written.append((site,name))
        for site,root in ROOTS.items():
            run([PHP,'artisan','optimize:clear'],root,log)
        migration='database/migrations/2026_10_02_083000_add_library_portal_flow.php'
        print('Adding admission selection fields, session leases and email queue; existing records are preserved ...',flush=True)
        run([PHP,'artisan','migrate','--path='+migration,'--force','--no-interaction'],ROOTS['library'],log,timeout=300)
        # Existing bridge identity and credentials are preserved.
        for site,root in ROOTS.items():
            run([PHP,str(helpers/'verify.php'),str(root),site],root,log)
            run([PHP,'artisan','view:cache'],root,log)
            run([PHP,'artisan','route:cache'],root,log)
        for site in list(reversed(maintenance)):
            run([PHP,'artisan','up'],ROOTS[site],log)
            maintenance.remove(site)
        print('APPLIED | admission availability | mobile-number initial login | approval email | reports',flush=True)
        print('APPLIED | separate admin/student login | student single session | test portal session enforced',flush=True)
        print('VERIFIED | routes | schema | page/report rendering | reminders=25,28 | cutoff=10',flush=True)
        print('No library enrollments, seats or paid test packages were deleted.',flush=True)
        print('PRIVATE_BACKUP='+str(backup),flush=True)
        print('STUDENT_LOGIN=https://cnetlibrary.mciedu.com/student-login',flush=True)
        print('ADMIN_REPORT=https://cnetlibrary.mciedu.com/admin/student-report',flush=True)
        print('Actual admission email delivery and a student login require owner-led live verification.',flush=True)
    except Exception:
        for site in ROOTS:
            if site not in maintenance:
                try:run([PHP,'artisan','down','--retry=60'],ROOTS[site],log);maintenance.append(site)
                except Exception:pass
        for site,name in reversed(written):
            target=ROOTS[site]/name;original=originals[(site,name)]
            if original is None:
                if target.exists():target.unlink()
            else:target.write_bytes(original)
        if config_written:
            if old_config is None:
                if BRIDGE.exists():BRIDGE.unlink()
            else:BRIDGE.write_bytes(old_config);os.chmod(str(BRIDGE),0o600)
        for site in ROOTS:
            try:run([PHP,'artisan','optimize:clear'],ROOTS[site],log)
            except Exception:pass
        print('STOPPED | Application files restored. Additive portal tables/columns may remain; no tables were dropped. Private log: '+str(log),flush=True)
        raise
    finally:
        for site in reversed(maintenance):
            try:run([PHP,'artisan','up'],ROOTS[site],log)
            except Exception:
                print('STOPPED | Return '+site+' online using artisan up. Private log: '+str(log),flush=True)

if __name__=='__main__':
    try:main()
    except Exception as error:
        print('STOPPED: '+str(error),flush=True)
        sys.exit(1)
