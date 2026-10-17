# C137 / B12 — MAC-12B15B-R1 pre-ceremony readiness audit (source only)

**Date:** 2026-10-17. **Repo:** `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`. **Branch:** `c137-gc24-k2p03-b12-mac12b1-disabled-rc01`. **Reviewed prior HEAD:** `1663c62625ec4c8fe4a440ac34035c97f37cada0`.

**Classification:** SOURCE-ONLY READINESS AUDIT; **NOT THE B15C KEY CEREMONY**. The latest user instruction was simply “proceed,” after a question about whether the user or another trusted person would hold the operational signing key. It **does not answer that question or appoint a custodian**. No key generation, Mac commands, file provisioning, access to Docker/SQL/production, signer pinning, manifest signatures, test execution or activation is authorized by this checkpoint.

## 1. Verified existing source contract

- Supervisor `tools/b12_mac12b3_rc01_supervisor_disabled.py`, Git blob `9628679e80836cfc4c148f17790638c70c5e2cbf`; `EXECUTION_ENABLED=False`, `TRUSTED_APPROVER_PUBLIC_KEY_SHA256=None`, `B12-MAC12C-RC01-ONE-USE-V1`, scope `ISOLATED_SYNTHETIC_LAYER_EDIT_EDIT_ONLY`, disposable `renee_b12_disposable`, exact repo/branch/source/supervisor/harness pins and PHP image identity.
- PHP harness `tests/integration/k2p03_b12_mac_rc01_preliminary_disabled.php`, Git blob `2e288c611e0b43217eb1b8e15234ee823b65f940`, literal `B12_RC01_EXECUTION_ENABLED=false`. Both fuses are OFF.
- Existing manifest validation has **15 exact top-level JSON keys**, at most 15 minutes UTC validity, strictly integer `maximum_runs=1`, `synthetic_only=true`, `production_contact=false`, and a never-reused 32-lowercase-hex `run_id`. Signature verifier is local `openssl dgst -sha256 -verify`; trust pin is SHA-256 of the exact raw `trusted_approver_public.pem` file bytes, *not* of a normalized key or certificate.
- The Mac lab approval directory, should it ever be separately authorized, requires owner-only 0700 directory, 0600 regular files, no symlinks. The **private** signing key must not be kept in the runner's `.b12-approvals/`, Git, Docker mounts, production app credentials, chat, CI or automatic Mac lab backups.
- User-returned MAC-12B12-OFF report: all **44/44 offline tests passed, zero skips**, pinned source blobs 9/9, hard-disabled exit 90. Offline tests used **temporary disposable 2048-bit RSA keys**, not an operational signer. Neither RSA-3072 support nor an operational encrypted-key custody procedure has been tested or selected.
- R-A was explicitly selected **only** for the cooperative-operator, single disposable, preliminary, noncertifying Layer RC01 experiment in MAC-12B15A. It does not provide malicious-same-UID resistance, real-time revocation, or independent machine-enforced human sign-off. Formal repaired RC01–RC14 passes are still 0/14; migration 092 not authorized, B12 production cutover HARD OFF.

## 2. Pre-ceremony decision record: pending, not inferred

| Gate | Question requiring non-secret human approval | Status |
| --- | --- | --- |
| P1 | Who is the actual human project authorizer of the one-time experiment? | PENDING |
| P2 | Who is the private RSA signing-key custodian? **User self-custody or a different named trusted person?** | PENDING — “proceed” does not identify the custodian |
| P3 | Who runs the isolated Mac lab, preserves sanitized evidence, and serves as emergency stop owner? Document overlap openly. | PENDING |
| P4 | What approved signing platform, OpenSSL/provider/version, RSA key size/padding, passphrase/encrypted storage and offline custody are used? | PENDING |
| P5 | Where will non-secret approval records be retained away from temporary B12 cleanup? Who can review them? | PENDING |
| P6 | What are key-compromise, lost-key, recovery, retirement, retention and disposal rules for the one-off signer? | PENDING |
| P7 | Has an explicit **real key ceremony authorization** been granted naming custody, scope, source checkpoint and permitted operations? | NO |
| P8 | Have public PEM provenance/fingerprint, source-only trust pin review and fresh full offline tests been approved? | NO |
| P9 | Has any specific MAC-12C Docker/DB/worker action received an explicit one-use approval? | NO |

