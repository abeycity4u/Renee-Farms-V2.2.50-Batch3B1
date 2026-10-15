# C137 / GC24 / K2-P03 / B12 — WS52-KZ hard-disabled Docker broker request/redaction source contract

**Date:** 2026-10-15. **Classification:** SOURCE_PREPARED_ONLY, NONEXECUTING. **Runtime physical inspector, broker, Docker engine, network, DB and cleanup NOT ATTESTED**. No tests have been executed as part of this GitHub source commit.

## 1. Exact lineage and unchanged protected sources

- Repository: abeycity4u/Renee-Farms-V2.2.50-Batch3B1.
- Exact parent: c137-gc24-k2p03-b12-ws52ky-trust-boundary-offline-pass-evidence-source @ 9da2dff0c3db1420524ac184a886354e217582b1.
- Successor: c137-gc24-k2p03-b12-ws52kz-broker-projection-disabled.
- NEW pure source: tools/b12_ws52kz_broker_projection_source_contract.py, Git blob 6bf2e8c2bf6a70838ffafe3d560e3f205c5da2ea.
- NEW 29 fictional offline tests: tests/test_b12_ws52kz_broker_projection_source_contract.py, Git blob 65fdcac4de6c677b72d95d1489492076929c5531. **Authored, not executed; do not claim 29/29 PASS yet.**
- Reused unchanged WS52-KX trust-boundary code: tools/b12_ws52kx_inspector_trust_boundary_design.py blob 221809f072d661948d3dd02b8a69ad05f75ccc27.
- Reused unchanged WS52-KV observation-phase design: tools/b12_ws52kv_physical_inspector_design_contract.py blob f6255fca70b6bdf959e77caf2b7a813c1afc0514.
- Reused unchanged WS52-KT volume/cleanup shape: tools/b12_ws52kt_one_volume_identity_cleanup_contract.py blob e3c6b7b79bb979902b9d610234b6fd84f2120c2d.
- Existing hard-off gate: lib/daily_record_revision_cutover_gate.php blob 6e88c913ad5e5b371a9d0c92ce08870214ac1268; CAS authority lib/daily_record_revision_authority.php blob 5fbb90903b0cc801be45c684f6c3db85c660979a.
- Disabled PHP GH RC01 candidate: tests/integration/k2p03_b12_gh_rc01_preliminary_synthetic.php blob e78318509e270ccd24684bf41f5e854b74765f76.
- Protected original B12 branch c137-gc24-k2p03-b12-cas-writer-cutover-source @ 2327a22a3cd7a95ffea42fc76dd94805f5b1e7af; protected production v320-renee-agrisuite-branding @ 10041da3a3c4e4a7b8101f8183c243d056c7b128.

## 2. Scope of new policy, by trust boundary

WS52-KX already set the *nonexecutable* plan for six narrowly described Docker metadata GET route templates, mandatory independently reviewed broker, no PHP/test worker Docker socket exposure, no secrets/permissions and no runtime approval. It correctly warns that an HTTP GET restriction on an **unrestricted raw Docker socket is not a sandbox**; direct socket access can effectively grant powerful host privileges. WS52-KZ must NOT change this premise or put a Docker socket/API credential in PHP, a GitHub Actions job, or the source-only checker.

The new pure WS52-KZ module delegates six route templates to WS52-KX rather than independently redefining the GET allowlist. It declares a hypothetical request/context/already-minimized response schema. **It contains no Docker client or broker, no socket/network, no HTTP requests, no filesystem, subprocess, SQL, CLI, mutation or cleanup functions**. BROKER_EXECUTION_ENABLED, DOCKER_SOCKET_ACCESS_GRANTED, DOCKER_MUTATIONS_ALLOWED, NETWORK_ALLOWED, SQL_ALLOWED, RC01_ALLOWED are literal False. PHYSICAL_BROKER_APPROVED and APPROVED_ONE_USE_RUN remain None.

### Request design

