# B12 WS52-LW — Independent source-freezing and one-use authorization review (READ-ONLY)

**2026-10-16 | REVIEW_ONLY, NOT CERTIFICATION.** This checkpoint performs a GitHub source review without triggering Docker, GitHub Actions, SQL, AWS, a production backup, migration, CI dispatch or physical isolation checks. The **third physical preflight is NOT AUTHORIZED**.

## Authority and source identities independently checked for this review

- Repo `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`; WS52-LV evidence parent branch `c137-gc24-k2p03-b12-ws52lv-parent-binding-pass-evidence` HEAD `4c130be74212cb0b267b2c5d4ace748111093946`.
- WS52-LU verified source was `c137-gc24-k2p03-b12-ws52lu-stale-test-assertion-fix` HEAD `8ddc3d00e5c2ede4fe6a9208b7a78b75b03583a4`; 22 of 22 Mac-reported offline tests PASS, evidence saved at `docs/C137_GC24_K2_P03_B12_WS52LV_WS52LU_PARENT_BINDING_OFFLINE_22_OF_22_PASS.md`.
- Candidate hard-disabled workflow `.github/workflows/c137-b12-ws52ls-gh-readonly-docker-one-use-disabled.yml` blob `9d4ff5171575acd75e41402b9b1ce09dea6004a6`; literal `if: false`; `permissions: {}`, `ubuntu-24.04`, no workflow_dispatch, services or checkout action.
- Offline 22-test fixture `tests/test_b12_ws52lt_event_before_parent_binding_offline.py` blob `b9369f460c3aff85ed0960a549b594b84d7d40d4`.
- Protected original B12 source branch HEAD `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af`, protected production `v320-renee-agrisuite-branding` HEAD `10041da3a3c4e4a7b8101f8183c243d056c7b128`; these heads are protected under operational procedure, not proven GitHub branch protection.
- Original cutover gate `lib/daily_record_revision_cutover_gate.php` blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268`; CAS authority `lib/daily_record_revision_authority.php` blob `5fbb90903b0cc801be45c684f6c3db85c660979a`.

## Findings: what the source does establish

- The proposed workflow defines a push trigger restricted to a named branch and a single approval-file path. The sole job is currently hard-disabled; **no new approval document exists or is authorized**.
- The guard checks the exact GitHub event type/ref/repository/head SHA/message, one-run/one-attempt fields, bounded push JSON, runner label/self-claim, and nonzero `event.before` commit. It then makes a limited read-only HTTPS GitHub REST commit lookup for `GITHUB_SHA` and compares its single parent with `event.before`. That API commit record must contain only one added file at the exact approval path, with the expected commit message.
- Bounded public API access uses a fixed HTTPS URL, redirect rejection, timeout 10 seconds, 65,536-byte maximum response and fail-closed fixed diagnostics. API egress and its availability/rate limits remain environment dependent; outbound GitHub API access itself is **not proof of network isolation**.
- Docker reader allowlists read-only CLI `version`, `info`, `container ls --all --no-trunc --quiet`, `volume ls --quiet`, `network ls --format {{json .}}`, plus host-local `/proc/net/tcp` and `tcp6` port-3306 listener status. The CLI would contact a local Docker Unix socket; possession of such a socket can grant privileged control of the host even with GET-only tool code. There is NO separate OS-enforced privileged broker or independently attested host boundary.
- No image pulls, container/volume creation or removal, SQL, RC workers, migrations or cutover are in the candidate's intended scope. Actual physical safety cannot be inferred from source policy alone.

## Critical difference: lineage agreement is NOT independent approval

- `event.before == commit_api.parents[0].sha` means two GitHub-provided descriptions of the commit's parent agree. It **does not** bind that parent to a pre-approved code revision; an unexpected parent can satisfy the comparison if all other self-reported inputs agree. The current workflow intentionally emits `APPROVED_SOURCE_AUTHORITY=NOT_ATTESTED`.
- Embedding the workflow's own eventual full commit SHA in the workflow would be self-referential. The solution is an **external source-authority checkpoint**, NOT accepting an arbitrary parent as proof of authorization.
- GitHub repo write access or an in-repository `APPROVAL.txt` cannot substitute for the user's explicit, scoped, independent authorization. The approval commit should be treated as a mechanically verified trigger, not as its own grant of access.

## Required freeze → approval → execution procedure (FUTURE ONLY)

1. Prepare a **distinct fresh branch** and proposed executable one-use workflow (different path/name from consumed WS52-LK, WS52-LP and hard-disabled WS52-LS). Keep it non-triggerable without a unique approval-file addition. It must include job `run_number == 1` and `run_attempt == 1`, plus the Python event guards, and no unreviewed jobs, services, checkout actions, self-hosted runner label or write permissions.
2. Independently inspect the entire workflow and net source diff, including event guards, public API URL/time/byte limits, Docker command allowlist, no raw output, no external scripts, and that the new approval-file path does **not exist**. Verify protected branch SHA and critical gate blobs unchanged. If any reviewed input changes, abandon this freeze and review a new candidate.
3. Record the **actual GitHub SHA `S` of the prepared executable workflow commit**, its full Git blob, exact repo/branch/file path, workflow job scope, runner label and approval-file pathname in an immutable, *external-to-workload* review record. The SHA is established only **after** the prepared workflow commit exists, never guessed or written into its own content.
4. Ask the user for **fresh explicit one-use approval** quoting `S`, repo/branch, runner class, one read-only GitHub commit API GET and exact five Docker read-only commands + host TCP/3306 observation; prohibit image pulls, resource mutations, SQL, production/historical backup, migrations, RC workers and cutover. Previous two approvals are consumed. Nothing presently provides such an approval.
5. Only after approval, create **one separate approval commit `A` immediately after `S`**, adding exactly the one approved document at the frozen path, with the exact reviewed commit message; **no other file change or merge**. Independently compare `S...A` before interpreting any Actions result. Abort if branch HEAD drifted, multiple commits/files changed or the approved workflow blob differs.
6. The workflow's internal API must verify `A` has a single parent equal to `event.before`, exactly one added approved file and exact message; its run number and attempt must both equal 1. The external `S` comparison is an additional independent control, not something the workflow can infer. Never manually rerun an exhausted or failed job.
7. Limit final evidence to fixed codes and synthesized non-secret observations; do not output raw runner/engine IDs, paths, event JSON, confidential environment values, Docker data or HTTP API responses. A single failure leaves the physical result BLOCKED; do not launch another attempt without renewed approval.

## Open issues before even presenting an executable candidate

- New executable branch/workflow with unique trigger and exact source/approval authority not yet created or independently reviewed. The current WS52-LS job remains `if: false`; no approval file was created.
- Independent physical GitHub runner provenance, Docker Engine identity, OS privilege separation and outbound network/egress enforcement are **NOT ATTESTED**. A future read-only metadata pass does not certify any of these independently.
- Historical OCI MariaDB 10.11 full index/manifest/config/layers, isolated scratch environment, synthetic RC01 race test harness and repaired RC01–RC14 results remain OUTSTANDING, separate approvals required.
- First one-use read-only attempt WS52-LK run `38857466870` failed before Docker at generic event guard; second WS52-LP run `38863642180` failed before Docker at `EVENT_ADDED_FILES_MISMATCH`; both authorizations exhausted. Neither can be rerun.

## Present decision

**WS52-LW review outcome: SOURCE_FREEZE_PROCESS_DOCUMENTED; EXECUTABLE_SOURCE_NOT_FROZEN; THIRD_PHYSICAL_PREFLIGHT=NOT_AUTHORIZED.** This is not a clearance to flip `if: false`. Preserve every existing closed offline suite without unnecessary replay.

`RUNTIME_RC01_APPROVAL=NOT_GRANTED`; `REPAIRED_RC01_RC14_CERTIFIED_PASSES=0`; `MIGRATION_092=NOT_AUTHORIZED`; `B12_CUTOVER=HARD_OFF`.

Acceptance for this documentation checkpoint: one new Markdown only on a fresh branch off WS52-LV, one commit ahead/zero behind, no workflow/source changes or Actions runs, and protected branch heads/critical product blobs unchanged.
