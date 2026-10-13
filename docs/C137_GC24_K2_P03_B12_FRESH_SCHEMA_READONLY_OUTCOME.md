# C137 / GC24 / K2-P03 B12 — Fresh disposable schema inventory result and FK visibility gap

**2026-10-13; one-use synthetic workflow completed SUCCESS; section 05 evidence INCOMPLETE. This is not B12 edit/concurrency certification and not an inspection of actual tenant data.**

## Immutable evidence

- Repository: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- Prepared synthetic-only branch: `c137-gc24-k2p03-b12-fresh-schema-ci-prepared`.
- Pre-approval HEAD: `f3ef82d1c5d827750a4ef3def51ff6efc8e4076a`.
- One-file authorization commit: `da5b117b853c12703245e5603680e3e6cad2de82`.
- New run: [38451673014](https://github.com/abeycity4u/Renee-Farms-V2.2.50-Batch3B1/actions/runs/38451673014); first and only attempt, conclusion SUCCESS.
- Job ID: `115483865124`; 13 job steps including cleanup completed successfully.
- MariaDB `10.11.19` in GitHub-hosted ephemeral `mariadb:10.11` service; loopback-only endpoint, database `renee_b12_disposable_ci20261013_01` initially had zero tables.
- Pinned source schema blob: `e77f287d62c5812a3d55dc23b5b20f5d167db389`.
- Pinned 11-section inventory blob: `6dd5614edb2dae2432d9e8628b43ba53177e7133`.
- Pinned runner blob: `6584d52ea4334a48f7a5d92d7be9ec0391933a84`.
- One-use approval/source checks, offline SQL checksum and parser, fail-closed unauthorized-run test, schema import, database identity, SELECT-only account grant check, inventory execution, and container shutdown PASSED.
- No AWS/cPanel/production/staging/protected fixtures/real tenant data, revision migration, B12 concurrency rerun or load testing was performed.

## What the inventory actually established

- Three Daily Record tables and `production_cycles` exist as InnoDB tables in **the synthetic fresh import**.
- Three `cycle_id` columns exist, each nullable.
- No `record_revision` column was returned for the three Daily Record tables.
- Baseline unique keys are `(cycle_id,record_date)` for Layer and Broiler and `(record_date,animal_type,cycle_id)` for Ruminant.
- All Layer/Broiler/Ruminant row counts, NULL-cycle duplicate groups, same-day ambiguities, cross-tenant links and trigger counts were zero **because this new synthetic import had no user/farm rows**. These zero counts must NOT be presented as real tenant-data certification.
- **CRITICAL GAP:** `05_FOREIGN_KEY_DELETE_RULES` returned **ZERO rows** despite a zero-error SELECT and the imported schema declaring cycle foreign keys. The prior independently certified R2 synthetic run `38301126240` demonstrated three SET NULL cycle FKs on the same pinned source schema. This run did not establish whether the zero rows arise from metadata visibility of the restricted SELECT-only account, the query, or actual imported FK state. The runner previously labeled the audit complete even when this section returned no rows.

## Corrective source-only patch (no CI rerun)

On the new follow-up branch `c137-gc24-k2p03-b12-fk-preflight-hardening`:
- `scripts/k2p03_b12_schema_readonly_gate.php` now requires exactly one observable `production_cycles` foreign key with `ON DELETE SET NULL` for **each** of the three Daily Record tables. Empty, partial, duplicate or wrong-rule data fails closed.
- The default, DB-connection-free `--lint-only` mode includes positive and negative validator fixtures.
- Isolated PHP function contract tests (not database or whole-runner execution) returned 5/5 PASS.
- The prior approved workflow branch and run remain immutable. **Do not rerun run 38451673014** or repurpose its one-use approval.

## Remaining gates

1. On a **separately authorized new disposable isolated environment**, determine whether metadata omission is a `SELECT`-only visibility limitation or an actual schema discrepancy. Distinguish FK metadata visibility from schema validity, preserve the read-only inventory user for tenant-data queries, and prevent empty section 05 from certifying success.
2. Before any schema/writer migration, perform a separately approved actual-schema and aggregate legacy tenant-data inspection on an independently verified isolated clone (not live/protected databases). Resolve NULL-cycle duplicates, cross-farm cycle links and cycle-deletion semantics; never auto-deduplicate customer records.
3. Coordinated three-table revision migration plus seven-writer source/API/browser integration and separately authorized MariaDB multiworker certification remain NOT DONE.
4. Original failed B12 concurrency run `38233373699` remains FAILED `TWO_STALE_COMMITS`; its one-use approval remains consumed. D12 15 gates BLOCKED; Stage D UNAUTHORIZED; 150 VU HOLD; 200 VU PROHIBITED.

**Result classification:** CI transport and isolated SQL execution: PASS. Complete foreign-key metadata verification: INCOMPLETE. B12 runtime correctness: NOT CERTIFIED.
