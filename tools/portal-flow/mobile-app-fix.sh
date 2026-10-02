#!/bin/bash
set -euo pipefail
umask 077
test "$(id -un)" = mcied45x || { echo 'STOPPED: Use mcied45x cPanel Terminal.'; exit 1; }
for B in git python3 tar mktemp timeout; do
    command -v "$B" >/dev/null || { echo "STOPPED: $B unavailable."; exit 1; }
done
test -x /usr/local/bin/ea-php83
python3 -c 'import sys; sys.exit(0 if sys.version_info >= (3,6) else 1)'
R='/home4/mcied45x/repositories/C-Net-Library'
S=${1:?Pinned app fix commit is required}
[[ $S =~ ^[a-f0-9]{40}$ ]] || { echo 'STOPPED: Invalid source pin.'; exit 1; }
case "$(git -C "$R" remote get-url origin)" in
    git@github.com:shekharnalanda/C-Net-Library.git|https://github.com/shekharnalanda/C-Net-Library.git|https://github.com/shekharnalanda/C-Net-Library|ssh://git@github.com/shekharnalanda/C-Net-Library.git) ;;
    *) echo 'STOPPED: Library repository does not match.'; exit 1 ;;
esac
W=$(mktemp -d '/home4/mcied45x/cnet-library-app.XXXXXX')
git -C "$R" cat-file -e "$S^{commit}"
git -C "$R" archive "$S" app/Http/Middleware/InjectMobileAppInstaller.php app/Http/Controllers/LibraryAppController.php app/Providers/LibraryPortalFlowServiceProvider.php resources/views/public/app-installer.blade.php routes/library-app.php public/js/library-app-install.js public/library-app-sw.js public/library-app-offline.html tools/portal-flow | tar -xf - -C "$W"
python3 "$W/tools/portal-flow/mobile-app-install.py"
