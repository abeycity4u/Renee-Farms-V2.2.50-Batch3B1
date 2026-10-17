# C137 / B12 — MAC-12B15B-R2 Mac encrypted-storage design and test-only RSA-3072 preflight

**Prepared:** 2026-10-17. **Repository:** `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`. **Branch:** `c137-gc24-k2p03-b12-mac12b1-disabled-rc01`. **Reviewed parent HEAD:** `eb49c5d58988c8b042ee70dd2e1f209c5c177840`.

**Scope:** documentation + an optionally user-operated local **test-only** cryptographic preflight. This milestone neither generates nor authorizes a *real* signing key, nor creates a persistent encrypted disk image, nor configures a trusted public-key fingerprint, signed approval manifest, live Docker/MariaDB connection, source fuse, MAC-12C workers or migration/cutover. Any test-only temporary key made by the offline compatibility test is disposed of at the end of that test and must NEVER be used as an operational B12 signing identity.

## 1. Decisions grounded in this conversation (not inferred)

- **R-A chosen and explicitly accepted only for a single isolated disposable preliminary noncertifying Layer edit/edit RC01 race experiment.** It assumes the Mac operator is cooperative; it does not defend against deliberate same-user account compromise and does not replace production security or formal certification.
- **Operational signing private-key custodian:** the user explicitly stated **"Yes, I will manage it."** This assigns the *custody role* only; it is NOT authorization for any actual key ceremony or signing.
- **Available hardware:** the user reports **no dedicated USB drive or separate offline computer**.
- **Proposed storage:** a password-protected macOS **AES-256 encrypted disk image** on the existing Mac, kept **unmounted** except for a future explicitly authorized signing task; no location, credentials, image, key or keychain item has been created.
- **Unresolved human/technical decisions:** Mac operator and authorizer role overlap, evidence keeper, emergency stop owner, exactly where image resides and what backup/sync exclusions exist, strong passphrase handling/recovery, crypto provider/format/signing compatibility, retention and key retirement. Do not claim these settled because of a generic "proceed".

## 2. Existing source constraints independently read before this document

- Supervisor `tools/b12_mac12b3_rc01_supervisor_disabled.py` Git blob `9628679e80836cfc4c148f17790638c70c5e2cbf`: `EXECUTION_ENABLED = False`, `TRUSTED_APPROVER_PUBLIC_KEY_SHA256 = None`, `B12-MAC12C-RC01-ONE-USE-V1`, `ISOLATED_SYNTHETIC_LAYER_EDIT_EDIT_ONLY`. Uses macOS `openssl dgst -sha256 -verify <public.pem> -signature <signature.sig> <manifest.json>` for detached RSA-SHA256 approval; pin is **SHA-256 of exact raw public PEM bytes**, including whitespace/line-endings, not normalized key mathematics.
- PHP harness `tests/integration/k2p03_b12_mac_rc01_preliminary_disabled.php` blob `2e288c611e0b43217eb1b8e15234ee823b65f940`: `B12_RC01_EXECUTION_ENABLED = false`.
- Operational private RSA material is **not** allowed in `~/renee-b12-rc-mac/.b12-approvals/`, Git working copies, GitHub, chats, Docker, CI, root/test MariaDB secrets or readable shared folders. If later approved, the runner receives *only* exact frozen public PEM, single signed manifest and detached signature under owner-only 0700 directories / regular 0600 files.
- Existing MAC-12B12-OFF offline test evidence: **44/44 PASS, zero skipped**, using **ephemeral test-only RSA-2048**, Git blobs checked, Python/PHP fuses OFF. That evidence **does not establish RSA-3072 or encrypted private-key workflow compatibility**. MAC-12B13–B15B-R1 were documentation-only.

## 3. Proposed macOS encrypted image: configuration requirements (do NOT create during R2)

For a future expressly authorized setup on the user's Mac, use **Disk Utility → File → New Image → Blank Image**. Candidate settings, pending approval:
- Store the image in a **private local directory outside any Git clone, B12 lab, Docker mount, synced Desktop/Documents/iCloud Drive, Dropbox/OneDrive, shared or web-served folder**. Check actual Mac backup/sync configuration first; do not infer a directory is excluded just because its name is private.
- Descriptive innocuous filename, purpose explicitly *B12 synthetic signer*. Choose **APFS** file system (Mac-only), **256-bit AES encryption**, **read/write disk image** and a modest capacity sufficient for one small private-key file and nonsecret signing notes; exact size/format and storage path to be recorded after verifying current Disk Utility interface, not guessed.
- Use a **unique high-entropy passphrase** stored separately from the Mac session as appropriate to user custody. Prefer not saving it in the login Keychain or any browser/cloud password sync for this constrained lab design; verify whether macOS prompts to remember it and decline if possible. NEVER paste passphrase into commands, chat, GitHub, CLI history, logs, screenshots, clipboard-sync or test reports.
- Mount manually for a separately approved signing task; unmount/eject immediately afterward; verify it is no longer mounted before resuming B12 work. Lock Mac screen when unattended. Mounted storage can be read by a compromised Mac account: this is an **accepted R-A residual risk**, NOT R-B isolation.
- Confirm FileVault/host account security, Time Machine/image backup and network sync boundaries *before* real key generation. An encrypted file may be backed up **while unlocked or while the host is compromised**, so "encrypted disk image" must never be described as an automatic guarantee of backup safety. A short-lived one-use key may instead be retired and regenerated under separate authorization if lost.
- Do not place `.b12-approvals`, its claims directory, signing private key and approval evidence together in the same writable/synced location. Unmounting the image is necessary but not proof of machine isolation or deletion of secrets from swap/memory/backups.
- On suspected leak, lost passphrase, unexpected mount, storage drift or compromised Mac: **STOP**, keep both fuses OFF, do not sign new manifests, preserve nonsecret evidence, obtain a new specific review for any key replacement or incident response. There is no source-level online revocation in V1.

