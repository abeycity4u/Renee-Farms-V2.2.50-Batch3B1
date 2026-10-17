# C137 / B12 — MAC-12B9 trust-anchor readiness (design only)

**Date:** 2026-10-17. **Repository:** `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`. **Branch:** `c137-gc24-k2p03-b12-mac12b1-disabled-rc01`. **Reviewed parent HEAD:** `c7240edc08992c2335b5418fd323cd70f0126957`.

**Status:** SOURCE/DESIGN REVIEW ONLY. This document defines a proposed approval/signing-key ceremony and explicit blockers. It does *not* configure, authorize, generate, deploy or activate a signing key. It does not approve a test, issue a signed manifest, execute a worker, or touch the isolated database. The decisions labelled **PENDING** below have **not** been made by the user.

## Grounded existing MAC-12B8 contract

Verified at reviewed parent HEAD:
- `tools/b12_mac12b3_rc01_supervisor_disabled.py` Git blob `9628679e80836cfc4c148f17790638c70c5e2cbf` has `EXECUTION_ENABLED = False` and `TRUSTED_APPROVER_PUBLIC_KEY_SHA256 = None`. Future signer PEM is a fixed exact-byte SHA-256 fingerprint, not an environment value. The source uses RSA-SHA256 verification via `openssl dgst -sha256 -verify` against snapshots of fixed files.
- PHP `tests/integration/k2p03_b12_mac_rc01_preliminary_disabled.php` remains `B12_RC01_EXECUTION_ENABLED = false`, Git blob `2e288c611e0b43217eb1b8e15234ee823b65f940`.
- Approval files, if ever explicitly authorized to exist, are fixed at `~/renee-b12-rc-mac/.b12-approvals/`: `mac12c_one_use.json`, `mac12c_one_use.sig`, `trusted_approver_public.pem`, each regular owner-only 0600; enclosing directory owner-only 0700. Separate `claims/` must be 0700.
- Exact signed JSON keys: `contract`, `scope`, `run_id`, `issued_utc`, `expires_utc`, `repository`, `branch`, `source_commit`, `supervisor_blob`, `harness_blob`, `database`, `php_image_id`, `maximum_runs`, `synthetic_only`, `production_contact`. No duplicate or extra fields.
- Current contract `B12-MAC12C-RC01-ONE-USE-V1`; scope `ISOLATED_SYNTHETIC_LAYER_EDIT_EDIT_ONLY`; `maximum_runs` strictly integer 1; `synthetic_only=true`; `production_contact=false`; timestamps UTC seconds, issued <= now < expiry, window <= 15 minutes. `run_id` is unique 32 lowercase hex.
- Signature is verified *before* parsing the strict payload; public PEM exact-byte SHA-256 must match literal compiled trusted fingerprint; source commit / supervisor Git blob / harness blob are independently verified. Freshness is checked again after preflight and after the irreversible claim, before workers. A claim uses `O_CREAT|O_EXCL` 0600, fsync(file), fsync(claim directory). Claimed IDs are preserved even on failure; no automatic retry.
- Private source snapshot is separately hash-verified and mounted read-only. Supervisor runs only when Python and PHP fuses are explicitly changed under separate reviewed approval.
- MAC-12B8D local user-returned evidence: exact eight Git blobs PASS; 34/34 offline tests, zero skips; ephemeral **test-only** RSA positive test PASS; supervisor direct entry exits 90; subsequent MAC-12B8E removed exactly two preserved temporary folders. No real signing authority created.
- Formal program limits: `REPAIRED_RC01_RC14_CERTIFIED_PASSES=0`, `MIGRATION_092=NOT_AUTHORIZED`, `B12_CUTOVER=HARD_OFF`. Synthetic preliminary RC01 would not establish production readiness.

## Trust model: roles and separation (proposed; NOT yet adopted)

