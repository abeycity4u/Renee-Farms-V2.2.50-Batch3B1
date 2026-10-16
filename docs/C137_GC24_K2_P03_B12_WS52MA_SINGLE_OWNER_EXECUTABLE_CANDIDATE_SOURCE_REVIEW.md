# C137 / GC24 / K2-P03 / B12 — WS52-MA source-prepared single-owner physical metadata preflight

**2026-10-16. SOURCE REVIEW ONLY. THIRD PHYSICAL RUN NOT AUTHORIZED.** The candidate job is syntactically executable on a narrowly scoped future approval-file push; no such file exists, no run was created, no Docker contacted, and no SQL, OCI image, backup, worker, migration or B12 cutover performed.

## 1. Exact source and scope

- Repository `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- Source branch `c137-gc24-k2p03-b12-ws52lz-oneuse-readonly-preflight`, created from WS52-LZ hard-disabled source/evidence HEAD `795aaf69f1a2acdee3dc4c4c1c9491ae1ba39f99`.
- New unique workflow `.github/workflows/c137-b12-ws52ma-readonly-one-use.yml`, source blob `284c15052c3392fda42974decf3db5b745001986`, introduced in workflow-only commit `fbbd4cceabe767228a6c357615399ccda39b0844`.
- **Final prepared HEAD S must be independently read AFTER this review Markdown commit.** Do not embed S in the workflow (self-referential) or assume S remains the workflow-only commit. The workflow blob must remain `284c15052c3392fda42974decf3db5b745001986`.
- Existing disabled WS52-LZ `.github/workflows/c137-b12-ws52lz-singleowner-readonly-preflight-disabled.yml` remains Git blob `ffecb7954f4a9448bef16fe9876a2215d724468a` and `if: false`. Inherited disabled WS52-LX workflow remains blob `e729b9a108970e121c9775390488848ea484e9cb`.
- The unique future approval path `docs/C137_GC24_K2_P03_B12_WS52MA_READONLY_ONE_USE_APPROVAL.txt` is **ABSENT** as independently checked by GitHub (404); do not create it without fresh explicit user one-use approval.
- Proposed future exact approval commit message `ci-b12(ws52ma-run): authorize one readonly Docker and GitHub commit API preflight` must be used ONLY after one-use consent and exact branch/source checks.

## 2. Workflow behavior reviewed

- GitHub `on: push` restricts to exact WS52-LZ branch and unique approval pathname; job-level expression additionally requires `push`, exact branch, exact head commit message, `github.run_number == 1`, `github.run_attempt == 1`. Python separately checks the same first-run/attempt event identities.
- Job runs-on `ubuntu-24.04`, binds GitHub environment `b12-ws52lx-oneuse-readonly-review`, timeout five minutes, `permissions: {}`, no checkout action, external action, services, `workflow_dispatch`, or arbitrary shell command source.
- User (only account owner/collaborator) reports environment saved with required reviewers unchecked, admin bypass unchecked, and a selected deployment branch rule identifying WS52-LZ. GitHub connector **cannot independently read these environment-protection settings**. GitHub REST reports WS52-LZ `protected: false`, and visible repository rulesets `[]`.
- Hence **the environment branch-selection rule is NOT an independent second-person approval, a branch write restriction, or independently enforced one-use consent**. A sufficiently privileged repo writer could modify this workflow or create the one-use approval file. This is an accepted, explicitly disclosed limitation of a proposed single-owner procedural authorization, NOT a security certification.
- The guard verifies bounded GitHub push event SHA/message/ref/repo/commit-count/`before` then uses a bounded, unauthenticated TLS HTTPS GitHub commit metadata GET for that exact 40-hex SHA. It rejects redirects, proxies, invalid encoding/status/oversize responses; verifies one commit parent == `event.before`, exactly ONE file added at the approval pathname, fixed commit message; emits sanitized fixed source-owned reason codes without sensitive raw data.
- The READ-ONLY Docker metadata query handler is unchanged (byte-for-byte) from the disabled predecessor: allowlisted `version`, `info`, `container ls --all --no-trunc --quiet`, `volume ls --quiet`, `network ls --format {{json .}}`; launches `/usr/bin/docker --host unix:///var/run/docker.sock`, `shell=False`, 12-second bounded process timeout and bounded response; no image pull, service run, container/volume/network mutation.
- The host-local TCP/3306 listener metadata function and core `guard()` are unchanged byte-for-byte. GitHub commit API helper differs only by the WS52-MA identity strings. The script does not establish independent GitHub runner or Docker Engine provenance, egress isolation, or Docker Unix-socket privilege separation.
- Source reports `APPROVED_SOURCE_AUTHORITY=NOT_ATTESTED`, `RUNTIME_RC01_APPROVAL=NOT_GRANTED`, `REPAIRED_RC01_RC14_CERTIFIED_PASSES=0`, `MIGRATION_092=NOT_AUTHORIZED`, `B12_CUTOVER=HARD_OFF` in all audit paths.

