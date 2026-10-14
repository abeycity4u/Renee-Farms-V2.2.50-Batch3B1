# C137 / K2-P03 B12 — WS12 Source-readiness and certification-gap decision

**Date:** 2026-10-14. **Status:** SOURCE-ONLY AUDIT; repaired B12 is NOT certified; cutover HARD-OFF. No new test or runtime execution in WS12.

## 1. Immutable source and evidence boundary

| Guard | Pinned evidence |
| --- | --- |
| Reviewed source branch | c137-gc24-k2p03-b12-ws11-fail-closed-browser-source |
| Reviewed HEAD | e3ac48a0f13a3febccfe3bb395aa9b07e2240e6a |
| WS12 audit branch | c137-gc24-k2p03-b12-ws12-certification-gap-audit-source |
| Protected original B12 branch | c137-gc24-k2p03-b12-cas-writer-cutover-source at 2327a22a3cd7a95ffea42fc76dd94805f5b1e7af |
| Protected production branch | v320-renee-agrisuite-branding at 10041da3a3c4e4a7b8101f8183c243d056c7b128 |
| Cutover fuse | lib/daily_record_revision_cutover_gate.php, blob 6e88c913ad5e5b371a9d0c92ce08870214ac1268, literal return false |
| Historical consolidated offline runner | scripts/verify_k2p03_b12_ws07_offline.sh, blob a1550c1ff9b6aa1ab05f45cafc4cd59c8e9f4bdb, unchanged |
| Historic B12 lost-update test | GitHub Actions run 38233373699, attempt 1, FAILED TWO_STALE_COMMITS; one-use approval consumed |
| Historic synthetic schema R2 | GitHub Actions 38301126240 PASSED on fabricated disposable MariaDB rows only |
| Historic synthetic FK metadata attestation | GitHub Actions 38454255713 PASSED on disposable MariaDB only |
| Historic first synthetic schema run | GitHub Actions 38299970527 FAILED; must not be relabeled |

The original Mac checkout and its pinned HEAD were reported guarded/unchanged in user-supplied WS07–WS11 offline logs; remote GitHub source heads above were independently checked in WS12. No statement that customer schema or runtime is identical to the source snapshot is warranted.

**Legend:** SV = source-verified/isolated fake PDO or browser VM, not real application. SX = historical synthetic MariaDB evidence only. U = unverified. B = release-blocking prerequisite. RC = real concurrent connection certification (not performed for repaired B12).

## 2. Seven recognized writer categories and coverage

The seven paths are a recognized direct-source inventory; it does not prove absence of dynamically constructed SQL, triggers, external jobs or off-host clients. See docs/C137_GC24_K2_P03_B12_SEVEN_WRITER_SOURCE_AUDIT.md.

| Writer | Current B12 prepared route and shared authority | Assessment |
| --- | --- | --- |
| W1 Layer daily save: poultry/layers_daily_record.php | Gate-conditional JSON save via lib/daily_record_revision_route_adapter.php → save pipeline → exact farm/row/revision CAS; feed, mortality, continuity, pooled Layer egg sales | SV source/pure checks; B real RC01/02/09/11 and HTTP |
| W2 Broiler daily save: poultry/broiler_daily_record.php | Same shared save pipeline, Broiler-specific business facts | SV; B RC03 and downstream business effects |
| W3 Ruminant daily save: ruminant/ruminant_daily_record.php | Same shared route with cycle-species validation, group mortality and feed | SV; B RC04/species and delegated-role E2E |
| W4 Poultry daily DELETE: api/delete_record.php | Gate-conditional lib/daily_record_revision_delete_pipeline.php; source-rendered original-row DELETE tokens, tenant/permission/CSRF | SV; B RC07 and real auth/409 |
| W5 Ruminant DELETE: lib/ruminant_daily_record_deletion.php, called by privileged page and includes/permission_runtime.php | Both share one canonical future transaction and JSON/409 policy; original form tokens and revision-aware AJAX binder | SV; B RC07/08 privileged/delegated, feed/mortality reversal |
| W6 Later-row continuity: lib/daily_population_continuity.php | Future lib/daily_record_revision_continuity_apply.php bumps only genuinely changed durable later-row revisions | SV; B RC09/11/12 and live downstream consistency |
| W7 Production-cycle first Daily Record: management/production_cycles.php | Future lib/daily_record_revision_cycle_seed.php uses caller transaction and revision=1 initial record | SV; B RC10 seed retry, rollback and baseline |

