# C137 / GC24 / K2-P03 / B12 — WS52-KO one-redirect OCI configuration verification: source-only review

**Prepared:** 2026-10-15. **RESULT: SOURCE_PREPARED_ONLY.** No live registry/CDN configuration bytes fetched, no Mac user-returned WS52-KO result, and 16 authored fixture-only Python unit tests **NOT EXECUTED** by this commit.

## 1. Frozen identity and parent

- Repo: abeycity4u/Renee-Farms-V2.2.50-Batch3B1.
- Exact WS52-KN parent: c137-gc24-k2p03-b12-ws52kn-redirect-discovery-evidence-source at 3ed89a8ed1ac566d1edec279299a76df4e78fafe.
- New branch: c137-gc24-k2p03-b12-ws52ko-allowlisted-redirect-config-source.
- New candidate: tools/b12_ws52ko_allowlisted_redirect_config_readonly.py; Git blob ad32147f4e6f847071bec318f86edec75d5089f9.
- New authored 16-case fixture/mocked tests: tests/test_b12_ws52ko_allowlisted_redirect_config_readonly.py; Git blob 064e6c2450cead89aa7e4b3b2194a72dfe089159.
- Previous WS52-KM and WS52-KK source untouched. Original protected B12 HEAD 2327a22a3cd7a95ffea42fc76dd94805f5b1e7af; protected production HEAD 10041da3a3c4e4a7b8101f8183c243d056c7b128.
- Shared CAS authority blob 5fbb90903b0cc801be45c684f6c3db85c660979a. Cutover always-OFF gate blob 6e88c913ad5e5b371a9d0c92ce08870214ac1268.

## 2. Grounded evidence versus candidate assumptions

- User-returned WS52-KM observation: public registry request PINNED_CONFIG_BLOB_GET returned HTTP 307 to HTTPS host production.cloudfront.docker.com; Location was NOT followed, raw URL/path/query was not returned. Registry/CDN content **NOT** downloaded. Evidence report: docs/C137_GC24_K2_P03_B12_WS52KN_WS52KM_307_REDIRECT_DISCOVERY_EVIDENCE.md.
- Expected immutable index sha256:7db29378d4fdab73f8123bbc2b48905c90d1a4b00cf848b028f1e81e623257f2 and Linux AMD64 child sha256:dc9dd57be7f46ad6f350c598a331d64f295899c4abac3ec5652eff831fc38a6c are from earlier user-reported WS52-KI/KJ metadata.
- Fixed expected child CONFIG descriptor digest sha256:41096ae9913468679bb5e05bbc19f44edcaf0eae45d67fcde224ffdeda9d3a1f, length exactly 8790 bytes, eight layer descriptors. The configuration object bytes remain NOT VERIFIED.
- Important candidate assumption: the CloudFront path is expected to be /registry-v2/docker/registry/v2/blobs/sha256/41/41096ae9913468679bb5e05bbc19f44edcaf0eae45d67fcde224ffdeda9d3a1f/data. **This path was NOT observed in WS52-KM evidence.** If actual redirect path is different, the verifier MUST reject, report BLOCKED, and await separate review rather than broaden its path allowlist.
- No trust of mutable mariadb:10.11 tag, no publisher-signature/certificate-chain/supply-chain claim, no assertion that CloudFront runtime endpoint has been independently tested.

## 3. Strict one-redirect flow in dormant source

1. Mac-only source with TLS-validated HTTPS and environment proxies disabled; no subprocess, Docker, SQL, local files, layers, private backup or worker execution.
2. GET anonymous pull token solely from auth.docker.io fixed endpoint, max 16,384 token JSON bytes. Keep token only in memory, never log it.
3. GET exact registry-1.docker.io/v2/library/mariadb/blobs/<expected-config-digest> with bearer; do not read any registry blob body. Require exactly one HTTP 307, never implicitly follow any redirect.
4. Validate Location **before any destination request**: exact HTTPS host production.cloudfront.docker.com, standard port, no userinfo, fragment, control/unicode characters, relative URL, query escape malformation or unknown path. Require digest-bound fixed blob path, bounded signed query/URL, no URL logging.
5. Create a separate no-proxy TLS opener for CDN. GET only validated signed URL with **NO Authorization header**, no second redirect, 12-second timeout, and limit body to 8,790 bytes +1 reject sentinel. Verify exact Content-Length if returned, accepted media type if returned, exact body length and SHA256 BEFORE parsing JSON.
6. Check linux/amd64, 8 valid rootfs layer diff_ids, declared volumes and entrypoint/command *shape* only; do not emit Env, history, raw entrypoint/command values, signed URL, token or network exception.
7. Print a short proof block on Mac; any unexpected response fails CLOSED. A blob-hash PASS establishes content bytes, **NOT** publisher provenance, executable image identity, Docker/container isolation, MariaDB runtime, RC01 CAS concurrency or release authorization.

## 4. Offline coverage and restrictions

- 16 authored fixture/mocked tests cover exact single-307 host/digest-path acceptance; mismatched host/path, scheme, userinfo/port/status, broken query/fragment; SHA256 and size tampering; duplicate JSON keys; linux/amd64/layer shape; token validation; clean no-bearer CDN request; second redirect and direct-origin response rejection; non-Mac hard block and static source operation guards.
- These tests are **NOT an executed 16/16 PASS**, and no GitHub Actions one-use workflow or approval has been created. Only GitHub source/blob and semantic-diff inspection occurs in this stage.
- Do not trigger any runtime registry/CDN request as part of this source-only report. If the operator elects a future exact one-use public read-only Mac check, first pin/review the committed source SHA; report the sanitized block verbatim and preserve BLOCKED results if any.

## 5. B12 invariant gates

- B12_CUTOVER=HARD_OFF; historic race TWO_STALE_COMMITS remains FAILED; REPAIRED_RC01_RC14_CERTIFIED_PASSES=0.
- MIGRATION_092=NOT_AUTHORIZED. Real legacy NULL-cycle/cross-farm schema audit not completed; historical production-derived backup restore NO_GO.
- No Docker image pull, local engine/container, SQL/DDL, actual production/staging data access, image-layer download, RC01 worker, GitHub Actions re-run, product deployment or Stage D/150/200 VU advancement authorized.
- Post-commit acceptance: remote compare against exact WS52-KN parent SHA shows three newly added files (source, offline tests, report), no modified/deleted product file, old/production refs and cutover gate unchanged, zero GHA runs on branch.
