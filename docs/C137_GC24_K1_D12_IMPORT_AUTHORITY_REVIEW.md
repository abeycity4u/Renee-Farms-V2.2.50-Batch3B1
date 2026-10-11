# C137 GC24 K1/D12 — Final import authority and safety-gate requirements

**Status: OFFLINE REQUIREMENTS REVIEW ONLY.** Closing D12 must **not** authorize building or running an importer, writing synthetic fixture events, connecting to an isolated/protected database, executing a purported live import dry run, restoring a backup, starting capacity tests, or changing production source.

## Frozen lineage and protected resources

The protected certified Reports branch `c137-gc-reports-prefetch-weakmap` remains at `322438870263825f9e739a12c4bd478983e67141`; Node A certified checkout `/opt/renee-agrisuite-c137-gc` is read-only/clean. The preparation branch `c137-gc24-fixture-dryrun-z23` begins D12 at `d5e78b1b6f140b477d276627b2f6dd2fc0f079f0`. D11 source-review JSON Git blob `6e6ce4ed0f4af9a76bb637c1f5c1c2f3f56bf342`; D11 R1/R2 specification SHA-256 `5de70a178ca4660eba53346f7dc43fb47302322511369261cdb1773cd7c17600` and `d424fc5bfc773619aaeadb6a245b1c5cea85c6a36b86d3cecb11bb8c7a413023`. Original **2026** R1/R2 event digest SHA-256 remains `e31421ee40ce25b16feb698313fcf337e74126a8e63d8c5dadeedf74e30165f2` and `985341ed4315788ee8f3b9d62b327da66d38fc63ea16d406c5c52bc9949d3dfe`. Do not backdate either. Calendar **2025** is design-only, without an approved transaction-date policy.

Previously reported isolated target database `renee_c137_gc24_http` and intended farm slug `c137-gc24-synthetic` are references, not verified active identities or approval. Protected fixture `renee_c137_fixture`, protected restore `renee_c137_gc24_restore_z25` and encrypted off-volume backup labeled `C137_GC24_Z25A_20261011T072212Z_31149.sql.enc` must be preserved. These identities and backup state have **not** been re-queried or re-certified in D12. Node A private `172.31.40.149` is the only verification host; Nodes B and C `172.31.35.250` and `172.31.32.62` are not contacted.

## Pre-import stop list: what is still unproven

K1/D01–D11 have closed as **offline source requirements reviews** only. Every actual implementation/fixture gate remains unresolved: D01 per-event 2025 dates, D02 initial Layer baseline/age, D03 dated eggs and sales pool, D04 Broiler cohorts/exits, D05 feed receipts and historical valuation, D06 isolated tenant/actor authority, D07 Inventory writers, D08 Sales/credit/population effects, D09 actual Daily Record continuity and the source-confirmed `$sourceId` preview signature mismatch, D10 financial allocation revisions/cost conservation, and D11 genuine eligible positive receipt-backed USED counts, 2,048 prefetch versus 2,049 fallback and capacity quality.

A passed static verifier cannot transform any of these into runtime evidence. Both proposed V2 event streams **do not exist**. All **86 individual runtime/financial/capacity inputs remain null**, including import identities, backup verification, 2025 dataset amounts, validation metrics, test approvals and rollbacks.

## Executable versus inert tools: do not run mistaken 'dry run'

The repository `scripts/run_migrations.php` **opens the configured PDO database and executes schema SQL**; it is not a fixture importer and must never be used as an offline documentation verifier. `scripts/ga_dr_post_restore_verify.php` guards isolated restore usage but then connects to a database, so it is likewise prohibited in K1/D12. `scripts/verify_c137_reports_prefetch_contract.php` is designed for a different small isolated C137 fixture and connects using a protected read-only credential; even its SQL reads are **not authorized** now. These commands were reviewed only as source text.

The existing maximum-capacity plan and environment specification require isolated capacity infrastructure, no production credentials/mail/payment or cron linkage, synthetic-only data, explicit stop conditions and per-tier evidence. Their documented tiers are a **framework**, not GC24 permission. The 150 VU tier remains **HOLD** and 200 VU **PROHIBITED**. The 2,048 annual prefetch row cap is a memory/query optimization boundary, **not** a target to fill by inventing stock movements.

A future importer or importer dry run must be designed, source reviewed, scoped to the intended tenant and exact isolated database, with replays/idempotency, atomic failure rollback, source/provenance preservation, separate restore plan and read-only evidence approval **before** any network, database or runtime contact. An option named `--dry-run` is not itself proof of zero database activity. D12's `--dry-run` refers only to this inert **Python requirements verifier**.

## Separate evidence and authorization sequence

