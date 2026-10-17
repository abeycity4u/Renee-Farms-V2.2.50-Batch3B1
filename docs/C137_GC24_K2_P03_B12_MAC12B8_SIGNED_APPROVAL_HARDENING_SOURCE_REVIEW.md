# C137 / B12 — MAC-12B8 signed-approval hardening (source only)

Date: 2026-10-17. Based on MAC-12B7 source security review and MAC-12B6 fixture-free Docker preflight. Working branch: `c137-gc24-k2p03-b12-mac12b1-disabled-rc01`. Parent HEAD before B8: `7c0cfcfc6d6e831348823371889700e82a6d5645`.

## Scope and non-authorization

This is a source-only security hardening checkpoint. No GitHub workflow, Docker container, SQL query, database fixture, production operation, signing key setup, RC01 controller/worker or migration was executed as part of this commit series. The two execution gates remain literal OFF: Python `EXECUTION_ENABLED = False`, PHP `B12_RC01_EXECUTION_ENABLED = false`. The trusted signer remains `TRUSTED_APPROVER_PUBLIC_KEY_SHA256 = None`. Official repaired RC01–RC14 certified passes remain 0; migration 092 and B12 cutover remain NOT AUTHORIZED/HARD OFF. Future approval requires a separate reviewed source commit and fresh one-use external signed authorization.

## Targeted source changes

1. `tools/b12_mac12b3_rc01_supervisor_disabled.py`: create a private source snapshot in a dedicated `b12_mac12b8_src_*` directory under the existing Mac B12 lab, *separate from the writable barrier mount*. Each of five pinned source files is copied from the verified checkout, independently Git-blob-verified and made 0400. Exact directory/file inventories, no symlinks, owner identity, modes and hashes are rechecked before constructing the future PHP command. Docker read-only per-file mounts now reference the snapshot, not the live Git worktree. Only an exact verified snapshot is removed after successful worker and owned-container cleanup. On failed run, preserve the source snapshot for manual inspection. This mitigates ordinary working-tree drift; it is **not** an immutable filesystem nor a defense against a malicious same-UID operator.
2. One-use anti-replay claim: keep `O_CREAT|O_EXCL`, restrictive 0600 mode and file fsync; now also fsync the claims parent directory after creating the claim. If that second sync fails, execution fails closed and the claim is preserved; no retry or silent deletion. Directory-fsync compatibility on the user's Mac is a **new runtime verification requirement**.
3. Approval freshness: re-validate the exact signed grant after potentially slow source/Docker/schema preflight, immediately before one-use claim, and again before the secret transport/worker launch. A late expiration after claim must stop execution and preserve the consumed claim.
4. `tests/test_b12_mac12b5_signed_approval_offline.py`: require OpenSSL for the real ephemeral RSA signing/verification/tampering case instead of allowing a unittest skip. Add offline tests for file-plus-directory fsync, fail-closed directory sync errors, pre-claim expiration and post-claim pre-worker expiration.
5. `tests/test_b12_mac12b3_host_supervisor_offline.py`: adapt PHP command tests to use the private snapshot source and add a tamper rejection test; source/worker remain hard-disabled.

## Verification gates still required on the user Mac

The *changed* source needs a new, exact-blob MAC-12B8 offline verification: Python syntax, 33 focused B3+B5 regression tests (17+16), **zero skips**, demonstrated real ephemeral RSA verification/tampering, Python hard-disabled entry exit 90, exact unchanged PHP harness and hard-OFF gate, and fully removed temporary test-source directory. If any test fails or skips, stop for targeted investigation; do not treat the B5 28-test pass as evidence for B8. Review exact remote semantic diff and unchanged protected heads after pushes. Do not re-run the closed MAC-12B6 Docker verification without drift evidence.

## Known residual pre-activation risks

The trusted signer/key custody and rotation/revocation plan remain unconfigured. Claims are local same-account anti-replay markers, not externally tamper-proof evidence. A malicious same-UID actor may still mutate local source snapshots. The newly staged snapshot and directory-fsync behavior are only source-reviewed; no real MAC-12C worker execution has occurred. Root observer timeouts and the real two-session lock wait remain unverified; migration, legacy NULL-cycle, all seven writers, permission/client tokens, full CAS and certification are distinct future gates.

## Next controlled step

Run only the new MAC-12B8 **offline** source and unit-test verification under the exact new Git HEAD with no Docker or SQL; review the copy-back evidence. Stop on first failure. Only after B8 passes should separately authorized signer trust-anchor design and activation-source review be proposed. No automatic MAC-12C execution or signing-key generation.
