# B12 WS52-LT — Non-self-referential source lineage for a future ONE-USE GitHub Docker preflight (source only)

**2026-10-16.** Scope: source-only change to the hard-disabled third-generation preflight candidate. NO GitHub Actions execution, no third one-use runtime approval, no Docker, SQL, backup, OCI downloads, production, migration, or cutover. **New unit tests authored, NOT YET EXECUTED.**

## Exact source lineage and changes

- Repo `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`. Parent WS52-LS `c137-gc24-k2p03-b12-ws52ls-third-readonly-preflight-preparation` HEAD `b1d17d3b0f90f9d9522864be12bd8e80f5aa2337`.
- This new source-only branch `c137-gc24-k2p03-b12-ws52lt-lineage-binding-source-disabled`.
- Modified ONLY the branch copy of `.github/workflows/c137-b12-ws52ls-gh-readonly-docker-one-use-disabled.yml`, new Git blob `9d4ff5171575acd75e41402b9b1ce09dea6004a6`.
- New focused offline fixture suite `tests/test_b12_ws52lt_event_before_parent_binding_offline.py`, Git blob `cd0669ca73aed1599b37f787a55f1315961751a7` with **22 test cases authored, NOT RUN**. New tests are isolated fake-GitHub-API checks and negative before-SHA cases; do not rerun previously closed WS52-LQ 17/17 tests absent evidence of regression.
- Historical source-only WS52-LQ commit API guard passed 17/17 Mac offline and was preserved at WS52-LR HEAD `c15b55714d1fb951766e213745e0c0db274a1a18`.
- Protected original B12 `c137-gc24-k2p03-b12-cas-writer-cutover-source` HEAD `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af`; protected production `v320-renee-agrisuite-branding` HEAD `10041da3a3c4e4a7b8101f8183c243d056c7b128`.
- Critical product CAS gate `lib/daily_record_revision_cutover_gate.php` Git blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268`, CAS authority `lib/daily_record_revision_authority.php` blob `5fbb90903b0cc801be45c684f6c3db85c660979a` unchanged.

## Self-reference problem and non-self-referential fix

- A Git commit cannot embed its own final SHA as a fixed literal in the same file that determines that commit's SHA. The WS52-LS zero-SHA placeholder deliberately blocked all approval attempts.
- WS52-LT changes the isolated `verify_commit_delta_via_api` signature from `commit_sha` to `(commit_sha, expected_parent_sha)` and verifies the latter is a canonical nonzero 40-character Git commit identifier BEFORE any HTTP attempt.
- The guarded push event first validates `GITHUB_*` identity/one-use fields, commit message/ref/repository/count and `event.before` shape. Only then does it make the intended bounded, redirect-rejecting HTTPS GitHub commit API request for that *exact* `GITHUB_SHA` and require the API commit's sole parent to equal `event.before`, with **exactly one added approval document**, no other files and matching approved commit message.
- The event-before/API-parent equality is **cross-checking GitHub-provided metadata**, not independent human approval, independently attested runner/engine identity, or provenance of the code itself. The new `APPROVED_SOURCE_AUTHORITY` report field is fixed at `NOT_ATTESTED`. If source author/branch changes, the test must fail closed; no runtime authorization can be inferred from a passing synthetic lineage fixture.
- **Actual human authorization must be external to the workload:** freeze a prepared EXECUTABLE workflow on a new, distinct branch only AFTER separate security review; independently verify GitHub source HEAD and workflow blob, then obtain an explicit one-use user authorization identifying that HEAD, runner scope and bounded read-only GitHub API GET. Next, add exactly one approval document as a separate next commit. Independently compare that approval commit against the user-reviewed prepared source parent before interpreting job evidence. A future workflow must perform the event.before ↔ API commit-parent cross-check; GitHub repo write privileges, self-reported run variables and an in-repository approval doc are NOT independent proof of human authorization.
- WS52-LT deliberately has literal `if: false` and no authorization document created, no executable activation, no workflow_dispatch, no actions checkout, no services and no permissions. The old WS52-LK/WS52-LP one-use approvals are consumed. Do not edit those branches or rerun their jobs.

## Current physical and product gate

- First one-use GitHub run `38857466870` failed at opaque entry guard before Docker; second one-use run `38863642180` failed with `EVENT_ADDED_FILES_MISMATCH` before Docker. Both are closed and exhausted; no third attempt has run.
- A future approved physical preflight remains limited to read-only HTTPS GitHub commit API metadata plus read-only Docker CLI version/info/container/volume/network listings and host local TCP/3306 listener observations. No container/volume/image/network mutations, image pulls, SQL, migrations, RC01 workers, customer backup or production access.
- Even successful self-reported runner/engine metadata will not establish independent isolation, egress block, Docker socket privilege separation, full OCI image layers, historical DB compatibility or repaired concurrency RC01–RC14 certification.
- `THIRD_PHYSICAL_PREFLIGHT=NOT_AUTHORIZED`; `RUNTIME_RC01_APPROVAL=NOT_GRANTED`; `REPAIRED_RC01_RC14_CERTIFIED_PASSES=0`; `MIGRATION_092=NOT_AUTHORIZED`; `B12_CUTOVER=HARD_OFF`.

## Controlled next step

- Independently verify WS52-LT source Git blobs and execute only the 22 newly authored fake-API offline tests on Mac; preserve PASS or BLOCKED exactly, including failed test names; no Docker/API network contact from the tests, no GitHub Actions. The only network use in the Mac verifier should be the pinned GitHub raw source download.
- After source PASS, record the evidence without re-running closed tests or triggering any physical job. A new reviewed executor configuration and explicit third-run authorization remain distinct prerequisites.
- GitHub remote acceptance: this branch changes only one already-disabled candidate workflow plus one test file and this report; no product or inherited workflow changes, no Actions runs, no protected HEAD drift.
