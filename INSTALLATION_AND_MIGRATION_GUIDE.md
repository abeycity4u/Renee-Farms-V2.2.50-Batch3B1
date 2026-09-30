# Renee AgriSuite installation and migration guide

This guide covers two different deployment paths:

1. **Fresh setup** — a brand-new Renee AgriSuite installation using an empty database.
2. **Existing-system migration** — moving an existing Renee AgriSuite production environment to another host without losing tenant/business data.

These two paths are intentionally different. **Do not run the fresh-install bootstrap against an existing production database.**

Current deployment helpers are designed around a cPanel-style account where the application root defaults to `$HOME/public_html`, private workers default to `$HOME/renee-private`, and QA/runtime worker logs default to `$HOME/renee-qa-logs`.

---

## 1. Source authority

Use the intended certified release branch or tag from this repository.

For the current certified v3.2.0 portability baseline, the branch is:

```text
v320-renee-agrisuite-branding
```

Keep the Git working copy separate from the live web root when possible. A typical layout is:

```text
$HOME/renee-deploy     # Git/source working copy
$HOME/public_html      # live application
$HOME/renee-private    # private worker copies
$HOME/renee-qa-logs    # worker / deployment logs
```

Do not commit production secrets, production `.htaccess`, uploaded business files, or runtime logs to GitHub.

---

# Part A — Fresh setup

## 2. Fresh-install prerequisites

Before running the application bootstrap:

- create the hosting account / document root
- deploy the Renee AgriSuite source for the certified release
- create a **brand-new empty MySQL/MariaDB database**
- create a database user and grant it the required privileges on that database
- configure HTTPS/domain routing
- create the real production `.htaccess` from `.htaccess.example`
- replace every `xxxxxx` placeholder with the correct production value
- confirm PHP CLI is available for bootstrap, migrations, and workers

The real `.htaccess` must remain server-only and must not be committed.

If the deployment uses a domain different from the canonical domain shown in `.htaccess.example`, update the rewrite host and related public URL values before going live.

## 3. Configure the production environment

From the application root:

```bash
cd "$HOME/public_html" || exit 1
cp .htaccess.example .htaccess
```

Edit `.htaccess` and configure the real values for:

- `DB_HOST`
- `DB_USER`
- `DB_PASS`
- `DB_NAME`
- `APP_ENV`
- billing/public URL values
- payment provider keys/hashes
- SMTP host/user/password
- default sender/reply-to values
- mail enabled state

Do not paste production secrets into Git commits, deployment notes, chat transcripts, or shell commands that would expose them unnecessarily.

## 4. Confirm the database is empty

The fresh-install bootstrap has a hard empty-database gate. It will refuse to continue if any base tables already exist.

Do **not** manually import `database_schema.sql` first. The bootstrap imports it itself.

## 5. Run the fresh-install bootstrap

From the application root:

```bash
cd "$HOME/public_html" || exit 1
php scripts/bootstrap_fresh_install.php
```

Expected success markers:

```text
Fresh-install empty database gate passed.
Imported database_schema.sql: 69 tables.
Imported database_seed.sql: 6 roles, 71 global permissions.
Registered 92 baseline migration filenames.
No tenant farms or users seeded.
Fresh-install bootstrap complete.
```

The fresh bootstrap uses:

- `database_schema.sql` — current structural baseline
- `database_seed.sql` — system-reference data only
- `database_baseline_migrations.txt` — historical migrations already represented by the baseline

It does not seed production farms, users, passwords, payments, stock balances, or tenant business data.

## 6. Run the normal migration runner

After the fresh bootstrap succeeds:

```bash
cd "$HOME/public_html" || exit 1
php scripts/run_migrations.php
```

At the current baseline, the 92 historical migrations registered by the bootstrap are skipped. Any future migration added after the baseline is applied normally.

## 7. Install private workers

Run the installer from the deployed source tree that contains the `deployment/` directory:

```bash
cd "$HOME/public_html" || exit 1
bash deployment/install_private_workers.sh
```

By default this installs the private worker sources under:

```text
$HOME/renee-private/v310-credential-worker
$HOME/renee-private/v320-subscription-lifecycle
$HOME/renee-private/v320-subscription-reminder
```

and creates/uses:

```text
$HOME/renee-qa-logs
```

Runtime lock/log files such as `production-launcher.lock`, `lifecycle-worker.lock`, `reminder-worker.lock`, and the credential `worker.log` are created by the worker launchers when they actually run. They do not need to be created manually.

## 8. Generate cron definitions

Run:

```bash
cd "$HOME/public_html" || exit 1
bash deployment/show_cron_jobs.sh
```

Review the generated paths and then add the printed lines to cPanel **Cron Jobs**.

The generator prints the cron definitions; it does **not** install them automatically.

The current worker schedule generated by the repository is:

- credential outbox worker — every 5 minutes
- subscription lifecycle worker — hourly at minute 17
- subscription renewal reminder worker — daily at 04:15

## 9. Fresh-install verification

Before opening the system for normal use, verify:

