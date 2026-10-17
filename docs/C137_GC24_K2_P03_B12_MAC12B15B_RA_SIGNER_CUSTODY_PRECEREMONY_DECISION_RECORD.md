# C137 / B12 — MAC-12B15B R-A signer custody and pre-ceremony decision record

**Prepared:** 2026-10-17. **Repository:** `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`. **Branch:** `c137-gc24-k2p03-b12-mac12b1-disabled-rc01`. **Reviewed parent source HEAD:** `74ba9f320aa882f25f2f6acea152ebec93ce3856`.

**Stage classification: SOURCE DOCUMENTATION ONLY.** The user chose `R-A_LIMITED_SYNTHETIC_ONLY` for **one disposable, preliminary, noncertifying RC01 Layer edit/edit concurrency experiment** and authorized preparatory documentation. This is NOT authorization to create a real signer, provision key material, sign a manifest, modify the trust fingerprint, enable either fuse, access Docker/MariaDB, run a worker, use production records, enable migration 092, or cut over B12. The human authorizer, signer custodian, signing environment and evidence keeper are all **PENDING**, not assumed from repo ownership or a general instruction to proceed.

## 1. Fixed, source-grounded security contract

- `tools/b12_mac12b3_rc01_supervisor_disabled.py` Git blob `9628679e80836cfc4c148f17790638c70c5e2cbf`: literal `EXECUTION_ENABLED = False`, `TRUSTED_APPROVER_PUBLIC_KEY_SHA256 = None`. The supervisor verifies a SHA-256/RSA detached signature with `openssl dgst -sha256 -verify`, against a *snapshot* of exact approved manifest bytes and `trusted_approver_public.pem`. The SHA-256 fingerprint covers **raw PEM file bytes**, not a canonicalized public-key representation. An equally valid PEM with different whitespace fails the current digest check.
- PHP `tests/integration/k2p03_b12_mac_rc01_preliminary_disabled.php` Git blob `2e288c611e0b43217eb1b8e15234ee823b65f940`: literal `B12_RC01_EXECUTION_ENABLED = false`.
- Fixed approval directory **not yet provisioned for an operational signer under this authorization**: `~/renee-b12-rc-mac/.b12-approvals/` mode 0700; `mac12c_one_use.json`, `mac12c_one_use.sig`, `trusted_approver_public.pem` regular files mode 0600, owner-only, no symlinks; `claims/` 0700. **The private RSA key is NEVER stored or mounted in that directory, the Docker container, Git, CI, chat, a shell argument, logs or a normal B12 runtime backup.** Future provisioning requires separate approval.
- Existing V1 manifest has **exactly 15 fields**: `contract`, `scope`, `run_id`, `issued_utc`, `expires_utc`, `repository`, `branch`, `source_commit`, `supervisor_blob`, `harness_blob`, `database`, `php_image_id`, `maximum_runs`, `synthetic_only`, `production_contact`. It is limited to `B12-MAC12C-RC01-ONE-USE-V1`, `ISOLATED_SYNTHETIC_LAYER_EDIT_EDIT_ONLY`, `renee_b12_disposable`, `maximum_runs=1`, `synthetic_only=true`, `production_contact=false`, new unclaimed 32-hex run ID and a validity window no wider than 15 minutes.
- Verified baseline from the user's MAC-12B12-OFF report: 44/44 offline tests PASS, zero skips, nine exact source/test Git blobs PASS, test-only ephemeral RSA positive and swapped-signature negative PASS, local concurrent claim PASS, supervisor hard-OFF exit 90 and owned cleanup PASS. The test-only RSA keys used there are disposable and **must not become the operational signing key**. MAC-12B13–B15A were documentation-only steps.
- Protected status is unchanged by this proposal: repaired official RC01–RC14 certification `0/14`; `MIGRATION_092=NOT_AUTHORIZED`; `B12_CUTOVER=HARD_OFF`. No 150/200 VU load testing follows from an eventual preliminary synthetic RC01 PASS.

## 2. R-A trust boundary and ownership choices — human decision still required

Under R-A, the Mac account is presumed cooperative/trusted for this strictly disposable experiment; existing local claims and clock checks are **not** secure against a malicious same-UID actor. Even with an RSA signature, the current supervisor does not authenticate independent human approval or support real-time signer revocation. If this narrower assumption is no longer acceptable, STOP and reconsider R-B rather than claiming independent enforcement.

| Logical role | Expected responsibility | Assignment | Consequence if shared |
| --- | --- | --- | --- |
| Scope authorizer | Reviews one exact future synthetic fixture/worker/teardown proposal; issues dated one-use human decision | **PENDING** | Self-approval must be acknowledged; not an independent review |
| Manifest signer / private-key custodian | Protects the real private key; signs only the exact, separately authorized manifest | **PENDING** | If also Mac runner, independent-key-compromise protection is not claimed |
| Mac operator | Maintains isolated test environment; receives **public** PEM and signed one-use artifacts only | **PENDING** | Has local Docker/database capabilities; R-A does not stop deliberate bypass |
| Evidence custodian | Retains no-secret decision/fingerprint/run result and failure evidence outside routine temporary test cleanup | **PENDING** | Same-account log is traceability, not a tamper-proof ledger |
| Emergency stop owner | Stops new approvals, keeps fuses OFF, quarantines claims/signatures, reviews revocation or loss | **PENDING** | Same-role overlap must be written down and explicitly accepted |

