# C137 / GC24 / K2-P03 / B12 — WS19 backup provenance classification and metadata-only discovery

**Date: 2026-10-15. Status: SOURCE-ONLY PROVENANCE TRIAGE PREPARED, NOT A RESTORED CLONE OR DATABASE AUTHORIZATION.**

## Evidence boundaries

- WS19 fork parent: WS18 `efcffe98a8cd87ff0695c28959f50be7569aaebb`.
- Protected production source: `v320-renee-agrisuite-branding` at `10041da3a3c4e4a7b8101f8183c243d056c7b128`.
- Protected original B12 source: `c137-gc24-k2p03-b12-cas-writer-cutover-source` at `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af`.
- B12 cutover gate blob: `6e88c913ad5e5b371a9d0c92ce08870214ac1268`, hard-OFF.
- Historical runner blob: `a1550c1ff9b6aa1ab05f45cafc4cd59c8e9f4bdb`.
- WS18 source-only audit: `docs/C137_GC24_K2_P03_B12_WS18_PHYSICAL_CLONE_READINESS_AUDIT.md`.
- This WS19 document makes no runtime/source PHP changes. No backup was opened, scanned, copied, hashed, decrypted, imported, transmitted or restored here. No Mac, cPanel, Node A/B/C, database, GitHub Actions, CI, migration, E2E or capacity test was executed.

## Distinguish the two user-reported backups

**Artifact A — historical C137 encrypted fixture backup:** The previous C137/GC24 Z24–Z25 handoff reports an AES-256-CBC/PBKDF2 encrypted Mac/off-volume backup generated from **`renee_c137_gc24_http`**, a historical C137 HTTP testing database. That record includes a specific local filename, historical file size/ciphertext SHA and a separate scratch restoration to **`renee_c137_gc24_restore_z25`** on a designated Node B test environment with 69 tables and matching schema/row counts. These are historical, user-reported test-environment evidence only. Neither database, scratch restoration nor the archive should be repurposed as a B12 customer-production clone or contacted without fresh specific authorization.

**Artifact B — independently user-described production backup:** In the subsequent B12 discussion, the user identified a pictured backup as generated from Renee AgriSuite's **live production database**. The displayed screenshot and confirmation do **not** establish its local filesystem path, archive contents, checksum, original database name, generation timestamp/consistency mechanism, restore history, credentials, or any independently isolated target. Do not conflate Artifact B with Artifact A. Do not infer production rows from source schema or synthetic fixture results.

## Historical B12 discovery evidence

Earlier Mac B12 local metadata discovery reported: existing synthetic DB container protected/not repurposed; `OTHER_LOCAL_DATABASE_CONTAINER_CANDIDATES=0`; `HISTORICAL_CLONE_PROVENANCE=NOT_ESTABLISHED`; zero DB connections, queries, clone creation, files changed or production contact. These results describe the historical local context, not a current full host inventory and not evidence that a clone cannot exist elsewhere.

## WS19 non-content Mac preflight objective

Obtain a current local **filename, size, modification time and regular-file/symlink metadata inventory** for plausible backup candidates in bounded user-owned folders, without opening or hashing file data, decrypting archives, mounting volumes, invoking Docker or MariaDB, SSHing, or requesting credentials. The local check must print only concise evidence between the established copy markers. Avoid recursive scans of unrelated private directories and do not expose contents or customer names. A filename/extension match can yield **CANDIDATE ONLY**, not a verified backup or approved source.

If no candidate is found, the user may supply the location of the previously pictured production backup or a separately scoped cPanel/server metadata-only plan can be reviewed. Do not connect to or inspect a protected host under WS19 automatically.

## Physical clone certification is separately gated

Before any restore or database read, obtain a new specific authorization for an independently isolated, disposable target with proven host/container identity and network/privilege separation, backup chain of custody and integrity, effective SELECT-only grants, source SQL and runner blobs, zero application writers, data privacy/redaction limits, and teardown/retention plans. Never point the B12 read-only runner at a database based only on its name or a self-asserted environment flag. Read-only aggregate inventory and race certification require distinct approval scopes.

Even a true production dump is **not automatically safe** to upload to CI, decrypt in an ordinary container, restore beside a live database, or copy into ChatGPT.

## Existing B12 certification remains unchanged

- WS17 focused source-only PHP/temporary ON-OFF/browser checks: user-returned PASS.
- WS18 source-only physical clone readiness audit: documented/remote-diff reviewed.
- Historical B12 run `38233373699` attempt 1 FAILED `TWO_STALE_COMMITS`; consumed approval unchanged.
- Physical customer-data clone independently attested: **NO**.
- Read-only actual three-table integrity counts: **UNKNOWN**.
- Repaired RC01–RC14 independent-connection passes: **ZERO**.
- Migration 092: not created/approved/applied; hard-OFF B12 seven-writer cutover.
- D12 import gates BLOCKED; Stage D UNAUTHORIZED; 150 VU HOLD; 200 VU PROHIBITED.

**Next controlled action:** user-side metadata-only candidate discovery, followed by review of returned candidate summary. No SQL, archive reading, schema change, migration, AWS/cPanel connection, or capacity test is authorized by this document.
