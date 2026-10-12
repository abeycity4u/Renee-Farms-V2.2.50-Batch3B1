# C137 GC24 K2-P03 B12 — R2 Synthetic Schema Evidence: PASS

**2026-10-12. Exact one-use GitHub-hosted synthetic MariaDB execution SUCCEEDED. This is NOT B12 lost-update/concurrency certification.**

## Exact source, run and safeguards

- Repository: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- Isolated R2 branch: `c137-gc24-k2p03-b12-schema-evidence-r2-prepared`.
- Prepared source HEAD: `37eb472964acb4284d432db15789165f090014b4`.
- One-file authorization/execution commit: `860a942d9b88c7fd743d502eea9a6cfa3524e62e`.
- Workflow: `.github/workflows/k2p03-b12-schema-evidence-r2.yml` (source blob `1def3696f5d29dd08025dc6677d3f4a57ed37a55`).
- Test: `tests/integration/k2p03_b12_schema_evidence_r2_synthetic.sh` (source blob `7590ee638e9a1f89b32ab50a248d318c4c304281`).
- Read-only inventory: `docs/C137_GC24_K2_P03_B12_READONLY_SCHEMA_INVENTORY.sql` (blob `6dd5614edb2dae2432d9e8628b43ba53177e7133`).
- Fresh-install schema: `database_schema.sql` (blob `e77f287d62c5812a3d55dc23b5b20f5d167db389`).
- [GitHub Actions run 38301126240](https://github.com/abeycity4u/Renee-Farms-V2.2.50-Batch3B1/actions/runs/38301126240), job `114984037585`; **attempt 1; completed; conclusion SUCCESS**.
- GitHub-hosted disposable MariaDB `mariadb:10.11` service on `127.0.0.1:3306`, database `renee_b12_schema_synthetic_r2`, nonsecret disposable CI credentials. Synthetic farms 91001/91002 and six distinct synthetic production cycles.
- No cPanel/VPS, AWS Nodes A/B/C, production, staging, protected fixture, real customer data, original B12 CI rerun, migration against protected DB, or deployment.

## Evidence from completed job log

All workflow steps, including the one-use approval guard, MariaDB service startup, branch/source-pin checkout, database client install, synthetic test, and container cleanup, **passed**.

```text
B12_R2_NEW_ONE_USE_APPROVAL_GUARD=PASS
PROTECTED_DATABASE_CONNECTIONS=NONE
B12_SCHEMA_EVIDENCE_R2_MODE=DISPOSABLE_MARIADB_SYNTHETIC_ONLY
PASS=DB_NAME | value=renee_b12_schema_synthetic_r2
PASS=FRESH_SCHEMA_TABLES | value=0
PINNED_SCHEMA_BLOB=PASS
FRESH_SCHEMA_IMPORT=PASS
PASS=THREE_DAILY_TABLES_EXIST | value=3
PASS=RECORD_REVISION_COLUMNS_ABSENT | value=0
PASS=THREE_SOURCE_CYCLE_FOREIGN_KEYS_SET_NULL | value=3
PASS=THREE_EXPECTED_NULLABLE_CYCLE_KEYS | value=3
SYNTHETIC_FIXTURES_INSERTED=YES
PASS=FIVE_SYNTHETIC_ROWS_layer_daily_records | value=5
PASS=NULL_CYCLE_REPEATED_layer_daily_records | value=2
PASS=FIVE_SYNTHETIC_ROWS_broiler_daily_records | value=5
PASS=NULL_CYCLE_REPEATED_broiler_daily_records | value=2
PASS=FIVE_SYNTHETIC_ROWS_ruminant_daily_records | value=5
PASS=NULL_CYCLE_REPEATED_ruminant_daily_records | value=2
SOURCE_READONLY_INVENTORY_EXECUTED=PASS
PASS=INVENTORY_07_NULL_CYCLE_DUPLICATES_layer | value=1,1
PASS=INVENTORY_08_UNSCOPED_UPDATE_AMBIGUITY_layer | value=1,3
PASS=INVENTORY_09_CYCLE_TENANT_INTEGRITY_layer | value=0,1
PASS=INVENTORY_07_NULL_CYCLE_DUPLICATES_broiler | value=1,1
PASS=INVENTORY_08_UNSCOPED_UPDATE_AMBIGUITY_broiler | value=1,3
PASS=INVENTORY_09_CYCLE_TENANT_INTEGRITY_broiler | value=0,1
PASS=INVENTORY_07_NULL_CYCLE_DUPLICATES_ruminant | value=1,1
PASS=INVENTORY_08_UNSCOPED_UPDATE_AMBIGUITY_ruminant | value=1,3
PASS=INVENTORY_09_CYCLE_TENANT_INTEGRITY_ruminant | value=0,1
SCHEMA_RISK_EXPECTATIONS=PASS
PASS=CYCLE_SET_NULL_CREATES_EXTRA_LAYER_LEGACY_COLLISION | value=3
SYNTHETIC_SET_NULL_COLLISION=PASS
SOURCE_SCHEMA_RISKS=CONFIRMED_ON_SYNTHETIC_FIXTURES
LIVE_LEGACY_DUPLICATE_COUNTS=UNKNOWN
MIGRATIONS_APPLIED_TO_PROTECTED_DATABASES=NONE
B12_CONCURRENCY_RERUN=NONE
```

Aggregate test figures for each of Layer, Broiler and Ruminant Daily Records:

| Synthetic inventory field | Expected and observed |
|---|---|
| Duplicate `cycle_id IS NULL` logical-key groups | 1 |
| Excess rows in duplicate NULL-cycle group | 1 |
| All-cycle farm/date(/species) ambiguity groups | 1 |
| Extra rows matching ambiguous group | 3 |
| Orphan cycle links | 0 |
| Cross-tenant source/cycle links | 1 |

The extra cross-tenant link in this fixture is synthetic proof that a plain `cycle_id` foreign key does **not** enforce agreement between source `farm_id` and cycle `farm_id`. Actual records were not queried.

A synthetic Layer cycle deletion caused `cycle_id` of that record to become NULL, increasing synthetic same-farm/date NULL-cycle records from 2 to 3. This confirms that a future uniqueness design must address **cycle delete behavior** as well as duplicate legacy rows.

## What this PASS DOES NOT certify

- No claim about duplicates, wrong-tenant links, triggers, or actual deployed schema in protected/live databases; real data counts remain UNKNOWN.
- No claim of implementation of `record_revision` columns, client-loaded revisions, row-id-scoped UPDATEs or CAS deletion flows.
- No B12 stale-overwrite concurrency certification; original B12 workflow run `38233373699` remains FAILED (`TWO_STALE_COMMITS`; attempt 1).
- The original R1 synthetic run `38299970527` remains FAILED due to Bash `section_` label expansion; R2 is **a separate, newly authorized run**, not a rerun or retroactive R1 PASS.
- No authorization to mutate protected databases, deduplicate actual data, deploy, run migrations `092`, or proceed to Stage D.

## Next development gate (source-only unless newly authorized)

1. Design the shared immutable row-id + client-loaded revision CAS contract across all seven active Daily Record writers and both Ruminant deletion callers; keep tenant/permission/CSRF and feed/stock/population/Layer egg-sale reversals correct.
2. Define a cycle nullable legacy identity strategy accounting for real data, legitimate same-day multi-cycle records, Ruminant species case-normalization, and `ON DELETE SET NULL`. Do not infer that a single unique index solves the migration.
3. Add/finalize guarded `record_revision` schema/migration draft **without executing against protected DB**, after the actual legacy data audit is separately authorized.
4. Use exact-head source guards, focused verifiers and remote GitHub semantic diff. Do not rerun closed R2 or original B12 without a new explicit authorization.

**D12:** 15 import gates BLOCKED; Stage D UNAUTHORIZED; 150 VU HOLD; 200 VU PROHIBITED. Protected system guard remains in force.
