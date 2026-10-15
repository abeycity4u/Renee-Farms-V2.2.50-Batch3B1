# C137 / GC24 / K2-P03 / B12 — WS52-KT hard-disabled one-volume identity and cleanup contract

**Review date:** 2026-10-15. **Result:** SOURCE_PREPARED_ONLY. **Real Docker inspection: NO; SQL: NO; physical cleanup attested: NO; execution approved: NO.**

## 1. Exact source checkpoint

- Repository: abeycity4u/Renee-Farms-V2.2.50-Batch3B1.
- Parent branch: c137-gc24-k2p03-b12-ws52ks-runtime-volume-isolation-source-review at exact HEAD b59f199747cc6338c20a77e3a3432c3cf6f884f4.
- New WS52-KT branch: c137-gc24-k2p03-b12-ws52kt-volume-identity-cleanup-contract-source.
- New PURE hard-disabled policy: tools/b12_ws52kt_one_volume_identity_cleanup_contract.py, Git blob e3c6b7b79bb979902b9d610234b6fd84f2120c2d.
- New 20-case fixture-only unit test source: tests/test_b12_ws52kt_one_volume_identity_cleanup_contract.py, Git blob 38f2272be8e8058899fee1f798b4eb7de37eb960. **Tests authored, not executed and not certified by this source commit.**
- Existing policy left intact: tools/b12_ws52kh_rc01_isolation_cleanup_contract.py, blob f5f9b82453fae41cc8bcc070414d988f052c5e14.
- Existing hard-disabled GH RC01 candidate left intact: tests/integration/k2p03_b12_gh_rc01_preliminary_synthetic.php, blob e78318509e270ccd24684bf41f5e854b74765f76.
- Cutover gate unchanged: lib/daily_record_revision_cutover_gate.php, blob 6e88c913ad5e5b371a9d0c92ce08870214ac1268.
- CAS authority unchanged: lib/daily_record_revision_authority.php, blob 5fbb90903b0cc801be45c684f6c3db85c660979a.
- Protected B12 original branch HEAD 2327a22a3cd7a95ffea42fc76dd94805f5b1e7af; production branch HEAD 10041da3a3c4e4a7b8101f8183c243d056c7b128.

## 2. Why a separate contract is necessary

The WS52-KS source review established that the previous WS52-KH synthetic contract insists on an empty Mounts array, but the user-returned, SHA256-verified OCI configuration for Linux AMD64 MariaDB 10.11 declares exactly one volume, including /var/lib/mysql. Docker may create an implicit anonymous volume: declared VOLUME does not prove any actual future mount. Blindly ignoring that mount, overwriting old zero-mount policy, accepting a pre-existing volume, or trusting a booleans-only cleanup result would undermine safety.

Expected immutable OCI child digest sha256:dc9dd57be7f46ad6f350c598a331d64f295899c4abac3ec5652eff831fc38a6c and configuration digest sha256:41096ae9913468679bb5e05bbc19f44edcaf0eae45d67fcde224ffdeda9d3a1f are content identifiers, not a physical runnable image identity. The full image layers, actual Docker host and any engine/container/volume remain NOT_ATTESTED.

## 3. New source-only policy: closed data contracts, no execution authority

The new module exports assess_postcreate_shape(evidence), assess_cleanup_shape(pre, post), and safe_status(pre_result, post_result). It imports only dataclasses, re and typing, and contains no CLI, Docker, filesystem, network, subprocess, SQL or cleanup operations. EXECUTION_ENABLED=False; APPROVED_ONE_USE_RUNTIME, APPROVED_PHYSICAL_INSPECTOR and APPROVED_IMAGE_FOR_EXECUTION are all None.

Every result has physical_attested=False and execution_authorized=False **by construction**, including a structure-valid synthetic fixture. safe_status returns SOURCE_SHAPE_ONLY_PHYSICAL_NOT_ATTESTED only when both closed-shape checks pass, otherwise BLOCKED_MANUAL_REVIEW. Synthetic JSON, labels, SHA-looking strings and even a matching run ID are NOT authentic physical evidence. No preflight output from this module grants one-use execution.

### Precreation and postcreate model

