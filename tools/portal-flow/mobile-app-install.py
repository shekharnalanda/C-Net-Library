#!/usr/bin/env python3
"""Python 3.6 compatible, file-only app deployment with private rollback backup."""
import hashlib
import json
import os
from pathlib import Path
import shutil
import struct
import subprocess
import sys
import tempfile
import time
from urllib.request import urlopen
import zipfile

SOURCE = Path(__file__).resolve().parents[2]
ROOT = Path('/home4/mcied45x/repositories/C-Net-Library')
HOME = Path('/home4/mcied45x')
PHP = '/usr/local/bin/ea-php83'
URL = 'https://cnetlibrary.mciedu.com'


def digest(data):
    return hashlib.sha1(b'blob ' + str(len(data)).encode() + b'\0' + data).hexdigest()


def verify_files(entries, source=None, root=None):
    source = SOURCE if source is None else source
    root = ROOT if root is None else root
    for entry in entries:
        relative = entry['path']
        if Path(relative).is_absolute() or '..' in Path(relative).parts:
            raise RuntimeError('Invalid app file path.')
        target, prepared = root / relative, source / relative
        if not prepared.is_file() or digest(prepared.read_bytes()) != entry['blob']:
            raise RuntimeError('Prepared app file requires review: ' + relative)
        if target.is_symlink() or any(p.is_symlink() for p in target.parents if p != root and root in p.parents):
            raise RuntimeError('Linked app file requires review: ' + relative)
        actual = digest(target.read_bytes()) if target.is_file() else None
        if actual not in entry['original_blobs'] + [entry['blob']]:
            raise RuntimeError('Live app file requires review: ' + relative)


def replace_file(target, data, mode=0o644):
    target.parent.mkdir(parents=True, exist_ok=True)
    descriptor, temporary = tempfile.mkstemp(prefix='.library-app-', dir=str(target.parent))
    try:
        with os.fdopen(descriptor, 'wb') as handle:
            handle.write(data)
        os.chmod(temporary, mode)
        os.replace(temporary, str(target))
    finally:
        if os.path.exists(temporary):
            os.unlink(temporary)


def apk_check(root=None):
    """Format diagnostics only; Android signing/package compatibility is not asserted."""
    root = ROOT if root is None else root
    apk = root / 'public/downloads/C-Net-Library.apk'
    if not apk.is_file():
        return {'present': False}
    result = {'present': True, 'bytes': apk.stat().st_size}
    try:
        with zipfile.ZipFile(str(apk)) as archive:
            names = archive.namelist()
            if sum(info.file_size for info in archive.infolist()) > 536870912:
                result['zip_integrity'] = 'size_limit_requires_review'
                result['android_device_install'] = 'not_verified'
                return result
            result['android_manifest'] = 'AndroidManifest.xml' in names
            result['dex_files'] = any(n.startswith('classes') and n.endswith('.dex') for n in names)
            result['zip_integrity'] = 'ok' if archive.testzip() is None else 'failed'
            result['v1_signature_files'] = any(n.startswith('META-INF/') and n.endswith(('.RSA', '.DSA', '.EC')) for n in names)
        # v2/v3 signing block footer is immediately before the ZIP central directory.
        with apk.open('rb') as handle:
            handle.seek(max(0, apk.stat().st_size - 65557))
            tail = handle.read()
            offset = tail.rfind(b'PK\x05\x06')
            if offset >= 0 and len(tail) >= offset + 22:
                central = struct.unpack_from('<I', tail, offset + 16)[0]
                if central >= 16:
                    handle.seek(central - 16)
                    result['v2_v3_signing_marker'] = handle.read(16) == b'APK Sig Block 42'
    except (zipfile.BadZipFile, RuntimeError, OSError):
        result['zip_integrity'] = 'failed'
    result['android_device_install'] = 'not_verified'
    return result


def public_check(base=URL):
    checks = [('/', 'text/html', b'data-cnet-app-installer'),
              ('/student-login', 'text/html', b'rel="manifest"'),
              ('/library-app.webmanifest', 'application/manifest+json', b'"display":"standalone"'),
              ('/library-app/icon/192.png', 'image/png', b'\x89PNG\r\n\x1a\n'),
              ('/library-app/icon/512.png', 'image/png', b'\x89PNG\r\n\x1a\n'),
              ('/js/library-app-install.js', 'javascript', b'beforeinstallprompt'),
              ('/library-app-sw.js', 'javascript', b'cnet-library-app-shell-v1'),
              ('/library-app-offline.html', 'text/html', b'C-Net Library')]
    for path, mime, marker in checks:
        with urlopen(base + path + '?appcheck=' + str(int(time.time())), timeout=20) as response:
            body = response.read(1048577)
            if response.status != 200 or response.geturl().split('?')[0] != base + path or mime not in response.headers.get('Content-Type', '') or marker not in body or len(body) > 1048576:
                raise RuntimeError('Public app verification failed: ' + path)
            if path.endswith('.webmanifest'):
                manifest = json.loads(body.decode('utf-8'))
                if manifest['start_url'] != '/student-login' or manifest['scope'] != '/':
                    raise RuntimeError('Public app entry requires review.')
        print('PUBLIC_APP_OK | ' + path, flush=True)