Cross-cutting core inspected: lib/daily_record_revision_authority.php performs tenant/farm/immutable-scope row locking and conditional revision increment; lib/daily_record_revision_parent_lock.php uses farm → selected cycle → source lock; lib/daily_record_revision_create_guard.php does a current locking absence read after acquiring parents. The source-only gate prevents this from becoming a deployed proof. Existing stock allocation and population projection lock graphs may differ: RC12 is necessary. Platform Owner deletion in management/farms.php is an explicit tenant-purge exception, not a normal eighth editor; independently inventory dynamic SQL/trigger/external writers.

## 3. Browser and HTTP readiness matrix

| Requirement | Source evidence | Still missing |
| --- | --- | --- |
| Exact tenant-scoped scope GET → exact GET, original immutable edit ID/cycle/date/species/revision, no failed EDIT → CREATE fallback | SV: api/daily_record_revision_read.php; API adapter/reader, modal, editor, transport and fake-fetch contracts | B: genuinely authenticated requests, status/role parity |
| Layer/Broiler/Ruminant gate-conditional asset order and exclusive browser ownership | SV: lib/daily_record_revision_page_switch.php; WS08B/WS10 isolated PHP rendering and Node browser VM | B: full real browser, Bootstrap, static asset availability and permissions |
| All Cycles is navigation-only and never implicit legacy NULL-cycle editing | SV: WS09 and read-only navigation module; future ON suppresses DELETE controls | B: authenticated real UI/navigation, no misleading editor affordances |
| Derived Layer rate/crates, feed selector, Ruminant unit and population side panel | SV: shared fields/lifecycle pure tests | B: realistic browser/data integration parity |
| SAVE conflict, duplicated submission, stale asynchronous callbacks, draft protection | SV: shared sender/editor and source/fake-DOM verifiers | B: genuine AJAX, redirects, network errors and draft retention in a browser |
| DELETE confirmation/409, exact prior revision, delegated Ruminant | SV: shared source/Node tests, no native data-confirm when future ON | B: actual token, roles, browser and durable reversal tests |
| Failure during JS initialization cannot expose normal user-interactive native forms | SV: WS11 future forms and Ruminant DELETE forms start inert; unlocked after binder listeners attach; OFF unchanged | B: real browser behavior and server-side AJAX-only enforcement, since inert is not security authorization |

### WS12 NEW, SOURCE-CONFIRMED RELEASE BLOCKER: inconsistent save transport

The staged save dispatcher is behind the hard-OFF fuse, but each active page executes several checks **before** reaching that dispatcher:

- poultry/layers_daily_record.php around lines 163–235: CSRF/date/cycle/numeric validation may emit an HTML 419 or Location redirect before future JSON dispatch.
- poultry/broiler_daily_record.php around lines 153–213: same pre-dispatch redirect/HTML behavior.
- ruminant/ruminant_daily_record.php around lines 318–383: same, including animal type and cycle checks.
- lib/daily_record_revision_route_adapter.php around lines 268–313: daily_record_revision_route_dispatch_json creates a JSON response *after* being called, but does not enforce an AJAX-only or Accept/X-Requested-With precondition before committing its transaction.

This is a **source-confirmed integration-contract gap**, NOT proof of a deployed vulnerability (the gate remains OFF). It differs from Ruminant DELETE, whose shared lib/daily_record_revision_delete_http.php already rejects native non-AJAX DELETE requests. Under a future ON page, a direct native save POST must not perform a write or silently redirect; an inert HTML form alone is not a server-side enforcement boundary. **Next source-only patch WS13 should implement one shared conditional AJAX-only/consistent JSON save-validation boundary** while leaving the legacy gate-OFF behavior and every existing role/tenant/CSRF check intact. Test 401/403/409/419/422/500/503, HTML/redirect/network denial and saved-draft behavior; do not activate the cutover.

