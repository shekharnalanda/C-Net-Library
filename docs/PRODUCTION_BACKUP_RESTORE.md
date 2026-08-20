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

## Runtime tables

Production `.env.example` uses database-backed sessions, cache, and queues. The migration `2026_08_20_006000_create_runtime_support_tables.php` provides the required runtime tables on fresh installs.

If a deployment already has framework runtime tables created outside this repository, confirm their schema before running or rolling back framework-table migrations.

## Release gate

Before production deployment, verify all of the following:

- `composer.lock` is committed and matches `composer.json`.
- CI has explicitly passed on the exact commit being deployed.
- `APP_ENV=production` and `APP_DEBUG=false`.
- `APP_URL` uses HTTPS.
- `SESSION_SECURE_COOKIE=true`.
- Database backup completed and restore procedure is known.
- Private digital resources are included in file backup.
- Writable Laravel storage/cache directories are configured.
- Public storage symlink exists where required by the hosting setup.
- Queue worker/scheduler requirements, if enabled, are configured by the host.
