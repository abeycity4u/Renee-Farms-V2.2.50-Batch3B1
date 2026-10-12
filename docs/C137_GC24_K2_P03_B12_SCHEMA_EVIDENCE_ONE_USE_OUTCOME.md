# C137 GC24 K2-P03 B12 — one-use synthetic MariaDB schema evidence outcome

**2026-10-12. GitHub-hosted run 38299970527 FAILED. Exactly one attempt; approval consumed. DO NOT rerun without new explicit, separate authorization.**

## Source / scope

- Repository: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- Isolated branch: `c137-gc24-k2p03-b12-schema-evidence-once`.
- Before-approval source HEAD: `a03bd78b214e730f7eb0ce79cdef737dda543379`.
- One-file approval commit/run HEAD: `b4092746c8c99f52a66bb3de96779e1d953317aa`.
- Workflow: `.github/workflows/k2p03-b12-schema-evidence-once.yml`. Synthetic probe: `tests/integration/k2p03_b12_schema_evidence_synthetic.sh`.
- GitHub run: https://github.com/abeycity4u/Renee-Farms-V2.2.50-Batch3B1/actions/runs/38299970527
- Run attempt: 1. Outcome: failure. Job ID: 114980232254.
- Disposable service: `mariadb:10.11`, actual container log `MariaDB 10.11.19`; localhost only, database `renee_b12_schema_synthetic_once`; synthetic fixture farm IDs 91001/91002. Only nonsecret disposable CI credentials used; no protected secrets, database endpoints, deployments or original B12 rerun.
- The original B12 failure `38233373699` remains separate with its one consumed attempt.

## What definitely PASSED in GitHub job logs

1. GitHub-hosted runner/container startup, isolated checkout, **exact-one-file approval and SHA guards**, MariaDB client installation.
2. `bash -n` source syntax check before execution.
3. Source database identity `renee_b12_schema_synthetic_once` and zero initial tables.
4. `database_schema.sql` blob `e77f287d62c5812a3d55dc23b5b20f5d167db389` imported successfully into the fresh disposable MariaDB instance.
5. The three Daily Record tables exist: count 3.
6. The `record_revision` columns across those tables are absent: count 0.
7. The three Daily Record cycle FKs have `ON DELETE SET NULL`: count 3.
8. The three `cycle_id` columns are nullable: count 3.
9. Synthetic test INSERTs completed for two test farms, six distinct cycles and five Daily Record rows per table.
10. Each Daily Record table admitted two rows with **the same synthetic farm/date (and same Ruminant species) and NULL cycle_id**. This is direct proof that existing schema indexes do not enforce legacy logical-key uniqueness.
11. All 11 SELECT-only statements in `docs/C137_GC24_K2_P03_B12_READONLY_SCHEMA_INVENTORY.sql` executed without SQL error; output was captured within the disposable job and was not subsequently verified due to the failure.
12. GitHub job steps for post-checkout and **container shutdown/removal** completed successfully.

The full scan was not connected to any production database. No genuine customer/tenant duplicate counts can be inferred from synthetic data.

## Exact failure / partial-result boundary

The probe stopped after printing:

```
SOURCE_READONLY_INVENTORY_EXECUTED=PASS
tests/integration/k2p03_b12_schema_evidence_synthetic.sh: line 108: section_: unbound variable
```

The bug was in a **reporting label**, not SQL or data setup: Bash under `set -u` parsed `$section_` as an unset variable in the PASS branch of `check_section()`. After the run, the one-line fix replaced it with `${section}_${kind}` in source-only commit `5f54e606c47c38e0c9008a919f5c68eb0e90aba3`. No execution was made after this edit, so corrected code is not runtime-certified.

The failed run **does not certify**:
- Section 07 aggregate duplicate counts and assertions.
- Section 08 ambiguous same-day/cross-cycle UPDATE match aggregates.
- Section 09 orphan/cross-tenant counts (synthetic cross-tenant source references were inserted successfully, but the counted assertions were never reached).
- The synthetic cycle deletion `ON DELETE SET NULL` collision demonstration.
- Any optimistic revision migration, client CAS integration, multi-process B12 no-stale-overwrite certification or real tenant database state.

The runner reported the test step failed and the overall GitHub run failed. **Never rebrand this run PASS based on earlier successful subchecks.**

## Safe continuation

- Treat `38299970527` as the **one and only** authorized new schema-evidence execution. No automatic retry, no `rerun_failed_jobs`, no `rerun_job`, no reuse of old approval file, no second approval commit without new authorization.
- The workflow's pre-execution source pin refers to the exact script blob run (`bad4e82eea139d1df4ae74c9d5850564861ce418`), not the post-failure one-line fix. A separately approved future run must re-pin and use a new isolated branch/workflow approval, rather than silently reusing this consumed trigger.
- Continue source-only schema design / review while additional execution authority is absent. A future test must be explicitly authorized with a fresh, distinct GitHub branch/approval commit, first verify the new script and all relevant runner guard pins, and include synthetic-only data.
- Actual production/staging/retained fixture data review, destructive deduplication and a `092` migration remain **unauthorized**. Legacy duplicate counts in protected environments are UNKNOWN.
- B12 original concurrency run remains FAILED/TWO_STALE_COMMITS, prior B01–B11 certifications unchanged, D12 fifteen import gates BLOCKED, 150 VU HOLD, 200 VU PROHIBITED.