- Exact repository and WS52-KY parent HEAD, FUTURE_EPHEMERAL_GITHUB_HOSTED_ONLY target, unverified source-only review status, broker NOT_CREATED, physical engine and egress provenance NOT_COLLECTED, approval NOT_GRANTED, zero inherited secrets/runner permissions and zero socket access. A passing hypothetical context remains FABRICABLE.
- A mock run number, one exact 64-character hexadecimal container ID and a run-scoped synthetic volume name b12-rc01-vol-r<run>-<16hex>; these are merely closed-shape fictional identities. They are not independent resource ownership evidence.
- Only uppercase GET with a path exactly matching one of the six WS52-KX templates after substitution of this one mock container/volume identity. No query, custom header, request body, broker token, automatic redirect, alternative scheme/path encoding, mutating method, extra endpoint or execution claim.
- Illustrative routes include broad /info and /containers/json; those are especially sensitive and cannot safely be forwarded as raw Docker responses to a consumer. Any future real broker must independently constrain and minimize these results and may need a narrower route design before actual execution.

### Response minimization design

- Only a strictly closed, *already-minimized synthetic fixture* envelope may pass, with six known route-class labels, HTTP 200 integer, exact application/json / identity encoding, SYNTHETIC_SCHEMA_ONLY, empty projected_keys and NOT_COLLECTED projection state. An actual Docker response (including Env, Mounts.Source, AuthConfig, container config, raw ID, host paths, tokens or unreviewed nested arrays) fails shape validation rather than being exported.
- Output is a constant, short allowlisted dict: SOURCE_ONLY_SHAPE_NOT_ATTESTED or BLOCKED_SOURCE_CONTRACT, plus NOT_GRANTED runtime approval / NO physical inspection / NO raw response exported / HARD_OFF cutover. It never interpolates URL, secret, untrusted key/value or exception into the user-visible report.
- **Important limitation:** this code does not sanitize arbitrary real Docker HTTP responses; it rejects them. A separate physically isolated broker MUST implement true input parsing, byte/time caps, response field-level minimization, and security boundaries before any physical inspection. Synthetic envelope checks are not proof that a real broker redacts sensitive output.

### Evidence/release semantics

- Each SourceDecision always has physical_attested=False, broker_executed=False, one_use_approved=False, raw_response_exported=False and cleanup_certified=False, even when shape_valid=True.
- There is NO DOCKER_PULL, container create/start/stop/rm, anonymous/explicit volume manipulation, access to customer backup, any database connection, SQL, RC01 worker, GitHub Actions execution, or production/staging change in this step.

## 3. Offline negative tests authored (29 tests; NOT executed)

Tests cover each of six hypothetical GET routes with non-authorizing results, all hard-off gates, reuse of previous policies, wrong/extra/missing fields, repo and source drift, early physical/broker approval claims, socket/privilege/secrets, run-scoped resource identity, forbidden methods, forbidden endpoint paths, cross-tenant resource substitution, encoded paths and query bypasses, headers/body/redirects, wrong route-class, injection of raw Docker JSON, forbidden status/MIME/encoding, fake provenance/redaction, type confusions, non-leaking constant summaries and AST import purity. No tests in WS52-KT/KV/KX should be rerun without regression evidence.

**Next exact safe action:** a single pinned Git-blob Mac **OFFLINE** execution of these 29 fixture tests, verifying all four unchanged dependency and new source/test blobs. It may fetch only pinned GitHub source; no Docker hub/CDN, Docker, SQL or HTTP against production. If BLOCKED, preserve failed test names; if PASS_29_OF_29, preserve user-returned result in a separate documentation-only successor.

## 4. Frozen blockers and future restrictions

- Even after offline PASS there is no real read-only broker, isolated Docker host provenance, anonymous volume protection, firewall/egress observation, actual container/volume identity or cleanup proof. Separate source review plus narrowly scoped one-use approval remain mandatory before any physical Docker inspection or runnable MariaDB test.
- The earlier user-returned 8,790-byte MariaDB OCI config SHA256 PASS establishes content integrity and a declared /var/lib/mysql VOLUME, not image layers, publisher provenance, or a live engine/mount identity.
- Original MariaDB B12 race run 38233373699 FAILED_TWO_STALE_COMMITS. Repaired RC01–RC14 certified passes=0. Historical legacy NULL-cycle/cross-farm clone audit incomplete. Production-derived historical backup restore NO_GO. MIGRATION_092=NOT_AUTHORIZED; B12_CUTOVER=HARD_OFF.

## 5. Remote acceptance

Parent 9da2dff0c3db1420524ac184a886354e217582b1 -> WS52-KZ HEAD must show three NEW files only (pure source, 29 offline tests, this report), no modifications/deletions to older files, no migrations/workflows/runtime permissions, protected B12/production heads and critical blobs unchanged, and zero GitHub Actions runs.
