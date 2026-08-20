# C-Net Library Production Backup & Restore Runbook

## Scope

Back up both the MySQL database and application-managed files. A database-only backup is incomplete because gallery/public uploads and private digital resources are stored on disk.

## Before every deployment

1. Put the application into maintenance mode when the deployment includes schema changes that are not backward-compatible.
2. Create a timestamped MySQL backup before running migrations.
3. Back up `storage/app/private/digital-resources/` and any public upload directories under `storage/app/public/`.
4. Record the currently deployed Git commit SHA and migration status.
5. Verify there is enough free disk space for both the backup and the new release.

## Database backup

Use the hosting control panel backup facility or `mysqldump` with transaction-safe options for InnoDB. The backup must include schema, data, triggers, and routines if any are later introduced.

Keep database credentials outside the repository and do not paste passwords into shell history, tickets, or GitHub Actions logs.

## File backup

Back up at minimum:

- `storage/app/private/digital-resources/`
- `storage/app/public/`
- production `.env` through the hosting provider's secure secret/config backup process

Do not place production `.env` or backup archives in the web root or Git repository.

## Restore drill

A backup is not considered verified until it can be restored to a non-production database and the application can boot against it.

Recommended restore verification:

1. Restore the database into an isolated database/schema.
2. Restore private/public storage into an isolated application copy.
3. Configure a temporary `.env` pointing only to the isolated resources.
4. Run `php artisan migrate:status`.
5. Run `php artisan route:list`.
6. Confirm login, one student profile, one receipt, one attendance record, one physical-library issue, and one private digital resource can be read.
7. Confirm no production email/SMS/WhatsApp provider is enabled during the drill.

## Rollback policy

Prefer application roll-forward migrations. Do not run `migrate:rollback` in production merely to undo a failed release unless the exact migration has been reviewed for data-loss behavior.

Several operational tables retain financial, attendance, seat-allocation, and library history. Treat student, branch, membership, attendance, payment, and audit records as retention-sensitive data.

The runtime-support migration intentionally uses a non-destructive rollback because it may have skipped pre-existing framework runtime tables during `up()`. Do not expect `migrate:rollback` to delete sessions/cache/queue tables created or adopted by that migration.

## Runtime tables

Production `.env.example` uses database-backed sessions, cache, and queues. The migration `2026_08_20_006000_create_runtime_support_tables.php` provides the required runtime tables on fresh installs.

If a deployment already has framework runtime tables created outside this repository, confirm their schema before running framework-table migrations.

## Finance migration preflight

Before applying migrations that add unique transaction-reference indexes, check production data for duplicate non-null references first. A unique-index migration will fail if duplicates already exist.

Run the read-only payroll reconciliation audit before the payroll transaction-reference migration whenever upgrading an existing database:

```text
php artisan payroll:audit-reconciliation
```

The command reports duplicate payroll transaction references and paid payroll rows whose linked cashbook expense is missing or does not match branch, amount, date, category, or transaction reference. It does not alter any data.

Historical salary expenses created manually before payroll-to-cashbook linking was introduced are not auto-linked because matching them by amount/date/payee could attach the wrong ledger entry. Review those records manually and preserve an auditable reconciliation decision.

Apply the same duplicate-reference preflight discipline to payment, expense, payroll and library-charge transaction-reference unique migrations. Do not delete financial rows merely to make an index migration pass; correct or annotate duplicates through an approved accounting process and retain the audit trail.

## Safe existing-database upgrade sequence

For an existing deployment, do not jump straight to `php artisan migrate --force` when the release contains financial unique indexes or reconciliation changes. Use this order:

1. Deploy the new code to a production-like/staging copy of the existing database first.
2. Run `php artisan release:preflight`. The command is schema-aware: it skips checks for tables/columns that do not exist yet instead of issuing invalid SQL, while still reporting incomplete core migration state.
3. Run `php artisan payroll:audit-reconciliation` if the payroll tables are present.
4. Review duplicate non-empty transaction references in payments, expenses, payrolls and library charge payments before their unique indexes are applied.
5. Resolve duplicate financial references through an approved accounting reconciliation process. Do not delete ledger rows just to satisfy an index.
6. Take the verified MySQL and storage backup.
7. Put production into maintenance mode when required by the migration set.
8. Run `php artisan migrate --force`.
9. Run `php artisan migrate:status` and confirm every expected migration is applied exactly once. If an older deployment used renamed migration filenames, reconcile the `migrations` table before rerunning equivalent schema changes.
10. Run `php artisan release:preflight` again against the fully migrated schema.
11. Run `php artisan release:smoke`, `php artisan route:list`, and `php artisan schedule:list` before reopening traffic.

If any migration fails, preserve the database and logs for diagnosis. Prefer a forward repair after review rather than an automatic production rollback.

## Application timezone and cached configuration

Production must set:

```text
APP_TIMEZONE=Asia/Kolkata
```

Membership start/expiry dates, attendance dates, receipt dates, reservation expiry and the scheduled membership lifecycle commands all depend on the Laravel application timezone. The scheduler expires due memberships at 00:01 and activates due scheduled memberships at 00:05 application time, so leaving Laravel on UTC would shift both jobs into the wrong local-time window.

After changing `.env` or deploying config changes, clear and rebuild Laravel's cached configuration before verification:

```text
php artisan optimize:clear
php artisan config:cache
```

Then run:

```text
php artisan release:preflight
php artisan release:smoke
php artisan schedule:list
```

