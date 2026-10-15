# C137 / GC24 / K2-P03 / B12 — WS52-E final import-readiness gap assessment and decision matrix

**2026-10-15 | Result: `WS52E_DESIGN_ASSESSMENT_COMPLETE` / `ACTUAL_BACKUP_RESTORE_READINESS=BLOCKED`.** This is the user-authorized **design-only** assessment derived from previously recorded WS50–WS52-D evidence. **No new local audit, Mac commands, Docker operations, SQL, backup read, customer-data handling, credentials, production contact or restoration** occurred during WS52-E. Only this Markdown report is to be committed on a new docs-only GitHub successor branch. Neither this design document nor any preceding synthetic test authorizes import.

## 1. Evidence lineage and immutable release guards

Repository `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.

| Git checkpoint | Verified identifier |
|---|---|
| Immediate parent docs-only WS52-D branch | `c137-gc24-k2p03-b12-ws52d-synthetic-mariadb-oracle-evidence-source` |
| Immediate parent HEAD | `c47716aa100cd9bb33cf7a0a01ee686de0009f44` |
| New WS52-E docs-only branch | `c137-gc24-k2p03-b12-ws52e-import-readiness-gap-matrix-source` |
| Original protected B12 code branch | `c137-gc24-k2p03-b12-cas-writer-cutover-source` |
| Original code HEAD | `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af` |
| Protected production code branch | `v320-renee-agrisuite-branding` |
| Production HEAD | `10041da3a3c4e4a7b8101f8183c243d056c7b128` |
| Literal B12 HARD-OFF gate, `lib/daily_record_revision_cutover_gate.php` | Blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268` |
| Historical WS07 offline verifier, `scripts/verify_k2p03_b12_ws07_offline.sh` | Blob `a1550c1ff9b6aa1ab05f45cafc4cd59c8e9f4bdb` **in later evidence history**, **absent at original code HEAD** |

Reconfirm branch SHA, gate and verifier immutability after committing evidence. The original Mac source checkout intentionally remains on the original B12 branch/HEAD and clean; the historical verifier must NOT be required via that original `HEAD:path` reference (WS51 correction). No source checkout/edit/deployment is needed for this assessment.

**Standing operational locks:** B12 CAS writer cutover `HARD_OFF`, migration `092` **NOT AUTHORIZED**, corrected real MariaDB RC01–RC14 **0 certified passes**, previously observed concurrency failure `TWO_STALE_COMMITS`; D12 **15 import gates BLOCKED**, Stage D **UNAUTHORIZED**, 150 VU **HOLD**, 200 VU **PROHIBITED**. No live production, staging, cPanel, AWS or protected older C137 fixture contact.

## 2. Accepted historical evidence: precise scope