- Exact repository, pinned WS52-KS source parent, GitHub-hosted ephemeral runner claim, first run attempt, fixed expected OCI child/config digests; format checks for engine fingerprint, observed image and container IDs.
- Strict run-scoped disposable container name and volume name, with run-ID-bound synthetic labels; no source drift or unexpected metadata keys.
- Closed precreation container and volume inventory (unique, syntactically valid IDs) and after-creation inventory; require **exactly one new container ID and exactly one new volume name**, without losing/renaming any preexisting resource. A pre-existing candidate volume or an additional anonymous volume is rejected.
- One and only one effective Mounts entry linked to volume inspection: Type=volume, exact new name, local driver, destination /var/lib/mysql, writable true, matching mountpoint fingerprint and run labels. Reject bind mounts, protected/preexisting mounts, second mounts, extra anonymous mounts, read-only mounts, mismatched sources or drivers.
- Privileged must be false; bridge mode and only 127.0.0.1:3306 TCP binding; network/egress observer is syntactically represented as an **untrusted pending-review label**, NOT a real firewall or egress measurement. Runtime viability and least-privilege DB grants remain unresolved.

### Postcleanup model

- Post evidence must match exact repository, pinned source, run, engine, container and volume identities from pre-state.
- Container and volume inventories must independently return **exactly to the original precreation inventories**, not merely assert container_removed=True or ephemeral_volume_removed=True.
- Exact container and volume identity absent-from-inspect flags, independently reported postcheck flag, zero workers, zero transactions, zero synthetic business rows and zero barrier artifacts. These are shape constraints only; the future independent inspector must supply real evidence.
- Any incomplete, drifted, ambiguous or mismatched evidence returns BLOCKED_MANUAL_REVIEW; STOP and quarantine. No broad deletion, prune, wildcard, auto-retry or removal of resources whose provenance is unknown.

## 4. Authored, NOT YET EXECUTED offline tests

The 20 fictional/mocked unit cases cover a valid-but-never-authorized synthetic shape, literal hard-off fuses, mismatched repo/source/OCI digest, run/name/attempt drift, pre-existing volumes, unexpected anonymous volumes, missing original volumes, extra/reused containers, duplicate/malformed inventories, zero/multiple mounts, bind/driver/target/read-only/fingerprint drift, volume and container label mismatches, public port/privileged/egress deviation, nominal synthetic postcleanup (still not physically attested), inventory-restoration failures, identity drift, residue and unverified removal, preflight failure despite claimed cleanup, unknown keys, safe_status refusal to claim execution and AST checks disallowing runtime I/O imports.

The test source was additionally corrected prior to runtime to classify volume-inspect mountpoint fingerprint drift as an effective-mount linkage mismatch; the policy source itself was not changed. Do not say 20/20 PASS until a user-returned pinned-source offline test result or another independently observed fixture-only execution is available. Do not rerun previously verified WS52-KO 16/16 tests; their code has not changed.

## 5. Limitations and exact next gate

This policy does NOT yet query a Docker engine, discover a mountpoint or prove it belongs to a fresh disposable volume, verify TLS/OCI publisher signatures, start MariaDB, certify network restrictions, grant SQL/PROCESS, or execute/clean up RC01. All proposed observations can be fabricated within source-only fixtures; a genuine physical inspector requires a NEW separately reviewed, HARD-DISABLED source and one-use GitHub-hosted runner decision.

Recommended next step: **fixture-only offline verification of these exact new 20 tests**, with the policy/test Git blob hashes pinned. If PASS, preserve user-returned sanitized evidence in a separate documentation-only branch. Later only, source-review physical runtime inspection strategy; no Docker/SQL while source-only. Do not infer approval from generic requests to proceed.

Frozen B12 state: B12_CUTOVER=HARD_OFF, REPAIRED_RC01_RC14_CERTIFIED_PASSES=0, historic original MariaDB race FAILED_TWO_STALE_COMMITS, MIGRATION_092=NOT_AUTHORIZED, real historical production-derived clone audit incomplete and backup restore NO_GO, Stage D/capacity work unauthorized.

**GitHub acceptance:** compare WS52-KT to exact WS52-KS parent b59f199747cc6338c20a77e3a3432c3cf6f884f4; expect three ADDED files only (pure source, fixture tests, this report), no modified/deleted old code/workflow/migrations/approvals, original B12/production branches and critical blobs unchanged, and zero GitHub Actions triggered.