1. **Project authorizer / scope approver (PENDING identification).** Human owner signs off on a **single exact proposed** synthetic fixture/worker operation after reviewing Git commit, result risk, DB identity, planned cleanup and rollback/quarantine. This approval must be recorded separately from the cryptographic signature.
2. **Signing-key custodian (PENDING identification).** A different security principal from the test executor should hold an RSA private key offline or in a hardware-backed protected device. No private key in Git, CI, Docker, cPanel, VPS, MariaDB, backups available to the runner, or chat transcripts. The custodian independently checks written human authorization, exact manifest fields, expiry, and unique ID before signing. Avoid ungrounded claims that the Mac account is independent unless verified.
3. **Mac isolated runner.** Holds only exact reviewed *public* PEM and its pinned SHA-256 digest plus one signed manifest/signature for the sole authorized run. Must not possess the private key, credentials for production/staging, or any bypass env flag. Must keep both execution fuses OFF until a *separate*, fully reviewed activation step.
4. **Independent auditor (PENDING designation).** Retains human authorization, source/approval fingerprints, claim record and sanitized outcome in tamper-resistant storage outside the same Mac account. The current local `claims/` directory alone is not tamper-proof.

If the same person must hold multiple roles, document that concentration of authority as a risk and require a compensating approval/evidence check. No identity is assigned by this review.

## Key policy and exact fingerprint design (proposed; not a ceremony)

- Use a freshly created, dedicated RSA keypair compatible with the committed `openssl dgst -sha256 -verify` workflow. **PENDING:** key size, signer platform, cryptographic provider/version, lifecycle and private-key escrow/recovery policy. Do not reuse the ephemeral RSA keys generated by offline tests.
- For this source version, the pin is `sha256(exact raw bytes of trusted_approver_public.pem)`, not a normalized key identity or certificate. Two PEM encodings of the same key are **not interchangeable** for the existing check. Record the exact PEM byte hash in a separately reviewed, cryptographically authenticated decision record; independent second-person comparison before any *future* source commit may pin it.
- Never paste raw private-key material, passphrases or signing tokens into chat, source control, command history, CI logs, or copy-back output. The public key is not confidential, but its correctness and provenance are critical.
- Use explicit, human-verifiable domain/scope; do not make a generic farm-wide signer. No approval for production, staging, migrations, B12 cutover or other test suites is expressible by the current constrained contract.
- **Open design question:** public-key exact-byte PEM digest versus an algorithm-tagged standardized SubjectPublicKeyInfo fingerprint and independent signer key-ID/revocation record. Changing the encoding/algorithm requires a new contract/source revision and its own verification; do *not* silently reinterpret the current 64-hex pin.

## Candidate future key ceremony (CHECKLIST ONLY; DO NOT EXECUTE)

Preconditions, all separately authorized: human scope approver and distinct key custodian named; offline signing environment selected and audited; tested secure private-key storage; means to independently inspect exact public PEM and public fingerprint; independent audit record; revocation and recovery mechanism; exact frozen Git SHA and offline tests green; source/signer pin changes to be reviewed separately with both fuses still OFF.

Proposed sequence after *new* explicit authorization:
1. Independently attest the signing environment and actor; ensure it cannot reach production or staging and will not expose key bytes. Generate a fresh dedicated key under custodian control; document generation attestation and retention location without revealing secrets.
2. Export public key; independently verify algorithm, encoding, full exact-byte SHA-256 fingerprint, ownership and provenance. Signer/authorizer cross-check fingerprint over a second trusted channel; store immutable audit record.
3. Review an exact **source-only** patch pinning the trust anchor to the verified public PEM hash while keeping `EXECUTION_ENABLED=False` and PHP OFF. Remote semantic diff and fresh offline tests are mandatory. Do not issue a one-use manifest at this point.
4. If separately authorized, provision public PEM and private approval/claims directories on the isolated Mac; validate owner, modes, no symlinks, no copied signing private key. Prove trust rejection and signature tamper rejection without workers.
5. **Only in a later, separate approval**, propose the exact bounded one-use manifest, inspect all source/database/synthetic fixture evidence, obtain external human authorization, have custodian sign. Treat each `run_id` as permanently spent once claimed, including failure.
6. Activating Python and PHP fuses, creating fixtures, running real RC01 workers, stopping containers and teardown require **their own explicit decision**. Successful synthetic RC01 remains noncertifying. Never automatically retry.

