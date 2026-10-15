# C137 / GC24 / K2-P03 / B12 — WS52-D synthetic MariaDB 11.4 oracle and guest-capacity evidence

**Date:** 2026-10-15.  
**User-returned result:** `WS52D_SYNTHETIC_ORACLE_PASS`. This is a **bounded synthetic-only MariaDB 11.4 client/server behavior test and a guest-filesystem measurement** on a separate disposable container, **not** full MariaDB grammar certification, actual-dump import compatibility, restore storage sufficiency or authorization to import any production-derived records. `B12_CUTOVER=OFF`, migration 092 not authorized.

## 1. Authorization, scope and lineage

The user explicitly approved **WS52-D**: instantiate **one new networkless disposable MariaDB 11.4 container and volume**, execute strictly bounded synthetic SQL behavior checks (including versioned comments and known syntax errors), measure guest free space/inodes on that **new** volume, clean up **only exact verified WS52-D identities** and confirm preservation of WS32 and protected original resources. The approved scope excluded opening/copying/importing the historic production-derived backup, customer data, production credentials, production/staging/AWS/cPanel contact, migration 092 and cutover.

Repo: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.

| Protected reference | Pinned value |
|---|---|
| Immediate parent WS52-C evidence branch | `c137-gc24-k2p03-b12-ws52c-synthetic-parser-qualification-source` |
| Parent HEAD | `3eaf61ca3305cc431710ba9ed87d3cfaca6e6b72` |
| New WS52-D docs-only evidence branch | `c137-gc24-k2p03-b12-ws52d-synthetic-mariadb-oracle-evidence-source` |
| Original B12 source branch | `c137-gc24-k2p03-b12-cas-writer-cutover-source` |
| Original B12 code SHA | `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af` |
| Protected production branch | `v320-renee-agrisuite-branding` |
| Production SHA | `10041da3a3c4e4a7b8101f8183c243d056c7b128` |
| Literal HARD-OFF B12 gate `lib/daily_record_revision_cutover_gate.php` blob | `6e88c913ad5e5b371a9d0c92ce08870214ac1268` |
| Historical WS07 verifier `scripts/verify_k2p03_b12_ws07_offline.sh` blob | `a1550c1ff9b6aa1ab05f45cafc4cd59c8e9f4bdb` |

Historical WS07 verifier exists in **later evidence history**, not the protected original code HEAD (see WS51R correction). Original local checkout must remain clean and fixed to the original B12 SHA.

## 2. Local synthetic-only method and fixture

Mac Terminal script checked pinned source branch, HEAD, clean tree, Git origin and hard-OFF gate, and local Docker Desktop context/engine and cached MariaDB 11.4 Linux/amd64 image ID fingerprint `2ee3c4a72fcac30a`. Preexisting 3 running containers/4 named volumes and their metadata were fingerprinted: two older protected running containers with three old named volumes, plus separate running WS32 container `renee-b12-ws32-mariadb114-stopped` and dedicated volume `renee-b12-ws32-datadir`. No original container was repurposed.

Only for this test, created new labeled `renee-b12-ws52d-parser-mariadb114` + `renee-b12-ws52d-parser-datadir`, `--network none`, no published ports, MariaDB server `--skip-networking`, capped 768 MiB RAM, 1 CPU, 128 PIDs, restart no, no-new-privileges, unique volume mapped to `/var/lib/mysql`. Bootstrap root was **passwordless only inside this new networkless synthetic-only fixture**; it must **never** be used for any environment carrying actual production-derived data. All SQL used only synthetic constants, randomly named synthetic schema/objects and the new local socket.

Client-side execution budget: **18 synthetic SQL client invocations**, **12 accepted positive tests**, **6 rejected negative syntax tests**, all negatives required MariaDB **error code 1064** specifically; an arbitrary nonzero exit was not treated as passing. The positive suite covered baseline query, sandbox comment followed by synthetic SELECT, quoted-semicolon/comment-shaped strings and quoted identifier, MySQL/MariaDB active versioned comments and future-version skipped comments, `sql_mode` default vs `NO_BACKSLASH_ESCAPES`, and synthetic schema/table creation, insert/read/drop. **Client metacommand escape attempts were not run**; sandbox-mode **restriction enforcement** and full grammar were not certified. These are narrow observed MariaDB behaviors, not independent full-parser verification.

