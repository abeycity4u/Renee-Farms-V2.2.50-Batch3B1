# B12 WS52-LI — Actual Unix-socket HTTP reader code, permanently HARD-OFF in this revision

**2026-10-16 | SOURCE_IMPLEMENTED_ONLY | NO real Docker/SQL/runner activity | 32 tests authored, NOT EXECUTED.**

## 1. Proven source and branch context

- Repo `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`; exact WS52-LH source parent `202bbe63387da8d6dc430bb0eae2316941a918a0` on `c137-gc24-k2p03-b12-ws52lh-ingress-offline-pass-evidence`.
- WS52-LI branch `c137-gc24-k2p03-b12-ws52li-unix-transport-hardoff`.
- `tools/b12_ws52li_docker_unix_transport_hardoff.py` Git blob `df1804eb86b002b216abf2558d554bb37f729488`.
- `tests/test_b12_ws52li_docker_unix_transport_hardoff.py` Git blob `f36047388c08dace0c8ea11658c5907708b80553`: 32 unit tests *authored, NOT executed*.
- Reuses WS52-KZ `tools/b12_ws52kz_broker_projection_source_contract.py` blob `6bf2e8c2bf6a70838ffafe3d560e3f205c5da2ea` and WS52-LE `tools/b12_ws52le_raw_docker_response_projection_core.py` blob `857c7e1bc0a9c85a26a3918b9a751f1d52102ba4`, which delegates further to LB and KT. Existing source and completed suites untouched.
- Protected original B12 branch HEAD `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af`; protected production branch HEAD `10041da3a3c4e4a7b8101f8183c243d056c7b128`. CAS gate blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268`, CAS authority blob `5fbb90903b0cc801be45c684f6c3db85c660979a`, disabled RC01 candidate blob `e78318509e270ccd24684bf41f5e854b74765f76` are unchanged.

## 2. Actual functionality implemented, versus execution gate

- Source now contains an **actual Python Unix-domain socket HTTP/1.1 GET reader** (not another fake Docker DTO); it is NOT used by any workflow or production route. The public `inspect_once` AND private `_bounded_socket_get` both independently require the same `_runtime_gate()` to pass. All gate inputs are literal `False` or `None`: `RUN_ENABLED`, `DOCKER_SOCKET_PERMISSION`, `APPROVAL_VERIFIER_IMPLEMENTED`, `INDEPENDENT_ENGINE_ATTESTED`, `ISOLATED_HOST_ATTESTED`, `ONE_USE_APPROVAL`. There is no user/environment variable override or approval verifier.
- Request bytes are generated internally: `GET` only, exact one of six inherited KZ route templates, exact reviewed 64-character container ID and run-scoped synthetic volume name, fixed Host, Accept, Accept-Encoding and Connection headers. No custom method/path/query/header/body, Docker API write operation or raw caller-built request bytes reaches the socket boundary.
- `_read_from_connection` is a dependency-injectable receive loop. The production socket opener has fixed Unix path `/var/run/docker.sock`, 2-second socket timeout, 4096-byte receive chunks, max 8192-byte headers, max 65536-byte raw JSON body and total wire cap; checks caps **as bytes are received**. Only controlled test fake connections exercise this loop without opening a socket.
- `_parse_http_wire` is a complete-response parser: HTTP/1.1 `200 OK` only, strict CRLF boundaries, no folded/newline/control headers, closed known header-name set and no duplicates, explicit canonical Content-Length matching actual complete payload, content type `application/json`, identity encoding, `Connection: close`. Reject unknown redirects, Transfer-Encoding/chunked, compression, status changes and incomplete/extra bodies. This intentionally fails closed if a real Docker daemon uses other response framing; it is not production-compatibility attested.
- The source-only guarded path would invoke inherited KZ context/policy then send a request, parse the bounded response and call WS52-LE raw-to-minimized JSON projection; it never returns raw or minimized Docker data as evidence or a user-visible string. `safe_summary` always returns constant HARD_OFF fields.
- Even if an authorized future implementation checks one physical request, it **cannot** use this result alone as independent Docker engine/host or runtime cleanup attestation. The current `inspect_once` is permanently inaccessible and returns `TRANSPORT_HARD_OFF` without socket calls.

## 3. Specific outstanding security controls (runtime BLOCKED)

- **Docker socket authority:** possession of a Docker socket is often equivalent to high privilege on its host; restricting a client to GET does not provide an operating-system enforced capability boundary. Use a separately controlled, disposable test host, nonshared socket, isolated privileged broker with independent observer, no application worker socket, no production or historical data.
- **Approval:** before any one-use runtime, separately review immutable source/broker blob, runner identity, complete OCI index+manifest+config+layer digest and signature policy, engine identity, exact run ID/attempt, network/egress and listener protections, image/persistent storage ownership, deletion scope, independent pre/post inventory, and abort/quarantine. Signed one-use approval MUST be independent from test process/env strings; not implemented.
- **Historical container is unrelated:** the earlier isolated local Mac historical-audit MariaDB 11.4.13 container is for separate archive compatibility work, not the GitHub MariaDB 10.11 synthetic RC01; the production-derived backup is NOT authorized for restore or RC01 tests.
- **Unsafe legacy workflow:** `.github/workflows/k2p03-mariadb-b12.yml` uses mutable `mariadb:10.11` with schema import from a file containing destructive SQL and belongs to the failed original B12 path; original workflow run `38233373699` concluded failure. Do NOT rerun it as repaired certification. `.github/workflows/c137-b12-ws52kf-rc01-gh-candidate-disabled.yml` has literal `if: false` and no approved physical runner. Source-only steps do not unblock it.

## 4. Verification plan and release invariant

- Run **only** the new 32 fake-socket Python tests, with exact Git blob/HEAD verification and no host engine use. This suite directly tests all six GET request builders, strict HTTP parsing, duplicate/injected/oversized headers, truncated/oversized body, fake-socket receive loop, and both public AND private hard-off barriers. Prior closed suites remain closed.
- Status as of this commit: `TESTS_AUTHORED_NOT_EXECUTED`; **zero** repaired RC01–RC14 physical certifications, **zero** live Docker socket accesses. No Actions jobs or DB operations created by this source-only branch.
- `RUNTIME_APPROVAL=NOT_GRANTED`, `REPAIRED_RC01_RC14_CERTIFIED_PASSES=0`, `MIGRATION_092=NOT_AUTHORIZED`, `B12_CUTOVER=HARD_OFF`. This source implementation is a prerequisite toward an isolated broker, not a broker ready to run.
- Remote semantic acceptance: three ADDED files only (one source, one test, this report), no modified or deleted prior product files, protected branch heads/blobs unchanged, Actions run count 0. Any physical step requires separate exact one-use authorization and independent host safety proof.
