# C137 GC24 K1/D01 — Owner selects completed 2025 reporting year

The owner explicitly chose **2025-01-01 through 2025-12-31** as the
complete reporting period for a **separate, new, versioned synthetic V2
fixture design**. It is a design-period selection only, not a grant of
source change, event generation, database access, deployment or load testing.

## Frozen predecessor / append-only decision

Keep the Z27K decision-register snapshot at
`docs/C137_GC24_Z27K_DECISION_REGISTER.json` unchanged. Its
historical state correctly shows 12 unselected decisions *before this
user's choice*. This new choice lives in
`docs/C137_GC24_K1_D01_2025_SELECTION.json` as a separate
append-only selection.

- Certified Reports source HEAD unchanged:
  `322438870263825f9e739a12c4bd478983e67141`.
- Preparation parent HEAD:
  `476d0d34b1d58f4984208ea34bdffd1bbc9210da`.
- Frozen Z27K decision register Git blob SHA-1:
  `babb4fe2e575c08b543bc435cc8cd04080f3e0e9`.
- Previous dossier-spec hashes, R1:
  `3823c305c095a5eefa025ab4054580bec3dc4d04e3013a5cbf04b4df0a65ac63`,
  R2:
  `17d40dbd17a72a470e1c8d99aea85d544b7be6bf9250ce6aace59c9f909bbde6`.
- Original 2026 R1 5,452 events and SHA-256:
  `e31421ee40ce25b16feb698313fcf337e74126a8e63d8c5dadeedf74e30165f2`.
- Original 2026 R2 13,770 events and SHA-256:
  `985341ed4315788ee8f3b9d62b327da66d38fc63ea16d406c5c52bc9949d3dfe`.

The 2025 calendar is complete (365 days). The original 2026 R1/R2
events are **not** moved to 2025 or rehashed. Proposed identifiers
`R1-V2-2025-DESIGN` and `R2-V2-2025-DESIGN` are design labels,
not event identifiers, payloads or generated event streams.

## Decision scope, what is still open

D01's *year selection* is now owner-confirmed for design, but
date and event-identity rules for each event category remain pending.
The other 11 decisions, D02–D12, are unapproved. In particular:

- Layer acquisition/bird age/headcount, phase transitions, mortality and
  dated egg counts cannot be invented from synthetic egg sales volumes.
- Broiler cohort rotation, growing/harvest, headcounts and explicit
  population exits remain unproven.
- Both Layer/Broiler feed item receipts, chronological costing, Daily
  Record linkage and exactly-once consumption source remain unresolved.
- Category/item, Sales and Daily Record reusable writer extraction remains
  a separately authorized engineering phase.
- 2048-row actual eligible USED-cost Reports proof, isolated import
  authorization, recovery and load testing are not complete.

**Next controlled step:** K1/D02, the offline Layer opening-flock,
acquisition and biological stage *requirements* specification. Inspect
the certified services and record required inputs/validation without
inventing numeric populations or opening a database.

## Verifier

`scripts/verify_c137_gc24_k1_d01_year_selection.py` checks the
frozen Git blob, selected window, source lineage, denied permissions,
and tampered in-memory selections. It emits R1/R2 *decision spec*
SHA-256 values only. Do not interpret these as new event hashes.

Safe Node A usage after branch/HEAD/worktree verification:

```sh
PYTHONDONTWRITEBYTECODE=1 python3 -B scripts/verify_c137_gc24_k1_d01_year_selection.py --dry-run --database renee_c137_gc24_http --farm-slug c137-gc24-synthetic --legacy-year 2026 --as-of 2026-10-11 --selected-year 2025
```

Expected `GC24CG_K1_D01_OFFLINE_VERIFIER=PASS`,
`UNAPPROVED_DECISIONS_REMAINING=11`, `DATABASE_WRITES=0`,
`V2_EVENTS_GENERATED=NO`. Node A output is required before the
checkpoint is closed.

Protected `renee_c137_fixture`, `renee_c137_gc24_restore_z25`
and `renee_c137_gc24_http` remain untouched. **150 VU HOLD;
200 VU PROHIBITED.**
