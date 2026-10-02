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
from urllib.error import HTTPError, URLError
from urllib.request import Request, urlopen
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


def public_directory_inventory(entries, root=None):
    root = ROOT if root is None else root
    public = root / 'public'
    paths = set()
    for entry in entries:
        target = root / entry['path']
        if public in target.parents:
            for parent in target.parents:
                if parent == public:
                    break
                paths.add(parent)
    inventory = []
    for path in sorted(paths, key=lambda p: (len(p.parts), str(p))):
        if path.is_symlink() or (path.exists() and not path.is_dir()):
            raise RuntimeError('Public asset directory requires review: ' + str(path.relative_to(root)))
        inventory.append({'path': str(path.relative_to(root)), 'mode': path.stat().st_mode & 0o7777 if path.is_dir() else None})
    return inventory


def enable_public_directories(inventory, root=None):
    root = ROOT if root is None else root
    for item in inventory:
        path = root / item['path']
        path.mkdir(parents=True, exist_ok=True)
        # Public asset parents must be readable/traversable by the web server.
        # Keep existing write/special bits and leave all private directories alone.
        mode = (item['mode'] if item['mode'] is not None else 0o700) | 0o055
        os.chmod(str(path), mode)
        print('PUBLIC_ASSET_DIRECTORY | ' + item['path'] + ' | before=' + (oct(item['mode']) if item['mode'] is not None else 'missing') + ' | applied=' + oct(mode), flush=True)


def restore_public_directories(inventory, root=None):
    root = ROOT if root is None else root
    for item in reversed(inventory):
        path = root / item['path']
        if item['mode'] is not None:
            os.chmod(str(path), item['mode'])
        elif path.is_dir() and not any(path.iterdir()):
            path.rmdir()


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


def public_response(base, path, mime):
    # Match the identified diagnostic client already accepted by the hosted site.
    # Do not append probe query parameters or use urllib's anonymous default profile.
    accept = 'text/javascript, application/javascript' if mime == 'javascript' else mime
    request = Request(base + path, headers={
        'User-Agent': 'MCI-Portal-Check/1.0',
        'Accept': accept + ', */*;q=0.1',
        'Cache-Control': 'no-cache',
    })
    try:
        with urlopen(request, timeout=20) as response:
            body = response.read(1048577)
            if response.status != 200 or response.geturl() != base + path or mime not in response.headers.get('Content-Type', '') or len(body) > 1048576:
                raise RuntimeError('Public app response requires review: ' + path)
            return body
    except HTTPError as error:
        code = error.code
        error.close()
        raise RuntimeError('Public app request blocked: ' + path + ' | HTTP ' + str(code))
    except URLError:
        raise RuntimeError('Public app network check failed: ' + path)


def public_preflight(base=URL):
    # Establish that this server's HTTP check is accepted before changing any files.
    for path in ['/', '/admission']:
        body = public_response(base, path, 'text/html').lower()
        if b'<html' not in body or (path == '/admission' and b'<form' not in body):
            raise RuntimeError('Public page preflight requires review: ' + path)
        print('PUBLIC_PREFLIGHT_OK | ' + path, flush=True)


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
        body = public_response(base, path, mime)
        if marker not in body:
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
    asset_directories = public_directory_inventory(entries)
    (backup / 'directories.json').write_text(json.dumps(asset_directories, indent=2))
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
            public_preflight()
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
            enable_public_directories(asset_directories)
            for entry in entries:
                replace_file(ROOT / entry['path'], (SOURCE / entry['path']).read_bytes())
            artisan('route:clear'); artisan('view:clear')
            if cached_routes:
                artisan('route:cache')
            run([PHP, str(SOURCE / 'tools/portal-flow/mobile-app-check.php'), str(ROOT), 'verify'])
            artisan('up'); down = False
            public_check()
            result = {'status': 'APPLIED', 'old_apk': apk_check(), 'database_changed': False, 'public_verified': True, 'mobile_install_verified': False,
                      'public_directories': [{'path': item['path'], 'previous_mode': item['mode'], 'applied_mode': (ROOT / item['path']).stat().st_mode & 0o7777} for item in asset_directories]}
            (backup / 'summary.json').write_text(json.dumps(result, indent=2))
            print('APPLIED | browser app installer | Android/iPhone guidance | real library icons | private data never cached')
            print('VERIFIED | manifest | install controls | service worker | public assets')
            print('OLD_APK_CHECK | ' + json.dumps(result['old_apk'], sort_keys=True))
            print('PRIVATE_BACKUP=' + str(backup))
            print('APP_URL=' + URL + '/')
            print('A real phone install still needs confirmation. Seats, students, fees and test packages were not changed.')
        except Exception as error:
            log.write(('FAILURE | ' + str(error) + '\n').encode('utf-8')); log.flush()
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
                restore_public_directories(asset_directories)
                artisan('view:clear')
            if down:
                artisan('up')
            state = 'library app files restored' if changed else 'library files not changed'
            print('STOPPED | ' + state + ' | no database changes | private log: ' + str(backup / 'install.log'))
            raise


if __name__ == '__main__':
    try:
        print('C-NET LIBRARY MOBILE APP UPDATE', flush=True)
        main()
    except Exception as error:
        print('STOPPED: ' + str(error), flush=True)
        sys.exit(1)
