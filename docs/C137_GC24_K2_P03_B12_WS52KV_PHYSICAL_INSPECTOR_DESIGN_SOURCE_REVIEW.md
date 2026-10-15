# C137 / GC24 / K2-P03 / B12 — WS52-KV hard-disabled physical-inspector DESIGN contract

**Date:** 2026-10-15. **Stage:** SOURCE_PREPARED_ONLY. **Physical Docker host/volume inspection: NOT PERFORMED.** The code is an observation-plan shape validator, not a real inspector, Docker adapter, SQL runner, or cleanup mechanism.

## 1. Exact lineage / pinned GitHub scope

- Repo: abeycity4u/Renee-Farms-V2.2.50-Batch3B1.
- WS52-KU predecessor branch: c137-gc24-k2p03-b12-ws52ku-volume-contract-offline-pass-evidence-source.
- Exact WS52-KU parent HEAD: 70817ab14f820e9da8304e8b8dd126ce8bf0a7d0.
- New branch: c137-gc24-k2p03-b12-ws52kv-physical-inspector-design-disabled.
- New pure design source: tools/b12_ws52kv_physical_inspector_design_contract.py, Git blob f6255fca70b6bdf959e77caf2b7a813c1afc0514.
- New 22 authored but NOT EXECUTED fixture-only tests: tests/test_b12_ws52kv_physical_inspector_design_contract.py, Git blob 03e6c065eddcac3e67211d5ee486fee40999c931.
- Reused existing WS52-KT one-volume shape contract, unchanged: tools/b12_ws52kt_one_volume_identity_cleanup_contract.py, Git blob e3c6b7b79bb979902b9d610234b6fd84f2120c2d.
- Previously preserved WS52-KT user-returned offline 20/20 PASS: docs/C137_GC24_K2_P03_B12_WS52KU_WS52KT_VOLUME_CONTRACT_OFFLINE_20_OF_20_PASS.md, Git blob 108a321b508da1496c79b0199caa9bcc7c60c524.
- Protected original B12: c137-gc24-k2p03-b12-cas-writer-cutover-source @ 2327a22a3cd7a95ffea42fc76dd94805f5b1e7af.
- Protected production: v320-renee-agrisuite-branding @ 10041da3a3c4e4a7b8101f8183c243d056c7b128.
- Literal B12 gate blob 6e88c913ad5e5b371a9d0c92ce08870214ac1268 and shared CAS authority blob 5fbb90903b0cc801be45c684f6c3db85c660979a remain unchanged.
- Existing WS52-KH policy blob f5f9b82453fae41cc8bcc070414d988f052c5e14, preliminary hard-disabled GH RC01 candidate blob e78318509e270ccd24684bf41f5e854b74765f76 remain unchanged.

## 2. Purpose and trust boundary

The earlier WS52-KS review identified the actual user-returned, SHA256-checked MariaDB config declares VOLUME /var/lib/mysql (exact config blob sha256:41096ae9913468679bb5e05bbc19f44edcaf0eae45d67fcde224ffdeda9d3a1f). Existing WS52-KH rejects any mount and has no exact physical volume-ownership proof. WS52-KT was therefore authored and user-executed fixture-only 20/20 PASS with a replacement **SOURCE-ONLY** single-volume shape model, but no Docker host inspector.

WS52-KV adds a separate read-only INSPECTION DESIGN SEQUENCE only, reusing WS52-KT assess_postcreate_shape and assess_cleanup_shape without duplicating their volume/mount cleanup rules. It imports only standard-library dataclasses/typing/re and the WS52-KT module. It has no CLI/main, Docker SDK, Docker API, shell/subprocess, filesystem, HTTP/network, SQL or mutation operations. Its *read-only intents* are NONEXECUTABLE labels and are NOT executable allowlisted Docker commands.

All flags INSPECTOR_EXECUTION_ENABLED, DOCKER_MUTATIONS_ALLOWED, SQL_ALLOWED, RC01_ALLOWED are literal False; PHYSICAL_INSPECTOR_APPROVED and ONE_USE_RUN_APPROVED are None. Every returned DesignResult.physical_attested, execution_authorized and cleanup_certified stays False by construction. Fixture-shaped dicts and collector strings may be fabricated and MUST NEVER be treated as signed or actual engine evidence. Thus even a positive DesignResult.shape_valid is only SOURCE_INSPECTOR_DESIGN_ONLY_NO_PHYSICAL_PROOF.

## 3. Six future physical observation checkpoints — DESIGNED, NOT RUN