def main():
    os.umask(0o077)
    if ROOT.resolve() != ROOT or not (ROOT / 'artisan').is_file():
        raise RuntimeError('Library root requires review.')
    if (ROOT / 'storage/framework/down').exists():
        raise RuntimeError('Library is already in maintenance mode.')
    entries = json.loads((SOURCE / 'tools/portal-flow/mobile-app-manifest.json').read_text())
    verify_files(entries)
    backup_parent = HOME / 'mci-library-app-backups'
    backup_parent.mkdir(mode=0o700, exist_ok=True)
    if backup_parent.is_symlink():
        raise RuntimeError('Backup directory requires review.')
    backup = Path(tempfile.mkdtemp(prefix=time.strftime('%Y%m%dT%H%M%S-'), dir=str(backup_parent)))
    cached_routes = (ROOT / 'bootstrap/cache/routes-v7.php').is_file()
    cached_route_file = ROOT / 'bootstrap/cache/routes-v7.php'
    if cached_routes:
        shutil.copy2(str(cached_route_file), str(backup / 'routes-v7.php'))
    saved, changed, down = [], False, False
    with (backup / 'install.log').open('wb') as log:
        def run(args):
            log.write(('STEP | ' + ' '.join(args[-3:]) + '\n').encode()); log.flush()
            process = subprocess.run(args, cwd=str(ROOT), stdout=log, stderr=subprocess.STDOUT, timeout=90)
            if process.returncode:
                raise RuntimeError('App deployment check failed. Private log: ' + str(backup / 'install.log'))
        def artisan(*args):
            run([PHP, str(ROOT / 'artisan')] + list(args))
        try:
            run([PHP, str(SOURCE / 'tools/portal-flow/mobile-app-check.php'), str(ROOT), 'preflight'])
            for entry in entries:
                relative = entry['path']; target = ROOT / relative
                for_php = relative.endswith('.php')
                if for_php:
                    run([PHP, '-l', str(SOURCE / relative)])
                saved.append({'path': relative, 'present': target.is_file(), 'mode': target.stat().st_mode & 0o777 if target.is_file() else 0o644})
                if target.is_file():
                    dest = backup / 'files' / relative
                    dest.parent.mkdir(parents=True, exist_ok=True)
                    shutil.copy2(str(target), str(dest))
            (backup / 'files.json').write_text(json.dumps(saved, indent=2))
            print('Private file backup ready; enabling short library maintenance ...', flush=True)
            artisan('down', '--retry=5'); down = True
            verify_files(entries)
            changed = True
            for entry in entries:
                replace_file(ROOT / entry['path'], (SOURCE / entry['path']).read_bytes())
            artisan('route:clear'); artisan('view:clear')
            if cached_routes:
                artisan('route:cache')
            run([PHP, str(SOURCE / 'tools/portal-flow/mobile-app-check.php'), str(ROOT), 'verify'])
            artisan('up'); down = False
            public_check()
            result = {'status': 'APPLIED', 'old_apk': apk_check(), 'database_changed': False, 'public_verified': True, 'mobile_install_verified': False}
            (backup / 'summary.json').write_text(json.dumps(result, indent=2))
            print('APPLIED | browser app installer | Android/iPhone guidance | real library icons | private data never cached')
            print('VERIFIED | manifest | install controls | service worker | public assets')
            print('OLD_APK_CHECK | ' + json.dumps(result['old_apk'], sort_keys=True))
            print('PRIVATE_BACKUP=' + str(backup))
            print('APP_URL=' + URL + '/')
            print('A real phone install still needs confirmation. Seats, students, fees and test packages were not changed.')
        except Exception:
            if changed:
                if not down:
                    artisan('down', '--retry=5'); down = True
                for item in saved:
                    target = ROOT / item['path']
                    if item['present']:
                        replace_file(target, (backup / 'files' / item['path']).read_bytes(), item['mode'])
                    elif target.exists():
                        target.unlink()
                # Restore the exact previous route cache, including when a new route cache fails.
                if cached_routes:
                    replace_file(cached_route_file, (backup / 'routes-v7.php').read_bytes())
                elif cached_route_file.exists():
                    cached_route_file.unlink()
                artisan('view:clear')
            if down:
                artisan('up')
            print('STOPPED | library app files restored | no database changes | private log: ' + str(backup / 'install.log'))
            raise


if __name__ == '__main__':
    try:
        print('C-NET LIBRARY MOBILE APP UPDATE', flush=True)
        main()
    except Exception as error:
        print('STOPPED: ' + str(error), flush=True)
        sys.exit(1)