Guest capacity was measured using non-SQL `df -Pk` and `df -Pi` against the new guest datadir `/var/lib/mysql`. Mac host physical backing free was also observed. After testing, exact WS52-D ID/name/image/scope/labels/network/volume were checked before removing the temporary container and volume. Before/after Docker inventories and fingerprints of three original pre-existing containers were checked; report states unchanged. WS32 and customer backup were not read, modified or executed.

## 3. Exact sanitized user-returned audit

```text
#####COPY FROM HERE#####
AUDIT=B12_WS52D_SYNTHETIC_MARIADB_ORACLE
RESULT=WS52D_SYNTHETIC_ORACLE_PASS
FAILED_STAGE=NONE
REASON=NONE
SOURCE_GUARDS=PASS
IMAGE_FINGERPRINT=PASS
NEW_FIXTURE_ISOLATION=PASS
TEST_IMAGE=MARIADB_11_4_PINNED
TEST_CONTAINER_SCOPE=WS52D_ONLY
NETWORK_MODE=NONE
PUBLISHED_PORTS=NO
SERVER_TCP_NETWORKING=DISABLED
ROOT_AUTH=NETWORKLESS_SYNTHETIC_ONLY
SYNTHETIC_SQL_CLIENT_INVOCATIONS=18
POSITIVE_TESTS_PASS=12
POSITIVE_TESTS_EXPECTED=12
NEGATIVE_SYNTAX_TESTS_PASS=6
NEGATIVE_SYNTAX_TESTS_EXPECTED=6
NEGATIVE_ERROR_CODE=1064
CLIENT_META_COMMANDS_EXECUTED=NO
GUEST_DATADIR_FREE_GIB=212.98
GUEST_DATADIR_FREE_INODES=15252481
MAC_HOST_FREE_BEFORE_GIB=166.8
GUEST_CAPACITY_MEASURED=YES
EXPANDED_BACKUP_FOOTPRINT=UNKNOWN
RESTORE_SPACE_SUFFICIENCY=NOT_CERTIFIED
WS52D_CONTAINER_DISPOSAL=PASS
WS52D_VOLUME_DISPOSAL=PASS
ORIGINAL_RESOURCE_INVENTORY=PASS
PROTECTED_METADATA_UNCHANGED=PASS
PROTECTED_ORIGINAL_CONTAINERS=2
PROTECTED_ORIGINAL_VOLUMES=3
WS32_DATABASE_ACCESSED=NO
PRODUCTION_BACKUP_OPENED=NO
PRODUCTION_BACKUP_COPIED=NO
PRODUCTION_BACKUP_RESTORED=NO
CUSTOMER_DATA_ACCESSED=NO
PRODUCTION_CREDENTIALS_ACCESSED=NO
RAW_SQL_DISPLAYED=NO
DOCKER_LOGS_READ=NO
SQL_EXECUTION=SYNTHETIC_ONLY
REAL_BACKUP_PARSER_ALLOWLIST=NOT_CERTIFIED
FULL_MARIADB_GRAMMAR=NOT_CERTIFIED
REAL_BACKUP_IMPORT_COMPATIBILITY=NOT_CERTIFIED
RESTORE_AUTHORIZED=NO
PRODUCTION_CONTACT=NO
MIGRATION_092_EXECUTED=NO
B12_CUTOVER=OFF
#####COPY TO HERE#####
```

## 4. Findings and evidence boundaries