**Future storage acceptance evidence (nonsecret only):** recorded path class (not arbitrary sensitive paths), APFS, AES-256, expected owner/mode, manual-mount procedure, absence from project/Docker/shared mounts, backup/sync decision, ability to manually eject and confirm not mounted, owner/custodian attestation and emergency-stop/retirement policy. A verbal "it's encrypted" without platform confirmation is insufficient.

## 4. Mac-native test-only RSA-3072 compatibility preflight

A separately supplied script may be run interactively by the user on their Mac **before any operational key ceremony**. The script must:
1. Assert Darwin platform, executable `openssl` and `hdiutil` availability; `hdiutil` availability is **not** proof that a particular AES-256 disk image has been created or mounted.
2. Create an owner-private temporary directory with permissions 0700 in the OS temporary location, not in `~/renee-b12-rc-mac`, and generate a **test-only 3072-bit RSA key**, never an operational signing key.
3. Export only a temporary public PEM; verify `RSA-3072/SHA-256` sign → `openssl dgst -sha256 -verify` returns `Verified OK`; confirm deliberate **message tampering is rejected**; record test-only public PEM exact-byte SHA-256 fingerprint consistency (without printing PEM or private key).
4. Inspect local OpenSSL output **without** pasting a private key, passphrase, file contents, raw OpenSSL command stderr or unneeded environment data into ChatGPT.
5. Cleanup **only that exact temporary directory** and its test-only files, even after failure, and report proof of no remaining temporary directory. No `hdiutil create` / `attach`, no real disk image or Keychain mutation, no Docker/SQL/worker, no signer pin.
6. Return only concise, nonsecret results between literal `#####COPY FROM HERE#####` and `#####COPY TO HERE#####`, with `RESULT=PASS_OFFLINE_TEST_ONLY` only after every assertion and cleanup succeeds. Otherwise fail closed and do not certify Mac compatibility.

**Important boundary:** this verifies only the test Mac's crypto provider at that time can generate/verify ephemeral RSA-3072 and detect tampering. It does **not** verify encrypted disk image operation, passphrase-protected RSA private-key interoperability, operational signing-key storage/custody, private-key provenance, an independent human authorization record, or any one-use RC01 runtime behavior. A different provider, key format or future host upgrade may require fresh verification.

## 5. Explicit go/no-go for next steps

| Gate | After MAC-12B15B-R2 |
| --- | --- |
| R-A limited single disposable preliminary RC01 | SELECTED |
| Operational key custodian | USER CONFIRMED, no other custodian assumed |
| macOS encrypted image | AES-256/APFS design proposed; **NOT CREATED** |
| Mac RSA-3072 test-only compatibility | **PENDING local Mac test result** |
| Real key algorithm/provider/encrypted format | PROPOSED, NOT APPROVED |
| Recovery/passphrase, owner-role overlap, evidence retention and emergency-stop policy | PENDING nonsecret decisions |
| Real private RSA key | **NOT GENERATED / NOT AUTHORIZED** |
| Public PEM fingerprint trust source pin | **NONE / NOT AUTHORIZED** |
| Approved live signed one-use manifest | **NONE / NOT AUTHORIZED** |
| Python and PHP fuses | **OFF** |
| MAC-12C Docker/SQL/worker execution | **NOT AUTHORIZED** |
| Migration 092 | **NOT AUTHORIZED** |
| B12 production cutover | **HARD OFF** |
| Official repaired RC01–RC14 certification | **0/14** |

**Stage result:** `MAC12B15B_R2=STORAGE_PROCEDURE_AND_TEST_ONLY_COMPATIBILITY_PLAN_DOCUMENTED`. This is a source-only audit result, not an operational security attestation. **Next step:** obtain user Mac test-only compatibility report, then resolve nonsecret passphrase/backup/recovery choices. No operational key, persistent encrypted image or execution may be created simply because the user says "proceed".