**Feasible R-A role pattern (a proposal, NOT an assignment):** if one project owner operates the Mac and authorizes the lab test, that person can accept a *documented* scope-authorizer/runner overlap under R-A. Prefer a separate protected signer context/device and an independently retained human decision record if practicable. Do not assert a second person, second GitHub account or external reviewer exists. If roles overlap, write the accepted limitation into the eventual decision record; do not label it independent attestation.

## 3. Candidate private-key custody policy — no key generation yet

**Key scope:** a new *dedicated test-approval signing identity* for only this limited B12 preliminary synthetic RC01. It is not a Renee AgriSuite production signing root, API token, certificate authority, payment/webhook secret or database credential. A temporary RSA key generated inside an offline unit test is categorically ineligible.

**Cryptographic compatibility:** present verifier uses RSA-SHA256 with OpenSSL/LibreSSL `dgst` verification. **Candidate for a later reviewed ceremony:** RSA-3072 with SHA-256 and a properly encrypted private-key encoding, if compatible with the separately verified Mac crypto provider. This is a **proposal, not a selected specification**; record future decisions about provider/version, supported padding/format, entropy, passphrase protection and operational fingerprint before any generation. Switching algorithm/encoding or changing V1 schema demands a separately reviewed source change and new offline tests; never silently change the verifier.

**Custody candidates, in descending isolation preference:**
1. Dedicated offline signing device or machine controlled by the custodian, with encrypted non-exported or tightly protected signing material and an audited way to sign reviewed manifest bytes. Compatibility with current `openssl dgst` verifier and realistic portability must be established; do not claim that a device is supported before inspecting its documented interface.
2. Separately protected, disconnected encrypted storage/media for a dedicated signing account, available **only during a separately approved ceremony or individual sign-off**, then safely ejected/locked. Do not claim that removable media mounted under the same compromised Mac identity supplies R-B malicious-operator resistance.
3. A passphrase-encrypted file under the *same* Mac user with a manually executed workflow is the weakest candidate and is **not the default recommendation**; it would need explicit residual-risk acceptance. Do not place such a file alongside `.b12-approvals`, test-source trees or automatic backups. A file permission of 0600 alone is not sufficient key-at-rest protection.

**Private key custody rules:** never paste or transmit the private key/passphrase via ChatGPT, GitHub, screenshots, shell command arguments/history, clipboard sync, CI/CD, Docker mount, remote logging, developer notes, or output between COPY markers. Never put a private key into an attachment or public artifact. Do not sign an incomplete manifest or one that carries extra fields, a longer than 15-minute window, a second allowed run, production contact or an unreviewed source HEAD.

**Backup/recovery:** decide whether one verified encrypted offline recovery copy is warranted for a short-lived, single-use test. **PENDING** retention and expiration policy; if the key is lost or suspected leaked, stop approvals, keep execution fuses OFF and require a new authorized ceremony and reviewed fingerprint pin. Do not restore a test-only key, weaken cipher protections or silently reuse a previous approval.

**Rotation/revocation:** the supervisor pins a single exact-byte PEM digest and has no online revocation. Emergency response is operational STOP, preserve relevant evidence/claims and review a separate source patch setting/removing/replacing the trusted digest **while fuses remain OFF**. A still-valid signed approval is not proof of continued signer trust; never silently accept old and new keys concurrently.

## 4. Required public-key provenance and trust-pin review, future only

**Proposed evidence chain** (execute only after separate key-ceremony authorization):
1. Custodian establishes that the signing context is trusted, isolated from the Docker/production environment and appropriate for the chosen RSA signing mode; generation event yields a **new real test-approval key**, never reuse prior test fixtures.
2. Export the public verification key in one frozen PEM serialization. Independently compare SHA-256 of its **exact raw bytes** using two trusted readings or an independent human/device check. A formatting change requires recomputing/reviewing the source pin; matching RSA mathematics is not enough for the present contract.
3. Record only the public PEM digest, key algorithm/length, custodian identity, non-secret generation attestation, source commit and approved signer scope in separately retained evidence. No private-key bytes or passphrase.
4. **Separate source-only commit** may pin `TRUSTED_APPROVER_PUBLIC_KEY_SHA256` after reviewing the exact public PEM and obtaining explicit trust-anchor change authority, with Python `EXECUTION_ENABLED=False` and PHP `B12_RC01_EXECUTION_ENABLED=false`. Verify remote semantic diff, protected heads and fresh offline RSA/negative tests under the *new* exact Git blobs before provisioning. This document does not make that change.
5. Any later approval-file provisioning and actual one-use signature occurs only after another distinct approval, using the final future frozen `source_commit` and final supervisor Git blob. Never pre-sign based on today's HEAD because later signer pin/source edits will change it.

