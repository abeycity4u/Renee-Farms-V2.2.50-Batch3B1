# B12 WS52-LE — Real-shaped Docker response projection core; runtime HARD-DISABLED

**Source-review date: 2026-10-16. Status: NEW IMPLEMENTATION + TESTS AUTHORED; NOT EXECUTED; NOT A LIVE BROKER.**

## Pinned ancestry

- Repository: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- Exact parent `c137-gc24-k2p03-b12-ws52ld-enforceable-broker-architecture` HEAD `9dcf25a4907d676a8c27070eab60603744c4ad2d`.
- Branch `c137-gc24-k2p03-b12-ws52le-real-response-projection-disabled`.
- New functional in-memory raw projection core `tools/b12_ws52le_raw_docker_response_projection_core.py` blob `857c7e1bc0a9c85a26a3918b9a751f1d52102ba4`.
- New adversarial unit tests `tests/test_b12_ws52le_raw_docker_response_projection_core.py` blob `801947caf61ba085206f914fef143a0e162e3155` — **28 authored, NOT YET RUN**.
- Reused original WS52-KZ request-context broker design blob `6bf2e8c2bf6a70838ffafe3d560e3f205c5da2ea`, WS52-LB minimized schema validator blob `66727cccf56f3ead11610d55bb39cbe0fe760e12`, WS52-KT pinned image config contract blob `e3c6b7b79bb979902b9d610234b6fd84f2120c2d`; unchanged.
- Original B12 hard-disabled RC01 harness blob `e78318509e270ccd24684bf41f5e854b74765f76`, cutover gate blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268` and CAS authority blob `5fbb90903b0cc801be45c684f6c3db85c660979a` must remain unchanged.
- Protected B12 branch `c137-gc24-k2p03-b12-cas-writer-cutover-source` HEAD `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af` and protected production branch `v320-renee-agrisuite-branding` HEAD `10041da3a3c4e4a7b8101f8183c243d056c7b128`.

## What is genuinely implemented now

- Unlike WS52-LB, which only accepts ALREADY-MINIMIZED synthetic envelopes, WS52-LE implements **pure in-memory extraction from supplied full Docker-shaped JSON bytes**. Successful source-only input can contain Docker extras such as Config.Env, HostConfig.Binds, Mounts.Source, volume Mountpoint, RegistryConfig, NetworkSettings and unrelated inventory Labels. Those raw extras are dropped BEFORE handing the minimized object to the existing strict WS52-LB policy.
- Explicit six GET route mapping reused from WS52-KZ; exact run-scoped synthetic container/volume identity; request-context checks run before JSON decoding; non-200, altered MIME, compression or wrong verb/path/query/identity fail closed. No arbitrary path or caller-specified Docker endpoint is accepted.
- Raw parser requires **bytes** with length 1–65,536, strict UTF-8 and JSON, rejection of duplicate or escaped-duplicate keys and NaN/Infinity, <=1024 total nodes, <=12 depth, <=96 children per dict/list, key <=128 characters, text <=4096 characters, no surrogate Unicode or floating-point fields. It does **not** implement transport streaming, read timeouts, OS privilege isolation or HTTP socket access.
- For /version: Version and ApiVersion only. For /info: ID and ServerVersion only. For /containers/json: list of only Id. For /containers/{exact_id}/json: Id, pinned Image, HostConfig Privileged/NetworkMode/exact 3306 loopback PortBindings, and effective Mounts Type/Name/Destination/RW/Driver only. For /volumes: list of Name only. For /volumes/{exact_name}: Name/Driver and exact {run_id,purpose} Labels; **unreviewed extra volume Labels reject rather than silently vanish**.
- The minimized record is reserialized within memory and validated through existing WS52-LB, including KT immutable image config matching, one writable named local /var/lib/mysql volume, zero extra mounts, no public 3306 listener and strict boolean RW=True. Any unreviewed data or mismatch fails closed with a constant blocker code. Output's `CoreResult.minimized` contains internal opaque resource/engine IDs and must **never** be exported as a customer-facing report; `safe_status` emits only constant labels and no supplied IDs/paths/tokens/raw bytes.
- `BROKER_RUNTIME_ENABLED=False`, `DOCKER_SOCKET_ACCESS_ALLOWED=False`, `TRANSPORT_IMPLEMENTED=False`, `IMAGE_PULL_ALLOWED=False`, `SQL_ALLOWED=False`, `RC01_ALLOWED=False`, `APPROVED_ONE_USE_RUNTIME=None`. `physical_attested`, `real_broker_executed`, `one_use_approved`, `actual_raw_response_redaction_certified`, `cleanup_certified` remain literal **False** even for an internally valid projection.

## Security boundary and exact remaining implementation

- **This is not a full broker and not proof of real redaction, even though its pure projection function processes raw Docker-shaped input.** Caller-supplied bytes and context are untrusted and fabricable; the module contains NO Docker SDK, HTTP fetch, socket, filesystem, subprocess, SQL, workflow, mount/volume mutation, cleanup or service listener.
- In a future separately authorized step, an independently privileged, isolated broker must enforce Docker API capability boundaries *at transport*, fetch actual raw bytes with strict streaming size/time limits BEFORE buffering, and call this pure projector only inside the trusted process. The broker must redact raw source at the origin before any logs, evidence storage or export and must prohibit caller access to the Docker socket and unrestricted API proxying.
- Additional runtime gates remain: attest ephemeral host/engine identity, immutable OCI index/child/config/layer chain, effective Docker mounts/inventories, independent host/egress/loopback policy, volume lifetime/ownership, quiescent workers/transactions, exact cleanup/quarantine, and separate one-use human authorization. Six-source projections and labels are not physical attestation.
- Any future transport upgrade must NOT simply flip a false constant. A specific, separately reviewed, explicitly approved broker connector with independent identity, permissions, logging/redaction and teardown proof is required.

## Verification plan — bounded and offline

- 28 newly authored Python unittest cases cover six full raw-looking Docker payloads with sensitive extra fields; preexport minimization and constant summaries; all runtime fuses; malicious input types/oversize; duplicates, Unicode, nonfinite, nested bombs and collection caps; missing raw fields; wrong pinned image/volume/container/labels; extra/anonymous/bind mounts, `RW` integer versus literal True, host networking/privileges, public ports; invalid KZ requests and identity; non-200/compressed responses; sensitive tokens in raw Env/Mounts.Source; and AST import/CLI safety.
- **No tests executed in this commit yet.** Next permitted action: run ONLY these 28 cases on a Mac after verifying exact pinned Git blobs for KT, KV, KX, KZ, LB, LE and new tests; preserve user-returned evidence in a documentation-only successor. Do not rerun previously closed WS52-KT/KV/KX/KZ/LB suites unless regression evidence emerges.
- GitHub acceptance must show exact parent pinned, three new files only (source, tests and this report), no old source modifications, remote semantic diff PASS, protected B12/production heads unchanged and GitHub Actions run count 0.

## B12 frozen gates

- Original GitHub Actions race job `38233373699=FAILED_TWO_STALE_COMMITS`; repaired RC01–RC14 certified passes=0. Historical production-derived backup review requires separate authorization. `MIGRATION_092=NOT_AUTHORIZED`, `RUNTIME_APPROVAL=NOT_GRANTED`, `B12_CUTOVER=HARD_OFF`.
