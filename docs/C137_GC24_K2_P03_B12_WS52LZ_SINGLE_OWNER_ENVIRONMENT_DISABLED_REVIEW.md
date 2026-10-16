# C137 / GC24 / K2-P03 / B12 — WS52-LZ single-owner environment scope (HARD DISABLED)

**2026-10-16. Source-only preparation and security inspection; no physical-run authorization.** The user is the only person with access to the `abeycity4u` account and reports having saved a GitHub environment with no required reviewers and a selected-branch rule matching this new branch. This is a user report/screenshot, NOT independently verified GitHub environment settings (the connector cannot read or mutate GitHub environments).

## Exact identities

- Repository: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- Parent WS52-LY safety-stop evidence: `c137-gc24-k2p03-b12-ws52ly-unprotected-trigger-safety-stop` HEAD `10dbe4e9fbba35fbfe6698187c146d4bef74e7d1`.
- New source-only branch: `c137-gc24-k2p03-b12-ws52lz-oneuse-readonly-preflight`. The branch name matches the GitHub UI rule viewed by the user.
- New workflow `.github/workflows/c137-b12-ws52lz-singleowner-readonly-preflight-disabled.yml` in source commit `c0b4f0648202f2e86d36300947f82cd3a02ba30c`, Git blob `ffecb7954f4a9448bef16fe9876a2215d724468a`.
- The source branch also inherits the WS52-LX safety-stopped workflow `.github/workflows/c137-b12-ws52lx-gh-readonly-docker-one-use.yml`, Git blob `e729b9a108970e121c9775390488848ea484e9cb`, also `if: false`. Neither workflow is authorized or executable.
- Environment: `b12-ws52lx-oneuse-readonly-review`; added as job-level `environment` binding on the new workflow. **Do not infer this is a required-reviewer gate.** Required reviewers are unchecked because there is no independently trusted second reviewer.
- Unique future path `docs/C137_GC24_K2_P03_B12_WS52LZ_READONLY_PREFLIGHT_ONE_USE_APPROVAL.txt` deliberately does NOT exist. **Do not create it without a new explicitly scoped human approval.**
- Original protected-by-procedure B12 branch `c137-gc24-k2p03-b12-cas-writer-cutover-source` HEAD `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af` and production `v320-renee-agrisuite-branding` HEAD `10041da3a3c4e4a7b8101f8183c243d056c7b128` should be independently checked again at final acceptance.
- Critical cutover gate blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268`, shared CAS authority blob `5fbb90903b0cc801be45c684f6c3db85c660979a` unchanged.

## Technical scope of prepared workflow (NOT an authorization)

- Exactly one workflow job, **literal `if: false`**, GitHub-hosted `ubuntu-24.04` specified, no checkout/third-party actions, no services, workflow `permissions: {}`, five-minute job timeout.
- Source filter: push to the exact WS52-LZ branch changing the unique approval path. Guard expects the fixed commit message `ci-b12(ws52lz-run): authorize one readonly Docker and GitHub commit API preflight`, run number one, run attempt one and exact repository/ref/event/one-commit metadata.
- The guard uses one bounded, unauthenticated TLS HTTPS GitHub REST commit metadata GET, with redirects prohibited, fixed API URL prefix/40-hex SHA, 10-second timeout, 65,536-byte response ceiling and sanitized fixed-code errors; checks `event.before` == GitHub API commit parent and exactly one added approval file.
- If and only if separately authorized in a **different source-frozen, executable** future workflow, Docker CLI operations would be precisely `version`, `info`, `container ls --all --no-trunc --quiet`, `volume ls --quiet`, and `network ls --format {{json .}}`, pinned to `unix:///var/run/docker.sock`; plus read-only `/proc/net/tcp*` port-3306 listener observation. No image pulls, container/volume/network mutations, DB connection, SQL, customer data, old backup, actual RC workers or production contact.
- The source checks `APPROVED_SOURCE_AUTHORITY=NOT_ATTESTED`. This value is intentional: checking GitHub commit lineage is not verifying independent human authorization or a trusted third-party witness.
- The inherited closed WS52-LU fake-GitHub-API parent-binding suite passed 22/22 on the user's Mac. This *new* environment binding and source identity have received GitHub static/semantic review only, not a fresh offline execution test or physical certification. Do not overstate or needlessly rerun old closed suites.

## Single-owner trust boundary

- User confirms only they control `abeycity4u`, so the recommended independent required-reviewer environment gate is unavailable. The saved environment branch-selection filter is a **deployment scope restriction**, not a writer restriction or external authorization gate. It does not stop an actor with sufficient repository-write authority from changing a workflow or adding a trigger.
- The repository was previously observed to have `protected: false` on WS52-LX and zero visible rulesets. Treat WS52-LZ branch protection as **unverified** pending authenticated GitHub inspection. No `required reviewers` configuration is currently claimed.
- Single-owner acceptance, if the user later chooses it, must be explicit and narrow: confirm verified frozen executable candidate HEAD, workflow blob, branch, trigger, read-only GitHub API egress and exact Docker allowlist; maintain separate written authorization; make **only one approval commit**. It is procedural authorization, NOT equivalent to a GitHub-enforced second-person approval, and must be described as such.
- **Do not change `if: false` on this reviewed source** or create an approval file to bypass the stop. Prepare any later executable candidate as a new independently checked source commit with its own freeze and release gate. Approval must be sought separately after review.
- An OS-level privilege separator for Docker's powerful Unix socket, independent runner/engine provenance, physical network egress restrictions, complete OCI image chain and RC01–RC14 concurrency certification remain unproven.

## Next controlled gate

- Remote GitHub semantic diff must verify this WS52-LZ branch differs from the WS52-LY parent only by this new disabled workflow and this report; no protected source changes, no Actions runs and approval file absent.
- Capture/verify final evidence-only HEAD and blob without altering them afterward. If future executable candidate is desired, perform a new full review and obtain a *fresh explicit one-use physical authorization* before creating any trigger.
- Both WS52-LK physical job `38857466870` and WS52-LP job `38863642180` failed before Docker. Both one-use authorizations are consumed and cannot be rerun.

`THIRD_PHYSICAL_PREFLIGHT=NOT_AUTHORIZED`
`RUNTIME_RC01_APPROVAL=NOT_GRANTED`
`REPAIRED_RC01_RC14_CERTIFIED_PASSES=0`
`MIGRATION_092=NOT_AUTHORIZED`
`B12_CUTOVER=HARD_OFF`
