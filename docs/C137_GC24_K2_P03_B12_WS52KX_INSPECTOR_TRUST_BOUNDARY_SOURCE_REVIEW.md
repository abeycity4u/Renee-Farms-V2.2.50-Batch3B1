# C137 / GC24 / K2-P03 / B12 — WS52-KX inspector trust-boundary source review

**2026-10-15 | SOURCE_ONLY_PREPARED | REAL_INSPECTION_NO_GO**

## Exact ancestry and protected references

- Repository: abeycity4u/Renee-Farms-V2.2.50-Batch3B1.
- Parent branch: c137-gc24-k2p03-b12-ws52kw-inspector-design-offline-pass-evidence-source @ 59b459432fa05726219ec8d7e8c43e89e1a807ef.
- New branch: c137-gc24-k2p03-b12-ws52kx-inspector-trust-boundary-disabled.
- New pure source: tools/b12_ws52kx_inspector_trust_boundary_design.py, Git blob 221809f072d661948d3dd02b8a69ad05f75ccc27.
- New 34 authored fixture-only tests, NOT EXECUTED: tests/test_b12_ws52kx_inspector_trust_boundary_design.py, Git blob d71f6ebf6f42fc9463517a3554fd737943386e52.
- Reused WS52-KV design: tools/b12_ws52kv_physical_inspector_design_contract.py, blob f6255fca70b6bdf959e77caf2b7a813c1afc0514.
- Reused WS52-KT volume contract: tools/b12_ws52kt_one_volume_identity_cleanup_contract.py, blob e3c6b7b79bb979902b9d610234b6fd84f2120c2d.
- Prior user-returned WS52-KV 22/22 offline PASS preserved by WS52-KW documentation; not rerun.
- Protected original B12 branch: c137-gc24-k2p03-b12-cas-writer-cutover-source @ 2327a22a3cd7a95ffea42fc76dd94805f5b1e7af.
- Protected production: v320-renee-agrisuite-branding @ 10041da3a3c4e4a7b8101f8183c243d056c7b128.
- B12 cutover gate blob 6e88c913ad5e5b371a9d0c92ce08870214ac1268; CAS authority blob 5fbb90903b0cc801be45c684f6c3db85c660979a; RC01 disabled PHP harness blob e78318509e270ccd24684bf41f5e854b74765f76 — unchanged.

## Grounded findings versus design assumptions

- The earlier user-returned WS52-KO 8,790-byte config SHA256 PASS for sha256:41096ae9913468679bb5e05bbc19f44edcaf0eae45d67fcde224ffdeda9d3a1f declared one MariaDB /var/lib/mysql volume. No executable whole image or physical Docker runtime has been inspected.
- WS52-KT fictional pre/post contract models an exact new named disposable local volume, sole writable /var/lib/mysql mount, zero other mounts, identity-linked container and full inventory restoration. WS52-KV defines six ordered independent observation phases. Both already have user-returned source-only fixture test passes.
- New WS52-KX restricts the FUTURE collector trust-boundary *design*, without repeating the existing mount/cleanup/phase logic. Six Docker metadata GET method/path templates are NONEXECUTABLE documentation, not a Docker client permission, Docker socket sandbox, or host mutation authorization.
- Docker socket access may confer extensive host privileges even if a proposed application promises to call only GET. A future collector therefore needs a separately reviewed, isolated, restrictive read-only broker rather than giving the PHP worker Docker socket access. GET /info and GET /containers/{id}/json may expose environment, mount source paths, labels or sensitive config; broker must minimize and redact responses before exporting evidence. No raw response dumps, logs, Env, auth tokens, signed URLs, host mountpoint paths or customer records may be emitted.
- GitHub-hosted runner status, workflow identity, Git SHA and claimed observer identity are not independently established by strings supplied in untrusted fixture JSON. A future physical inspector needs independent runner/provenance checks and a narrowly permitted one-use workflow, no inherited secrets or mutable branch-based approval, exact fresh engine/container/volume IDs and timestamps/snapshot binding.
- Bridge mode and a loopback port specification are not proof of restricted egress or actual host listener isolation. Independent host-side network observations remain mandatory. Similarly a fictional pre/post inventory cannot prove cleanup; unexpected/missing physical assets require STOP/QUARANTINE rather than prune or wildcard removal.

## Source-only invariant contract

- New module has no CLI, Docker import/API client, filesystem, subprocess, network or SQL operations. INSPECTOR_EXECUTION_ENABLED=False, DOCKER_SOCKET_ACCESS_GRANTED=False, DOCKER_MUTATIONS_ALLOWED=False, SQL_ALLOWED=False, RC01_ALLOWED=False; all runtime approvals None.
- Source shape requires pinned repository/WS52-KW HEAD, explicit future-ephemeral-runner-only scope, no workflow created, no permissions/secrets granted, no app/inspector Docker socket access, no broker approval, and network/loopback as PENDING_INDEPENDENT measurement.
- Closed route templates allow hypothetical GET metadata /version, /info, /containers/json, /containers/{pinned_container_id}/json, /volumes, /volumes/{pinned_volume_name}; all are DESIGN LABELS ONLY. Missing/extra/reordered routes, mutations, archives, logs, events, unbounded queries and unknown fields are rejected. A later broker must independently enforce method/path templates, prohibit arbitrary query parameters, restrict identity scope and redact at the source.
- Closed output projection contains only status labels. Sensitive/raw Docker fields are prohibited by schema. Empty provenance placeholders remain NOT_COLLECTED; attempting to report runtime-approved or physical proof is rejected in this source-only version.
- BoundaryResult physical_attested, one_use_approved, network_isolated, cleanup_certified and rc01_certified are ALWAYS False. Sanitized summaries are either SOURCE_DESIGN_SHAPE_ONLY_NOT_ATTESTED or BLOCKED_SOURCE_CONTRACT. No runtime approval route exists.
- 34 offline tests authored with negatives for wrong repo/head/scope, runner identity, tokens/secrets, direct socket access, broker bypass, mutation, egress/loopback claims, raw response leakage, Docker mutating endpoints, forbidden logs/archives/events, route/phase/intent order, projection/provenance drift, missing or unknown keys, and source AST purity. These tests are **NOT EXECUTED** in WS52-KX; do not claim 34/34 PASS.

## Unresolved gates / safe next step

- Physical Docker inspector implementation, independent identity trust root, guarded broker capabilities, real network/port/egress proof, one-use runtime provisioning, MariaDB observer grants, DB fixture, and exact volume cleanup are ALL PENDING separate source reviews and explicit one-use approval. Do not turn this design into an executable runtime merely by changing a flag.
- Original GitHub B12 race run 38233373699 remains FAILED_TWO_STALE_COMMITS, repaired RC01–RC14 certified passes=0, actual legacy-production-derived schema read-only clone audit incomplete, backup restore NO_GO, MIGRATION_092=NOT_AUTHORIZED, B12_CUTOVER=HARD_OFF.
- **Next immediate action:** conduct one pinned-source Mac offline test of only the 34 new fictional cases, with two dependency blobs verified. Preserve sanitized PASS or specific failed test names, and then separately commit evidence. No Docker/SQL/requested runtime test.
- Remote diff acceptance: source branch must be three added files only (pure contract, 34 tests, report) relative to exact WS52-KW HEAD, zero old file modifications, protected HEADs/blobs unchanged, GitHub Actions run count 0.