1. **Original local backup (WS43 and WS51S):** The user identified `~/Downloads/database.sql` as derived from *live production*; production export method was not independently attested. Last verified backup size **619,163 bytes**, mode **0600**, same-user owner and SHA-256 `c7b99b6e00fc34b26eddd806cb399cad32187645fd4d8654f97fa3ecd7eef259`. **The file was not opened or rehashed in WS52-E.**
2. **Static lexical structure (WS51S):** 69 `DROP TABLE`, 69 `CREATE TABLE`, 65 `INSERT INTO`, 69 `LOCK TABLES`, 69 `UNLOCK TABLES`, 138 conditional `ALTER TABLE`, 225 conditional `SET`, 364 versioned comments (361 MySQL, 3 MariaDB), one MariaDB sandbox directive, no unrecognized *lexical* top-level statement and **zero flags under that scanner's limited rules**. These counts do **NOT** certify the effective executable SQL for MariaDB 11.4 or the exact schema destinations. All **69 DROP TABLE** operations remain destructive if misrouted.
3. **Offline parser feasibility (WS48):** Four proposed database-free CLI flags were not observed in the MariaDB 11.4 client help; a verified MariaDB-compatible full offline parsing method remains **NOT ESTABLISHED**. Absence of those four flags is not proof no other safe offline parsing architecture exists.
4. **Synthetic client/server acceptance (WS49):** Two synthetic `SELECT 1` client/server cases, including a sandbox-directive-shaped input, passed on a newly created network-disabled disposable MariaDB 11.4 server that was deleted. This **did not** prove sandbox enforcement or whole-backup compatibility.
5. **Backup and original resources (WS51R):** Verified user backup hash/size/mode, original source/HARD-OFF gate and Docker Desktop protected inventory. Two older protected running containers and three named volumes, plus separate WS32 network-disabled MariaDB container/volume remained unchanged. WS49 resources absent.
6. **Import contract (WS51T):** Design-only requirement for *both* new standalone no-network MariaDB instance and target-schema-scoped non-root importer; strict executable SQL/client directive classification, synthetic non-target denial, fresh strong secrets, containment and deletion/rollback. **Requirements were defined, not deployed.**
7. **Importer isolation (WS52-A):** Newly created, separate, synthetic-only MariaDB 11.4 fixture; 11 synthetic SQL client invocations; permitted target operations, exact schema-scoped grants and **five permission-denial cases** passed with expected error codes; forbidden sentinel intact; created WS52-A container+volume disposed; originals unchanged. Does **not** authorize reusing the fixture or passwordless synthetic bootstrap for production-derived data.
8. **Parser research/prototype (WS52-B/C):** Design report concluded full parser and guest storage unknown. A custom prototype matched a **58-case synthetic corpus** (23 expected allows, 35 expected rejects) after correcting seven false rejects. This transient prototype was not published as an independently reproducible/audited parser and was **not validated on the real backup or full MariaDB grammar**.
9. **Actual MariaDB selected-case oracle (WS52-D):** Separate new network-disabled MariaDB 11.4 fixture passed **12 positive** and **six explicit 1064 syntax-error negative** synthetic tests over 18 SQL client invocations. This supports selected versioned-comment, SQL-mode, sandbox input and quoted-string behaviors. It did **not** run all 58 WS52-C cases, prove sandbox restrictions, inspect the original backup or exercise client metacommand escapes. WS52-D guest datadir reported **212.98 GiB available and 15,252,481 free inodes**; host physical backing reported **166.80 GiB** before testing. WS52-D container/volume disposal and original inventory/metadata checks **PASS**.

**Capacity caution:** 212.98 GiB *guest* free and 166.80 GiB *host* physical free are **not additive**; the guest metric belonged to the now-disposed synthetic WS52-D fixture, not a future actual restore. Docker sparse backing nominal space does not replace physical host headroom. Expanded import size is unknown; a ~619 KB SQL text file is not a defensible table/index/temp/log capacity estimate.

## 3. Formal import-readiness decision matrix (current state)

`PASS_SYNTHETIC` means only the prior named synthetic cases worked; it must not be reinterpreted as actual-backup readiness.

