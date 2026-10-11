# C137 GC24 — Z26 controlled import preflight (offline only)

## Authorization and status

User approved **implementation without insertion**. Z26 therefore prepares
deterministic in-memory import events and validates all modeled parent/child
relationships, but intentionally exposes **NO INSERT / import execution
mode**. Command-line `--execute` is rejected, and there is no DB driver,
SQL connection, credentials, file output or network code.

Separate preparation branch: `c137-gc24-fixture-dryrun-z23`, forked from
certified HEAD `322438870263825f9e739a12c4bd478983e67141`.
No application route, shared service, migration or GA source file is edited.

Retained: empty-data capacity certification 25/50/75/100/125 VU.
150 VU HOLD; 200 VU PROHIBITED; Draft PR #4 unmerged.

## Source authority confirmed

This inspection located canonical service boundaries; their use in a real
fixture writer remains PENDING:

- `lib/production_cycle_service.php`:
  `production_cycle_create_v3(PDO,int,array,?int)` creates the cycle
  **and** an opening population baseline; it resolves poultry/ruminant
  identity, and always creates the new cycle as active.
- `lib/sale_revenue_allocation_persistence.php`:
  `sale_revenue_allocation_persistence_apply(PDO,int,int,array,int,?string)`.
- `lib/stock_consumption_allocation_persistence.php`:
  `stock_consumption_allocation_persistence_apply(PDO,int,int,array,int,?string)`.
- `lib/financial_allocation_service.php`:
  `financial_allocation_service_validate_desired_rows(...)`.
  Expense allocation mutation ownership, including immutable revision
  provenance, must be resolved before any writer is enabled.

Do not create direct SQL shortcuts for these service-owned contracts.
The existing R1/R2 fixture model is representative *planning input*,
not an approved set of persisted financial, cycle or stock records.
The exact required fields and semantic mapping of sales, expenses and
stock parent writers remain pending review.

## Files and commands

- `scripts/c137_gc24_fixture_dryrun.py`: Z23 deterministic source of
  events and certified digests. Z26 adds an **optional in-memory event
  stream** to the existing plan without changing its default output.
- `scripts/c137_gc24_fixture_import_preflight.py`: validates certified
  digest, 2026 dates, approved DB+farm, cycle/stock/category references,
  sale quantity * unit price, 60/40 allocation conservation, monthly
  versus annual totals, and nonnegative stock ledger. Rejects execution.
- `scripts/verify_c137_gc24_import_preflight.py`: static no-IO check,
  two scenario tests, fail-closed target tests and tampering rejection.
- Existing recovery dry-run and Z23 verifier remain unchanged.

Run in source checkout, offline:

```sh
PYTHONDONTWRITEBYTECODE=1 python3 -B scripts/verify_c137_gc24_fixture_dryrun.py
PYTHONDONTWRITEBYTECODE=1 python3 -B scripts/verify_c137_gc24_import_preflight.py
PYTHONDONTWRITEBYTECODE=1 python3 -B scripts/c137_gc24_fixture_import_preflight.py --dry-run --scenario R1 --database renee_c137_gc24_http --farm-slug c137-gc24-synthetic --year 2026
PYTHONDONTWRITEBYTECODE=1 python3 -B scripts/c137_gc24_fixture_import_preflight.py --dry-run --scenario R2 --database renee_c137_gc24_http --farm-slug c137-gc24-synthetic --year 2026
```

`--execute` must fail. Do not manually bypass it.

## Independent Z25 recovery evidence from user

Z25A encrypted Mac backup: `C137_GC24_Z25A_20261011T072212Z_31149.sql.enc`.
AES-256-CBC/PBKDF2, verified encrypted/decrypted SHA-256 and 69 tables.
Z25B scratch restore: `renee_c137_gc24_restore_z25`;
69 tables, schema metadata, counts, synthetic farm/admin matched.
**The scratch DB exists** and should not be overwritten or blindly
deleted. This is prior externally verified evidence; Z26 tools do not
access or reverify the backup.

## Remaining STOP gates before any future data insertion

1. Review canonical parent writers for sales, expenses and stock movements,
   expense revision mutation, stock costing and source recording, full
   business context and privileges.
2. Convert the planning model into canonical service-compatible payloads.
   Current `cycle.status='planned'` is a planning label, whereas canonical
   `production_cycle_create_v3` creates an active cycle. Do not bypass this.
3. Establish and test write-transaction orchestrator with isolated DB/farm
   identity and protected fixture denylist, exact database backup evidence,
   idempotency marker, external FK/revision tables and fresh restore guard.
4. Require a **separate explicit user authorization** for DB insertion,
   importing fixture records, scratch cleanup, or any new load test.
5. Reconcile persisted canonical financial reports and tenant isolation.
   Preserve protected `renee_c137_fixture`, original HTTP backup and GA.
6. Do not reopen certified empty-data VU tests; representative-data
   capacity is an independent certification track.

Current Z26 result can establish `IMPORT_PREFLIGHT=PASS_OFFLINE` only.
It must NEVER report `PERSISTED_DATA=PASS` or `RESTORE_READY=PASS`
without fresh runtime evidence.