**Fail immediately** if public PEM origin/encoding is uncertain, provider compatibility fails, private material appears in any working-copy tree/log/chat/CI backup, source pin is not exact, roles and emergency-stop decision are missing, or the protected production branch changes unexpectedly.

## 5. Non-secret authorization and evidence templates (blank, not approvals)

**Pre-ceremony decision record (proposed fields; blank until independently approved):**
- `ceremony_decision_id`: PENDING
- `project_authorizer`: PENDING
- `private_key_custodian`: PENDING
- `signing_context_and_account_boundary`: PENDING
- `operator_and_role_overlap_exception`: PENDING
- `approved_crypto_provider_algorithm_key_length_format`: PENDING
- `encrypted_key_storage_and_recovery_owner`: PENDING
- `independent_nonsecret_evidence_location`: PENDING
- `key_loss_compromise_revocation_contact`: PENDING
- `ceremony_window_and_key_retirement_rule`: PENDING
- `approved_scope`: single disposable synthetic RC01 signer setup ONLY; no key ceremony authorization has been issued by this template
- `reviewed_source_commit_and_blob_inventory`: freeze at a future pre-ceremony checkpoint, never trust an old hash automatically

**Separate future one-use *execution* approval record** (do not issue during B15B) must identify named human approver, exact future source HEAD and supervisor/harness blobs, approved public PEM SHA-256, signature/manifest digest, disposable DB/image/network/fixture-plan identity, never-used `run_id`, <=15-minute UTC issued/expiry, explicit two-worker/host-observer/teardown scope, STOP/quarantine owner, and express one-time authorization. Human sign-off remains operational under R-A; the present V1 verifier does not independently check an external human decision.

**Audit retention:** agree on a protected location and retention/erasure schedule without storing any secret. Keep sanitized authorization decision, public digest, run ID, consumed-claim event, result or first failure, fixture teardown proof and quarantined-artifact identity if any. Do not expose user/customer records. A local fsynced claims file is not an adversarially immutable audit record.

## 6. Pre-ceremony acceptance gate and order of operations (not permission to execute)

| Gate | Needed before proceeding | State after B15B |
| --- | --- | --- |
| G1 | R-A limited user decision for this synthetic test | ACCEPTED FOR DESIGN ONLY |
| G2 | Authorizer, signer custodian, Mac operator, evidence and emergency-stop owner assigned | PENDING |
| G3 | Approved crypto provider/key length/private encrypted storage and no-secret handling | PENDING |
| G4 | Independent recording method and recovery/revocation/retirement policy | PENDING |
| G5 | Exact future source/branch/fuse/DB identity reviewed and pre-ceremony decision expressly authorized | PENDING |
| G6 | Separate **actual real key ceremony** after G2–G5 | NOT AUTHORIZED |
| G7 | Public key exact-byte fingerprint source pin; both execution fuses still OFF; new offline tests | NOT AUTHORIZED |
| G8 | Approval files, one-use exact signed manifest and final operator run authorization | NOT AUTHORIZED |
| G9 | MAC-12C single preliminary synthetic concurrency worker/database execution | NOT AUTHORIZED |
| G10 | Formal RC01–RC14 certification, migration 092, B12 production cutover | 0/14; NOT AUTHORIZED / HARD OFF |

**Critical distinction:** approving R-A is *not* approval to create the key. Approving key generation is *not* approval to pin the digest, issue a signed manifest, enable the Python or PHP fuse or operate the disposable database. No generic “proceed” broadens any of these gates.

## 7. MAC-12B15B handoff and status

`MAC12B15B=PRECEREMONY_CUSTODY_DESIGN_COMPLETE_ONLY` after verified one-document Git diff; `R_A_SELECTION=LIMITED_SYNTHETIC_ONLY`; `PERSONNEL_ROLES=UNASSIGNED`; `REAL_RSA_KEY=NOT_GENERATED`; `CEREMONY=NOT_AUTHORIZED`; `TRUSTED_SIGNER=UNCONFIGURED`; `ONE_USE_APPROVAL=NOT_ISSUED`; `PYTHON_FUSE=OFF`; `PHP_FUSE=OFF`; `MAC12C=NOT_AUTHORIZED`; `MIGRATION_092=NOT_AUTHORIZED`; `B12_CUTOVER=HARD_OFF`; `OFFICIAL_RC01_RC14=0_OF_14`.

**Next controlled step:** obtain **non-secret** human decisions for the five pending G2–G5 categories: who will control signing, where private key custody and sanitized approval evidence will reside, role overlap, crypto provider/compatibility and emergency stop/recovery. After those facts are recorded, propose an *explicitly authorized* pre-ceremony audit, still without generating a signer by default. Do not create key material in response to an unspecific continuation instruction.