| Gate | Verified basis | WS52-E status | Minimum independent closure evidence / pass condition |
|---|---|---|---|
| A. Original code/production branch immutability and B12 HARD-OFF | Verified GitHub refs, gate blob and prior Mac source guards | **PASS_REVIEW** (recheck every later runtime stage) | Exact immutable branch HEADs, clean source, gate blob, and protected resource IDs before/after next authorized action |
| B. User-provided backup file identity | WS51S pinned hash/size/mode | **PASS_HISTORICAL_ONLY** | Fresh local owner/mode/no-symlink/SHA256 validation **before** any later file read/stream; no raw contents published |
| C. Separate networkless MariaDB 11.4 and safe container cleanup | WS49/A/D new synthetic fixtures passed and were deleted | **PASS_SYNTHETIC; REAL_TARGET_ABSENT** | New separately approved real restore-only container/volume, exact new identities, exclusive datadir, no ports/network or old resource sharing; strong bootstrap auth |
| D. Target-empty schema and scoped non-root importer | WS52-A positive+five negative permissions passed synthetic | **PASS_SYNTHETIC; REAL_IMPORTER_ABSENT** | In new real restore-only fixture, unique new **empty** target schema, exact grants limited to `target.*`, negative non-target/global/account/file tests; no root importer/blank password |
| E. Entire *effective* actual SQL + client directive classification | WS51S regex/token counts, WS52-C 58 prototype corpus, WS52-D 18 actual synthetic examples | **BLOCKED** | Reproducible, independently reviewed MariaDB 11.4/client-aware grammar or equally demonstrably constrained alternative; coverage for every active statement/comment/mode/client command and **exact target schema only**; zero unknowns; fail closed |
| F. Destructive/cross-schema/file/privileged SQL containment | 69 `DROP TABLE` tokens; zero limited lexical risk flags, synthetic negative privileges | **BLOCKED** | Confirm all destructive target statements are confined to new empty schema; prohibit qualified non-target identifiers, `USE` redirection, file/network/client source/metacommands, privilege/global/admin changes; reproduce rejects independently |
| G. Full actual backup compatibility | Only selected MariaDB 11.4 synthetic statements executed | **NOT TESTED / BLOCKED** | Specified, approved actual import or other independently reviewable validation only **after** safe classifier, target isolation and data controls; no compatibility PASS before full scoped evidence |
| H. Storage headroom, guest inodes and import growth | WS52-D guest 212.98 GiB / 15,252,481 inodes; host 166.80 GiB before; expanded footprint unknown | **BLOCKED** | Current host physical, sparse backing and **new actual fixture** guest bytes/inodes/quota metrics + defensible upper-bound table/index/temporary/redo/undo/log growth and protected reserve, checked again before data stream |
| I. Confidentiality/retention/at-rest security | Data kept local and unpublished so far; WS50/51T design rules | **BLOCKED** | Attest host and Docker backing encryption, snapshots/backups, access controls, new strong credentials/ephemeral channels, no raw stderr/log/history/data escape, finite retention, specifically approved secure disposal/key destruction path |
| J. Reliable abort, recovery and disposal | WS49/A/D synthetic exact-resource disposal passed | **PASS_SYNTHETIC; REAL_DATA_PLAN_NOT_CERTIFIED** | Independent future-stage rollback/retention policy for non-transactional DDL; exact resource identity; no deleting protected/unknown volumes; secure data erasure/backup retention caveats closed |
| K. Original export provenance and coherent snapshot | User-attested live export; no global consistent transaction established | **UNVERIFIED** | Either independent capture/snapshot evidence or explicit limited **historical-rehearsal-only** acceptance with no claim about cross-table state; any new production export needs separate permission |
| L. Corrected real B12 concurrency certification/cutover | Original failed `TWO_STALE_COMMITS`; 0 corrected RC01–RC14 PASS | **BLOCKED_INDEPENDENT** | Separate controlled real MariaDB corrected RC01–RC14 evidence; no link from a restored historical fixture alone to CAS correctness |
| M. Approvals for actual import and eventual disposal | User authorized only through WS52-D and this WS52-E design review | **NOT AUTHORIZED** | New, *specific*, separately scoped permissions for parser source/data read, new restore target creation/bootstrap SQL, sensitive WS53 backup stream+DDL/data SQL, WS54 aggregate validation/disposal and any release action |

### Overall GO / NO-GO

- `DESIGN_REVIEW=COMPLETE`.
- `SYNTHETIC_NETWORK_AND_PRIVILEGE_CONTROLS=DEMONSTRATED_IN_PRIOR_SCOPED_TESTS`.
- `ACTUAL_DUMP_ALLOWLIST=BLOCKED`.
- `ACTUAL_RESTORE_TARGET=NOT_CREATED`.
- `ACTUAL_IMPORT_AUTHORIZED=NO`.
- `CAPACITY_SUFFICIENCY=NOT_CERTIFIED`.
- `CONFIDENTIALITY_AND_DISPOSAL=NOT_CERTIFIED`.
- `SNAPSHOT_CONSISTENCY=NOT_CERTIFIED`.
- `MIGRATION_092=NOT_AUTHORIZED`.
- `B12_CUTOVER=HARD_OFF`.
- **`FINAL_DECISION=NO_GO_FOR_PRODUCTION_DERIVED_BACKUP_IMPORT`.**

## 4. Minimal closure strategy — stop repeating broad synthetic tests

**Recommended first remaining technical dependency: a reproducible, independently reviewable MariaDB/client-effective-SQL classifier and synthetic conformance oracle.** WS52-C's transient Python prototype and WS51S heuristic are not a reusable proof. Another generic `SELECT 1` or empty synthetic database test would add little. **Do not repeat closed WS42–WS52-D tests unless newly observed regression evidence warrants it.**

A later stage, after **separate permission**, should:

