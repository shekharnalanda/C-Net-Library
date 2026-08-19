# C-Net Library Production Deployment

Target: `https://cnetlibrary.mciedu.com`

## 1. Hosting prerequisites

- PHP 8.2 or newer
- MySQL 8 compatible database
- Composer 2
- Required PHP extensions for Laravel: BCMath, Ctype, cURL, DOM/XML, Fileinfo, JSON, Mbstring, OpenSSL, PDO MySQL, Tokenizer
- HTTPS enabled for `cnetlibrary.mciedu.com`
- Web server document root must point to the Laravel `public/` directory, not the project root

If BigRock cannot change the document root, deploy the Laravel project outside `public_html` and expose only the contents of Laravel's `public/` directory from the subdomain document root. Do not expose `.env`, `vendor/`, `storage/`, database dumps, or application source files publicly.

## 2. Before every production deployment

1. Confirm the intended commit SHA/tag.
2. Confirm `composer.lock` exists in the release commit and `composer validate --no-check-publish` passes.
3. Confirm the main GitHub Actions CI workflow passes for the exact release commit.
4. Put the application in maintenance mode when the change includes migrations or sensitive writes:
   `php artisan down --retry=60`
5. Create a database backup before migrations.
6. Preserve the existing production `.env`; never copy `.env.example` over it.
7. Confirm production has `APP_ENV=production`, `APP_DEBUG=false`, HTTPS `APP_URL`, and the correct database credentials.

Do not deploy a commit without `composer.lock`. CI intentionally fails when the lockfile is missing.

## 3. Generating the Composer lockfile

The repository contains a manual GitHub Actions workflow dedicated to generating the lockfile on a network-enabled GitHub runner.

1. Open GitHub Actions for the repository.
2. Select **Generate Composer Lockfile**.
3. Run the workflow against `main`.
4. Confirm the Composer update/validation step succeeds.
5. Download the `composer-lock` artifact from that workflow run.
6. Review the generated `composer.lock`, especially Laravel/framework and other direct dependency versions.
7. Commit the exact generated `composer.lock` to `main`.
8. Run the main **CI** workflow again and require it to pass before production deployment.

The lock-generation workflow is a maintenance tool only. Production and normal CI must install from the committed lockfile; they must not run `composer update` as a fallback.

## 4. Database backup

Example MySQL command (replace values; do not store passwords in Git):

`mysqldump --single-transaction --routines --triggers --default-character-set=utf8mb4 -h DB_HOST -u DB_USER -p DB_NAME > cnet-library-YYYYMMDD-HHMMSS.sql`

Immediately verify that the dump exists and is non-empty. Keep database backups outside the public web directory.

For a major release, retain at least:
- one backup immediately before deployment
- the latest known-good backup
- periodic off-host backups according to the organization's retention policy

## 5. Deploy application code

From the production project directory:

1. Fetch/check out the intended release.
2. Verify the deployed commit matches the CI-approved commit.
3. Install exact production dependencies:
   `composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction`
4. Run migrations:
   `php artisan migrate --force`
5. Create the public storage link if not already present:
   `php artisan storage:link`
6. Cache production configuration and routes:
   `php artisan optimize`

Do not run `composer update`, `migrate:fresh`, `db:wipe`, or destructive seed commands in production.

## 6. Seed data

`php artisan db:seed --force` should only be run when the release explicitly requires idempotent master/default seed data and the seeders have been reviewed for production safety.

The demo admin credentials from development seed data must never remain valid in production. Create/change production administrator credentials securely after initial setup.

## 7. Permissions

The PHP/web-server user needs write access to:

- `storage/`
- `bootstrap/cache/`

Application source files and `.env` should not be broadly writable by the web process.

## 8. Queue and scheduler

The current application uses `QUEUE_CONNECTION=database` by default. A persistent queue worker is required for queued communication work once providers are connected.

Preferred worker command:
`php artisan queue:work --sleep=3 --tries=3 --max-time=3600`

For Laravel scheduled jobs, configure one cron entry:
`* * * * * cd /path/to/cnet-library && php artisan schedule:run >> /dev/null 2>&1`

If the hosting plan cannot run persistent workers, use an appropriate cron-based queue strategy and document the operational limitation.

## 9. Post-deploy smoke check

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

Also verify `php artisan route:list` and review the Laravel production log for new errors.

## 10. Rollback

If the release fails before irreversible data changes:

1. Put the application in maintenance mode.
2. Restore the previous known-good code release.
3. Run `composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction` for that release.
4. Restore the pre-deploy database backup if the failed release changed schema/data incompatibly.
5. Run `php artisan optimize`.
6. Bring the application up:
   `php artisan up`
7. Repeat the smoke check.

Laravel migrations do not automatically guarantee a safe production rollback. Treat the database backup as the authoritative rollback path for destructive or incompatible schema changes.

## 11. Security release checklist

- `APP_DEBUG=false`
- unique production `APP_KEY`
- HTTPS only
- secure session cookie enabled
- no demo/default admin password
- `.env` inaccessible over HTTP
- DB credentials are least-privilege
- backups are outside web root
- storage uploads cannot execute PHP/scripts
- admin/security permissions reviewed
- audit logging operational
- mail/SMS/WhatsApp credentials stored only in environment/secrets

## 12. CI gate

Before production release, the GitHub Actions **CI** workflow must pass for the exact release commit:

- committed `composer.lock` presence check
- Composer manifest/lockfile validation
- dependency install from `composer.lock`
- Laravel environment/bootstrap
- migrations on a clean SQLite database
- route registration
- PHPUnit feature/integration tests

The CI workflow also supports manual `workflow_dispatch` runs for release verification.

Do not treat an empty or unavailable connector status as a passing build. Confirm the green workflow run in GitHub Actions before deployment.
