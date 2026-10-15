# C137 / GC24 / K2-P03 / B12 — WS52-A disposable synthetic MariaDB privilege-isolation evidence

**Date:** 2026-10-15  
**Outcome:** `WS52A_SYNTHETIC_PRIVILEGE_PASS` — *the specifically authorized synthetic-only proof passed*. **No production-derived data was imported or read. Real backup parser/allowlist, destination privileges, compatibility, backup consistency and restoration remain NOT CERTIFIED / NOT AUTHORIZED.** `B12_CUTOVER=OFF`.

## Authorization, code and branch guards

The user explicitly said **yes** to WS52-A: provision a **new independently isolated, disposable test container and volume**, perform bounded **synthetic SQL operations** verifying a new non-root schema-limited account with positive and negative controls, then remove only exact identity-verified WS52-A resources. This does **not** authorize using `~/Downloads/database.sql`, copying/uploading customer data, touching original WS32 or two older protected containers/three old volumes, migration 092, cutover, production/staging/AWS/cPanel, or any real import.

Repository `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`; immediate parent `c137-gc24-k2p03-b12-ws51t-import-safety-contract-source` HEAD `cd4830ff6f0b094a1b790166867c57119caeb39e`; new docs-only evidence branch `c137-gc24-k2p03-b12-ws52a-synthetic-privilege-pass-source`. Immutable original B12 branch `c137-gc24-k2p03-b12-cas-writer-cutover-source` HEAD `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af`; protected production `v320-renee-agrisuite-branding` HEAD `10041da3a3c4e4a7b8101f8183c243d056c7b128`. B12 literal HARD-OFF gate `lib/daily_record_revision_cutover_gate.php` blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268`. Historical WS07 verifier `scripts/verify_k2p03_b12_ws07_offline.sh` blob `a1550c1ff9b6aa1ab05f45cafc4cd59c8e9f4bdb` in **later evidence history**, absent in the original B12 HEAD (the WS51 wrong-ref failure was corrected in WS51R).

The supplied one-shot Mac Terminal script checked exact original Git branch/HEAD/clean worktree, remote and B12 HARD-OFF gate, expected absence of WS07 runner in the original code tree, local Docker Desktop identity and pinned cached MariaDB 11.4 image SHA fingerprint `2ee3c4a72fcac30a`; 3 prior running containers, 4 prior named volumes, WS32 isolated network-none container, unique dedicated WS32 volume, two older protected running containers and three older volumes. It fingerprinted pre-existing container metadata and compared against post-cleanup fingerprints and exact Docker inventories.

## Isolated synthetic execution topology

- Created **new** `renee-b12-ws52a-privilege-mariadb114` container and dedicated volume `renee-b12-ws52a-privilege-datadir`, labels `renee.b12.scope=WS52A` / `renee.b12.class=disposable` from pinned Linux/amd64 `mariadb:11.4`.
- Docker `--network none`, zero published ports, MariaDB server `--skip-networking`, Unix socket only; resource caps 768 MiB memory/swap, 1 CPU, 128 PIDs, `--restart no`, `no-new-privileges`. No host backup/production mount. WS32 and two older protected containers excluded.
- Bootstrapped randomized synthetic **target** schema and distinct synthetic **forbidden** schema, a synthetic sentinel table/row, and an OS-user-mapped MariaDB `'nobody'@'localhost'` account using `unix_socket` authentication. Granted precisely `SELECT, INSERT, CREATE, DROP, INDEX, ALTER, LOCK TABLES` **on the target schema only**, no global/other-schema grants; grant text evaluated inside Mac process but not emitted to user.
- The bootstrap used **a passwordless root only inside this networkless disposable synthetic server**; this is **strictly prohibited for any future production-derived-data restore environment**. No actual passwords or real credentials acquired; no customer data, original backup, Docker logs or real database contacted.
- Exactly **11 synthetic SQL client invocations** observed: bootstrap, empty-target check, importer identity, grants, positive target operations, five forbidden operations, forbidden-sentinel integrity check.
- Negative checks required the client to return a **recognized MariaDB permission-denied code** rather than treating an arbitrary nonzero status as denial; a separate privileged local query verified the forbidden sentinel and absence of attempted unauthorized objects/account.
- At end, the cleanup checked Docker context and engine ID plus exact new resource container ID/name/image/labels/network/mount identity and new volume labels before deleting **only WS52-A container/volume**. Final inventory and pre-existing container metadata fingerprints reported unchanged.

## Verbatim user-returned sanitized audit

```text
#####COPY FROM HERE#####
AUDIT=B12_WS52A_SYNTHETIC_PRIVILEGE_ISOLATION
RESULT=WS52A_SYNTHETIC_PRIVILEGE_PASS
FAILED_STAGE=NONE
REASON=NONE
SOURCE_BASELINE=PASS
TEST_IMAGE=MARIADB_11_4_PINNED
TEST_SCOPE=WS52A_NEW_DISPOSABLE_ONLY
NETWORK_MODE=NONE
PUBLISHED_PORTS=NO
SERVER_TCP_NETWORKING=DISABLED
ROOT_AUTH=NETWORKLESS_SYNTHETIC_BOOTSTRAP_ONLY
IMPORTER_AUTH=UNIX_SOCKET_NONROOT
SYNTHETIC_SQL_CLIENT_INVOCATIONS=11
SYNTHETIC_NEGATIVE_ATTEMPTS=5
BOOTSTRAP=PASS
IMPORTER_IDENTITY=PASS
RESTRICTED_GRANTS=PASS
TARGET_OPERATIONS=PASS
FOREIGN_SELECT=DENIED_EXPECTED
FOREIGN_CREATE=DENIED_EXPECTED
FOREIGN_DROP=DENIED_EXPECTED
GLOBAL_SETTING=DENIED_EXPECTED
ACCOUNT_CREATION=DENIED_EXPECTED
FORBIDDEN_SENTINEL=PASS
WS52A_CONTAINER_DISPOSAL=PASS
WS52A_VOLUME_DISPOSAL=PASS
ORIGINAL_RESOURCE_INVENTORY=PASS
PROTECTED_METADATA_UNCHANGED=PASS
PROTECTED_ORIGINAL_CONTAINERS=2
PROTECTED_ORIGINAL_VOLUMES=3
WS32_DATABASE_ACCESSED=NO
PRODUCTION_BACKUP_OPENED=NO
PRODUCTION_BACKUP_COPIED=NO
PRODUCTION_BACKUP_RESTORED=NO
PRODUCTION_CREDENTIALS_ACCESSED=NO
CUSTOMER_DATA_ACCESSED=NO
RAW_SQL_DISPLAYED=NO
SQL_IDENTIFIERS_DISPLAYED=NO
DOCKER_LOGS_READ=NO
SQL_EXECUTION=SYNTHETIC_ONLY
REAL_BACKUP_PARSER_ALLOWLIST=NOT_CERTIFIED
REAL_IMPORT_PRIVILEGE_CONTAINMENT=NOT_CERTIFIED
RESTORE_COMPATIBILITY=NOT_CERTIFIED
RESTORE_AUTHORIZED=NO
PRODUCTION_CONTACT=NO
MIGRATION_092_EXECUTED=NO
B12_CUTOVER=OFF
#####COPY TO HERE#####
```

## Evidence conclusions (precisely scoped)

| Assertion | Status | Basis/limit |
|---|---|---|
| Bootstrap and target-empty verification | **PASS** | Synthetic, network-isolated test server only |
| Restricted non-root identity and exact grants | **PASS** | `nobody@localhost` with `unix_socket`; schema-only grants checked |
| Target `CREATE`, `INSERT`, `SELECT`, `ALTER`, `LOCK/UNLOCK`, `DROP` | **PASS** | Hardcoded synthetic table/data only |
| Forbidden schema `SELECT`, `CREATE TABLE`, `DROP TABLE` | **DENIED_EXPECTED** | Explicit error-code filtering; does not assess arbitrary SQL bypasses |
| Global server-setting change and `CREATE USER` | **DENIED_EXPECTED** | Recognized permission error from synthetic account |
| Forbidden sentinel remained intact | **PASS** | Root performed sanitized integrity query inside new synthetic server |
| WS52-A container and volume removal | **PASS / PASS** | Exact identity verified before teardown |
| Original Docker inventory/metadata | **PASS / PASS** | Two older protected containers and three volumes plus WS32 unchanged |
| Actual backup parser-aware allowlist | **NOT CERTIFIED** | WS51S was lexical screening; a full MariaDB dialect/client-aware classification remains outstanding |
| Real-backup privilege containment and import compatibility | **NOT CERTIFIED** | This proof never accessed production-derived SQL |
| Snapshot consistency, confidential data at rest, safe erasure and expanded import footprint | **NOT CERTIFIED** | No real restore target or customer data; Docker guest capacity not measured |

**Security conclusion:** the scoped positive/negative privilege controls are feasible in a fresh isolated MariaDB 11.4 instance; this is a **necessary but insufficient** component of the future restore plan. It is not evidence that the specific actual backup is safe/parseable/consistent, nor that its future import would be contained. Testing non-root `unix_socket` authentication here does not create an account in WS32 or production and does not authorize creation elsewhere.

## Next safe continuation / required approvals

1. **Close the actual SQL-execution contract and storage+data-protection blockers before considering a production-derived import.** WS51S pinned the 619163-byte dump SHA256 `c7b99b6e00fc34b26eddd806cb399cad32187645fd4d8654f97fa3ecd7eef259` and found 69 DROP TABLE, 69 CREATE TABLE, 65 INSERT, 69 LOCK/UNLOCK, 138 versioned ALTER, 225 versioned SET, one MariaDB sandbox directive, **zero lexical heuristic risks**; none of this is a full SQL/client-directive grammar certification. Actual backup SQL may have unforeseen executable semantics. An independently reviewed dialect-aware allowlist or explicitly justified alternative constrained execution mechanism is still required.
2. Confirm **guest** storage capacity/free inodes, and credible expanded table+index+temp+redo/undo/log storage headroom. WS51S measured **166.81 GiB** Mac host free and Docker backing logical **233.47 GiB**, allocated **2.81 GiB**, but guest capacity and actual expanded footprint are unknown. Don't add the three identical host-space readings together.
3. Establish confidential treatment of real customer/tenant records, at-rest/backing-file encryption, snapshots/retention, ephemeral import credential lifecycle, error-output redaction and scoped secure disposal/rollback; original file remains local, mode 0600, never uploaded.
4. Proposed next gate **WS52-B**: a narrowly scoped **read-only backup parser-compatibility/containment feasibility and guest-capacity design/metadata** assessment, explicitly stating any necessary Docker exec, file-content inspection and parser limitations **before** execution. Read-only analysis/design may proceed only if the user separately approves exact checks; any new container, SQL, data transfer or cleanup is **outside** this result's authorization. Alternatively a separately approved **new synthetic-only** negative parser/client-command enforcement rehearsal could be considered, but this would not certify the actual dump.
5. Only after safety gates are verified should a **separate** user decision consider WS52 real restore-environment provisioning using new strong credentials, an empty schema and identity-pinned containers/volumes, without touching the backup; **WS53** import of production-derived SQL containing destructive statements requires another explicit authorization. No implied approval or chaining; **WS54** post-import assessment/disposal also requires separate authorization.

**Standing controls unchanged:** `B12_CUTOVER=HARD_OFF`; migration 092 **NOT AUTHORIZED**; corrected real MariaDB RC01–RC14 passes **0** and prior actual concurrency failed `TWO_STALE_COMMITS`; D12's 15 import gates BLOCKED; Stage D UNAUTHORIZED; 150 VU HOLD; 200 VU PROHIBITED. Never contact production/staging/AWS/cPanel, modify original code, protected containers/volumes or WS32, or copy/publish customer data. No reason to repeat already-closed WS42–WS52-A tests absent regression evidence.

## GitHub proof contract for this report

Commit **exactly one new Markdown report** to a freshly branched successor from WS51T HEAD `cd4830ff6f0b094a1b790166867c57119caeb39e`. Independently verify remote semantic diff **ahead=1, behind=0; sole changed file is added docs/C137_GC24_K2_P03_B12_WS52A_SYNTHETIC_PRIVILEGE_PASS.md** and re-fetch exact protected original/production branch SHAs, hard-OFF gate blob and later-history WS07 runner blob. No runtime/application/code edits or production data copies.
