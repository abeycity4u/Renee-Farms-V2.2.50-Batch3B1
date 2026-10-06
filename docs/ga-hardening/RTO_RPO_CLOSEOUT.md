# Renee AgriSuite v3.2 — RTO / RPO Operational Closeout

Closeout date: 2026-10-05

This document records the recovery objectives and runtime evidence established
during Renee AgriSuite v3.2 GA hardening. It distinguishes measured technical
restore capability from broader business/service recovery time.

## Recovery Time Objective evidence

A clean, isolated restore drill was executed against a pre-provisioned DR
target using the production backup set and the repository DR verifier.

Measured technical restore result:

- measured technical RTO: **3.449 seconds**;
- rounded reported technical RTO: **4 seconds**;
- restore class: **PREPROVISIONED_ISOLATED_TARGET**;
- restored table count: **69**;
- database restore: **PASS**;
- filesystem restore: **PASS**;
- post-restore verifier: **PASS**;
- source/staging/production isolation guards: **PASS**;
- final restore failure count: **0**.

The measured figure is a technical restore measurement for an already
provisioned isolated target. It is not a promise that a complete business
service recovery will always finish in four seconds. A business/service RTO
must also account for incident decision time, infrastructure provisioning,
operator response, DNS/network changes and any external-provider recovery.

## Recovery Point Objective

Approved production RPO:

**RPO <= 6 hours**

Production backup cadence:

- full production backup every six hours;
- scheduled at minute 43 of hours 00, 06, 12 and 18 in the server cron
  timezone;
- production database guard requires `renee_testdb`;
- filesystem and database backup artifacts are checksummed;
- each restore point includes `database.sql.gz`, `files.tgz`,
  `SHA256SUMS` and `manifest.txt`.

## Off-host backup

Production restore points are copied to private Backblaze B2 storage.

Operational properties:

- bucket access is private;
- server-side encryption is enabled;
- backup application key is restricted to the backup bucket/prefix;
- remote production prefix is `production/`;
- credentials remain outside the repository.

No credential value is recorded in this document.

## Retention policy

The production retention engine preserves the combined keep set of:

- newest **8** six-hour restore points;
- newest restore point from each of the newest **7 UTC days**;
- newest restore point from each of the newest **4 ISO weeks**.

Remote deletion must succeed before the matching local restore point may be
removed.

The retention engine has been executed successfully in production with zero
eligible deletion candidates. An actual aged restore-point deletion has not
yet occurred because the available restore points had not aged beyond the
retention keep set at certification time.

## Backup freshness monitoring

Production backup freshness monitoring is active.

Runtime policy:

- freshness threshold: **6 hours**;
- monitor schedule: minutes **9, 24, 39 and 54** each hour;
- monitor validates restore-point structure, manifest contract and SHA-256
  checksums;
- newer invalid restore-point directories are counted and skipped while the
  newest valid successful restore point remains authoritative;
- stale transition generates an alert;
- recovery transition generates a recovery alert;
- monitor state is persisted only after successful alert delivery when an
  alert is required.

The freshness schedule intentionally avoids the existing availability monitor
schedule.

## Runtime functional certification

Canonical deployed backup workers were executed after source-to-runtime
integrity validation.

Certified restore point:

`production-20261005T223412Z`

Results:

- production database: `renee_testdb`;
- local restore point finalized: **PASS**;
- off-host upload: **PASS**;
- backup cycle: **PASS**;
- retention execution: **PASS**;
- restore files present: **PASS**;
- SHA-256 verification: **PASS**;
- manifest verification: **PASS**;
- freshness monitor execution: **PASS**;
- freshness state: **OK**;
- freshness age at certification: **0.003 hours**;
- integrity: **PASS**;
- invalid newer restore points: **0**;
- alert required: **NONE**.

## Production automation state

The live production crontab contains exactly one backup-cycle job and exactly
one backup-freshness job inside the Renee v3.2 production RPO block.

Production backup:

`43 0,6,12,18 * * *`

Backup freshness:

`9,24,39,54 * * * *`

The pre-existing production/staging availability monitor remains installed
and unchanged.

At closeout:

**RPO_AUTOMATION_ACTIVE=YES**

## Residual conditions

This recovery closeout does not establish or claim:

- a maximum-capacity breaking point for the application;
- a genuinely independent third-party penetration test;
- a four-second end-to-end business/service recovery guarantee;
- observation of an aged retention candidate being deleted in production.

Those items must not be inferred from the RTO/RPO certification above.
