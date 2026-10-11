# C137 GC24 Z23 — representative Reports fixture, OFFLINE DRY RUN ONLY

Status: implementation preparation only. No fixture insertion, no database backup,
no restore, no schema migration, no load test, and no production deployment.

## Scope and immutable boundaries

- Certified parent: `322438870263825f9e739a12c4bd478983e67141`,
  branch `c137-gc-reports-prefetch-weakmap`. That branch remains untouched.
- Separate preparation branch: `c137-gc24-fixture-dryrun-z23`.
- Exclusive *future* HTTP fixture target: Node B `172.31.35.250`,
  MariaDB `renee_c137_gc24_http`, synthetic farm
  `c137-gc24-synthetic`.
- Protected `renee_c137_fixture` is read-only and must never be seeded,
  cloned over, dropped, or used as a restore-test destination.
- All 25/50/75/100/125-VU Reports *empty-data* results remain certified;
  they are not representative-data certifications. 150 VU HOLD;
  200 VU PROHIBITED. No Draft PR #4 merge.

## Offline implementation shipped

- `scripts/c137_gc24_fixture_dryrun.py` calculates deterministic modeled
  R1/R2 data events in memory, checks year coverage, integer-cent allocation
  conservation, stock balance and scenario-specific volumes, then prints only
  bounded totals and a canonical SHA-256 digest. It does not emit SQL or connect
  to any server. It currently models records and references; it is NOT a
  database-population tool.
- `scripts/c137_gc24_fixture_recovery_dryrun.py` matches the exact proposed
  plan digest, forbids all non-approved targets, and reports mandatory snapshot,
  restore-test, tenant and referential prerequisites. It has *no* rollback
  execution path and cannot produce a misleading backup PASS.
- `scripts/verify_c137_gc24_fixture_dryrun.py` checks both scenarios, the
  determinism and totals, the forbidden import/IO call surface of both tools,
  invalid target rejection and recovery-digest rejection.

Usage (does not write files or contact the database):

```sh
PYTHONDONTWRITEBYTECODE=1 python3 -B scripts/verify_c137_gc24_fixture_dryrun.py
PYTHONDONTWRITEBYTECODE=1 python3 -B scripts/c137_gc24_fixture_dryrun.py --dry-run --scenario R1 --database renee_c137_gc24_http --farm-slug c137-gc24-synthetic --year 2026
PYTHONDONTWRITEBYTECODE=1 python3 -B scripts/c137_gc24_fixture_dryrun.py --dry-run --scenario R2 --database renee_c137_gc24_http --farm-slug c137-gc24-synthetic --year 2026
```

## Test scenarios (illustrative synthetic economics, not real transactions)

| Case | Sale parents | Expense parents | Used-stock parents | Monthly prefetch design |
|---|---:|---:|---:|---|
| R1 | 1,460 | 730 | 1,800 | in-limit (2,048 source-row cap) |
| R2 | 4,380 | 2,190 | 3,600 | overflow/fallback intended |

Models include four symbolic poultry cycles, two inventory categories/items,
24 inventory receipts, deterministic layer/broiler dates, and cycle-vs-farm
scope with 60/40 allocation splits. Totals use integer cents. The records do
not contain live production data. The R2 overflow test remains an *intent*:
the canonical consumed-stock reader's eligibility rules and exact count must
be verified before claiming that its fallback actually executed.

## Safety requirements before any writer can be authorized

1. Identify exact Node A source, Node B MariaDB and target DB identities;
   check exactly one target farm/admin and the protected fixture exclusion.
2. Use the existing canonical domain write services (or a narrowly audited
   fixture import service with equivalent validations). Do **not** duplicate
   allocation or profitability business rules in a direct ad-hoc SQL loader.
3. Confirm all live schema constraints, generated columns, unique public
   references, the 29 outgoing composite FK constraints (31 links), the
   32 incoming FK constraints (34 links), the nullable livestock type
   semantics, permissions and tenant scope.
4. Capture a **complete, consistent snapshot of the isolated HTTP DB** before
   any import, including existing synthetic authentication state, schema and
   metadata. Store root-owned, mode 0600, encrypted where feasible; record a
   digest. Keep the protected fixture and GA out of the backup/restore path.
5. **Verify restoration to a NEW disposable scratch database** whose name is
   strictly distinct from `renee_c137_fixture`, production databases and
   `renee_c137_gc24_http`. Compare baseline schema, key counts and synthetic
   authentication anchor. Restore-test and checksum evidence must both PASS.
6. Produce an insertion manifest: scenario, event digest, unique marker,
   table-by-table inserted keys, parent-to-child relationships, 12 month sums,
   allocation-cent conservation, consumed-stock quantity and costing,
   existing farm/admin preservation, isolation checks.
7. Set a strict import authorization and fail-closed rollback guard before
   any mutation. If interrupted, stop isolated workload and use the verified
   full HTTP database snapshot. Avoid unreviewed per-table deletion because
   other tables reference the nine target tables with RESTRICT/SET NULL/
   CASCADE dependencies.
8. After data import, validate correct farm financial outputs against the
   canonical `getProfitabilitySummary` services and compare each monthly
   aggregation with annual totals. Include tenant-isolation and clean
   rollback evidence. Only then consider a **separately authorized** Reports
   benchmark on representative data with a watchdog and GA invariants.

## Present certification boundary

None of steps 2–8 above has been executed by these dry-run scripts. No backup
was taken or certified, no database credentials were used, and no real
rollback facility was armed. An explicit later user authorization is required
before *any* data insertion, activation or representative-data load test.
