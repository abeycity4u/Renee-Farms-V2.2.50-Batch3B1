# C137 GC24 Z27D — Offline canonical payload adapter

## Safety and source authority

Preparation branch only: `c137-gc24-fixture-dryrun-z23`. Certified Reports
source `322438870263825f9e739a12c4bd478983e67141` remains untouched.
Original Z23 and Z26 input models, event digests and tests remain unchanged.
The Z27D Python adapter generates **in-memory call-intent previews**, NEVER
SQL, HTTP POST requests, PHP service calls, database connections or imports.
`--execute` is unsupported. A passing offline verifier does not certify
any real data, permission, application execution or load performance.

## Z27C writer owners identified at certified source

- Cycle: `production_cycle_create_v3` (creates active cycle and opening population).
- Category: `inventory.php` POST controller; no reusable category writer.
- Item: `inventory.php` / delegated inventory permission POST controller;
  initial stock is posted through `stock_apply_movement`.
- Sale: `management/sales_records.php` POST transaction, centralized public
  reference, source effects, automatic/manual allocation, optional receivables.
  No standalone canonical sale-creation function yet.
- Poultry expense: `poultry_expense_entry_create`, including expense revision.
- Stock receipts and consumption: `stock_apply_movement`, append-only ledger,
  provenance, historical weighted-average costs and attribution resolver.
- Manual allocations: `sale_revenue_allocation_persistence_apply`,
  `financial_allocation_persistence_apply`,
  `stock_consumption_allocation_persistence_apply`. All require an active
  transaction and own revision/projection contract.

## Source-model blockers (diagnosed, NOT silently repaired)

- Source marks cycles `planned`; canonical writer creates `active`
  cycles plus population baseline. Opening headcount and naming policy absent.
- Parent sale, expense and some used-stock events model `farm` attribution
  for concrete `layer`/`broiler` scopes without cycle. Canonical
  `attribution_scope()` expects `production_type` when no cycle is selected.
- Pooled Layer egg sales belong to automatic unsold-egg allocation, not manual
  60/40 revenue allocation. No Daily Record egg-production events are modeled.
  Direct-cycle egg sales also lack operational production evidence.
- Inventory `operating` is not a financial classification. Eligible
  operating-consumption categories include medication_vaccine, supplement and
  consumables, according to the shared inventory finance contract.
- Receipt events lack acquisition unit cost, so normal USED weighted-average
  ledger cost cannot be presumed to equal the modeled 250.00 / 40.00 values.
  The normal stock API disallows explicit outgoing-cost overrides.
- Category/item role, feed usage, unit and default production attributes
  are unresolved. Manual sale category, unit, population and payment choices
  are unresolved. Poultry expenses need a current non-stock expense category.
- November/December 2026 receipts are future-dated at the pinned
  2026-10-11 audit date; the existing Inventory POST controller rejects them.
- Call permissions, real farm ID, actor ID and transaction boundaries are
  intentionally not provided by symbolic planning records.

## Implemented controls

`scripts/c137_gc24_canonical_payload_adapter.py`:
- Calls existing `validate_manifest()` first, including target, tenant,
  signed digest equivalence, allocation balance and stock conservation.
- Preserves every original event and its certified SHA-256 digest.
- Produces per-event symbolic call-intent previews with authoritative
  owner labels; never claims that they are callable or executable.
- Converts integer cents to formatted monetary inputs only for fields
  those service boundaries accept. Derived allocation percentages are
  *excluded* from intended service calls; two desired rows are grouped
  under the original parent reference.
- Emits bounded blocker counts without printing entire synthetic events.
- Always reports `BLOCKED_OFFLINE`, `HARD_DISABLED` and no runtime writes.

`scripts/verify_c137_gc24_canonical_payload_adapter.py` statically
checks the adapter's no-IO import/call surface and rejects: changed source
events, wrong tenant/DB/year, invalid as-of, forbidden execute flags, missing
writer owners, skipped events, incorrectly grouped allocation rows, and
misrepresented readiness.

## Safe CLI on the separate preparation checkout

```sh
PYTHONDONTWRITEBYTECODE=1 python3 -B scripts/verify_c137_gc24_canonical_payload_adapter.py
PYTHONDONTWRITEBYTECODE=1 python3 -B scripts/c137_gc24_canonical_payload_adapter.py --dry-run --scenario R1 --database renee_c137_gc24_http --farm-slug c137-gc24-synthetic --year 2026 --as-of 2026-10-11
PYTHONDONTWRITEBYTECODE=1 python3 -B scripts/c137_gc24_canonical_payload_adapter.py --dry-run --scenario R2 --database renee_c137_gc24_http --farm-slug c137-gc24-synthetic --year 2026 --as-of 2026-10-11
```

Expected: tool/verifier PASS with `READINESS=BLOCKED_OFFLINE`, never
`PERSISTED_DATA=PASS`. This document does not assert runtime verifier results
until they are independently captured and reviewed.

## Gate after Z27D

No import is authorized. Next planning decision is to resolve synthetic
model requirements and proposed canonical shared category/item/sale writer
extraction with focused non-DB tests. Any changed event plan requires an
explicit model version, distinct event digests and semantic diff review;
do not silently change the Z23/Z26 certified digests. A future live import,
cleanup, release, merge or load test needs separate explicit authorization.