## 4. Database schema, NULL identity and migration dependencies

| Gate | Evidence and disposition |
| --- | --- |
| Frozen source schema | SV: database_schema.sql blob e77f287d62c5812a3d55dc23b5b20f5d167db389 lacks three record_revision columns; has nullable cycle_id, unique keys allowing repeated NULL tuples and cycle foreign keys ON DELETE SET NULL. This does not describe actual currently deployed DB state. |
| Proposed revision migration | B: lib/daily_record_revision_schema_plan.php and docs/C137_GC24_K2_P03_B12_RECORD_REVISION_MIGRATION_DRAFT.sql.txt describe BIGINT UNSIGNED NOT NULL DEFAULT 1. There is NO approved/applied 092 migration and no accepted 3-table rollout/partial-DDL recovery. |
| Legacy NULL-cycle and logical keys | B: real tenant duplicate/ambiguity, cross-tenant link, orphan and trigger counts UNKNOWN. Need domain rule on normalized legacy identity, Ruminant species and cycle deletion before new uniqueness. |
| Synthetic DB data hazards | SX: R2 fabricated rows proved nullable-cycle duplicates, cross-tenant links and extra collisions caused by ON DELETE SET NULL. Do not infer any protected customer count. |
| FK metadata | SX: read-only SELECT grants can hide referential_constraints rows; 38454255713 independently attested fixed-identifier SHOW CREATE TABLE fallback in synthetic DB only. |
| MariaDB DDL safety | B: DDL implicit commit means three independent ALTERs are not atomic; exact pre/postconditions, partial apply recovery, schema history and rollback approval required. |
| Required next database evidence | U/B: separately authorized **read-only** scan of an independently attested physical isolated clone, using docs/C137_GC24_K2_P03_B12_READONLY_SCHEMA_INVENTORY.sql and scripts/k2p03_b12_schema_readonly_gate.php with approved SELECT-only grants and aggregate, nonidentifying output. No clone or SQL access authorized by WS12. |

## 5. Actual race certification status

No repaired RC01–RC14 scenario has an authorized runtime PASS. tests/integration/k2p03_b12_repaired_rc01_real_mariadb.php remains an intentionally hard-disabled source draft (B12_RC01_EXECUTION_ENABLED=false), NOT an executed certification. The original Actions failure 38233373699 with TWO_STALE_COMMITS remains FAILED.

| RC category | Required real-connection outcome | Status |
| --- | --- | --- |
| RC01–02 | Layer EDIT/EDIT one commit, one 409; saved revision +1 and exactly-once feed/mortality/continuity/sales | B NOT RUN |
| RC03–04 | Broiler and Ruminant original-token stale writers, isolated species and downstream facts | B NOT RUN |
| RC05–06 | Tenant/cycle/species isolation and CREATE/CREATE including nullable legacy keys | B NOT RUN |
| RC07–08 | EDIT/DELETE and duplicate DELETE; privileged/delegated Ruminant reversal | B NOT RUN |
| RC09–10 | Later-row revision bump, cycle seed concurrency and retry rollback | B NOT RUN |
| RC11–12 | Failure injection rollback and cross-module lock contention/deadlock | B NOT RUN |
| RC13–14 | Legacy NULL ambiguity and cross-tenant denial; no-op/overflow/type violations | B NOT RUN |

Required future evidence: separately approved isolated physical DB identity/grants/schema; **two truly independent MariaDB connections** with identical original loaded revision prior to deterministic race barrier; production shared service execution; exactly one commit plus explicit loser conflict; persisted revisions, downstream-ledger and rollback snapshots; bounded waits and thorough teardown. Source PHP/fakePDO/Node VM tests do not replace it.

## 6. Closed source-only work (per-phase counts, not additive runtime claims)