- HTTPS redirects correctly
- the production `.htaccess` contains the intended real environment values
- the application connects to the new database
- login/onboarding pages load
- the database contains no unexpected tenant farms/users immediately after bootstrap
- `php scripts/run_migrations.php` completes successfully
- private workers exist under `$HOME/renee-private`
- cron entries point to the private worker launchers
- worker logs can be written under `$HOME/renee-qa-logs`
- SMTP sender/authentication is accepted by the provider
- payment provider environment values are configured for the intended mode

---

# Part B — Migrating an existing production installation

## 10. Important rule for an existing system

An existing Renee AgriSuite installation already contains business state.

**Do not run:**

```bash
php scripts/bootstrap_fresh_install.php
```

against that database.

The bootstrap is only for a brand-new empty database and intentionally refuses databases that already contain base tables.

For an existing system, preserve the production database and uploads, restore them on the new host, then run only the normal migration runner for migrations not already recorded in `schema_migrations`.

## 11. Before migration: take authoritative backups

Before moving hosts, take current backups of:

- the full production database
- user/business uploads
- the production `.htaccess` or a secure record of all environment values
- any other server-only files not stored in GitHub

Do not rely on an old pre-migration SQL dump as the only production backup.

Store backups outside the public web root and protect them appropriately.

## 12. Prepare the new host

On the destination host:

- deploy the intended Renee AgriSuite release source
- create the destination database and database user
- configure the domain/HTTPS certificate
- create the production `.htaccess`
- configure current DB, SMTP, billing, and payment environment values
- keep real secrets outside GitHub

If the hostname/database credentials change, update the environment values for the new host rather than copying unusable host-specific credentials blindly.

## 13. Restore the production database

Restore the complete production SQL backup into the new destination database.

For a plain SQL file, a typical interactive restore is:

```bash
mysql -u YOUR_DB_USER -p YOUR_DB_NAME < production-backup.sql
```

For a gzip-compressed SQL file:

```bash
gzip -dc production-backup.sql.gz | mysql -u YOUR_DB_USER -p YOUR_DB_NAME
```

Use `-p` interactively rather than putting the database password directly on the shell command line.

After restore, confirm the database contains the expected production tables and tenant data before proceeding.

## 14. Restore uploads/business files

Restore the production upload directory separately.

GitHub contains only the source-controlled empty upload placeholder; real business uploads are intentionally not version-controlled.

Preserve the expected directory structure and permissions.

## 15. Run only pending migrations

After the existing production database has been restored and the new `.htaccess` points the application at it:

```bash
cd "$HOME/public_html" || exit 1
php scripts/run_migrations.php
```

The migration runner reads `schema_migrations`, skips migrations already applied, and applies only migrations that are not yet recorded, subject to its built-in recovery/safety handling.

Do not manually mark migrations as complete unless there is a specific audited recovery reason.

## 16. Reinstall private workers on the new host

Private workers are host-specific deployed copies and should be regenerated from repository source:

```bash
cd "$HOME/public_html" || exit 1
bash deployment/install_private_workers.sh
```

Do not manually copy old runtime lock files or old worker logs to the new host.

## 17. Recreate cron jobs

Generate the destination-host cron definitions:

```bash
cd "$HOME/public_html" || exit 1
bash deployment/show_cron_jobs.sh
```

Review the output and add those entries to cPanel Cron Jobs on the new host.

Do not assume cron jobs migrate automatically with the website files.

## 18. Existing-system migration verification

Before switching traffic permanently, verify:

- production database row/data presence is correct
- farms/users/roles/permissions are present as expected
- uploads open correctly
- login and tenant isolation work
- production cycles and livestock/population data are intact
- inventory, sales, expenses, profitability, and financial allocation screens load
- billing state and payment references remain intact
- SMTP configuration is accepted
- credential, lifecycle, and renewal-reminder workers run from the private directory
- cron entries use destination-host paths
- HTTPS and security headers are active
- no old host-specific secret or path remains where it should have been changed

Only after these checks should DNS/traffic be treated as fully migrated.

---

# Part C — Upgrade/deployment notes

## 19. Targeted runtime deploy helper

`deployment/deploy_runtime_files.sh` is a **targeted runtime deployment helper**, not a full fresh-install copier.

Its important rules include:

- deployment is targeted to explicitly named files
- apply mode requires an explicit base ref
- `config.php` and root `.htaccess` are protected
- `scripts/`, `migrations/`, `deployment/`, tests, and development artifacts are rejected from targeted live copying
- unrelated/live-only files are not deleted

Use it for controlled updates to an already-deployed environment, not as a substitute for preparing an initial full application tree.

## 20. Files that must remain server-only

Never commit real values from:

```text
.htaccess
SMTP credentials
payment secret keys / webhook hashes
database passwords
session/cookie files
private runtime logs
user uploads
runtime worker lock files
```

`.htaccess.example` is the safe source template and intentionally contains placeholders instead of production secrets.

## 21. Fresh setup vs migration decision

Use this rule:

```text
Brand-new empty environment
    -> fresh bootstrap
    -> normal migration runner

Existing Renee AgriSuite business environment
    -> restore full production DB + uploads
    -> DO NOT run fresh bootstrap
    -> normal migration runner only
```

For database-specific baseline details, also see:

```text
deployment/FRESH_INSTALL_DATABASE.md
```
