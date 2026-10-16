# B12 WS52-LM — Fixed-code diagnostic patch for the failed one-use read-only GitHub preflight

**2026-10-16 | SOURCE_ONLY; TESTS AUTHORED NOT RUN; WORKFLOW DORMANT.** No second GitHub Actions run is approved. The WS52-LK authorization was consumed by the failed first attempt.

## Immutable source and exact context

- Repo `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- Parent `c137-gc24-k2p03-b12-ws52ll-readonly-preflight-blocked-evidence` HEAD `1acd7c4ac5523ad7086e68e2dfb879ea53fc9cb4`. New isolated diagnostic feature branch `c137-gc24-k2p03-b12-ws52lm-guard-diagnostics-disabled`.
- Edited ONLY the feature-branch copy of `.github/workflows/c137-b12-ws52lk-gh-readonly-docker-one-use.yml`: new Git blob `9b14028a8cce7e516c556d80119c14021f62e7d8`, parent blob `d5ee15799e2b2b81075f27f85b4db947b091c3a2`.
- New pure synthetic event diagnostic tests `tests/test_b12_ws52lm_gh_event_guard_diagnostics_source.py`: Git blob `2aec4dc0149387ec9c245f161b99d32d51b53901`, 25 tests **authored but NOT executed**.
- The original WS52-LK one-use approval commit and run remain unchanged: workflow pre-approval HEAD `4ae17235654f4203fefde4d50650aee221ac4fbf`, approval commit `fbae8202d807ab6cdde9e359e7ebbd20638d4d96`, run ID `38857466870` concluded `failure` on 2026-10-16 with `ONE_USE_EVENT_GUARD=NOT_VERIFIED`, `DOCKER_READONLY_COMMANDS=NOT_RUN`, `ENGINE_METADATA_READ=NO`.
- Protected original B12 branch `c137-gc24-k2p03-b12-cas-writer-cutover-source` HEAD `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af`; protected production `v320-renee-agrisuite-branding` HEAD `10041da3a3c4e4a7b8101f8183c243d056c7b128`.

## Corrected behavior on this source-only branch

- Replaces opaque `assert` statements in `guard()` with `require(condition, 'FIXED_CODE')`; controlled `GuardReject` carries ONLY a source-defined reason code to the sanitized `emit()` routine. The final catch-all remains a fixed `UNEXPECTED_DOCKER_PREFLIGHT_FAILURE`, never raw exception strings, environment dumps, headers, event content, runner names or engine metadata.
- Distinct stages include GitHub action/event/repository/ref/run number/attempt, claimed runner OS/architecture/hosting, head SHA, GitHub event JSON file path/read/length/decode, event root, head commit ID/message and added/modified/removed approvals, commit count, webhook ref/deletion/repository, and nonzero `before` SHA. These are self-reported runtime claims, not an independent GitHub provenance signature.
- Crucially, the copied job has **literal `if: false`** and original WS52-LK consumed branch/event trigger; no new one-use approval path, live workflow or permission to perform Docker operations. Even a push of the old approval file on this source branch cannot run the job. This patch is a diagnostic implementation candidate only; a future executable workflow must be separately reviewed and authorized.
- All existing Docker read-only commands, pre-existing-resource checks and hard-off B12 release conditions remain in their earlier source only. No source lines were added to read historical backups, pull OCI images, create containers/volumes, connect MariaDB, use SQL, invoke RC01, or change product/CAS/migration logic.

## Offline verification plan and limits

- Tests use synthetic `GITHUB_EVENT_PATH` payloads with mocked `builtins.open` and controlled environment dictionaries; only the guard's safe Python definitions are compiled, not its GitHub Actions job entrypoint. No socket, file mutation, shell, network, Docker or SQL.
- Each failure stage should return a fixed no-secret diagnostic code. Test count: 25. At the time of this report, test execution has **NOT happened**; do not claim PASS from the remote source-only diff.
- Independently inspect the feature-branch remote semantic diff to ensure one workflow **modified** and one test file plus this report **added**, and unchanged protected branches, original one-use approval/run and product CAS gate.

## What a future operator must do

- Keep WS52-LK exhausted. After source-only tests pass and an independent security review, create a **different** clearly identified one-use workflow/branch and require **fresh explicit authorization** for the scope. Do not reuse the old WS52-LK approval or rerun job 38857466870.
- A future preflight must not output raw `GITHUB_EVENT_PATH`, secret values, Docker/host path strings or unsanitized failures; fixed staged reasons can identify the cause of a failed one-use event guard without echoing confidential data.
- Even a future successful runner/Docker read-only observation cannot establish independent host/egress isolation or grant MariaDB/RC01 execution. Those require distinct security proof and explicit approval.
- `RUNTIME_RC01_APPROVAL=NOT_GRANTED`; `REPAIRED_RC01_RC14_CERTIFIED_PASSES=0`; `MIGRATION_092=NOT_AUTHORIZED`; `B12_CUTOVER=HARD_OFF`.
