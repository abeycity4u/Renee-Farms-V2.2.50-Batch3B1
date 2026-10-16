# C137 / GC24 / K2-P03 / B12 — WS52-LS third-generation physical preflight PREPARATION (hard disabled)

**Date:** 2026-10-16. **Result:** workflow source prepared and reviewed; **NO PHYSICAL EXECUTION AUTHORIZED OR PERFORMED**. User's 'proceed' in context authorized preparation, NOT a third Docker-hosted run. Both earlier one-use authorizations are exhausted.

## Exact source checkpoint

- Repository `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- Parent branch `c137-gc24-k2p03-b12-ws52lr-api-proof-pass-evidence` HEAD `c15b55714d1fb951766e213745e0c0db274a1a18`.
- New source-only branch `c137-gc24-k2p03-b12-ws52ls-third-readonly-preflight-preparation`.
- Added candidate `.github/workflows/c137-b12-ws52ls-gh-readonly-docker-one-use-disabled.yml`, Git blob `a7462f883f18e5541d5629cd07363ae26a6d235d`. **Literal job `if: false`** makes it non-executable. No workflow_dispatch; no checkout/services; permissions empty. The approval file named below DOES NOT EXIST.
- Reuses the previously source-reviewed LQ GitHub commit API guard from old blob `5c02dafbfb48da81c9146aa3d5136b3035272523`. Its 17 synthetic API response tests passed offline, documented at `docs/C137_GC24_K2_P03_B12_WS52LR_WS52LQ_COMMIT_PROOF_OFFLINE_17_OF_17_PASS.md`.
- Original B12 branch `c137-gc24-k2p03-b12-cas-writer-cutover-source` HEAD `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af`; production `v320-renee-agrisuite-branding` HEAD `10041da3a3c4e4a7b8101f8183c243d056c7b128` are protected by procedure, not GitHub's branch-protection boolean.
- Cutover PHP guard blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268`, shared CAS authority blob `5fbb90903b0cc801be45c684f6c3db85c660979a`; no changes.

## Proposed fresh one-use scope, NOT current authorization

- GitHub-hosted `ubuntu-24.04` runner, one branch and one approval-file commit, exact run number 1 / attempt 1; job-level guards must be installed before ANY enablement. Those remain INCOMPLETE while the job is forcibly disabled.
- Approval-file candidate path: `docs/C137_GC24_K2_P03_B12_WS52LS_READONLY_PREFLIGHT_ONE_USE_APPROVAL.txt` — **DO NOT CREATE on this unapproved branch**.
- Proposed commit message for a separate, future approved activation: `ci-b12(ws52ls-run): approve distinct one-use readonly Docker and GitHub API preflight`.
- Planned HTTPS egress: one read-only unauthenticated TLS GitHub REST commit API request, restricted to `https://api.github.com/repos/abeycity4u/Renee-Farms-V2.2.50-Batch3B1/commits/{40-hex-sha}`; explicit redirect rejection, 10-second timeout, 65,536-byte response maximum. This was **not** part of the first two physical preflight scopes and must be disclosed in the new approval.
- Fail-closed commit proof requires the SHA, exact parent, exactly one added approval file, exact message, no other changed files. **Current parent check is deliberately `0000000000000000000000000000000000000000`**, an impossible GitHub parent; it MUST NOT be changed without a new approved source review and a satisfactory method to bind the final activated workflow to its approved source. There is a bootstrapping issue: embedding a workflow commit's own SHA in that same workflow is self-referential. Do NOT insert a guessed SHA or accept an unverified parent solely to make the job pass.
- Planned read-only Docker CLI operations: `version`, `info`, `container ls --all --no-trunc --quiet`, `volume ls --quiet`, `network ls --format {{json .}}`, plus local `/proc/net/tcp*` listener status for port 3306. Nothing is provisioned. No user-provided arbitrary paths/arguments, image pull, Docker mutating commands, shell interpolation or registry requests.
- Runner and engine metadata are only self-reported; successful command execution would NOT independently prove GitHub-hosted/engine identity, outbound network isolation, Docker privilege separation, OCI image/layer authenticity, clean physical volumes, MariaDB concurrency or safe B12 cutover.

## Why preparation remains BLOCKED for physical execution

- Old WS52-LK physical run `38857466870` failed at entry guard before Docker; its one-use authorization was consumed.
- Old WS52-LP physical run `38863642180` failed with `EVENT_ADDED_FILES_MISMATCH` before Docker; independent GitHub comparison showed the approval commit contained only the single added file. Its one-use authorization was consumed.
- WS52-LQ source-only fix replaced fragile push-event file-list assumptions with independent GitHub commit API proof and passed **17/17 offline**. WS52-LS reuses this method but cannot yet claim runtime attestation.
- The old workflow/approval branches and prior results are unchanged. Nothing should re-trigger them. **Do not submit an approval file, rerun an exhausted job, or switch job `if: false` to true from this preparation alone.**

## Required next controlled gates

1. Remote semantic diff acceptance: WS52-LS source branch only adds this candidate workflow plus this review; no existing product, migration, RC01 harness or previous workflow altered; protected HEADs and blobs unchanged; Actions run count 0. No unnecessary rerun of the previously closed WS52-LQ 17/17 suite.
2. Independent security review of the future activation mechanism, exact source/commit-parent binding, one-use event identity, third-party API egress, and host/runner provenance claims. Produce a new immutable approval manifest only after all prerequisite source identities are established.
3. Explicit new **third physical-run** authorization naming the intended single use and the additional GitHub API GET. Create a fresh executable approval flow under that authority only. A generic 'proceed' at the current stage does not authorize Docker contact.
4. If independently authorized, record exact sanitized GitHub Actions run/job evidence, stop on a single hard-off reason, do not silently rerun; never interpret runner self-report as full physical isolation certification.

`THIRD_PHYSICAL_PREFLIGHT=NOT_AUTHORIZED`, `RUNTIME_RC01_APPROVAL=NOT_GRANTED`, `REPAIRED_RC01_RC14_CERTIFIED_PASSES=0`, `MIGRATION_092=NOT_AUTHORIZED`, `B12_CUTOVER=HARD_OFF`.