| Phase | Latest user-supplied verified evidence |
| --- | --- |
| WS01–WS05B and WS06 | Source/pure CAS, cycle/species, parent locks, DELETE/continuity and seven recognized writer audit |
| WS07 | Consolidated pinned offline run: 24 PHP lints, 32 PHP tests, 13 Node tests; 69 PASS, 0 failures |
| WS08A | Global Layer calculator isolation; 6 assertions PASS |
| WS08B | Gate-conditional three-page integration; 5 PHP lints, 8 PHP suites PASS |
| WS09 | Integrated browser contracts and All Cycles; 6 PHP lints, 10 PHP suites, 14 Node suites PASS |
| WS10 | Isolated ON-only PHP fragment simulation 29 assertions plus emitted fake browser VM 19 assertions; 48 PASS |
| WS11 | Failure-path inert form / DELETE; 6 PHP lints, 4 PHP suites, 4 Node suites + browser VM PASS; isolated PHP fixture 36 assertions |

Do not aggregate cross-phase assertion counts as repaired B12 runtime certification or claim a fresh whole-suite pass on this HEAD. No previously closed QA should be redundantly rerun absent regression evidence.

## 7. Decision and exact next steps

**SOURCE_READINESS=PARTIAL; REAL_SCHEMA_DATA=UNKNOWN; B12_RUNTIME_READINESS=HOLD; CUTOVER_GATE=HARD_OFF; MIGRATION=UNAUTHORIZED; REPAIRED_RC01_RC14_CERTIFICATIONS=0.**

1. **WS13, next source-only task:** patch the shared SAVE transport/error boundary confirmed in section 3. Trace existing CSRF/tenant/permission and pre-gate validations across three pages. Keep OFF code user-visible behavior unchanged; ensure ON rejects native submit before mutation, and failures consistently emit JSON without losing draft. Focused pure/offline tests and remote semantic diff only.
2. Reconcile remaining source decision items: external/dynamic writer inventory, cycle deletion/NULL identity candidate policies, migration partial-apply recovery, detailed roles and lock-order contracts. Do not speculate actual customer data.
3. Request a **fresh, narrow, explicit authorization** before *any* physically isolated clone creation or read-only actual-data schema inventory. Verify physical host, origin, identity, credentials/grants and approved SQL SHA; use fixed FK metadata fallback and aggregate-only reports.
4. Only after aggregate evidence, settle legacy NULL/cycle deletion rules; design 3-table revision DDL plus coordinated seven-writer migration/rollback; separately authorize migration against an isolated target. Never rename or execute existing .sql.txt draft as a migration.
5. Obtain a new independent one-use approval for the real MariaDB RC01–RC14 run, with exact harness commit, independent workers, finite timeouts, rollback/teardown and explicit failure classification. Do not reuse/rerun original failed Actions authorization.
6. Separately authorize authenticated browser E2E for all species and representative roles with real HTTP status matrix, original row tokens, CSRF, native fallback denial, 409 draft survival, population/feed/egg-sale parity and single listener ownership.
7. Review all evidence and source/runtime integrity; only then request formal release approval, migration plan, rollback, production protection and future coordinated gate change. D12 15 import gates BLOCKED; Stage D UNAUTHORIZED; 150 VU HOLD; 200 VU PROHIBITED.

## 8. Method and limits

WS12 used pinned GitHub read-only source and historical evidence documents and user-supplied closed offline outputs. No SQL, CI workflow, source verifier reruns, native/browser HTTP, AWS/cPanel, staging/production, migration, database clone, protected fixture, capacity test or gate activation was performed. This is a documentation audit, not a product change or repaired B12 certificate.

Prior planning docs may still say the conditional page switch was NOT IMPLEMENTED because they predate WS08B–WS11. Read the current pinned PHP and JS implementation for present **dormant source** state; do not mistake staged future ON paths for deployed live functionality. Primary references: docs/C137_GC24_K2_P03_B12_REPAIRED_CAS_CERTIFICATION_CONTRACT.md; docs/C137_GC24_K2_P03_B12_SEVEN_WRITER_SOURCE_AUDIT.md; docs/C137_GC24_K2_P03_CI_B12_FAILURE_REVIEW.md; docs/C137_GC24_K2_P03_B12_SCHEMA_EVIDENCE_R2_SUCCESS.md; docs/C137_GC24_K2_P03_B12_FK_READONLY_DDL_ATTESTATION_PASS.md; docs/C137_GC24_K2_P03_B12_DATABASE_EVIDENCE_GATE.md; and current source files cited above.
