# C137 / GC24 / K2-P03 / B12 — WS52-LX executable-candidate source and security review

**2026-10-16 | SOURCE AND REMOTE DIFF REVIEW ONLY; NOT A PHYSICAL PREFLIGHT OR INDEPENDENT SECURITY CERTIFICATION.** No third one-use execution authorization has been granted. No GitHub Actions run, Docker contact, SQL, customer backup, AWS host interaction, image pull, migration or cutover occurred in this checkpoint.

## 1. Exact source authority and change scope

- Repository: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- Parent source-review branch: `c137-gc24-k2p03-b12-ws52lw-source-freeze-approval-review` HEAD `60e228f69899ab010b369f75b3d52c4b00f0a945`.
- New candidate branch: `c137-gc24-k2p03-b12-ws52lx-final-readonly-preflight-candidate`.
- Unique new candidate workflow `.github/workflows/c137-b12-ws52lx-gh-readonly-docker-one-use.yml`, source blob `3ffc5f7eb4438959121621000c98cdf4885a2c11`, prepared in workflow-only commit `84ff44384097b9609e9a41268df3805b563c5bf6`.
- This Markdown source-review document follows the workflow-only preparation commit. The **final reviewed HEAD S must be freshly verified AFTER this document is committed**. The one-use approval's parent must be exactly S; never guess S or embed a self-referential commit SHA into the workflow.
- Inherited frozen WS52-LT disabled source `.github/workflows/c137-b12-ws52ls-gh-readonly-docker-one-use-disabled.yml` blob `9d4ff5171575acd75e41402b9b1ce09dea6004a6` untouched, alongside closed 22/22 offline fixture `tests/test_b12_ws52lt_event_before_parent_binding_offline.py` blob `b9369f460c3aff85ed0960a549b594b84d7d40d4`.
- Protected original B12 source branch `c137-gc24-k2p03-b12-cas-writer-cutover-source` HEAD `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af`; protected production `v320-renee-agrisuite-branding` HEAD `10041da3a3c4e4a7b8101f8183c243d056c7b128`.
- CAS gate source blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268` and authority blob `5fbb90903b0cc801be45c684f6c3db85c660979a` remain unchanged.

## 2. Source-reviewed workflow — exactly what is configured

- `on: push` limited to branch `c137-gc24-k2p03-b12-ws52lx-final-readonly-preflight-candidate` and path `docs/C137_GC24_K2_P03_B12_WS52LX_READONLY_PREFLIGHT_ONE_USE_APPROVAL.txt`. **That approval file DOES NOT EXIST as of workflow-preparation HEAD. Do NOT create it before explicit authorization.**
- The job is *executable on its future approval-file push*, but **no execution has been authorized**. The job expression requires event `push`, exact branch, exact head-commit message `ci-b12(ws52lx-run): authorize one readonly Docker and GitHub commit API preflight`, `github.run_number == 1` and `github.run_attempt == 1`. Python repeats the first-run/first-attempt/identity checks.
- `permissions: {}`, `runs-on: ubuntu-24.04`, five-minute job maximum, one concurrency group without auto-cancellation; no `workflow_dispatch`, third-party actions, checkout, workflow `services:`, source-supplied scripts or DB services.
- Guard validates bounded push-event JSON, exact repository/ref/head SHA/message/commit count and nonzero `event.before`. It uses **one unauthenticated HTTPS GET** to GitHub REST commit API for the event `GITHUB_SHA` with TLS, no redirects, disabled proxies, 10-second timeout and bounded response (65,536 bytes). API result must identify exact commit SHA, exactly one parent equal to `event.before`, exactly one newly ADDED file equal to approval path, and exact commit message; failures emit fixed sanitized error codes.
- The preflight intentionally reports `APPROVED_SOURCE_AUTHORITY=NOT_ATTESTED`: matching two GitHub representations of commit lineage is not proof that the commit's parent was independently reviewed or that the user approved the operation.
- Docker operations after guard are limited to five allowlisted local CLI queries: `version --format {{json .Server}}`, `info --format {{json .}}`, `container ls --all --no-trunc --quiet`, `volume ls --quiet`, `network ls --format {{json .}}`. **WS52-LX pins `/usr/bin/docker --host unix:///var/run/docker.sock`**, unlike the previous generic CLI default, and checks no remote Docker environment override. No mutating command is present.
- The script also checks the host-local `/proc/net/tcp` and `tcp6` TCP/3306 listening state without opening a database connection. All raw Docker/API responses are processed in memory; output uses fixed status labels rather than hostnames, container IDs, engine IDs, event JSON, approval contents, API response bodies or stderr.
- Read-only program-level Docker CLI allowlisting does **not** remove Docker socket's inherent privileged host-control capability. Separate independently enforced OS permissions and external observation are NOT established here. Even a successful future observation will NOT independently certify runner/engine provenance or network isolation.

