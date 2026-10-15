# C137 / GC24 / K2-P03 / B12 — WS52-KM read-only config redirect metadata probe

**Prepared:** 2026-10-15  
**Stage:** SOURCE_PREPARED_ONLY. The user-returned WS52-KK redirect blocker remains **BLOCKED**. WS52-KM has **NOT** contacted Docker Hub, has **NOT** executed its 11 authored offline unit tests, and has **NOT** verified OCI config bytes. This is not B12 runtime certification or a one-use DB approval.

## 1. Origin and exact source identities

- Repository: abeycity4u/Renee-Farms-V2.2.50-Batch3B1
- WS52-KL parent: c137-gc24-k2p03-b12-ws52kl-redirect-blocked-evidence-source at 609175261992ea53fba7181a0553fa7a6dd599ff
- New source branch: c137-gc24-k2p03-b12-ws52km-redirect-discovery-readonly-source
- Probe source: tools/b12_ws52km_mariadb_config_redirect_metadata_readonly.py; Git blob 99efb97fd736f84537abe496452061db4f20de21
- Eleven authored, fixture-free Python negative/offline tests, NOT EXECUTED: tests/test_b12_ws52km_mariadb_config_redirect_metadata_readonly.py; Git blob 2670cb620597e2857a87b4e555a74aa97ac787c3
- Existing WS52-KK immutable-config verifier unchanged, blob d6eafb31e1f239e6cef5fd3d7ef2665b2e82c258
- Literal hard-OFF CAS cutover gate unchanged, blob 6e88c913ad5e5b371a9d0c92ce08870214ac1268
- Canonical shared CAS authority unchanged, blob 5fbb90903b0cc801be45c684f6c3db85c660979a
- Protected original B12 branch @ 2327a22a3cd7a95ffea42fc76dd94805f5b1e7af
- Protected production branch @ 10041da3a3c4e4a7b8101f8183c243d056c7b128

Previous evidence: docs/C137_GC24_K2_P03_B12_WS52KL_WS52KK_READONLY_REDIRECT_BLOCKED_EVIDENCE.md records WS52-KK RESULT=BLOCKED, REASON=HTTP_REDIRECT_BLOCKED, at 2026-10-15T20:28:18+00:00. Do not change this failure or relabel it PASS.

## 2. Very narrow public network/read-only behavior

- Public anonymous OAuth token GET on EXACT https://auth.docker.io/token with fixed service=registry.docker.io and scope=repository:library/mariadb:pull; token JSON bounded at 16,384 bytes. Never print token.
- If token succeeds, issue GET to EXACT original https://registry-1.docker.io/v2/library/mariadb/blobs/sha256:41096ae9913468679bb5e05bbc19f44edcaf0eae45d67fcde224ffdeda9d3a1f with transient pull token. **No config-blob response body is read even on HTTP 200**; this is not a blob-integrity test.
- Reject ALL redirects with a no-follow handler. Never contact Location. TLS verification enabled; no environment proxy; HTTP timeout 12 seconds per request; no writing files or image layers.
- Identify original redirect stage as PUBLIC_TOKEN_GET or PINNED_CONFIG_BLOB_GET, capture only HTTP code, sanitized scheme, destination hostname when syntactically valid, and destination classification (SAME_ORIGIN_NO_FOLLOW, OTHER_REVIEWED_ORIGIN_NO_FOLLOW, EXTERNAL_UNREVIEWED_NO_FOLLOW or rejection). Never print raw Location, signed query parameters, bearer token, config JSON, Env/history, or credentials.
- For non-HTTPS, username/password URLs, bad ports, localhost/IP literals, non-ASCII or invalid hostnames: classify without printing hostname. Never follow redirects regardless of category.
- NO_REDIRECT_OBSERVED still leaves CONFIG_BYTES_FETCHED=NO and CONFIG_BYTES_HASH_VERIFIED=NOT_ATTESTED.
- A digest/redirect destination does NOT authorize accessing a CDN, pulling an image or executing runtime tasks. Any expanded host scope requires a distinct reviewed authorization.

## 3. Authored but unexecuted offline tests

Eleven cases cover fixed-scope source guards, same-origin/external HTTPS classifications, signed query suppression, non-HTTPS/userinfo/nonstandard ports and malformed hosts, token-vs-blob request attribution, rejection of duplicate public token JSON keys, no destination request, and non-Mac blocking. All use fictional values/mocks; no test has been executed/certified at source preparation.

## 4. Frozen status and acceptance criteria

- B12_CUTOVER=HARD_OFF. REPAIRED_RC01_RC14_CERTIFIED_PASSES=0. Historic failed B12 race TWO_STALE_COMMITS.
- MIGRATION_092=NOT_AUTHORIZED. Actual legacy schema and NULL-cycle/cross-farm inventory NOT_COMPLETED. Historical production-derived backup restore NO_GO.
- No GitHub Actions workflow/approval created or rerun; no SQL/DDL, Docker operations, RC01 workers, customer backup, staging/production deployment or capacity advancement.
- Remote GitHub semantic compare against exact WS52-KL parent SHA should show **three added files only**, zero modifications/deletions, protected refs and CAS blobs unchanged, and zero Actions runs.

## 5. Next evidence handoff

After independently checking branch and Git blobs, use an exact source-blob-verified Mac-only bootstrap, return ONLY the sanitized fields between literal #####COPY FROM HERE##### and #####COPY TO HERE##### markers. If a redirect is observed, decide separately whether any redirected destination is allowable; never automatically follow it. Keep WS52-KK blocked until actual config bytes have been separately retrieved and hashed.