1. Choose and pin the parser/classifier architecture and implementation footprint **without executing or copying user backup**. The package/manifest needs exact source, deterministic version, hash, test command and synthetic-only fixtures that an independent party can rerun. No speculative new package install into original code checkout or GitHub production branch. The classifier is an **allowlist** of reviewed SQL classes, not a permissive formatter.
2. Build a negative test corpus including effective `/*!version*/` and `/*M!version*/` semantics, known sandbox directive, client metacommands (including `source` and `\\.`), SQL_MODE transitions and escaping, quoted/backticked identifiers and mixed quotes, data strings with semicolons/comments, `db.table`/\`db\`.\`table\` references, `USE` or `DELIMITER`, unrecognized commands, `DEFINER`, `LOAD DATA/OUTFILE/FILE`, privilege/global/account changes and syntax/errors. Separate *parser classification* from MariaDB execution; use a MariaDB 11.4 **synthetic-only** reference oracle where safe and authorized, and require expected deny, not arbitrary nonzero.
3. Independently review coverage and fail-closed behavior; log only approved aggregate pass/fail counts and hashes. Distinguish client directives, server-executed conditional comments, nested/unterminated literals, SQL modes and version-guard execution. **No full-dump PASS yet.**
4. **Only with another explicit authorization** for reading the pinned local production-derived backup, classify all effective statements and destinations. Never print raw SQL, object identifiers, table names, rows or customer details; do not upload it or create a derived dump. Any unclassified active statement, destination change or unexpected permission requirement => **BLOCK**.
5. In parallel, independently complete the **data-at-rest/backups/retention/secure disposal policy**, credential and confidential error/log handling; and a credible storage upper bound (not just the text dump's size). If any cannot be established, keep WS53 **NO-GO** regardless of parser success.
6. **Only after all import gates close**, propose separately authorized creation of a *new real-data-capable* network-disabled restore-only server/volume, truly empty application schema and strong-secret, least-privilege account. Provisioning is **not** permission to open/import backup. Freshly measure host/guest/inodes for that precise new instance; independently verify denials without customer data.
7. Request a distinct **WS53** approval naming source SHA and exact bounded sensitive-data stream, 69 DROP TABLE risk, new target identity, SQL execution/timeout/query budget, data retention/rollback, stop/error output policy, and no-customer-data-publication requirements. **WS54** validation/disposal and corrected B12 RC certification remain separate again.

If the user elects to treat an old, potentially inconsistent production export as **historical rehearsal only**, document that risk acceptance narrowly. It cannot establish coherent accounting totals, cross-table transactional integrity or B12 CAS correctness. A new verified consistent export, if necessary, is a different action requiring authorization; do not contact production during WS52-E.

## 5. Exact next proposed authorized step — NOT APPROVED by WS52-E

**Propose WS52-F: reproducible synthetic-only SQL parser contract / implementation qualification.** Define a deterministic, independently runnable, narrowly scoped MariaDB-11.4-aware *allowlist* implementation and synthetic test manifest, with a separate reference oracle design. The initial WS52-F gate must **not** read the user backup, issue SQL to any existing fixture, provision containers, copy customer data, retrieve credentials or change product source. Any tool/package installation, code artifact delivery, synthetic MariaDB execution, real backup read or runtime action requires its own disclosed scope and explicit approval. If a verified classifier cannot be built, report **BLOCKED**, not a permissive workaround.

**Alternative** if the user prioritizes capacity/confidentiality: a separate specific approval for fresh host/guest metadata on a *new* dedicated fixture, plus host backup/encryption/retention inventory, would be legitimate; it must not be mistaken for an import-ready determination.

The security strategy is **two independent barriers**: vetted effective SQL/client command allowlist **AND** a genuinely new isolated DB server with schema-scoped non-root importer and explicit target-empty checks. Neither barrier replaces the other. Customer-data encryption, errors/logs/snapshots and safe disposal are additional independent requirements.

## 6. WS52-E documentation-only execution and verification contract

- Only GitHub read-only evidence consultation and **one** new successor branch + **one** new Markdown report were authorized. **No SQL**, local host or Docker operation, sensitive file read, production contact, deployment or code changes.
- Produce no raw dump SQL, table/tenant identifiers, credentials or customer records in this report.
- After writing, independently retrieve remote branch head and compare against WS52-D parent: `ahead=1`, `behind=0`, **sole change is added `docs/C137_GC24_K2_P03_B12_WS52E_FINAL_IMPORT_READINESS_GAP_MATRIX.md`**. Reconfirm two protected source HEADs, HARD-OFF gate blob and later-history WS07 verifier blob.
- Preserve strict one-controlled-step-at-a-time authorization. This WS52-E assessment **does not** authorize WS52-F, actual parser implementation, customer backup inspection/streaming/import, restore container/schema provisioning, migration 092, any production system operation or B12 writer cutover.

**Final WS52-E interpretation:** `ASSESSMENT_COMPLETE`, `RESTORATION_NO_GO`, `NEXT_DEPENDENCY=INDEPENDENTLY_REPRODUCIBLE_SQL_ALLOWLIST_AND_DATA_PROTECTION_CAPACITY_GATES`, `B12_HARD_OFF`.
