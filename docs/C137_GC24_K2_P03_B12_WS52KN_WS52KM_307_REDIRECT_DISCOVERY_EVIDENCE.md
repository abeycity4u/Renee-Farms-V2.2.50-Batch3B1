# C137 / GC24 / K2-P03 / B12 — WS52-KN preserve WS52-KM read-only redirect metadata evidence

**Date:** 2026-10-15
**Observed UTC:** 2026-10-15T20:39:45+00:00
**Classification:** USER-RETURNED LOCAL MAC RESULT, NOT A GH-ACTIONS REGISTRY RUN.
**Narrow result:** READONLY_REDIRECT_METADATA_OBSERVED, REDIRECT_NOT_FOLLOWED. OCI configuration blob integrity remains NOT_ATTESTED.

## 1. Exact source and protected state at preflight

- Repo: abeycity4u/Renee-Farms-V2.2.50-Batch3B1.
- WS52-KM parent branch: c137-gc24-k2p03-b12-ws52km-redirect-discovery-readonly-source.
- Exact WS52-KM parent HEAD: 68c65ffa60dcd5643804940f02452c3a55a8cba2.
- New documentation-only successor: c137-gc24-k2p03-b12-ws52kn-redirect-discovery-evidence-source.
- WS52-KM diagnostic: tools/b12_ws52km_mariadb_config_redirect_metadata_readonly.py, Git blob 99efb97fd736f84537abe496452061db4f20de21.
- WS52-KM offline test cases (authored, no independently observed execution): tests/test_b12_ws52km_mariadb_config_redirect_metadata_readonly.py, Git blob 2670cb620597e2857a87b4e555a74aa97ac787c3.
- WS52-KM prior source report: docs/C137_GC24_K2_P03_B12_WS52KM_READONLY_REDIRECT_METADATA_SOURCE_REVIEW.md, Git blob d6156bec7fc6f9c0bce7ff947ad987a67164965e.
- WS52-KK config checker unchanged: tools/b12_ws52kk_mariadb_config_blob_readonly.py, Git blob d6eafb31e1f239e6cef5fd3d7ef2665b2e82c258.
- Production shared CAS authority unchanged: lib/daily_record_revision_authority.php, Git blob 5fbb90903b0cc801be45c684f6c3db85c660979a.
- Always-off cutover gate unchanged: lib/daily_record_revision_cutover_gate.php, Git blob 6e88c913ad5e5b371a9d0c92ce08870214ac1268.
- Protected original B12 HEAD: 2327a22a3cd7a95ffea42fc76dd94805f5b1e7af.
- Protected production HEAD: 10041da3a3c4e4a7b8101f8183c243d056c7b128.

## 2. WS52-KM user-returned sanitized Mac output — verbatim

~~~text
#####COPY FROM HERE#####
AUDIT=B12_WS52KM_READONLY_REDIRECT_DISCOVERY
RESULT=READONLY_REDIRECT_METADATA_OBSERVED
REASON=REDIRECT_NOT_FOLLOWED
OBSERVED_UTC=2026-10-15T20:39:45+00:00
REDIRECT_STAGE=PINNED_CONFIG_BLOB_GET
REDIRECT_HTTP_STATUS=307
REDIRECT_DESTINATION_SCHEME=https
REDIRECT_DESTINATION_HOST=production.cloudfront.docker.com
REDIRECT_DESTINATION_CLASS=EXTERNAL_UNREVIEWED_NO_FOLLOW
SOURCE_INDEX_DIGEST=sha256:7db29378d4fdab73f8123bbc2b48905c90d1a4b00cf848b028f1e81e623257f2
SOURCE_CHILD_DIGEST=sha256:dc9dd57be7f46ad6f350c598a331d64f295899c4abac3ec5652eff831fc38a6c
EXPECTED_CONFIG_DIGEST=sha256:41096ae9913468679bb5e05bbc19f44edcaf0eae45d67fcde224ffdeda9d3a1f
EXPECTED_CONFIG_SIZE=8790
CONFIG_BYTES_FETCHED=NO
CONFIG_BYTES_HASH_VERIFIED=NOT_ATTESTED
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

## 3. Interpretation and trust boundaries

- Token-endpoint GET did not cause this redirect: the diagnostic reached PINNED_CONFIG_BLOB_GET using the anonymous, ephemeral public pull token.
- The exact public registry configuration-blob GET at registry-1.docker.io responded with an HTTP 307 redirect. The returned Location destination was classified as HTTPS hostname production.cloudfront.docker.com, EXTERNAL_UNREVIEWED_NO_FOLLOW. No request was made to that destination.
- This hostname observation is consistent with a public CDN delivery pattern, but it is NOT proof of publisher signing, destination authorization, whole signed URL integrity, TTL, TLS connectivity to the redirected host, permitted path, or eventual blob bytes.
- The diagnostic intentionally reported only the sanitized destination hostname/scheme/status, not the raw Location URL, signed query parameters, bearer token, config body, Envs or history.
- The true config object bytes and SHA256 remain NOT_ATTESTED. Config OS/architecture, diff IDs, VOLUME declarations, Entrypoint/Cmd classifications, local image ID, Docker engine/container identity and runnable environment are NOT established.
- Earlier WS52-KI and WS52-KJ index and child manifest digests and the 8,790-byte config descriptor were user-returned integrity evidence; they do not authorize CDN retrieval, Docker pull or real MariaDB execution.
- A previous WS52-KK observed blocker HTTP_REDIRECT_BLOCKED is not overwritten by this *successful diagnostic*. Preserve both outcomes, scoped to their distinct purposes.

## 4. Next controlled, separately reviewed decision

- WS52-KN is documentation ONLY. Do NOT edit the WS52-KM or WS52-KK scripts, disable redirect protection, follow cloudfront redirects, or initiate Docker, SQL, database workers, sensitive backup access or migration.
- If pursued, a separately approved WS52-KO read-only config-byte retrieval would need pinned immutable source and exact config SHA256:41096ae9913468679bb5e05bbc19f44edcaf0eae45d67fcde224ffdeda9d3a1f (length 8790).
- Its design must authenticate the original registry request, validate the single 307 Location (HTTPS, exact reviewed host, safe port, no userinfo, bounded URL and no untrusted SSRF/extra redirects), never display raw signed URLs or secrets, and limit GET and byte read to that one exact object with strict size caps/timeouts.
- Follow only a separately reviewed allowlisted host/path/network scope after explicit authorization; no unknown host, loop, unexpected redirect or layer fetch. Always SHA256-validate actual raw bytes before parsing or claiming CONFIG_BYTES_HASH_VERIFIED=YES. Disable proxy environment and prevent credential forwarding to CDN.
- A successful object integrity check still does not certify a MariaDB runtime, official image signature, RC01 concurrency pass, legacy-data migration, or B12 cutover.

## 5. Frozen release gates and acceptance

- B12_CUTOVER=HARD_OFF; repaired RC01–RC14 certified passes=0; original B12 MariaDB race remains FAILED_TWO_STALE_COMMITS.
- MIGRATION_092=NOT_AUTHORIZED; actual legacy NULL-cycle and cross-farm schema audit NOT_COMPLETED; historical production-derived backup restore NO_GO.
- No GH-Actions run, live registry retry, Docker/image pull, SQL, RC01 workers, production deploy, migration or load-stage advancement approved by WS52-KN.
- Required remote compare WS52-KM parent SHA to WS52-KN HEAD: ahead 1, behind 0, exactly ONE new Markdown file, no modification or deletion; protect original B12/production refs, shared authority, gate and diagnostic Git blobs; zero GitHub Actions runs on new branch.