## 3. Security findings and unresolved gates

1. **Independent authorization not enforceable within repository alone.** Any actor who can write to the candidate branch and create the exact trigger file could cause an executable push event without the chat-based one-use approval. Neither a GitHub approval document nor `event.before` ↔ GitHub API parent agreement provides external human authorization. Review branch write restrictions, authorized actor and optional repository environment protection BEFORE authorizing an execution; do not assume those protections exist or that this workflow has them.
2. **Freeze dependency:** externally record verified *final prepared HEAD S*, exact workflow blob `3ffc5f7eb4438959121621000c98cdf4885a2c11`, repo, branch, unique approval path, approved actor and permitted scope; compare prospective approval commit A against S. Abort on branch drift, unreviewed workflow changes, more than one approval-file change, extra commits, or changed source blob.
3. **Runtime independence:** GitHub-hosted runner label and daemon self-reported ID are observational evidence, not an independent host and egress attestor. Docker socket can grant powerful privilege even though this source runs metadata GET only. A physical isolation preflight is not a security certification for a privileged broker or a basis for production use.
4. **Network and environment:** the new GitHub API GET is an explicitly added outbound network operation that must be included in a fresh physical-run approval. Actual egress enforcement and TLS/runtime behavior have not been independently attested. Errors, GitHub rate limits, unexpected Docker CLI layouts or preexisting containers/volumes must fail closed; no reruns without fresh approval.
5. **Execution result semantics:** a future single read-only metadata job may yield `READONLY_OBSERVATION_PASS`, but must never be interpreted as OCI image-layer verification, MariaDB clone approval, RC01–RC14 race certification, migration authority or B12 cutover clearance.

## 4. Fresh one-use approval procedure — NOT YET PERFORMED

- Independently compare current candidate branch against parent `60e228f69899ab010b369f75b3d52c4b00f0a945`: only NEW workflow and this review file; verify protected branch HEADs and critical blob identities, exact approval file ABSENT and Actions runs ZERO.
- Record final prepared HEAD S after this review document commit, along with workflow blob, and ensure no intervening pushes. If external branch protection/reviewer gate cannot be demonstrated, disclose the risk before authorization.
- Request explicit new user approval naming S, branch, one ephemeral GitHub-hosted Ubuntu runner, **one read-only GitHub REST commit API GET**, five pinned read-only local Docker metadata commands and local listener check. All other host/database/production actions remain prohibited.
- If user approves, check candidate HEAD still S and approval file absent, then create **one** additional GitHub commit A which ADDS only the unique approval file with the exact reviewed message. Do not rerun A on any failure.
- Independently compare S → A (one commit, one file ADDED) and inspect the single resulting Actions run and sanitized logs. Record `RUN_NUMBER=1`, `RUN_ATTEMPT=1`, source SHA, guard status and any confirmed metadata observations; stop at any failure.
- Keep exhausted WS52-LK run `38857466870` and WS52-LP run `38863642180` unchanged; both failed before Docker and may not be rerun.

## 5. Final present disposition

**SOURCE_SCOPE_REVIEW=PASS_WITH_DISCLOSED_EXTERNAL_AUTHORIZATION_RISK.** **PHYSICAL_ISOLATION_CERTIFICATION=NOT_ESTABLISHED.** The candidate workflow is executable only if a person with repository write authority creates the unique approval trigger file; it cannot independently verify the user's one-use consent. As of this source review **no approval file was created and no physical preflight was launched**.

`THIRD_PHYSICAL_PREFLIGHT=NOT_AUTHORIZED`; `RUNTIME_RC01_APPROVAL=NOT_GRANTED`; `REPAIRED_RC01_RC14_CERTIFIED_PASSES=0`; `MIGRATION_092=NOT_AUTHORIZED`; `B12_CUTOVER=HARD_OFF`.