| Order | Required checkpoint | Future independent physical evidence required | Stop conditions |
|---|---|---|---|
| 0 | SOURCE_PIN | Exact source commit/branch; one-use reviewed approval; immutable OCI index/child/config and Git blobs; hard-off release gate, ephemeral GitHub runner claim independently verified | Unknown ref/approval, mutable image or source drift |
| 1 | ENGINE_PRESTATE | Independent GitHub-hosted runner engine fingerprint; all pre-existing containers/volumes, relevant bind port listeners and protected resource inventory before creation | Existing resource collision, unreviewed Docker host, ambiguity or missing inventory |
| 2 | POSTCREATE_PRE_SQL | Actual Docker inspect config and effective Mounts, one NEW per-run container + one NEW uniquely named writable local volume bound only to /var/lib/mysql; inspected identities/labels/drivers/source fingerprint; zero additional/anonymous/host mounts | Unexpected mount, reused/protected volume, extra container, privileged mode or conflicting identity |
| 3 | NETWORK_GUARD | Independent listener and firewall/egress observation; loopback-only TCP 127.0.0.1:3306; no public exposure, uncontrolled outbound bridge egress or network privilege bypass | Bridge mode asserted without real egress restriction, public port, host mode or spoofed observer |
| 4 | PRE_TEARDOWN_QUIESCENCE | Independent worker/observer/session and transaction examination, exact synthetic fixture identity and authorization; all processes bounded, stopped/quiescent before any exact-ID cleanup | Worker/transaction lives, unknown DB, PROCESS/observer privileges unreviewed |
| 5 | POSTCLEANUP | New exact container and volume independently absent from real Docker inspection; original engine container/volume inventories restored; zero synthetic rows, barrier artifacts, open transactions, workers; sanitized postcheck evidence | Any lingering unknown volume/container/process/row, identity drift or incomplete teardown => quarantine/manual review |

**Critical ordering:** Checkpoint 2 must occur after separately authorized container creation but BEFORE any SQL. Checkpoints 4–5 cannot happen in the current source-only stage; their inclusion here is a future evidence DESIGN, NOT a declaration of a past runtime or authority to create/delete anything. The designs require independent observers, but collector labels are only fictional field constraints and do not physically authenticate observers.

## 4. Exact source-only validation contracts

- Closed top-level plan keys and six phase record keys; no raw signed CDN URLs, secrets, unrestricted mount paths or unknown metadata keys. Redacted fingerprints may be only sha256-formatted strings; this does not validate their authenticity.
- Fixed order, stage intent and observer-class labels, exact pinned repository/WS52-KU parent/runner/engine/run IDs and resource-ID lifecycle; container and volume IDs intentionally blank before provisioning, pinned after.
- Fixed list of nonexecutable read-only observation intents. Injected Docker rm/prune/run, SQL writes or reordered requests are rejected at shape level.
- Delegation to WS52-KT: before/after container/volume inventories; exactly one new run-scoped local named volume; sole writable effective /var/lib/mysql mount; protected/preexisting volume rejection; exact pre/post identity link and full inventory restoration.
- Strictly NOT_EXECUTED_SOURCE_ONLY status on every fictional observation stage; attempts to mark one physically executed are refused. Design shape validation cannot become physical certification merely from a status string.
- Safe decision may be SOURCE_INSPECTOR_DESIGN_ONLY_NO_PHYSICAL_PROOF or BLOCKED_MANUAL_REVIEW. No route to RUNTIME_APPROVED, MYSQL_CERTIFIED, RC01_PASS, VOLUME_REMOVED_VERIFIED, migration 092, B12 cutover or external host commands.

## 5. Unit-test scope (22 authored, NOT EXECUTED)

Offline cases cover a structurally valid fictional sequence (still no attestation), literal hard-disabled execution flags, parent/repo/scope drift, unknown keys, mutating plan labels, intent order drift, missing/excess phases, swapped stages and observer roles, noninteger/duplicate ordinals, false executed claims, leaked raw URL field, engine/run/source drift, unexpected early container ID, postcreate resource mismatches, malformed redacted fingerprints, extra anonymous volume, wrong mounts/public port, cleanup residue, safe-decision refusal of fabricated claims, and AST import checks against runtime Docker/network/SQL imports.

**No 22/22 PASS is claimed** until separately user-returned, source-blob-verified fixture-only execution occurs; previously closed WS52-KT 20/20 remains valid and does not need re-running without regression evidence.

## 6. Distinct outstanding gates before a real physical inspector or RC01

- WS52-KV contains no actual Docker calls. A later collector/source must be separately reviewed, explicitly authorized and constrained to a truly ephemeral host and read-only inspections; its raw data must not be accepted as proof merely because a dict matches these shapes.
- Physical authority must bind actual image ID, manifest digest, Docker Engine fingerprint, observed container ID, exact freshly created volume name and volume Mounts to the same one-use GitHub job and independent observer. A source-only fake sample is never sufficient.
- Future mutable operations (Docker pull/create/start/stop/remove, volume create/remove, real SQL, 2-worker RC01) require distinct one-use source/fixture/cleanup authorization. No approval from a generic 'proceed' in a source-only review.
- The existing GH RC01 PHP candidate is HARD_DISABLED. Real legacy production-derived backup restore is NO_GO, legacy NULL-cycle/cross-farm FK audit remains incomplete, and original run 38233373699 remains FAILED_TWO_STALE_COMMITS.
- Repaired RC01–RC14 certified passes=0, MIGRATION_092=NOT_AUTHORIZED, B12_CUTOVER=HARD_OFF. No production/staging/cPanel/AWS node changes, Stage D or 150/200 VU tests allowed.

## 7. Acceptance check and immediate next step

Remote GitHub compare with exact WS52-KU HEAD 70817ab14f820e9da8304e8b8dd126ce8bf0a7d0 must show exactly 3 added files (new pure contract, fixture-only tests, this report), zero modified/deleted files and protected refs/blobs unchanged; GitHub Actions branch run count 0. Next controlled user-side action is ONE offline Mac 22-test pinned-Git-blob check with no Docker/SQL/real data, followed by separately recorded result. No new runtime approvals.