**No row marked PENDING is a failed automated test; it is an unresolved human/operational prerequisite.** The 44 prior offline PASS results are not reinterpreted as evidence for any P1–P9 gate.

## 3. Practical recommended R-A custody option (proposal only)

For this narrow nonproduction test, a **single designated human project owner** may serve as scope authorizer and Mac operator if that role overlap is expressly acknowledged under the accepted R-A assumptions. A separate custodian is preferable but must not be fabricated. The private key should be created/used only in a **dedicated, separately protected signing context**: ideally a genuinely offline machine/device with compatible signing workflow, or encrypted offline/removable storage unlocked briefly under the actual custodian's control. An encrypted key file kept in the same Mac account is a weaker fallback and is **not yet approved**; neither 0600 mode nor same-account encrypted storage constitutes independent R-B resistance.

**Cryptographic proposal:** dedicated RSA-3072/SHA-256 signer, subject to a future **non-secret** compatibility review of the actual signing provider and Mac verifier. This is not an algorithm selection or instruction to run key generation. Existing ephemeral 2048-bit unit tests do not establish RSA-3072 viability, encrypted private-key workflow, or custody. No operational signing private material or passphrases should be transmitted to GitHub, ChatGPT, logs or copy-back output.

**Minimal decision to unblock a future ceremony proposal:** an explicit answer either “I will personally custody the separate signing key” or “Another trusted person will custody it,” plus the preferred protected signing environment. If another person is selected, capture their actual responsibilities only after confirmation. Do not create a key first and fill in the custody record retroactively.

## 4. Separation of future authorizations

- **Next permissible work before P1–P6:** source-only checklist review, deterministic offline *test-only* compatibility plan, and non-secret custody decisions. Do not create a real key.
- **Future B15C:** only after P1–P7 are resolved and an explicit, narrowly scoped key-ceremony instruction; independently verify provenance, private-key custody, exact public PEM serialization/fingerprint; keep BOTH execution fuses OFF.
- **Future source-only pin:** requires a separate commit/review that may set `TRUSTED_APPROVER_PUBLIC_KEY_SHA256` to the independently verified raw PEM digest. Source changes change the supervisor blob and Git HEAD, so **fresh 44+ offline tests, zero skips** and semantic diff are mandatory. No fuse activation.
- **Future manifest/signature:** must bind a fresh final source HEAD and exact supervisor/harness blob; be valid <=15 minutes, one-run only, with a separately recorded human scope approval and no production contact. Do not pre-sign an old HEAD.
- **Future MAC-12C:** distinct explicit single-execution permission naming exact disposable fixture inserts/updates/cleanup, independent InnoDB wait observation, database/image identities, failure stop/quarantine; neither R-A selection nor signer setup authorizes this.
- **Any uncertainty:** fail closed, preserve one-use claim after creation, never automatically retry, never broaden migration/production scope.

## 5. Status and next human checkpoint

`MAC12B15B_R1=SOURCE_READINESS_AUDIT_COMPLETE`; `R_A_SELECTION=LIMITED_SYNTHETIC_ONLY`; `OPERATIONAL_KEY_CUSTODIAN=UNASSIGNED`; `RSA3072_PROVIDER_COMPATIBILITY=NOT_VERIFIED`; `CEREMONY=NOT_AUTHORIZED`; `REAL_SIGNER=NOT_GENERATED`; `TRUSTED_SIGNER=UNCONFIGURED`; `PYTHON_FUSE=OFF`; `PHP_FUSE=OFF`; `MAC12C=NOT_AUTHORIZED`; `MIGRATION_092=NOT_AUTHORIZED`; `B12_CUTOVER=HARD_OFF`; `OFFICIAL_RC01_RC14=0_OF_14`.

**Next decision:** establish who will actually hold the signing private key and their intended protected signing environment. Do not call this document B15C, do not issue a ceremony approval on behalf of the user, and do not change executable source based on an unspecific “proceed.”
