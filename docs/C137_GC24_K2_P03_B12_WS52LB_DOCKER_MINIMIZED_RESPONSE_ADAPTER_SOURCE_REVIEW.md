# C137 / GC24 / K2-P03 / B12 — WS52-LB bounded minimized Docker metadata adapter (HARD-DISABLED)

**Review date:** 2026-10-15. **Stage:** SOURCE_PREPARED_ONLY; tests authored NOT EXECUTED; Docker physical inspection, real response redaction, broker implementation and RC01 runtime NOT AUTHORIZED.

## 1. Pinned source and protected refs

- Repo: abeycity4u/Renee-Farms-V2.2.50-Batch3B1.
- Exact predecessor WS52-LA: c137-gc24-k2p03-b12-ws52la-broker-projection-offline-pass-evidence-source HEAD 8f6a4b5925074bcc8c2ee8e5966608c4714b554a.
- New WS52-LB: c137-gc24-k2p03-b12-ws52lb-docker-response-redaction-disabled.
- New pure adapter: tools/b12_ws52lb_docker_redaction_adapter_source.py Git blob 66727cccf56f3ead11610d55bb39cbe0fe760e12.
- New 28 offline fixture cases: tests/test_b12_ws52lb_docker_redaction_adapter_source.py Git blob 32570b436174b8e84806be3b36d3a5c2807113e7 (authored; NOT YET EXECUTED).
- Reuses the existing KZ pure broker request contract: tools/b12_ws52kz_broker_projection_source_contract.py blob 6bf2e8c2bf6a70838ffafe3d560e3f205c5da2ea.
- Reuses the KT pinned OCI config digest: tools/b12_ws52kt_one_volume_identity_cleanup_contract.py blob e3c6b7b79bb979902b9d610234b6fd84f2120c2d.
- Prior unchanged KV inspector design blob f6255fca70b6bdf959e77caf2b7a813c1afc0514, KX broker trust boundary blob 221809f072d661948d3dd02b8a69ad05f75ccc27.
- Existing disabled GH RC01 harness blob e78318509e270ccd24684bf41f5e854b74765f76; literal B12 cutover gate blob 6e88c913ad5e5b371a9d0c92ce08870214ac1268; CAS authority blob 5fbb90903b0cc801be45c684f6c3db85c660979a.
- Protected B12 original branch c137-gc24-k2p03-b12-cas-writer-cutover-source HEAD 2327a22a3cd7a95ffea42fc76dd94805f5b1e7af.
- Protected production branch v320-renee-agrisuite-branding HEAD 10041da3a3c4e4a7b8101f8183c243d056c7b128.

## 2. Security boundary: SOURCE-ONLY minimized fixture schema, NOT a real HTTP raw redactor

The KZ user-returned 29/29 offline result established that the previous policy rejects arbitrary raw Docker responses and offers no actual client. WS52-LB builds a SMALL fixture-only normalized Docker-field adapter to reason about explicit source-level JSON parsing and export safeguards. It is not a Docker API client and has **no Docker socket, HTTP, network, filesystem IO, subprocess, SQL, runtime or cleanup functionality**.

**Critical distinction:** Docker's ACTUAL /info, container inspect, volume inspect and inventory JSON responses typically contain more fields than this hypothetical closed subset. For example Docker inspect includes sensitive Config.Env and Mounts.Source, volume inspection includes Mountpoint, and /info may expose driver state and host details. The WS52-LB adapter rejects these extra fields rather than claiming to safely sanitize arbitrary actual Docker responses. A FUTURE independent, isolated, separately reviewed broker must parse/validate and minimize raw Docker HTTP bytes BEFORE exporting only a narrowly reviewed structured projection. This module does not prove that such a broker exists or is secured.

## 3. Exact new source contract

