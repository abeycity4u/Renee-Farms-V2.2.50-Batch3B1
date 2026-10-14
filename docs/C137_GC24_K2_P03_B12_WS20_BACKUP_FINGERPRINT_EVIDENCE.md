# C137 / GC24 / K2-P03 / B12 — WS20 local backup fingerprint evidence

**Date: 2026-10-15. Classification: USER-RETURNED MAC FILE EVIDENCE + SOURCE-ONLY RECORD. Not a verified production backup or isolated clone.**

## Immutable history and safeguards

- Repository: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- WS19 parent/source branch: `c137-gc24-k2p03-b12-ws19-backup-provenance-discovery-source`, HEAD `199d199779d5e951f5fff35774d2f7ef5146a9f8`.
- WS20 branch: `c137-gc24-k2p03-b12-ws20-backup-fingerprint-source`.
- Protected production branch `v320-renee-agrisuite-branding`: `10041da3a3c4e4a7b8101f8183c243d056c7b128`.
- Protected original B12 branch `c137-gc24-k2p03-b12-cas-writer-cutover-source`: `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af`.
- Cutover gate `lib/daily_record_revision_cutover_gate.php`: blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268`, hard OFF.
- Historical WS07 runner: blob `a1550c1ff9b6aa1ab05f45cafc4cd59c8e9f4bdb`, unchanged.

## WS19 Mac filename discovery, as reported

The user returned `AUDIT=B12_WS19_MAC_BACKUP_METADATA`, `RESULT=LOCAL_METADATA_DISCOVERY_COMPLETE`. Five user-owned top-level candidate filenames were identified, including `~/Downloads/database.sql` at 619,163 bytes, modified 2026-10-14. Filename/size alone did not establish SQL contents or origin. The historically documented C137 encrypted test-fixture backup remains separate; it was created for `renee_c137_gc24_http`, not attested customer production.

## WS20 user-returned file fingerprint

The user returned `AUDIT=B12_WS20_BACKUP_FINGERPRINT` with `RESULT=WS20_BACKUP_FINGERPRINT_COMPLETE`:

| Observed field | User-returned value |
| --- | --- |
| Candidate | `~/Downloads/database.sql` |
| File kind | regular file, not symlink |
| Bytes | `619163` |
| SHA-256 | `c7b99b6e00fc34b26eddd806cb399cad32187645fd4d8654f97fa3ecd7eef259` |
| POSIX permissions | `644` |
| Creation | `2026-10-14_19:06:45` (Mac-reported local time, no offset supplied) |
| Modification | `2026-10-14_19:06:45` |
| Content read for SHA-256 | YES; raw SQL displayed NO |
| SQL statements executed / database connections / database writes | `0 / 0 / NO` |
| Production contact / backup changes | `NO / NO` |
| Reported origin | `PRODUCTION_ORIGIN=NOT_INDEPENDENTLY_VERIFIED` |
| B12 cutover | OFF |

This SHA identifies those exact local bytes as returned by the Mac command, **not** dump consistency, table completeness, unmodified-from-production provenance, database identity, successful restoration, or permission to use the customer data. The user's earlier statement that a separately pictured backup came from live production is not sufficient to match that pictured artifact uniquely to this exact file.

### Immediate privacy concern

Mode `644` makes an unencrypted plain SQL candidate *potentially readable* by other local users who can traverse the path. Do not display, share or upload the file. Recommended next user-side controlled action: verify file owner, expected size/SHA and path; change **only local permission metadata** to `600` with the user's explicit action; confirm SHA remains unchanged and effective mode is `600`. This is a local safety hardening step, not a database/write operation. Do not expose local username, home path, credentials or customer records in output.

## Subsequent structural-only gate (not executed)

Only after permission hardening, a user-invoked, offline, networkless structural-only **scan of the already pinned file** may classify SQL dump markers while reporting no raw SQL, customer values, insert payloads, account names or file contents. Initial allowed summaries: regular file, immutable SHA match, recognized SQL schema statement counts, specifically allowlisted three Daily Record tables and production cycles presence, high-level row-insert statement counts, transaction/DDL dump-format markers, and uncertainty warnings. Avoid dumping data or writing extracted SQL to another file. A structural scan cannot certify backup integrity, snapshot consistency or live provenance.

Actual production-derived physical clone restoration, MariaDB queries, use of `scripts/k2p03_b12_schema_readonly_gate.php --run`, RC01–RC14, migration 092, B12 cutover and D12/capacity testing **all require independent future host/fixture/operation-scoped authorizations**. Never use the existing C137 historical fixture as a customer clone.

## WS20 disposition

`MAC_FILE_FINGERPRINT=USER_VERIFIED`; `FILE_PERMISSIONS=644_NEEDS_HARDENING`; `CUSTOMER_BACKUP_ORIGIN=UNVERIFIED`; `DUMP_CONSISTENCY=UNKNOWN`; `PHYSICAL_CLONE=UNATTESTED`; `DATABASE_CONNECTIONS=0`; `REPAIRED_RC01_RC14_PASSES=0`; `B12_CUTOVER=HARD_OFF`; `CAPACITY_TESTING=ON_HOLD`.

No PHP/application, SQL, migration, CI, deployment, database or server changes were made by this document.
