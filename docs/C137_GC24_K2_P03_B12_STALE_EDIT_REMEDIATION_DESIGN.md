# C137 / GC24 / K2-P03 — B12 shared Daily Record stale-edit remediation architecture

**STATE: SOURCE-ONLY DESIGN, NOT IMPLEMENTED, NOT TESTED.** This is an architectural review and change plan, not permission to patch, deploy, migrate, import or retry any CI job.

**Date:** 2026-10-12. **Repository:** abeycity4u/Renee-Farms-V2.2.50-Batch3B1.
**Isolated design branch:** c137-gc24-k2p03-b12-remediation-design, forked from the terminal B12 **failure evidence** commit `39ab4dd71a896fa73e4f4513efa0bd1ab72263db`. The failed execution and its approval remain on their original immutable B12 history.

## 1. Verified evidence; keep conclusions narrow

- **Original B12 workflow:** [run 38233373699](https://github.com/abeycity4u/Renee-Farms-V2.2.50-Batch3B1/actions/runs/38233373699), job 114757993448; exactly one expressly authorized run, attempt 1, `conclusion=failure`. Approval-only commit `c26beac3e74256d071bcc453516b9cb6592bd024` consumed and **must never be reused or rerun**.
- **Failed invariant:** `B12_CONCURRENCY_OUTCOME=TWO_STALE_COMMITS`; `FAILURE_STAGE=NO_SILENT_OVERWRITE`; `K2_P03_B12_REAL_MARIADB=FAIL`.
- Both genuinely independent PDO/MariaDB workers loaded the **same** original Layer source, the second waited behind the first's held row lock, and **both transactions committed** different updates. No explicit stale-editor conflict was raised. The fixture replicates the affected route's target UPDATE predicate and calls original shared downstream writers; it is **not** authenticated browser E2E or a real-tenant reproduction.
- The nine scoped ledgers of a separate synthetic farm were untouched. Final surviving source-owned mortality/feed movement, feed balance, canonical population, later openings and automatic pooled egg-sale attribution reconciled. **Ledger consistency does not cure a lost user edit.**
- Job logs showed both PHP lint passes, isolated DB identity and empty-schema gate passes, disposable schema import, normal worker exit/cleanup and GitHub container/network shutdown. Reported **52 supporting PASS log lines**, but **B12 remains FAIL** and the B01–B11 historical **1,195 certified assertions do not increase**.
- Full failure report: `docs/C137_GC24_K2_P03_CI_B12_FAILURE_REVIEW.md` (blob `88e26c810b6e5c2b8d5984e8054ca87d2783ad37`).

## 2. Confirmed source impact map (read-only inspection)

| Surface | Frozen Git blob at source base | Relevant fact |
|---|---|---|
| `poultry/layers_daily_record.php` | `87b7c6ace9bd577ad2303ea4aa50312a780412d2` | Existing row lookup ~270–279 before transaction ~281; unconditional target UPDATE ~285; continuity -> feed -> mortality -> egg-sale rebuild. |
| `poultry/broiler_daily_record.php` | `2a797c7fec45d3f78e7b2f01cb72531b1c416699` | Existing lookup ~247–256 before transaction ~258; target UPDATE ~262 has no loaded-original revision check; continuity -> feed -> mortality. |
| `ruminant/ruminant_daily_record.php` | `27c6755f0ef9491a7019f94b6a62b602e8e8dcaa` | Existing lookup ~413–419 before transaction ~421; target UPDATE ~424 lacks loaded-original revision; species is part of scope; tagged deaths handled through registry, not Daily Record mortality. |
| `api/get_record.php` | `05c1ef422fc7df908cc484846e59e042fdb1d282` | Farm-scoped GET of saved rows; today returns original table columns and no explicit revision token. |
| `assets/js/layers-daily-record.js` | `cf4a24803935b61046f8856c4ee220ed71146fee` | Fetches saved record, populates modal, submits form; no revision token handoff. |
| `assets/js/broiler-daily-record.js` | `d0f860e78983a96557ebb449c286d53593eb6692` | Same client-side state issue. |
| `assets/js/ruminant-daily-record.js` | `e9d46ca50972ce39068bd1a40174ea6665427e65` | Same client state issue, plus animal_type scope. |
| `api/delete_record.php` | `4a3b8d03b565f65c4a103e9f1fae304d6ac75fda` | Layer/Broiler delete routes through feed reversal, mortality projection removal, then source DELETE; no editor revision guard. |
| `lib/daily_population_continuity.php` | `9503771b47c6c2fe26bb3237da69a17874e724a3` | Later rows locked ordered date/id and changed with old opening comparison, but target source stale revision unprotected; system-derived later opening/laying may invalidate users editing later rows. |
| `lib/daily_feed_sync.php` | `752707749fea0583249ec600bb5d624d416b8a47` | Explicit authoritative Daily Record -> stock transaction lock order and saved cycle verification. |
| `lib/stock_consumption_allocation_persistence.php` | `c94daf5b66b7ec2051f0f6fb8bd6aedef48d6172` | External allocation flow pre-reads movement, then locks saved Daily Record -> source stock transaction -> target cycles -> allocation state/revisions. |
| `lib/production_population_projection.php` | `c4cdae4c193b624a7469915e0451d41fef1b2f4b` | Source snapshot then ordered cycle locks, source movement lock, ordered baseline locks and projection sync; distinct lock contract. |
| `lib/production_population.php` | `fff1b3e663bb0845c34ccf734a52f6c92eaae706` | Canonical movement writer documents cycle -> baseline ordering. |
| `lib/sales_allocation.php` | `1a701fff059189b6a9ad3fe1a190188be958a96b` | Layer sale rebuild walks sales chronologically, locks each sale parent and protected allocation state inside transaction. |
| `database_schema.sql` | `e77f287d62c5812a3d55dc23b5b20f5d167db389` | Layer/Broiler unique (cycle_id,record_date); Ruminant unique (record_date,animal_type,cycle_id); ALL cycle_id nullable and **no Daily Record revision column**. |

**Verified (source):** all three existing target save UPDATEs lack an editor-supplied expected previous version/value. **Verified (runtime):** Layer representative concurrent source route logic permitted a silent stale overwrite. **Inference, not tested:** Broiler/Ruminant could exhibit the same risk; they require focused synthetic verification before any runtime certification.

## 3. Recommended shared contract: optimistic source row revision

Implement one reusable module (`lib/daily_record_write_authority.php` is a candidate name, not an existing file) with a closed map of supported types, trusted table/field identities, tenant scope and allowed persisted columns. Keep PHP pages thin and preserve their validation/permissions/CSRF and module-specific derived calculations.

**Storage design proposal:** add a monotonically increasing unsigned `record_revision` with default `1` on all three Daily Record tables using a separately reviewed migration (not this design commit). Use a typed integer revision rather than unreliable timestamp precision, field-only fingerprints or an unsigned client row hash. Backfill existing rows to revision 1; preserve a strict no-null invariant. Protect against overflow and document exact MariaDB compatibility. Source versions in `production_population_movements` are projection versions and MUST NOT substitute for Daily Record revisions.

**Read/intent protocol:**
1. Existing-record GET responses expose both immutable `id`, farm/cycle/date/species scope, and `record_revision` as the trusted editor snapshot. The modal retains `expected_record_id` and `expected_record_revision` as opaque edit intent; reset both on date/cycle/species changes, form reset, failed fetch or creation mode. Never accept a forged revision without subsequent scoped DB validation.
2. Form save explicitly differentiates `create` (no saved row at load time) from `edit` (requires both id and loaded revision). Missing/stale revision for edits fails closed with a user-facing conflict and form data retained. A server-side row read freshly taken **at submission time** is NOT the expected client version; doing that would reproduce the B12 lost update.
3. On edit, inside a single caller-owned transaction, resolve immutable row by `farm_id` + `id` and verified cycle/date/normalized species; acquire target row lock in established **source-first** order; compare the presented revision with the latest locked DB revision and abort on inequality. Use a conditional write with `WHERE id=? AND farm_id=? AND record_revision=?` PLUS immutable scope checks, and `SET record_revision=record_revision+1` with existing module-specific data values; require exactly one affected row. If value unchanged, do not silently skip conflict/contract checks or return a false success. Return new revision to the caller only after committing.
4. Only after that target CAS succeeds run original `daily_population_continuity_apply -> sync_daily_feed_usage -> daily_population_sync_mortality -> (Layer only) sales_rebuild_layer_egg_allocations` in ONE transaction. Any failure rolls back the CAS increment and all downstream ledger writes. No automatic stale-edit merge or background overwrite.
5. On create, explicitly require absence at the correct (farm, cycle, date, species) identity, then INSERT under correctly established concurrency constraints; duplicate/race errors map to a safe 409-style conflict, never to "update whichever row exists." For active nonnull cycles, respect the existing unique keys; for nullable legacy cycles, **inspect and settle effective uniqueness separately**. In MariaDB, UNIQUE permits multiple NULL key members; the existing unique keys do NOT close same-day legacy duplicates. Do not invent a fresh record id when selected cycle identity is ambiguous.
6. On delete, include expected source revision as an explicit intent; lock the source first, verify farm/role/CSRF/identity and expected revision before feed reversal / mortality projection removal / automatic egg rebuild; delete conditionally with revision and require one affected row, all within the caller transaction. Treat delete-versus-edit/create race as a conflict, not a successful stale delete.
7. System-owned later-record corrections (continuity changes to opening_stock and Layer laying_rate) MUST increment each affected row's revision **once**, under existing sorted later-row locks. Preserve current old-opening-stock optimistic equality predicate and rowCount assertion; avoid incrementing unchanged rows. Consequently an editor with a modal already open for a later date sees a safe conflict after an earlier historical correction.
8. Other writers mutating Daily Record authoritative columns, source reassignment, or delete must use the same revision contract. Enumerate them by direct calls/SQL before a code change. Do not treat their unrelated movement ledger revision counters as equivalent.

**Safety semantics:** no app-level access bypass, no SQL table names from POST, no trusting input farm_id, no unscoped id lookup, no assuming a date uniquely identifies legacy rows, no optimistic "save regardless" override. Preserve user-entered inputs on 409; show styled "This record was changed by another user. Reload before saving." For an authorized deliberate overwrite, explicitly reload and review the current record, then submit a NEW current revision as a separate action (not automatic retry).

## 4. Lock-order boundaries: preserve existing contracts, investigate cross-domain contention

Source inspection identifies **multiple** lock graph conventions:
- Daily Record write/Feed path: **target Daily Record -> later dated Daily Record rows -> stock movement/item -> canonical source projection/cycles/baseline -> (Layer) sale allocation**. The exact later subgraph and stock allocations must be validated in a bounded regression.
- Consumed-stock allocation persistence: **authoritative Daily Record -> stock transaction -> target cycles -> allocation projections/revisions**.
- Direct canonical population movement: **production cycle -> baseline -> movement**.
- Mortality projection: active source pre-read -> sorted cycles -> active source lock -> sorted baselines -> movement/version.

**Do not unilaterally move cycle locking ahead of all Daily Record saves**: external consumed-stock allocations deliberately acquire Daily Record before cycle. Such a unilateral change could invert existing order. Conversely, mixed direct cycle writers versus Daily Record-led writers may already have a lock-inversion hazard, but this was **not demonstrated by B12**. Log this as a distinct concern requiring targeted source-graph and (separately authorized) concurrent MariaDB evidence before modifying lock ordering.

Minimal safe patch scope for the **demonstrated same-target lost update** is shared version/CAS source authority, wired to the three routes + read/forms + affected delete/continuity paths, without rearranging independently owned population/feed/sales financial logic. Preserve sorted later-row locks and all tenant identity predicates. No deadlock retry should cause user data to be overwritten.

## 5. Schema / legacy / deployment decisions requiring gated investigation

- New migration for three revision columns, backed by a full exact-schema inventory, data validation, staged deployment order and rollback. **No migration number claimed** until migration directory and live schema version are separately verified; current design does not modify `database_schema.sql` or run SQL.
- Nullable cycle identity: resolve active cycle selection, legacy rows, duplicate legacy identities and cross-farm keys before enforcing any new unique constraint. An existing record with `cycle_id IS NULL` must be selected by trusted `id` and farm and must not accidentally collide with a similarly dated current-cycle record.
- Existing APIs `api/get_record.php`, `api/check_record.php`, `api/check_ruminant_record.php` must have consistent scoped behavior, authorization and unambiguous edit intent. Existing client modals must clear old revision values on asynchronous race/date or species switch; ignore stale async replies that no longer match selected scope.
- The Layer/Broiler/Ruminant pages presently run module-specific validation/derived calculations; retain those rules. Shared service should centralize authoritative row identity, create/edit/delete mode, revision checks and SQL policy. Do NOT duplicate new permission, pricing, validation, or visual policy in each route.
- Explicit rollout contract: migration compatibility window; old forms without revision cannot silently edit after enforcement; preserve user submissions and offer reload. Do not ship half-migrated code; separately verify schema identity/permissions and prepare rollback plan. No runtime source change is authorized by this document.

## 6. Next isolated regression matrix — all NOT RUN

Use a **new branch** and a **new disposable CI schema/one-use approval** after separately authorized source implementation. B12 original run 38233373699 remains FAILED and consumed; never rewrite, reset, rerun, or mark PASS. Proposed scenarios:

1. **B12-R1 same-target two editors:** two independent sessions read revision 1, A commits revision 2, B waits then must yield explicit stale conflict without persisted B writes. Confirm one authoritative source and whole downstream reconciliation, no partial B movements, and separate tenant unchanged.
2. **Fast sequential stale client:** A completes before B submits; B's old revision still conflicts (locking alone is not sufficient).
3. **Different date same cycle:** correction to early row bumps later row revision; open later-date form from before correction must reject safely. Verify chronological locks and canonical movement reconciliation.
4. **Two concurrent create attempts:** same active cycle/date (and Ruminant species) yields exactly one row, one conflict; legacy null-cycle identity ambiguity handled explicitly, not masked by MariaDB nullable UNIQUE.
5. **Edit vs delete:** both orders, including feed reversals and mortality source version/reversal. No orphan USED, no duplicate population reversal.
6. **No-op edit and retry:** unchanged payload with stale revision still conflicts; never hide stale UI edit under an idempotent query.
7. **Mutation-rollback injection:** malformed feed, insufficient inventory, invalid later egg/mortality, simulated downstream allocation failure => entire source revision/row/ledger rolls back; no double mortality.
8. **Cross-farm + wrong role + CSRF + wrong species/cycle/record id:** reject without revision probing or row changes, preserving prior B10 authority contract. A client cannot forge farm scope.
9. **Layer-specific egg auto allocation and Broiler/Ruminant-only paths:** no accidental Layer rebuild for others; consistency with Slaughter, Sale, Transfer and tagged ruminant exits.
10. **Lock-order negative race:** direct population movement vs Daily Record edit and consumed-stock allocation vs Daily Record correction; bounded timeouts, no user-data overwrite, no unapproved automatic deadlock retry. Design separate narrow fix if a genuine inversion is found.
11. **All-modes client contract:** new vs edit, modal async stale-response cancellation, revision hidden input reset, conflict UI/form preservation and date/cycle/species identity. Authenticated browser checks remain a separate E2E gate.

Acceptance: exactly one safe authoritative edit or explicit, user-visible conflict; downstream stock/mortality/egg allocations intact; tenant isolation; deterministic failure reporting; no stale silent overwrite. CI must report actual assertion counts, clean disposable environment and exact SHA. No claims for tests not actually executed.

## 7. Controlled implementation sequence; NOT authorized by this design

1. Independent source-only review of all SQL writers, migration inventory, cross-domain lock graph and tenant/actor permissions. Resolve ambiguity before code.
2. Separate **new** branch pinned to this design's exact reviewed HEAD, frozen production blobs and **minimal planned migration/shared-service/routes/API/JS** patch. Do not modify old approved/failed B12 branch.
3. Review full remote semantic diff and focused static checks; do not copy page-local SQL. Resolve all test/source pin changes explicitly before test execution.
4. Prepare a **separate dormant, new** synthetic MariaDB regression workflow. Do not invoke it with original B12 approval.
5. Obtain fresh **explicit exact-branch/HEAD one-run** authority naming disposable test schema and exclusivity, no reruns and no protected infrastructure. Only then invoke a one-use approval commit.
6. If the new run fails, stop and preserve real failure evidence. If it passes, evaluate whether other validated concurrency paths and browser coverage are still pending; certify ONLY the executed scope. Any staging/production deployment or migrations require their own separate authorization.

**Protected gates remain unchanged:** all fifteen D12 import gates BLOCKED; 150 VU HOLD; 200 VU PROHIBITED; Stage D NOT AUTHORIZED. No AWS/cPanel/staging/production access, D12 fixture, restore DB, backups or external integrations are part of this work.