- **G01 SOURCE_AND_BRANCH_LOCK:** pin certified Reports HEAD, remote preparation HEAD and clean worktrees; import must never run from protected certified checkout. Status: blocked pending separate evidence and approval.
- **G02 ISOLATION_AND_PROTECTED_ASSETS:** independently prove Node A/B/C identities, intended isolated target DB, least privilege, no production/fixture/restore DB routes. Status: blocked pending separate evidence and approval.
- **G03 RESTORE_POINT_AND_RECOVERY:** verify protected off-volume encrypted backup integrity and a separately authorized isolated restoration proof; no overwrites of protected databases. Status: blocked pending separate evidence and approval.
- **G04 2025_EVENT_DATE_AND_HISTORICAL_CONTEXT:** approve new 2025 dates/cohort start, opening inventory, receipts and payments; preserve original 2026 event streams. Status: blocked pending separate evidence and approval.
- **G05 TENANT_AND_ACTOR:** prove actual target tenant and authorized synthetic actor against D06 farm and module permissions, no borrowed production credentials. Status: blocked pending separate evidence and approval.
- **G06 POPULATION_EGGS_DAILY_RECORDS:** approve D02-D04/D09 source facts, reconcile mortality, exits and egg pools and investigate D09 sourceId mismatch. Status: blocked pending separate evidence and approval.
- **G07 COSTED_STOCK_AND_SALES:** approve D05/D07 receipts, valued inventory, D08 sales/receivables and physical effects using canonical owners. Status: blocked pending separate evidence and approval.
- **G08 FINANCIAL_RECONCILIATION:** approve D10 revision lineage, cents conservation, unallocated disclosure and profit/cash distinctions. Status: blocked pending separate evidence and approval.
- **G09 REPORTING_ELIGIBILITY:** independently count actual 2025 eligible effective costed USED by farm/month, differentiate SQL nonnull vs receipt-backed positive cost. Status: blocked pending separate evidence and approval.
- **G10 NO_SIDE_EFFECT_DRY_RUN_PLAN:** design a separately reviewed future dry run that does no SQL connection or write until explicitly authorized; dry-run flags alone are not evidence. Status: blocked pending separate evidence and approval.
- **G11 IMPORT_TRANSACTION_AND_REVERSIBILITY:** verify future importer design is scoped, replay-idempotent, transactional, auditable and has documented failure/rollback/recovery evidence. Status: blocked pending separate evidence and approval.
- **G12 SEPARATE_EXPLICIT_APPROVAL:** require separate written user authorization for import operator, exact command, target DB, source SHA, fixture digest, backup and window. Status: blocked pending separate evidence and approval.
- **G13 POST_IMPORT_RUNTIME_CERTIFICATION:** only after separately authorized import, verify source-driven, month-wise runtime balances and count; no capacity results inferred. Status: blocked pending separate evidence and approval.
- **G14 CAPACITY_TIER_APPROVAL:** retain 150 VU HOLD and 200 VU PROHIBITED; a capacity series needs independent approval, stop thresholds and isolated evidence. Status: blocked pending separate evidence and approval.
- **G15 STOP_ON_UNKNOWN_OR_INTEGRITY_CHANGE:** any unknown identity, parent source, protected asset, backup mismatch, D09 continuity failure or auth breach blocks import. Status: blocked pending separate evidence and approval.

These G01–G15 future gates are a **review checklist** only. The present step neither grants any gate nor contains executable importer instructions. Operator identity, exact source commit, import manifest hash, source/target network restrictions, backup and rollback hash, and approved timestamp/window must all be confirmed **in a new explicit user authorization**. A later capacity-test authorization must be independent of fixture import authorization.

## Node A read-only static D12 proof

The D12 JSON includes 13 pinned certified source/documentation contracts with 60 markers and 86 unknown import/capacity fields. The Python `scripts/verify_c137_gc24_k1_d12_import_authority.py` checks frozen D11 Git-blob and scenario spec-digest lineage, source marker preservation, G01–G15 denied statuses, hard-stop flags and in-memory negative tests rejecting invented events, actors, approvals, migration permission and the false premise that any import dry run was authorized.

```sh
PYTHONDONTWRITEBYTECODE=1 python3 -B scripts/verify_c137_gc24_k1_d12_import_authority.py --dry-run --database renee_c137_gc24_http --farm-slug c137-gc24-synthetic --legacy-year 2026 --as-of 2026-10-11 --reporting-year 2025 --certified-source-root /opt/renee-agrisuite-c137-gc
```

**PASS scope:** K1/D12 offline requirements review **CLOSED**, K1/D01–D12 documentary series completed. **NOT PASS:** implementation approval, 2025 data quality, database restoration, fixture creation, import or run permission, Reports eligibility, or maximum-capacity proof. Those need a new user decision and separate controlled workstream. No automatic advancement into execution.

**Safety invariant: 150 VU HOLD; 200 VU PROHIBITED.** No database connections/writes, PHP execution, fixture events, network to B/C, backups/restores, imports, application deploys or new load tests in D12.