`release:preflight` must report the resolved application timezone as acceptable and must not report production configuration/runtime-table failures. `release:smoke` checks non-destructive post-deployment boot contracts such as DB round-trip, critical route registration, scheduler registration, writable runtime paths and the public storage link. Neither command replaces CI, migration review, backup verification or browser smoke testing.

## HTTPS, reverse proxies and session cookies

Production requires `APP_URL=https://cnetlibrary.mciedu.com`, encrypted database sessions, `SESSION_SECURE_COOKIE=true`, an HttpOnly session cookie, and SameSite `lax` or `strict`.

If BigRock, a CDN, or another upstream terminates TLS before PHP, do not blindly trust every `X-Forwarded-*` header. Configure Laravel/web-server proxy trust only for provider-documented proxy addresses or trusted private network ranges. A wildcard trust-all proxy configuration can let an untrusted client spoof scheme/host information if it can reach the application directly.

After any proxy or TLS change, verify from the public HTTPS origin that generated URLs remain HTTPS, login/session cookies carry the Secure and HttpOnly attributes, redirects do not downgrade to HTTP, and HSTS is present. Keep `SESSION_DOMAIN` blank unless cross-subdomain cookie sharing is explicitly required; widening it unnecessarily increases cookie scope.

## Upload limits and file handling

Application validation currently limits gallery images to 5 MiB and private digital-resource files to 50 MiB. The PHP/web-server request limits must be at least as large as the intended application limits, otherwise PHP may reject requests before Laravel can return a useful validation error.

Check the production PHP configuration for `upload_max_filesize` and `post_max_size`. `post_max_size` must exceed `upload_max_filesize` enough to accommodate multipart form overhead. Gallery uploads are restricted to JPEG, PNG, WebP and GIF raster formats; do not re-enable SVG in public uploads without a dedicated SVG sanitizer. Digital-resource uploads are stored on the private/local disk and are served through authorization-controlled application responses.

## Scheduler and queues

Scheduled membership lifecycle handling depends on Laravel's scheduler. Production must invoke the scheduler every minute. On BigRock or another cron-based host, configure the equivalent of:

```text
* * * * * cd /absolute/path/to/cnet-library && php artisan schedule:run >> /dev/null 2>&1
```

Use the actual PHP binary and absolute application path supplied by the host. Do not assume the CLI PHP version matches the web PHP version; both must satisfy the application's PHP requirement.

The scheduler runs `memberships:expire-due` daily at 00:01 and `memberships:activate-scheduled` daily at 00:05 application time. If cron stops, expired memberships may remain marked active in storage and future renewals remain pending/reserved. Date-aware application queries reduce stale-access risk, but cron is still required to reconcile stored status and release expired seat allocations.

After deployment, verify both commands manually:

```text
php artisan memberships:expire-due
php artisan memberships:activate-scheduled
```

Inspect their expired/activated/skipped counts before relying on cron.

`QUEUE_CONNECTION=database` requires a queue consumer for any queued jobs introduced or enabled. Prefer a supervised long-running `php artisan queue:work` process when the host supports it. If BigRock does not support persistent workers, use a host-supported cron invocation with bounded execution, such as `php artisan queue:work --stop-when-empty --tries=3`, at an appropriate interval. Do not run both unmanaged worker strategies simultaneously without understanding duplicate process behavior.

Operational checks:

- Run `php artisan schedule:list` after deployment and confirm `memberships:expire-due` is registered for 00:01 and `memberships:activate-scheduled` for 00:05 application time.
- Run `php artisan queue:failed` regularly when database queues are enabled.
- Investigate failed jobs before using `queue:retry`; retries must be safe/idempotent.
- Review application logs for `Expired membership cleanup completed`, `Scheduled membership activation completed`, and any seat-conflict warnings.
- Monitor the `jobs` and `failed_jobs` tables for unexpected growth.
- Restart long-running queue workers after every deployment so they load the new application code.

## Release gate

Before production deployment, verify all of the following:

- `composer.lock` is committed and matches `composer.json`.
- CI has explicitly passed on the exact commit being deployed.
- `APP_ENV=production` and `APP_DEBUG=false`.
- `APP_URL` uses HTTPS.
- `APP_TIMEZONE=Asia/Kolkata`.
- `SESSION_DRIVER=database`, `SESSION_ENCRYPT=true`, `SESSION_SECURE_COOKIE=true`, and SameSite is `lax` or `strict`.
- `SESSION_DOMAIN` is left blank unless cross-subdomain cookies are intentionally required.
- If an HTTPS reverse proxy/CDN is used, only documented trusted proxies are configured and HTTPS URL/cookie behavior has been verified externally.
- PHP/web-server upload limits support the intended 5 MiB gallery and 50 MiB digital-resource application limits.
- `CACHE_STORE=database` and `QUEUE_CONNECTION=database` match the intended production runtime design.
- `php artisan optimize:clear` and `php artisan config:cache` have been run after final environment/config changes.
- `php artisan release:preflight` passes on the production-like/staging environment before and after migrations as applicable.
- `php artisan release:smoke` passes after migrations/config-cache refresh and before production traffic is reopened.
- Database backup completed and restore procedure is known.
- Private digital resources are included in file backup.
- Writable Laravel storage/cache directories are configured.
- Public storage symlink exists where required by the hosting setup.
- Finance duplicate-reference preflight has been completed before unique-index migrations.
- `php artisan payroll:audit-reconciliation` has been reviewed for existing production data.
- A one-minute Laravel scheduler cron is configured and verified.
- `php artisan schedule:list` shows membership expiry at 00:01 and scheduled membership activation at 00:05 application time.
- Queue worker or bounded queue cron is configured before enabling queued features.
- `jobs` and `failed_jobs` operational monitoring has an owner/process.
