# C137 / GC24 / K2-P03 / B12 — WS52-KJ immutable Linux AMD64 child-manifest read-only evidence

**Date:** 2026-10-15  
**Evidence observation (UTC):** `2026-10-15T20:02:48+00:00`  
**Evidence classification:** **USER-RETURNED LOCAL/MAC READ-ONLY SCRIPT RESULT**, preserved verbatim below. The assistant independently checked GitHub branch and source identities before committing this report, **not** the live Docker Hub response. No GitHub Actions registry call, image pull, database test, production mutation or authorization is implied.

**Result:** `READONLY_CHILD_MANIFEST_PASS` (user-returned); `CONFIG_BYTES_FETCHED=NO`; `REPAIRED_RC01_RC14_CERTIFIED_PASSES=0`; `B12_CUTOVER=HARD_OFF`.

## 1. Pinned source identities / documentation-only boundary

| Object | Identity verified in remote GitHub preflight |
|---|---|
| Repository | `abeycity4u/Renee-Farms-V2.2.50-Batch3B1` |
| WS52-KI parent branch | `c137-gc24-k2p03-b12-ws52ki-registry-image-identity-source-review` |
| Exact parent HEAD | `a6c4d4298844e294ca2c5efb832f45520a277b82` |
| New documentation branch | `c137-gc24-k2p03-b12-ws52kj-readonly-child-manifest-evidence-source` |
| Protected original B12 branch | `c137-gc24-k2p03-b12-cas-writer-cutover-source` @ `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af` |
| Protected production branch | `v320-renee-agrisuite-branding` @ `10041da3a3c4e4a7b8101f8183c243d056c7b128` |
| Literal always-OFF cutover gate | `lib/daily_record_revision_cutover_gate.php`, Git blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268` |
| Canonical shared CAS authority | `lib/daily_record_revision_authority.php`, Git blob `5fbb90903b0cc801be45c684f6c3db85c660979a` |
| Hard-disabled GH RC01 candidate | `tests/integration/k2p03_b12_gh_rc01_preliminary_synthetic.php`, Git blob `e78318509e270ccd24684bf41f5e854b74765f76` |
| Fixture-only policy source | `tools/b12_ws52kh_rc01_isolation_cleanup_contract.py`, Git blob `f5f9b82453fae41cc8bcc070414d988f052c5e14` |
| Prior index resolver | `tools/b12_ws52ki_mariadb_registry_manifest_readonly.py`, Git blob `ab109cd44b6c3965f284b3d53bc15a242a1fb970` |
| Previous WS52-KI evidence report | `docs/C137_GC24_K2_P03_B12_WS52KI_REGISTRY_IDENTITY_READONLY_DISCOVERY_GATE.md`, Git blob `a3e187ce1ad4dc55cf26065e38a2646ca8148704` |

Prior committed WS52-KI report (its source-only status predates the subsequent user-side live registry observations):  
https://github.com/abeycity4u/Renee-Farms-V2.2.50-Batch3B1/blob/a6c4d4298844e294ca2c5efb832f45520a277b82/docs/C137_GC24_K2_P03_B12_WS52KI_REGISTRY_IDENTITY_READONLY_DISCOVERY_GATE.md

## 2. User-returned sanitized WS52-KJ evidence — verbatim

```text
#####COPY FROM HERE#####
AUDIT=B12_WS52KJ_READONLY_CHILD_MANIFEST
RESULT=READONLY_CHILD_MANIFEST_PASS
REASON=NONE
OBSERVED_UTC=2026-10-15T20:02:48+00:00
SOURCE_INDEX_DIGEST=sha256:7db29378d4fdab73f8123bbc2b48905c90d1a4b00cf848b028f1e81e623257f2
EXPECTED_CHILD_DIGEST=sha256:dc9dd57be7f46ad6f350c598a331d64f295899c4abac3ec5652eff831fc38a6c
CHILD_MANIFEST_BYTES_VERIFIED=YES
CONFIG_DESCRIPTOR_DIGEST=sha256:41096ae9913468679bb5e05bbc19f44edcaf0eae45d67fcde224ffdeda9d3a1f
CONFIG_DESCRIPTOR_SIZE=8790
LAYER_DESCRIPTOR_COUNT=8
CONFIG_BYTES_FETCHED=NO
IMAGE_LAYERS_DOWNLOADED=NO
DOCKER_PULL_OR_OPERATIONS=NO
DOCKER_ENGINE_INSPECTED=NO
DATABASE_CONNECTIONS=0
SQL_EXECUTED=NO
RC01_WORKERS_EXECUTED=NO
HISTORICAL_BACKUP_ACCESSED=NO
RUNTIME_IMAGE_APPROVED=NO
ONE_USE_RUNTIME_APPROVAL=NOT_GRANTED
REPAIRED_RC01_RC14_CERTIFIED_PASSES=0
MIGRATION_092=NOT_AUTHORIZED
B12_CUTOVER=HARD_OFF
#####COPY TO HERE#####
```

The user previously returned a distinct WS52-KI public OCI INDEX read-only observation at `2026-10-15T19:53:00+00:00` for mutable source tag `docker.io/library/mariadb:10.11`, identifying the above index SHA256 and selected `linux/amd64` child SHA256. WS52-KJ is the subsequent independent **child-manifest-bytes** request/validation at the immutable child digest. These two steps must not be collapsed into a GH CI or physical Docker attestation.

## 3. Precisely what the evidence establishes

- According to the user-returned script's successful result, the immutable per-platform child manifest's raw bytes were SHA256-matched to `sha256:dc9dd57be7f46ad6f350c598a331d64f295899c4abac3ec5652eff831fc38a6c`, with checked response `Docker-Content-Digest`, schema/media types and descriptor structure.
- The **verified child-manifest bytes** contain the configuration **descriptor** with digest `sha256:41096ae9913468679bb5e05bbc19f44edcaf0eae45d67fcde224ffdeda9d3a1f`, declared raw length **8790 bytes**, and **8** layer descriptors.
- The configuration **object's raw bytes were not fetched/hashed**. No layers, local image, Docker engine, runtime container or executable MariaDB fixture were inspected, downloaded, created or certified.
- Hashing registry index and manifest objects is content-integrity evidence, **not publisher signing, provenance, vulnerability assessment, image execution identity, network/mount isolation, a database version attestation or the approval to pull/use this image**. The `mariadb:10.11` tag remains mutable.
- This is **user-returned local output**, preserved for continuity. GitHub offline parser tests verify the parser on fictional inputs only and cannot independently corroborate the live Docker Hub response.

## 4. Release/authority status — unchanged

- `B12_CUTOVER=HARD_OFF`, with the shared writer cutover gate literally returning false.
- Historic original one-use B12 concurrency result remains `FAILED_TWO_STALE_COMMITS` (run `38233373699`).
- `REPAIRED_RC01_RC14_CERTIFIED_PASSES=0`. No repaired GH PDO concurrency workers or real MariaDB 10.11 fixture were run as part of this evidence stage.
- `MIGRATION_092=NOT_AUTHORIZED`; actual legacy schema/NULL-cycle/cross-farm audit remains incomplete; production-derived historical backup restore remains NO-GO.
- No production or staging deployment, Docker pull/run, customer DB access, synthetic database bootstrap, SQL/DDL, migration, CI rerun, or cutover is authorized by recording this report.

## 5. Next controlled gate — optional WS52-KK (NOT executed or authorized by this report)

A separately scoped **public read-only OCI config-blob byte-integrity check** may retrieve exactly the expected `sha256:41096ae9913468679bb5e05bbc19f44edcaf0eae45d67fcde224ffdeda9d3a1f` object, declared **8790 bytes**, from `registry-1.docker.io/v2/library/mariadb/blobs/<digest>` under bounded HTTPS/TLS and restricted redirects, verify exact raw length/hash before parsing, and report only allowlisted OS/architecture/volume/entrypoint classifications. Never log config `Env` values, raw history, tokens or secrets; do not fetch layers or follow new hosts without separate scope. Any actual Docker runtime, fixture SQL, RC01 workers or image pull requires a wholly separate reviewed one-use execution authorization.

**Documentation-only acceptance:** The post-commit remote compare against exact WS52-KI HEAD must show **one added Markdown file only, no changed executable sources**, with protected B12 and production HEADs unchanged. No GitHub Actions run should be requested or triggered by this report.