- Import-free of Docker, subprocess, socket, urllib, requests, OS file IO and SQL; pure standard library json/re/dataclasses/typing plus existing KT and KZ shared pure policies.
- Literal hard-offs: ADAPTER_EXECUTION_ENABLED=False, DOCKER_SOCKET_ACCESS_GRANTED=False, RAW_DOCKER_HTTP_ACCESS_GRANTED=False, NETWORK_ALLOWED=False, SQL_ALLOWED=False, RC01_ALLOWED=False, APPROVED_REAL_BROKER=None and APPROVED_ONE_USE_RUN=None.
- Delegates HTTP method/path/run context to KZ assess_broker_fixture; does not redefine the route allowlist. Only inherited six metadata GET route classes are recognized, fixed exact one-run-scoped synthetic volume and one container ID, with zero headers/queries/bodies/tokens/redirects.
- Strict byte-length cap 1–4096 inclusive, UTF-8 decode, JSON parser with duplicate-key rejection via object_pairs_hook, nonfinite-number rejection, cap of 8 per dict/list collection, 100 nodes, six levels, limited key lengths, scalar strings and numeric types. Invalid/malformed/duplicate/oversized input results in a constant fail-closed blocker label, no exception contents or raw bytes logged.
- Closed synthetic route schemas using Docker-named fields: GET /version exposes only Version/ApiVersion; GET /info only ID/ServerVersion; GET /containers/json only list entries Id; GET /containers/{id}/json only Id, Image, HostConfig and Mounts (single writable named local volume at /var/lib/mysql and loopback-only 127.0.0.1:3306); GET /volumes only Volumes list of Name; GET /volumes/{name} only Name/Driver/Labels. Image ID matches the existing KT pinned config digest, without implying full OCI executable image verification.
- Any Env, Config, AuthConfig, Source, raw Mountpoint, logs, network data, extra Docker fields, mismatched container or volume identity, invalid driver/port/privilege, duplicate IDs, over-cap list, wrong MIME/redirect/request route (from KZ) is rejected. The only successful result is SOURCE_ONLY_MINIMIZED_FIXTURE_SHAPE, *not* a runtime PASS.
- Every SourceProjection has physical_attested=False, broker_executed=False, actual_raw_response_redacted=False, one_use_approved=False, cleanup_certified=False by construction. safe_report returns ONLY constant allowlisted status labels, HARD_OFF release gate and NOT_GRANTED approval. It never prints IDs, paths, Env, raw bytes, tokens or exception strings.
- This contract is deliberately more restrictive than an actual Docker API response and has only hypothetical data. It does not certify egress, volume ownership, complete physical inventory, network state, Docker daemon privilege separation, TLS/OCI publisher identity or effective mount creation/teardown.

## 4. Authored unit cases (28; NOT executed yet)

28 offline negative/positive fixture cases cover six minimized endpoint schemas with no physical attestations; hard-off constants; shared-policy reuse; oversized/empty/wrong-type bytes; invalid UTF8 and malformed JSON; duplicate and escaped duplicate keys; nested duplicate keys; NaN/Infinity; deeply nested JSON and excessive collection sizes; overlong names/values; unknown sensitive /info fields; duplicate inventories; raw Docker Env/Config/Source and Mountpoint rejection; immutable image/config identity, volume labels, localhost/privilege restrictions; strict boolean mount writeability (True not integer 1), extra mounts; disallowed cross-resource URLs/queries/methods; unapproved runtime claims; and constant-output non-leakage plus AST import purity.

**Test status is SOURCE_ONLY_UNEXECUTED.** Do not claim 28/28 PASS before the source-blob-pinned Mac fixture-only verifier returns that outcome. Prior closed 20/20, 22/22, 34/34 and 29/29 cases need not rerun without regression evidence.

## 5. Next step, frozen gates, remote review

- Immediate next allowed step is **one pinned-source Mac OFFLINE verifier** of these exact 28 tests using pinned source/test and unchanged dependency blobs, followed by separate documentation-only preservation of user-returned evidence. No Docker, SQL or production access.
- Any actual future raw Docker HTTP parsing/broker permission design or one-use host inspection needs fresh separately reviewed source and explicit authorization, not a generic 'proceed'. A full real broker also needs response byte/time cap at transport and independent observer trust, and must not give PHP/Docker workers unrestricted socket access.
- Repaired RC01–RC14 certified passes=0; original concurrency run 38233373699 FAILED_TWO_STALE_COMMITS; production-derived historical backup restore NO_GO; legacy NULL-cycle/cross-farm clone audit incomplete; MIGRATION_092=NOT_AUTHORIZED; B12_CUTOVER=HARD_OFF.
- GitHub acceptance: compare branch to exact parent 8f6a4b5925074bcc8c2ee8e5966608c4714b554a, expect three ADDED files only (pure source, 28 offline tests, this report), zero existing source/workflow/migration changes, protected branch heads/critical blobs unchanged and zero GitHub Actions runs.
