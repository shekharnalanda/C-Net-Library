#!/bin/bash
set -euo pipefail
umask 077
test "$(id -un)" = mcied45x || { echo 'STOPPED: Use mcied45x cPanel Terminal.'; exit 1; }
for B in git python3 tar mktemp timeout crontab; do
  command -v "$B" >/dev/null || { echo "STOPPED: $B unavailable."; exit 1; }
done
test -x /usr/local/bin/ea-php83
python3 -c 'import sys; sys.exit(0 if sys.version_info >= (3,6) else 1)'
L='/home4/mcied45x/repositories/C-Net-Library'
T='/home4/mcied45x/repositories/MCI-Test-Series'
LS='05e3a72c727fb62de6ab59c2ea36e8c2ebcc7b5b'
TS='2c7ed63a469add923e9e5cb8a0854638f9cc20dd'
BRANCH='codex/library-admission-single-login-20261002'
check_repo() {
  case "$(git -C "$1" remote get-url origin)" in
    "git@github.com:shekharnalanda/$2.git"|"https://github.com/shekharnalanda/$2.git"|"https://github.com/shekharnalanda/$2"|"ssh://git@github.com/shekharnalanda/$2.git") ;;
    *) echo "STOPPED: Repository mismatch: $2"; exit 1 ;;
  esac
}
check_repo "$L" 'C-Net-Library'
check_repo "$T" 'MCI-Test-Series'
W=$(mktemp -d '/home4/mcied45x/cnet-library-portal.XXXXXX')
mkdir "$W/library" "$W/tests"
for SITE in library tests; do
  if [ "$SITE" = library ]; then R="$L"; S="$LS"; else R="$T"; S="$TS"; fi
  if ! git -C "$R" cat-file -e "$S^{commit}" 2>/dev/null; then
    if ! GIT_TERMINAL_PROMPT=0 timeout 180 git -C "$R" -c core.sshCommand='ssh -o BatchMode=yes' fetch --no-tags origin "$BRANCH" >"$W/$SITE-fetch.log" 2>&1; then
      echo "STOPPED: Download failed. Private log: $W/$SITE-fetch.log"; exit 1
    fi
  fi
  git -C "$R" cat-file -e "$S^{commit}"
done
git -C "$L" archive "$LS" app bootstrap config resources routes database tools/portal-flow | tar -xf - -C "$W/library"
git -C "$T" archive "$TS" app bootstrap config resources routes | tar -xf - -C "$W/tests"
python3 "$W/library/tools/portal-flow/install.py" "$W/tests"
