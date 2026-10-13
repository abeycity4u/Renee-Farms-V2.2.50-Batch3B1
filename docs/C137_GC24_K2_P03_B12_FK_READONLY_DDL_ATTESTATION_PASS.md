# C137 GC24 K2-P03 B12 — SELECT-only foreign-key DDL attestation verified

**2026-10-13. Status: isolated synthetic MariaDB verification PASS. This does not certify tenant data, schema migration, B12 stale-edit prevention, or load capacity.**

## Source and immutable execution evidence

- Source branch: `c137-gc24-k2p03-b12-fk-readonly-ddl-attestation`.
- Revision of guarded audit runner: `31d5258213df5399f23a7acf02990b38193354b2`.
- Verified runner blob: `6ad60d311b98f85141d259142534fd1404907005`.
- Dispatched independent one-use disposable test branch: `c137-gc24-k2p03-b12-fk-ddl-attestation-ci`.
- Authorization HEAD: `6c54e1143eae078725a6726411a36d020fbc47fa`.
- [GitHub Actions run 38454255713](https://github.com/abeycity4u/Renee-Farms-V2.2.50-Batch3B1/actions/runs/38454255713), job `115492775320`, **attempt 1, completed SUCCESS**; all CI steps including container cleanup passed.
- MariaDB `mariadb:10.11` GitHub-hosted ephemeral localhost-only service with brand-new schema `renee_b12_disposable_fkddl_20261013`, pinned baseline database schema `e77f287d62c5812a3d55dc23b5b20f5d167db389`.
- Fresh database initially had zero tables. No real tenant or protected data, AWS, cPanel, staging, production, schema migration or B12 concurrency rerun.

## Reproduced metadata access finding

| FK metadata surface | Fresh disposable root | DB owner | SELECT-only reader |
|---|---:|---:|---:|
| `information_schema.referential_constraints` (three cycle FK names and `SET NULL`) | 3 | 3 | 0 |
| `information_schema.key_column_usage` (cycle FK names/relationships) | 3 | 3 | 3 |
| `information_schema.table_constraints` (three cycle FK names) | 3 | 3 | 0 |

- Owner `SHOW CREATE TABLE` independently confirmed the three `production_cycles` FK definitions.
- A strictly **SELECT-only reader** likewise read the three definitions using `SHOW CREATE TABLE`: `READER_SHOW_CREATE_FK=PASS`.
- The shared read-only audit runner's new **fixed-identifier** fallback returned `CYCLE_FK_ATTESTATION=READONLY_SHOW_CREATE_TABLE_PASS`; the diagnostic printed `READER_SHOW_CREATE_FK_ATTESTATION=PASS`.
- The PHP `--lint-only` parser positive/negative contract checks passed; the original read-only 11-section inventory SQL was not modified.
- No extra grants were given to the reader. `SHOW CREATE TABLE` was used as a read-only metadata statement, not DDL.
- The fallback checks each expected Daily Record table, its exact named `cycle_id` FK to `production_cycles(id)`, and `ON DELETE SET NULL`. Wrong/missing definitions fail closed.

## Interpretation and still-open safeguards

The earlier [one-use synthetic preflight 38451673014](https://github.com/abeycity4u/Renee-Farms-V2.2.50-Batch3B1/actions/runs/38451673014) correctly executed all 11 SQL statements but returned **zero FK metadata rows** in section 05 for its restricted reader. The independent [visibility diagnostic 38453474673](https://github.com/abeycity4u/Renee-Farms-V2.2.50-Batch3B1/actions/runs/38453474673) proved privilege-dependent metadata visibility and a fail-closed guard. Run 38454255713 independently certifies the **SELECT-only read-only DDL attestation fallback in this synthetic MariaDB 10.11 environment only**.

No old one-use workflow was rerun or relabeled. All three test runs were isolated one-use actions with their own approval commits.

**Not yet certified:**

1. Actual tenant-data and deployed-schema integrity remain uninspected. No customer/tenant duplicate counts, FK/cycle cross-tenant anomalies, historic NULL-cycle collisions or trigger counts have been established. A separately authorized read-only audit of an independently verified isolated clone is required before live-compatible schema migration.
2. The proposed coordinated three-table `record_revision` migration is not applied; current routes and all seven authoritative writers have not been cut over to client-loaded CAS.
3. Original B12 lost-update concurrency test `38233373699` remains FAILED (`TWO_STALE_COMMITS`); no re-execution of that consumed CI job is permitted. New concurrency certification requires fresh isolated workers and approval.
4. D12's 15 import gates BLOCKED, Stage D UNAUTHORIZED, 150 VU HOLD, 200 VU PROHIBITED. Do not mistake the successful schema reader audit for B12 or performance certification.

**Next approved-by-architecture development action:** continue source-only design of an actual-data-safe, coordinated CAS revision cutover; execute a read-only actual-clone integrity audit only after an independently verified disposable clone and separately scoped approval exist. Preserve `One fix, fit it all`, shared services, role/tenancy, CSRF, feed/population/egg-sale transactions, migration recovery and exact remote diffs.
