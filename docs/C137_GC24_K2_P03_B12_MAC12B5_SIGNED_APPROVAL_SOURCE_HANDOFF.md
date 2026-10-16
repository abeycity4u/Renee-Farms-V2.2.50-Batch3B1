# B12 MAC-12B5 — Source-only signed one-use approval contract

**Source checkpoint:** MAC-12B4 PASS_OFFLINE_ONLY (16/16, PHP 8.2 lint, exit 90); MAC-12B5 source changes require their **own new verification**, not inherited from B4. **No SQL, fixture, Docker worker, historical restore or B12 cutover executed or authorized in B5.**

## Scope and rationale

B12-MAC-12B3 included `APPROVED_RUNTIME_COMMIT` and `APPROVED_SUPERVISOR_BLOB` literals in the supervisor source. Embedding hashes of the containing Git commit and supervisor blob into that same source is self-referential and cannot remain stable. MAC-12B5 **removes the circular self-pins**, replacing them with an independently signed external one-use approval manifest.

**The two independent literal execution fuses remain HARD OFF**:
- Python: `EXECUTION_ENABLED = False`
- PHP: `B12_RC01_EXECUTION_ENABLED = false`

In addition, the trusted approver key fingerprint is `TRUSTED_APPROVER_PUBLIC_KEY_SHA256 = None`; the future signing key setup/key fingerprint requires another separately reviewed authorization and commit. Neither a user-provided environment flag nor a forged approval file can enable this source state. This staging step does **not** establish a trusted signing key.

## External manifest (future contract; no current file or signature created)

Path is fixed under the test Mac user's home: `~/renee-b12-rc-mac/.b12-approvals/`; ownership must match the invoking user and directory mode must be 0700. Manifest `mac12c_one_use.json`, signature `mac12c_one_use.sig`, and the pre-approved RSA PEM public key `trusted_approver_public.pem` are required as private 0600 regular files; no symlinks.

Manifest JSON (exact keys, no duplicates): `contract`, `scope`, `run_id`, `issued_utc`, `expires_utc`, `repository`, `branch`, `source_commit`, `supervisor_blob`, `harness_blob`, `database`, `php_image_id`, `maximum_runs`, `synthetic_only`, `production_contact`.

- Contract: `B12-MAC12C-RC01-ONE-USE-V1`.
- Scope: `ISOLATED_SYNTHETIC_LAYER_EDIT_EDIT_ONLY`.
- Approved GitHub repo/branch, exact 40-hex source commit, supervisor blob, harness blob; exact disposable database and pinned PHP test image.
- `run_id`: 32 lowercase hex characters; `maximum_runs=1` (strict integer, not Boolean), `synthetic_only=true`, `production_contact=false`.
- `issued_utc` and `expires_utc`: exact UTC seconds, valid at verification time, period at most 15 minutes.
- Host reads each file into a bounded private snapshot and compares the public PEM SHA-256 fingerprint to a **future pinned signer**. The detached signature is checked with local `openssl dgst -sha256 -verify` against the snapshot before parsing the JSON. No network and no private signing key in repository or supervisor.
- The signed `source_commit` must match the clean checked-out Git branch HEAD; the signed supervisor blob must match its actual Git blob, avoiding self-reference. Original B12 CAS, cutover gate and schema still have separately pinned blobs.
- Claim directory `.b12-approvals/claims/` must be precreated owner-only mode 0700 during a future separately approved setup. Before any synthetic fixture is created, an atomic `O_CREAT|O_EXCL` 0600 `claimed_<run_id>` marker is written and fsynced. It is never deleted or reset on failure. This is a local anti-retry control, **not a tamper-proof monotonic ledger**; its safety depends on controlled ownership and authorized operators preserving claims.

## Signed scope versus execution authority

A signed manifest is necessary but not sufficient. Future work must separately authorize the trust anchor key ceremony, review the exact frozen post-B5 source blobs, review and test container mount/namespace/UID/secret behavior, turn on **both** hard fuses only under separately reviewed one-use release code, and explicitly approve exact synthetic fixture inserts, concurrency updates and teardown. No environment variable alone can turn on either fuse.

If the signature is invalid, signer untrusted/unset, manifest expired/too broad, source or container identity mismatched, replay claim exists, observer cannot witness real B waiting for A, or cleanup is uncertain: STOP, quarantine, never retry automatically. No root credential mounted into PHP; no production or staging access.

## Source-only offline verification gates

- Existing 16 B3 cases updated for new approval sentinels.
- 12 new offline negative/positive approval-contract tests added, including temporary **test-only RSA** signing/verification under a disposable directory (not a real trusted signer), tampering, expiry, schema/claim replay and wrong signer.
- New tests, source Python syntax and hard-off exit 90 **must be executed on the user's Mac** against exact GitHub blobs before MAC-12B5 can be fully certified. Do not treat previous 16/16 evidence as new test results.
- Neither PHP CAS source, PHP RC01 harness, application routes, production branch, nor B12 cutover gate should change in MAC-12B5; perform remote semantic diff review after push.

**Permanent noncertifying boundary:** repaired RC01–RC14 official certification remains 0/14; migration 092 is not authorized; `B12_CUTOVER=HARD_OFF`. A synthetic-only RC01, even if later successful, is preliminary and does not prove legacy NULL cycle handling or tenant/writer/browser production readiness.