## Revocation, rollover and recovery (design requirements)

- **Emergency stop:** both fuses stay OFF by default. If signer compromise is suspected, stop future approvals, quarantine existing manifests/signatures and preserve claim evidence; block all future activations pending review. A cryptographically valid old manifest is not evidence that a signer remains trusted.
- **Current implementation limit:** source literal `TRUSTED_APPROVER_PUBLIC_KEY_SHA256` is a single pinned PEM digest. There is no independent real-time revocation list, key epoch or external tamper-proof replay service. Accordingly, revocation currently means independently preventing activation and committing/reviewing a new trusted fingerprint under an authorized source change. Any separate revocation mechanism would need its own design and tests.
- **Rotation:** create a new dedicated key under a separate ceremony; review replacement fingerprint; don't accept old and new keys silently or leave two valid trust anchors. Test old-key rejection, new-key verification, exact-byte tampering, invalid signatures, late expiry, duplicate JSON keys and replay.
- **Loss of signing key:** halt signer-dependent work; do not backdate an approval or substitute the ephemeral offline RSA test key. Any recovery is a new independent ceremony plus source pin/review.
- **Compromised runner account / removed claims:** local 0700/0600 permissions and fsync are *not* a robust defense against a same-UID actor who can replace or delete claim files. Independent append-only/out-of-band signed approval register is a future prerequisite if adversarial runner compromise is in scope; do not claim exactly-once guarantees against a malicious operator.
- **Time integrity:** the verifier trusts Mac UTC clock for its <=15 min approval. Before any run establish clock provenance/drift policy, fail closed on clock anomaly, and ensure expiration is valid at pre-worker checks. No automatic clock modification authorized.
- **Signer software supply chain:** verify OpenSSL/provider versions and known-good entropy/key storage tooling as part of a separately authorized ceremony. Current 34/34 offline evidence proves local test workflow only, not operational key custody.

## Source-only decision matrix (MAC-12B9 disposition)

| Gate | Status | Required evidence |
| --- | --- | --- |
| Frozen current Git HEAD and signed-approval source contract inspected | REVIEWED | HEAD `c7240edc08992c2335b5418fd323cd70f0126957`, supervisor `9628679e80836cfc4c148f17790638c70c5e2cbf` |
| Signer human identity and independent custodian | PENDING / NOT APPROVED | Named role, access boundary, human authorization record |
| Key type/provider/custody/recovery | PENDING / NOT APPROVED | Reviewed key ceremony proposal; no key generated |
| Public key fingerprint pinning and provenance | PENDING / NOT CONFIGURED | Independently verified PEM fingerprint, reviewed source patch |
| Emergency revocation and trust continuity | DESIGN GAP | Revocation/rotation/rollback plan and negative tests |
| Out-of-band approval/replay audit | DESIGN GAP | Independent preservation and operator-compromise risk policy |
| Time integrity / one-use signer/runner boundary | PENDING | Clock evidence, claim integrity and offline gate proof |
| Python/PHP fuses | HARD OFF | `False`, `false` |
| Runtime synthetic RC01 authority | NOT AUTHORIZED | Separate explicit scope-specific signed one-use approval and activation review |
| Formal RC01–RC14 certification and migration | NOT AUTHORIZED | No certification passes and no migration 092 |

**MAC-12B9 result:** Source-only trust-anchor DESIGN DOCUMENTED; signer and activation readiness BLOCKED pending independent decisions. No signer can be treated as configured by this document.

## Exact next controlled milestone (recommended)

**MAC-12B10 — offline threat-model/negative-test contract review, still NO key ceremony or execution.** Confirm desired signer/operator trust separation, publish a *source-only* candidate revocation and sign-off mechanism, and define the testable, deterministic acceptance gates before requesting authorization for any key generation. Require a distinct user authorization for the key ceremony and for any activated execution. Do not broaden MAC-12B9 into MAC-12C.
