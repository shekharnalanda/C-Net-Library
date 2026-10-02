# Library mobile app installation

The previous home-page control downloaded the existing Android APK into the browser's downloads. That does not invoke the Android package installer. Its signing, compatibility and on-device installation have not been verified; there is no Android build project or signing key in this repository.

The replacement primary installer is an installable browser app (PWA). Its manifest uses the Library's existing branding, correctly sized PNG icons and `/student-login` as the start URL. Chrome's install event is invoked from a user tap and consumed once. When no event is available, the control expands browser menu instructions; iPhone/iPad users use Safari → Share → Add to Home Screen. Browser eligibility decides when a native prompt is shown.

The root service worker stores only a generic offline information page. It never stores student records, ID cards, admissions, seat availability, fee reports or test responses. Same-origin GET navigations use the live network; offline navigation returns the generic page. POST submissions, API requests and the separate Test Series portal are not intercepted. Authentication and the existing single-session lease continue to run on the server.

## Deployment

Fetch the reviewed branch `codex/library-admission-single-login-20261002`, extract the pinned `tools/portal-flow/mobile-app-fix.sh`, and run it with that exact 40-character commit pin. This is a Library-only file deployment; no database changes or enrolment resets are performed. The launcher checks account and repository identity. The Python 3.6 compatible installer checks reviewed file hashes, PHP/GD and existing icon, saves private file and route-cache backups, uses a short maintenance window, refreshes routes/views and verifies the public app resources. On failure it restores the previous files and route cache and brings the site back up. Unknown live edits stop before replacement.

The private report also checks the existing APK's ZIP CRC, manifest/DEX entries and signing-file/block markers. These checks do not prove signature validity or Android compatibility. The APK is preserved and no new unsigned APK is distributed.

Public HTTP checks identify themselves as `MCI-Portal-Check/1.0`, the same client accepted by the previous hosted diagnostics, send a content-specific Accept header and request canonical URLs without probe query parameters. Home and admission HTTP preflights run before maintenance or file replacement. Any rejected response still blocks deployment; the failed path and HTTP status are saved in the private log. Server security rules remain enabled. The first mobile deployment was rolled back after a HTTP 406 on the former default urllib request; the precise hosting rule has not been identified.

## Verification

Automated tests cover manifest and PNG dimensions, no-APK installer rendering, admin/POST exclusion, one-use prompt and dismissal, manual instructions, installed state, offline privacy, cache isolation, repeat installation, unknown live edits and rollback with/without a route cache. A real Android/iPhone installation and subsequent student login still need live phone confirmation after deployment.
