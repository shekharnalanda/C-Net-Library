# C-Net Library Production Deployment

Target: `https://cnetlibrary.mciedu.com`

## 1. Hosting prerequisites

- PHP 8.3 recommended (the current cPanel deployment uses `ea-php83`; application minimum remains PHP 8.2)
- MySQL 8 compatible database
- Composer 2, or a production `vendor/` directory built from the committed `composer.lock`
- Required PHP extensions for Laravel/application runtime: BCMath, Ctype, cURL, DOM/XML, Fileinfo, Iconv, JSON, Mbstring, OpenSSL, PDO MySQL, Tokenizer
- HTTPS enabled for `cnetlibrary.mciedu.com`
- Web server document root must point to the Laravel `public/` directory, not the project root

If BigRock cannot change the document root, deploy the Laravel project outside `public_html` and expose only the contents of Laravel's `public/` directory from the subdomain document root. Do not expose `.env`, `vendor/`, `storage/`, database dumps, or application source files publicly.

## 2. Before every production deployment

1. Confirm the intended commit SHA/tag.
2. Confirm `composer.lock` exists in the release commit and `composer validate --no-check-publish` passes.
3. Confirm the main GitHub Actions CI workflow passes for the exact release commit.
4. Create a database backup before migrations.
5. Preserve the existing production `.env`; never copy `.env.example` over it.
6. Confirm production has `APP_ENV=production`, `APP_DEBUG=false`, HTTPS `APP_URL`, and the correct database credentials.
7. Confirm `vendor/autoload.php` exists and `vendor/` was generated from the current committed `composer.lock`.

The cPanel deployment intentionally fails early when `composer.lock` or `vendor/autoload.php` is missing. This prevents migrations from starting with an incomplete runtime.

Do not deploy a commit without `composer.lock`. CI intentionally fails when the lockfile is missing.

## 3. Production dependencies

Production must run the exact dependency graph recorded in the committed `composer.lock`. Never use `composer update` on the production server.

Preferred method when Composer is available on the host:

`composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction`

If Composer is unavailable on the hosting account, use the manual GitHub Actions workflow **Build Production Vendor**. It builds PHP 8.3 production dependencies from the committed lockfile and uploads a `production-vendor` artifact. Extract that artifact into the project so that `vendor/autoload.php` exists before using cPanel **Deploy HEAD Commit**.

Do not commit `vendor/` or `production-vendor.zip` to Git. The repository `.gitignore` intentionally excludes `vendor/`.

Whenever `composer.json` or `composer.lock` changes, rebuild/reinstall production dependencies before deploying the corresponding application commit.

## 4. Database backup

Example MySQL command (replace values; do not store passwords in Git):

`mysqldump --single-transaction --routines --triggers --default-character-set=utf8mb4 -h DB_HOST -u DB_USER -p DB_NAME > cnet-library-YYYYMMDD-HHMMSS.sql`

Immediately verify that the dump exists and is non-empty. Keep database backups outside the public web directory.

For a major release, retain at least:
- one backup immediately before deployment
- the latest known-good backup
- periodic off-host backups according to the organization's retention policy

## 5. cPanel deployment order

The repository `.cpanel.yml` performs these steps:

1. Create required Laravel runtime directories.
2. Refuse deployment if `composer.lock` or `vendor/autoload.php` is missing.
3. Clear cached configuration.
4. Run `php artisan migrate --force`.
5. Run `php artisan release:preflight`.
6. Ensure the public storage link exists when the host supports symlinks.
7. Run `php artisan optimize`.
8. Run `php artisan release:smoke`.

Automatic `db:seed --force` is deliberately not part of routine production deployment. Production CMS/settings/master data must not be silently reset by a code deployment.

For higher-risk schema changes, enable maintenance mode and take a verified backup before clicking Deploy HEAD Commit. cPanel's deployment task itself does not create a database backup.

## 6. Seed data

`php artisan db:seed --force` should only be run when the release explicitly requires reviewed master/default seed data. It is not a routine deployment step.

Review every seeder before production use. Seeders that use `updateOrCreate` can overwrite administrator-customized values when their update payload contains defaults.

Production administrator credentials must come from secure production configuration. Never introduce demo/default passwords.

## 7. Permissions

The PHP/web-server user needs write access to:

- `storage/`
- `bootstrap/cache/`

Application source files and `.env` should not be broadly writable by the web process.

## 8. Queue and scheduler

The application uses `QUEUE_CONNECTION=database`. A persistent queue worker is preferred for queued communication work once providers are connected.

Preferred worker command:
`php artisan queue:work --sleep=3 --tries=3 --max-time=3600`

For Laravel scheduled jobs, configure one cron entry:
`* * * * * cd /home4/mcied45x/repositories/C-Net-Library && /usr/local/bin/ea-php83 artisan schedule:run >> /dev/null 2>&1`

If the hosting plan cannot run persistent workers, use an appropriate cron-based queue strategy and document the operational limitation.

## 9. Release gates

Before production release, require all of the following:

- GitHub Actions CI is green for the exact release commit.
- `composer.lock` is committed and validated.
- Production `vendor/` matches that lockfile.
- Database backup exists for releases containing schema/data changes.
- `php artisan release:preflight` passes on production.
- cPanel deployment finishes with `php artisan release:smoke` passing.

`release:preflight` checks production environment/security configuration, required PHP/runtime conditions, database/runtime tables, transaction-reference integrity and payroll/cashbook reconciliation. `release:smoke` checks the application key, writable paths, storage link, database round-trip, critical routes and scheduler registration.

## 10. Browser smoke check

Verify over HTTPS:

- `/` public homepage
- `/login`
- `/admission`
- `/enquiry`
- `/jobs`
- `/digital-library`
- admin login and `/admin/dashboard`
- student login and `/student/dashboard` with a test student
- fee receipt rendering
- QR student ID rendering
- admin QR attendance scanner
- gallery/public storage image loading

Review the Laravel production log for new errors after the deployment.

## 11. Rollback

If the release fails before irreversible data changes:

1. Put the application in maintenance mode when possible.
2. Restore the previous known-good code release.
3. Install/re-extract the production dependencies matching that release's `composer.lock`.
4. Restore the pre-deploy database backup if the failed release changed schema/data incompatibly.
5. Run `php artisan optimize`.
6. Bring the application up with `php artisan up` if maintenance mode was enabled.
7. Repeat preflight/smoke/browser checks.

Laravel migrations do not automatically guarantee a safe production rollback. Treat the database backup as the authoritative rollback path for destructive or incompatible schema changes.

## 12. Security release checklist

- `APP_DEBUG=false`
- unique production `APP_KEY`
- HTTPS only
- secure, encrypted, HttpOnly session cookie
- no demo/default admin password
- `.env` inaccessible over HTTP
- DB credentials are least-privilege
- backups are outside web root
- storage uploads cannot execute PHP/scripts
- admin/security permissions reviewed
- audit logging operational
- mail/SMS/WhatsApp credentials stored only in environment/secrets

## 13. CI gate

The GitHub Actions **CI** workflow targets PHP 8.3 to match the current cPanel runtime. It must pass for the exact release commit and checks:

- committed `composer.lock` presence
- Composer manifest/lockfile validation
- dependency installation from `composer.lock`
- clean Laravel bootstrap
- migrations on SQLite
- route and scheduler registration
- release smoke checks
- PHPUnit feature/integration tests

Do not treat an unavailable workflow status as a passing build. Confirm the green workflow run in GitHub Actions before production deployment.