| Property | Observed and supported | Limit |
|---|---|---|
| New isolated MariaDB 11.4 client/server synthetic fixture | PASS | No real backup or external network |
| MariaDB synthetic SQL behavior | 12/12 expected positive results | Only named small cases; no entire MariaDB grammar |
| Invalid synthetic statement rejection | 6/6 returned syntax-error code **1064** | Does not prove all forbidden client or SQL paths are rejected |
| Guest datadir free bytes | **212.98 GiB free** | One temporary fixture at a point in time, not restore capacity certification |
| Guest available inodes | **15,252,481** | One temporary fixture, not full database sizing bound |
| Host physical backing space | **166.80 GiB** free before test | Same Mac-backed pool as earlier host measurements; sparse guest logical space may exceed host physical free |
| WS52-D container/volume disposal | PASS/PASS | Guest space reading belongs to deleted fixture |
| Original Docker inventory/metadata | PASS/PASS | Two original protected containers, their three volumes and WS32 retained |
| User-reported production-derived dump | Not opened or imported | Last verified hash remains prior WS51S evidence, not rechecked here |
| Actual dump allowlist and import compatibility | **NOT CERTIFIED** | WS51S zero lexical risk flags and WS52-C 58 synthetic classifier cases are not enough |
| Backup snapshot consistency | **NOT CERTIFIED** | Table locks are not globally coherent snapshot evidence |
| Confidential data at-rest handling, retention and disposal | **NOT CERTIFIED** | No production data was copied into guest |

**Capacity nuance:** `212.98 GiB` guest free and `166.80 GiB` physical Mac host free are **not additive**, and 212.98 GiB guest space is not proof that the sparse Docker backing file can grow by that amount on the host. The pinned backup's ~619 KB SQL text size does not bound the eventual expanded tables, indexes, redo/undo, binlogs, temp files, import logs or reserve. `EXPANDED_BACKUP_FOOTPRINT=UNKNOWN`; `RESTORE_SPACE_SUFFICIENCY=NOT_CERTIFIED`.

**Parser nuance:** WS52-D offers an **actual MariaDB 11.4 synthetic client/server oracle** for its selected active/future versioned comments, SQL modes and 1064 syntax failures. It does **not validate every one of WS52-C's 58 Python prototype cases**, the original SQL dump's effective statement targets, schema switching, client scripting/escape modes or full MariaDB 11.4 grammar. The MariaDB sandbox directive was synthetically accepted, but **sandbox enforcement itself was not tested**. No SQL grammar certification or blanket restore permission follows.

## 5. Recommended next safety decision — NOT AUTHORIZED

Rather than repeat WS49/WS52-A/WS52-D, focus on the material outstanding blockers: **complete effective SQL/client-directive classification and confinement of the real pinned backup**, plus a defensible expanded import storage bound and a production-derived-data confidentiality, retention and irreversible-disposal policy.

A practical next separately authorized stage can be **WS52-E — design-only gap-closure decision and strict import readiness matrix**: synthesize WS51S/WS51T/WS52-A/B/C/D, choose a vetted, reproducible MariaDB dialect-aware classifier or fail-closed alternative containment mechanism, define exact validation oracle, and select an at-rest/retention/disposal control. It would not read production data, execute SQL, provision infrastructure or restore a database. If a newly approved parser tool is unavailable or uncertain, explicitly retain `SQL_PARSER_AWARE_ALLOWLIST=BLOCKED`.

Only after that design review may a future named step request approval for local parser implementation and **separate** sensitive backup classification; and any future **new** restore-only container/account/schema provisioning requires its own permission. The production-derived backup import (including **69 DROP TABLE statements** per prior WS51S classification) is a wholly distinct high-risk **WS53** authorization, not implied by this test. WS54 aggregate verification/disposal separate again.

**Standing locked controls:** `B12_CUTOVER=HARD_OFF`; migration 092 **NOT AUTHORIZED**; corrected real MariaDB RC01–RC14 passes **0**, prior concurrency attempt **FAILED TWO_STALE_COMMITS**; D12 15 import gates BLOCKED; Stage D UNAUTHORIZED; 150 VU HOLD; 200 VU PROHIBITED. No production/staging/AWS/cPanel contact, no mutation of protected original code or containers/volumes, no copied or published customer data.

## 6. GitHub evidence contract

Exactly **one new Markdown report** on a new WS52-D docs-only successor from WS52-C HEAD `3eaf61ca3305cc431710ba9ed87d3cfaca6e6b72`. Independently compare remote branch against parent (ahead 1, behind 0, one added report file), and re-fetch original protected B12 and production SHAs, hard-OFF gate blob and later-history WS07 verifier blob. No code, migration or runtime file changes in GitHub.
