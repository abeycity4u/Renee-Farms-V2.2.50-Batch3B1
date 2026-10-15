# C137 / GC24 / K2-P03 / B12 — WS52-KK read-only OCI CONFIG-blob verifier source preparation

**Date:** 2026-10-15  
**Status:** `SOURCE_PREPARED_ONLY` — **configuration blob not fetched or hashed by this work**, offline unit tests written but **not executed or certified**. This report is not runtime or production approval.

## Parent identity and scope
- Repository: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- Exact WS52-KJ parent HEAD: `d508ddfcdef5ce3a8b4ff0744f6f5113f3fa042f`.
- New branch: `c137-gc24-k2p03-b12-ws52kk-readonly-config-blob-source`.
- Pinned config checker: `tools/b12_ws52kk_mariadb_config_blob_readonly.py`, Git blob `d6eafb31e1f239e6cef5fd3d7ef2665b2e82c258`.
- Fixture-free source test cases: `tests/test_b12_ws52kk_mariadb_config_blob_readonly.py`, Git blob `dbf024ba1da8a61bd8534d100bd9856aa5d573e5`; **10 authored, not executed**.
- No workflow, Actions approval, database service, runner, or execution fuse change introduced.

## Source and expected immutable objects
The prior user-returned Mac WS52-KJ result (verbatim evidence in `docs/C137_GC24_K2_P03_B12_WS52KJ_READONLY_AMD64_CHILD_MANIFEST_PASS.md`) reported:
- OCI index `sha256:7db29378d4fdab73f8123bbc2b48905c90d1a4b00cf848b028f1e81e623257f2`;
- Linux AMD64 child manifest `sha256:dc9dd57be7f46ad6f350c598a331d64f295899c4abac3ec5652eff831fc38a6c`;
- config descriptor `sha256:41096ae9913468679bb5e05bbc19f44edcaf0eae45d67fcde224ffdeda9d3a1f`;
- descriptor size **8790 bytes**, **8** manifest layer descriptors;
- **CONFIG_BYTES_FETCHED=NO**, no image pull, Docker, SQL or RC01 work.

The newly added checker performs **only** a public anonymous pull-scoped token GET against `auth.docker.io` and an exact immutable OCI **configuration blob** GET against `registry-1.docker.io/v2/library/mariadb/blobs/sha256:41096ae9913468679bb5e05bbc19f44edcaf0eae45d67fcde224ffdeda9d3a1f`. It has fixed URL/size/hash expectations, short timeouts, verified HTTPS, no proxy, no redirects, no token output, no raw blob print/storage, no Docker, no SQL and a Mac-only execution guard. **If this registry endpoint redirects to a CDN/other host, the checker intentionally BLOCKS without following it**. Do not relax this origin restriction without distinct review/authorization.

On successful GET, it checks raw byte length and SHA256 before strict duplicate-key JSON parsing; expects `os=linux`, `architecture=amd64`, and exactly eight valid rootfs diff IDs; reports only fixed classifications for declared volumes, presence of MariaDB datadir volume, and entrypoint/command shapes. It deliberately never prints `Env`, `history`, labels, tokens, raw arguments or JSON contents. The pure tests cover tampering, size/digest, platform, layer shapes, duplicate keys, volume/command types, scope and non-Mac fail-closed behavior. **Only source/static inspection was performed by the assistant here; no PHP/Python tests or public registry request were executed.**

## Persistent safety gates
- Protected original B12 branch `c137-gc24-k2p03-b12-cas-writer-cutover-source`: HEAD `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af`.
- Protected production ref `v320-renee-agrisuite-branding`: HEAD `10041da3a3c4e4a7b8101f8183c243d056c7b128`.
- Literal cutover gate Git blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268`; shared authority `5fbb90903b0cc801be45c684f6c3db85c660979a`.
- `B12_CUTOVER=HARD_OFF`; `REPAIRED_RC01_RC14_CERTIFIED_PASSES=0`; `MIGRATION_092=NOT_AUTHORIZED`; actual legacy-data schema audit not completed, historical production-backup restore NO-GO.
- No image layer download, Docker runtime, database bootstrap, SQL/DDL, worker, source cutover, migration, CI rerun, production or staging action is part of this phase.

## Next acceptance boundary
Obtain a **user-run, read-only Mac** result only after verifying the checker source against its exact pinned Git blob SHA and optionally performing fixture-free offline checks. The only accepted live result is a sanitized bounded `B12_WS52KK_READONLY_CONFIG_BLOB` block; record any BLOCKED result as blocked, with no fallback to unapproved redirect hosts, image layers or other artifacts. Preserve successful user-returned output in a later separate docs-only evidence commit after remote source and branch checks. Hashing a config blob still does **not** approve a runnable MariaDB image or a B12 RC01 concurrency test.
