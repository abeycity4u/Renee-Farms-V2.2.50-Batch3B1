# C137 / GC24 / K2-P03 / B12 — WS52-KL preserve WS52-KK redirect-blocked evidence (documentation only)

**Date:** 2026-10-15  
**Evidence observation (UTC):** 2026-10-15T20:28:18+00:00  
**Result:** BLOCKED, reason HTTP_REDIRECT_BLOCKED. **NOT a configuration integrity PASS**.

## 1. Pinned source checkpoint

- Repository: abeycity4u/Renee-Farms-V2.2.50-Batch3B1.
- Exact WS52-KK parent: c137-gc24-k2p03-b12-ws52kk-readonly-config-blob-source @ 4ddbd5b8af3cf2d6ad5965d111a1b007596e7047.
- Documentation-only WS52-KL branch: c137-gc24-k2p03-b12-ws52kl-redirect-blocked-evidence-source.
- Source: tools/b12_ws52kk_mariadb_config_blob_readonly.py — Git blob d6eafb31e1f239e6cef5fd3d7ef2665b2e82c258.
- Fixture-free authored tests: tests/test_b12_ws52kk_mariadb_config_blob_readonly.py — blob dbf024ba1da8a61bd8534d100bd9856aa5d573e5. Test execution not independently verified.
- Prior source report: docs/C137_GC24_K2_P03_B12_WS52KK_READONLY_CONFIG_BLOB_SOURCE_PREPARATION.md — blob 59f5b77ef01812fb068649e57296bceb92fcdb40.
- Shared CAS authority: lib/daily_record_revision_authority.php — blob 5fbb90903b0cc801be45c684f6c3db85c660979a.
- Always-OFF gate: lib/daily_record_revision_cutover_gate.php — blob 6e88c913ad5e5b371a9d0c92ce08870214ac1268.
- Protected original B12 branch HEAD: 2327a22a3cd7a95ffea42fc76dd94805f5b1e7af.
- Protected production branch HEAD: 10041da3a3c4e4a7b8101f8183c243d056c7b128.

## 2. USER-RETURNED sanitized WS52-KK Mac output — verbatim

This is user-returned local evidence, not independently repeated registry access, and must NOT be relabeled as an image/config pass.

~~~text
#####COPY FROM HERE#####
AUDIT=B12_WS52KK_READONLY_CONFIG_BLOB
RESULT=BLOCKED
REASON=HTTP_REDIRECT_BLOCKED
OBSERVED_UTC=2026-10-15T20:28:18+00:00
SOURCE_INDEX_DIGEST=sha256:7db29378d4fdab73f8123bbc2b48905c90d1a4b00cf848b028f1e81e623257f2
SOURCE_CHILD_DIGEST=sha256:dc9dd57be7f46ad6f350c598a331d64f295899c4abac3ec5652eff831fc38a6c
EXPECTED_CONFIG_DIGEST=sha256:41096ae9913468679bb5e05bbc19f44edcaf0eae45d67fcde224ffdeda9d3a1f
EXPECTED_CONFIG_SIZE=8790
CONFIG_BYTES_HASH_VERIFIED=NOT_ATTESTED
OS_ARCH=NOT_ATTESTED
ROOTFS_DIFF_ID_COUNT=NOT_ATTESTED
DECLARED_VOLUME_COUNT=NOT_ATTESTED
MYSQL_DATADIR_VOLUME_DECLARED=NOT_ATTESTED
ENTRYPOINT_CLASS=NOT_ATTESTED
COMMAND_CLASS=NOT_ATTESTED
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
~~~

## 3. Safe interpretation and unresolved facts

- The read-only Mac verifier's BlockRedirect handler refused a redirect as designed. A redirect is not proof of a defective MariaDB image.
- **The sanitized output does not identify which GET redirected.** The code uses the no-redirect opener for BOTH anonymous token GET to auth.docker.io AND config-blob GET to registry-1.docker.io. It does not reveal the stage, status code, redirect destination host, or Location value. Do not infer a CDN or claim the configuration request was definitely reached.
- The raw CONFIG BYTES remain unverified. OS/arch, eight diff IDs, volume declarations, entrypoint/command classifications are not attested. The config descriptor SHA256 and 8790 length came from prior verified child-manifest USER-RETURNED evidence, not from a configuration-blob GET.
- The existing exact immutable index/child/config digests are content identifiers, not a publisher signature, physical Docker image ID, container identity, isolation guarantee or approved runtime.
- Per user-returned output: no image-layer download, Docker operations/inspection, database connection, SQL, RC01 workers or sensitive historical backup access.

## 4. Next safe stage: redirect DISCOVERY ONLY, with a separate source review

Do not disable the redirect guard, silently follow Location or fetch image layers. If continuing, author a distinct tightly bounded READ-ONLY metadata probe that:
1. Uses exact public HTTPS origins, TLS checks, fixed digest, no proxy, no automated redirect following, and no local Docker/SQL/backup use.
2. Identifies WHICH public GET stage redirected, HTTP status/code, HTTPS/other scheme and sanitized Location HOSTNAME only, without printing raw URL, query credentials, bearer token, config, history or Env values.
3. Fails closed on unexpected host/scheme/status, ambiguous Location, loops, excessive response data, malformed headers or changes in source; does not contact the redirected destination.
4. Requires a separate reviewed scope before ever following a redirected host or changing the effective network/permission boundary.

## 5. Frozen release status and documentation-only acceptance

B12_CUTOVER=HARD_OFF. Repaired RC01-RC14 certified passes = 0. Original B12 real-MariaDB race FAILED_TWO_STALE_COMMITS. Migration 092 unauthorized. Actual legacy NULL-cycle/cross-farm schema audit incomplete. Historical production-derived SQL backup restore NO_GO. No GH runtime MariaDB, SQL/DDL, production/staging deployment, Docker, worker, CI rerun or cutover is authorized.

After committing, independently check remote GitHub compare from WS52-KK pinned parent HEAD: **ahead 1, behind 0, exactly one ADDED Markdown report** and zero executable modifications; protected original/production branches and B12 source hashes unchanged; no GitHub Actions triggered.
