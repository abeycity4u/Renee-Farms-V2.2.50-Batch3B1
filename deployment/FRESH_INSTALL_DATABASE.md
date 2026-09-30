# Renee AgriSuite fresh-install database

This document defines the fresh-install database contract for a new host.

## Fresh installation

Use a brand-new empty database.

The fresh-install database sources are:

- `database_schema.sql`
  - current structural baseline
  - 69 base tables
  - no production rows

- `database_seed.sql`
  - system-reference data only
  - current Renee AgriSuite roles
  - current `farm_id = 0` global permission defaults
  - no production farms, users, credentials, payments, stock balances, or tenant state

- `database_baseline_migrations.txt`
  - exact historical migration filenames already represented by the current baseline

- `scripts/bootstrap_fresh_install.php`
  - guarded fresh-install bootstrap

The bootstrap sequence is:

1. Verify the target database contains zero base tables.
2. Import `database_schema.sql`.
3. Verify the baseline contains 69 tables.
4. Import `database_seed.sql`.
5. Verify 6 system roles and 71 global permission defaults.
6. Record all filenames from `database_baseline_migrations.txt` into `schema_migrations`.
7. Verify no production farm or user rows were seeded.
8. Run the normal migration runner afterward.

## Migration behavior

Historical migrations remain source-controlled under `migrations/`.

They remain the upgrade path for existing installations.

A fresh installation does not replay historical migrations already represented by the current baseline. Their filenames are registered in `schema_migrations` by the fresh-install bootstrap.

Future migrations added after the baseline are not present in the baseline manifest and therefore run normally through `scripts/run_migrations.php`.

## Existing databases

Do not run `scripts/bootstrap_fresh_install.php` against an existing Renee AgriSuite database.

The bootstrap refuses to proceed when the target database already contains base tables.

Existing installations continue to use the normal migration path.

## Host migration order

For a new host:

1. create an empty database and database user
2. configure host environment values from `.htaccess.example`
3. run the fresh-install bootstrap
4. run `scripts/run_migrations.php`
5. install private workers with `deployment/install_private_workers.sh`
6. review cron entries with `deployment/show_cron_jobs.sh`
7. restore user uploads separately if migrating an existing business environment

Production `.htaccess`, credentials, payment keys, SMTP passwords, uploaded business files, and runtime logs are not stored in GitHub.
