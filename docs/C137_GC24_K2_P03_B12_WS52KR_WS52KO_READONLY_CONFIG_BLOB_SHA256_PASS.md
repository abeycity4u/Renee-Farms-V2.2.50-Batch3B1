# C137 / GC24 / K2-P03 / B12 — WS52-KR preserve WS52-KO immutable OCI CONFIG byte-integrity PASS

**Date:** 2026-10-15. **User-returned observation UTC:** 2026-10-15T21:05:44+00:00.
**Evidence classification:** USER-RETURNED MAC-LOCAL PUBLIC READ-ONLY REGISTRY/CDN RESULT. The assistant independently verified immutable GitHub source/blob and protected-ref identities, not the live Docker Hub/CDN response. Do not claim that GitHub Actions or this GitHub documentation operation re-executed the public retrieval.
**Narrow verified result:** WS52-KO reports READONLY_CONFIG_BLOB_SHA256_PASS on exact expected 8,790-byte OCI configuration object. **No executable image/runtime, MariaDB SQL, repaired CAS concurrency or B12 cutover has been certified.**

## 1. Exact source/parent identities at preflight

- Repository: abeycity4u/Renee-Farms-V2.2.50-Batch3B1.
- WS52-KQ parent branch: c137-gc24-k2p03-b12-ws52kq-offline-pass-evidence-source; exact HEAD 98099ca9c7a57b349a34254758408dc57f714b1c.
- New documentation-only branch: c137-gc24-k2p03-b12-ws52kr-config-blob-sha-pass-evidence-source.
- Executed Mac source as reported: tools/b12_ws52ko_allowlisted_redirect_config_readonly.py; Git blob ad32147f4e6f847071bec318f86edec75d5089f9 (unchanged across WS52-KO/WS52-KP/WS52-KQ).
- Corrected offline tests: tests/test_b12_ws52ko_allowlisted_redirect_config_readonly.py; Git blob f3cf8aa58bc529c7589d47a3cf564ac5ad9218a9.
- Previously preserved Mac offline tests PASS_16_OF_16: docs/C137_GC24_K2_P03_B12_WS52KQ_WS52KP_CORRECTED_OFFLINE_16_OF_16_PASS.md; Git blob f77c39a68ef494830b1504179f5d10ce1a73f69b.
- Literal B12 HARD_OFF gate: lib/daily_record_revision_cutover_gate.php; Git blob 6e88c913ad5e5b371a9d0c92ce08870214ac1268.
- Shared CAS authority: lib/daily_record_revision_authority.php; Git blob 5fbb90903b0cc801be45c684f6c3db85c660979a.
- Disabled preliminary GH RC01 candidate: tests/integration/k2p03_b12_gh_rc01_preliminary_synthetic.php; Git blob e78318509e270ccd24684bf41f5e854b74765f76.
- Protected original B12 branch: c137-gc24-k2p03-b12-cas-writer-cutover-source @ 2327a22a3cd7a95ffea42fc76dd94805f5b1e7af.
- Protected production branch: v320-renee-agrisuite-branding @ 10041da3a3c4e4a7b8101f8183c243d056c7b128.

## 2. Exact sanitized user-returned WS52-KO Mac result — VERBATIM

The following was returned by the user; the live registry/CDN call was **not** independently executed by the assistant. Preserve it exactly, including B12 safety gates:

~~~text
#####COPY FROM HERE#####
AUDIT=B12_WS52KO_SINGLE_ALLOWLISTED_307_CONFIG
RESULT=READONLY_CONFIG_BLOB_SHA256_PASS
REASON=NONE
OBSERVED_UTC=2026-10-15T21:05:44+00:00
SOURCE_INDEX_DIGEST=sha256:7db29378d4fdab73f8123bbc2b48905c90d1a4b00cf848b028f1e81e623257f2
SOURCE_CHILD_DIGEST=sha256:dc9dd57be7f46ad6f350c598a331d64f295899c4abac3ec5652eff831fc38a6c
CONFIG_DIGEST=sha256:41096ae9913468679bb5e05bbc19f44edcaf0eae45d67fcde224ffdeda9d3a1f
EXPECTED_CONFIG_BYTES=8790
CONFIG_BYTES_SHA256_VERIFIED=YES
OS_ARCH=linux/amd64
ROOTFS_DIFF_ID_COUNT=8
DECLARED_VOLUME_COUNT=1
MYSQL_DATADIR_VOLUME_DECLARED=YES
ENTRYPOINT_CLASS=ARRAY_NONEMPTY
COMMAND_CLASS=ARRAY_NONEMPTY
CDN_SECOND_REDIRECT_FOLLOWED=NO
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

