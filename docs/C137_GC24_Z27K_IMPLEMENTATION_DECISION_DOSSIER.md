# C137 GC24 — Z27K Implementation Decision Dossier

**Status: DECISION_DOSSIER_UNAPPROVED.** Z27K is a source-grounded,
machine-checked decision register, *not* an implementation authorization,
new fixture generator or import plan. All 12 decisions remain unselected
and not approved. The canonical PHP source remains protected.

## Authoritative state

| Authority | Verified reference |
| --- | --- |
| Certified Reports source | `c137-gc-reports-prefetch-weakmap` at `322438870263825f9e739a12c4bd478983e67141` |
| Previous preparation checkpoint | `c137-gc24-fixture-dryrun-z23` at `e050862fc75e8ead9193279c5b5f8a10983540ea` |
| Original 2026 R1 | 5,452 events, SHA-256 `e31421ee40ce25b16feb698313fcf337e74126a8e63d8c5dadeedf74e30165f2` |
| Original 2026 R2 | 13,770 events, SHA-256 `985341ed4315788ee8f3b9d62b327da66d38fc63ea16d406c5c52bc9949d3dfe` |
| Prior Z27J source review | R1 `20af186b096ba4e0404fe20442eb3091c5e0db25c46da45cb8213bfbd358e83d`; R2 `041a26508f5e6a2c3b3f7881740fd86bf35a05bec2a636fd76d658e205a35818` |

Planning as-of: **2026-10-11**. In the original workload, 1,180 R1
and 2,994 R2 events are future-dated at this checkpoint. The completed
year **2025** is only an *unselected candidate*. The alternative is a
separately designed 2026 full year after year-end. Do not backdate the
existing certified event streams or silently change their digests.

## Decision ownership and evidence

The full source of truth is
`docs/C137_GC24_Z27K_DECISION_REGISTER.json`. It specifies candidate
choices, decision owner, required evidence, dependencies and approval
state for each of 12 items. Neither choice nor approval is inferred.

| ID | Owner / boundary | What must be settled |
| --- | --- | --- |
| D01 | Farm owner + engineering | Full-year reporting period and independent event date policy |
| D02 | Farm owner | Layer opening stock, acquisition, bird age and phase per cycle |
| D03 | Farm owner + engineering | Dated Daily Record egg output, mortality and unsold stock |
| D04 | Farm owner + engineering | Broiler cohorts, harvest dates and explicit live-stock exits |
| D05 | Farm owner + engineering | Both poultry feed types, receipt prices and exactly one stock-use source |
| D06 | System owner + engineering | Isolated farm/actor IDs, tenancy, roles and access policy |
| D07 | Engineering | One shared category/item writer, preserving owner/delegated permissions |
| D08 | Engineering | Atomic shared sale-creation service, preserving all domain side effects |
| D09 | Engineering | Canonical Layer/Broiler Daily Record save/edit orchestration |
| D10 | Engineering | Allocation parent IDs, scope, revision and persistence ownership |
| D11 | Engineering | Posted, costed, eligible USED rows and 2,048 prefetch boundary |
| D12 | System owner | Separate explicit database import approval and backup/rollback evidence |

### Source-confirmed implementation boundaries

Z27J found two `stock_items` creation surfaces, in `inventory.php`
and `includes/inventory_permission_hardening.php`. They are currently
duplicated even though their INSERT signatures match. Any future shared
writer must preserve permission enforcement, category role, financial
type, transaction safety and the priced receipt through
`stock_apply_movement`. Category creation also remains page-local.

`management/sales_records.php` currently orchestrates sales references,
general inventory, slaughter, ruminant, poultry population effects,
automatic revenue allocation, receivables and rollback inside a sale
transaction. A safe extraction owns the *whole transaction*, not just
the `INSERT INTO sales_records`. Manual Layer egg 60/40 allocation
remains forbidden: dated unsold egg production is authoritative.
Broiler Head sales do not imply physical exits unless explicitly recorded.

Layer/Broiler Daily Record pages own validation, write transaction,
population continuity, feed synchronization and mortality. Layer records
also rebuild egg allocations. A shared service must preserve these
calls, transaction semantics, edits, reversals and source IDs without
duplicating feed consumption as separate manual USED movements.

Z27H identified 16,419/49,269 crate sales, 5,469/16,422 Head
sales and **no legacy Broiler feed-use records**. These are *unverified
financial workload quantities*, not approved physical flock, feed, or
production facts. No opening layer population, laying rate, broiler
cohort schedule, or outgoing stock cost is invented in this dossier.

## Proposed stages; no implementation authority conveyed

K0 records the decisions and closed source reviews only. K1 requires
explicitly chosen dates and biological inputs. K2 proposes extracting
Inventory category and item writers with focused tenancy/role/receipt
regression. K3 proposes extracting the shared Sales transaction and its
existing side effects with targeted general, ruminant, slaughter, poultry,
reference and receivable tests. K4 proposes extracting the shared Daily
Record orchestration, preserving continuity, inventory and eggs. K5
would generate **independently versioned** V2 events and SHA-256 digests
only after approvals. K6 would verify canonical semantics, eligible
Report rows, backups and target isolation. K7 requires a *new explicit
user authorization* before any database writes, import, migration,
cleanup, restore or load test.

**One fix, fit it all:** shared services, thin routes, no duplicated
SQL/permission/pricing/billing policy. Change only on a separately
approved implementation branch after full cross-module review; do
not rewrite the immutable certified Reports source. Use tight verifiers
and remote GitHub semantic-diff reviews; do not re-run previously closed
QA without regression evidence.

## Offline Z27K verification

The package adds a declarative JSON register and a verifier only.
The verifier reads that JSON, the original in-memory streams and the
16 certified PHP files *as text*, reuses Z27J's read-only source checks,
validates both original lineage digests, and rejects forged approvals,
year selection, load authorizations or removal of import prerequisites.
It emits two **dossier specification digests**, not event digests.

After guarding the two Git checkout HEADs and clean worktrees, run:

```sh
PYTHONDONTWRITEBYTECODE=1 python3 -B scripts/verify_c137_gc24_z27k_decision_register.py --dry-run --database renee_c137_gc24_http --farm-slug c137-gc24-synthetic --year 2026 --as-of 2026-10-11 --candidate-year 2025 --certified-source-root /opt/renee-agrisuite-c137-gc
```

Expected output: `GC24CG_Z27K_NODE_A_OFFLINE_VERIFIER=PASS`,
`DECISIONS_APPROVED=0`, `DECISIONS_SELECTED=0`,
`DECISIONS_UNRESOLVED=12` per scenario, no database connections,
source writes or new events. **Do not close Z27K before this runs on
Node A.**

Protected databases `renee_c137_fixture`,
`renee_c137_gc24_restore_z25`, and isolated
`renee_c137_gc24_http` remain untouched. **150 VU HOLD; 200 VU
PROHIBITED.**