## 3. Safety review and unresolved prerequisites

- **Provisional result:** `WS52MA_SOURCE_SCOPE_REVIEW=PASS` for static identity, diff and CLI-operation allowlist; **NOT** an independent security audit, no new runtime verification and no physical readiness certification. Closed WS52-LU fake-API lineage fixture previously passed 22/22 offline; new WS52-MA source has only static comparison against that guard. Do not claim its actual Actions trigger and GitHub environment behavior verified until a separately approved physical run.
- The first WS52-LK one-use attempt run `38857466870` and second WS52-LP run `38863642180` both failed before Docker inspection; both prior approvals are exhausted and may not be rerun.
- Reuse of the WS52-LZ branch allows the GitHub environment's user-selected exact branch pattern to match without creating a new unlisted branch. **No job started** when the workflow-only preparation commit was pushed because the trigger file did not change.
- An actual one-use run could fail at GitHub workflow/event quirks, API response/egress limits, runner Docker socket missing, engine reporting mismatch, containers/volumes already present or TCP/3306 listener use. One failure exhausts the new authorization and is not permission to rerun.
- The Docker Unix socket inherently provides privileged host authority; a source-level read-only CLI allowlist is not OS-level privilege separation. No independent runner/daemon/egress attestations or complete MariaDB OCI image-chain certification have been established.
- No production, staging, customer records, historical-derived backups or database use is permitted. Synthetic RC01–RC14 concurrency certification is still 0/14 and has separate prerequisites/approval.

## 4. Required external one-use approval process — pending

1. Perform remote GitHub semantic diff against `795aaf69f1a2acdee3dc4c4c1c9491ae1ba39f99` and verify ONLY this new workflow and this review document added, 2 commits ahead, 0 behind; verify original B12/production HEADs and PHP cutover blobs unchanged.
2. Independently record final **prepared commit SHA S** after the review doc; workflow blob `284c15052c3392fda42974decf3db5b745001986`, branch, environment name and exact approval path. Verify approval file absent and **zero Actions runs** on WS52-LZ. If source or branch drifts, abort and re-review.
3. Before any physical preflight, obtain **fresh explicit user approval** to create one approval-file commit and permit one GitHub-hosted Ubuntu read-only observation. Authorization must identify S, source workflow blob, one GitHub REST metadata GET, five Docker read-only operations, local TCP/3306 listener check, single attempt and prohibitions; `proceed` at the source-preparation stage DOES NOT grant it.
4. If explicitly authorized in a future turn, perform exact pre-commit SHA/absence checks and add exactly the one approval document with the fixed message; then independently compare `S...A` for exactly 1 added file/1 commit. No extra changes, no automatic retries. Scrutinize the run and preserve only sanitized fixed output.
5. Any branch protection/environment settings are external to this workflow. Under this single-owner approach user authorization is **procedural, not cryptographically or separately enforced**, and the user must knowingly accept that limitation if choosing to run.

**Present hard-off:** `THIRD_PHYSICAL_PREFLIGHT=NOT_AUTHORIZED`, `RUNTIME_RC01_APPROVAL=NOT_GRANTED`, `REPAIRED_RC01_RC14_CERTIFIED_PASSES=0`, `MIGRATION_092=NOT_AUTHORIZED`, `B12_CUTOVER=HARD_OFF`.