## 3. What this does and does not establish

- The user-returned result asserts that the exact raw 8,790 configuration bytes downloaded from the allowed HTTPS CloudFront URL after one registry HTTP 307 match the expected sha256:41096ae9913468679bb5e05bbc19f44edcaf0eae45d67fcde224ffdeda9d3a1f. The source checks SHA256 BEFORE parsing JSON, and emits only allowlisted metadata.
- The selected immutable Linux AMD64 child manifest digest remains sha256:dc9dd57be7f46ad6f350c598a331d64f295899c4abac3ec5652eff831fc38a6c, selected from OCI index sha256:7db29378d4fdab73f8123bbc2b48905c90d1a4b00cf848b028f1e81e623257f2. Original mutable tag mariadb:10.11 is NOT a pinned executable image.
- OS/arch linux/amd64 and exactly 8 configuration rootfs diff IDs were observed after hash validation. **Rootfs diff IDs do not attest that the layer blobs were separately downloaded/validated**. The 8 declared diff IDs are not container identity.
- Exactly one image VOLUME declared in config; /var/lib/mysql is one of the declared VOLUMEs. **This is a concrete future isolation/teardown blocker to review**: even without an explicit mount, a Docker engine may create an anonymous data-directory volume. Do not assert zero mounts or suppress a mount inspection failure. Future one-use runner plans must independently identify any new disposable volume by identity, isolate it from user/protected volumes, and prove exact cleanup. Otherwise STOP.
- Entrypoint and command are reported only as NONEMPTY ARRAY classes. Do not infer/print raw arguments. Config Env, history, signed CDN URL, token, secrets and customer data were not logged.
- The submitted result states no second redirect followed, no layers downloaded, no Docker pull or engine inspection, no DB or SQL activity, no RC01 workers, and no production-derived historical backup access.
- The successful object digest is content-integrity evidence only. It is **not** a publisher signature, trusted provenance, complete runtime image verification, network/volume isolation, successful MariaDB startup, a passing CAS race, or permission to run Docker/SQL. No live service was touched.
- Historical WS52-KK HTTP_REDIRECT_BLOCKED remains a correctly scoped fail-closed result; WS52-KM's 307 discovery was also correct. WS52-KO separately overcame the known redirect with narrowly reviewed host and digest-bound path, without erasing those historical observations.

## 4. Frozen release status / next safe workflow

- B12_CUTOVER=HARD_OFF, REPAIRED_RC01_RC14_CERTIFIED_PASSES=0, original B12 live-MariaDB GitHub Actions race 38233373699 FAILED_TWO_STALE_COMMITS.
- MIGRATION_092=NOT_AUTHORIZED. Actual independent legacy-schema, NULL-cycle identity, cross-farm collision and FK audit still NOT_COMPLETED; production-derived historical backup restore remains NO_GO.
- No new GH workflow, one-use runner, Docker/SQL fixture, network egress exemption, customer backup access, Stage D, 150/200 VU, staging/production deployment or migration is authorized by recording a successful config blob check.
- **Next recommended safe stage:** separate SOURCE-ONLY runtime candidate and one-use isolation/volume cleanup review based on verified OCI metadata; specifically reconcile declared /var/lib/mysql volume with strict empty-mount policy and assess digest pinning, actual runner/container/image ID attestation, independent lock observer and scoped cleanup *without starting a container*. A genuinely executable RC01 fixture requires distinct explicit one-use user approval after this planning and cannot substitute for actual legacy-data read-only clone audit.
- **Acceptance for this evidence preservation only:** remote GitHub comparison from exactly 98099ca9c7a57b349a34254758408dc57f714b1c to the successor HEAD must be ahead 1, behind 0 and show exactly ONE ADDED Markdown file; zero source/test/migration/workflow modification; protected B12 and production HEADs/critical Git blobs unchanged; zero Actions triggered.
