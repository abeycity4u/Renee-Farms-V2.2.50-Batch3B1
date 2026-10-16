# C137 / GC24 / K2-P03 / B12 — WS52-LJ preserve WS52-LI 32/32 offline Unix transport PASS

**Recorded 2026-10-16. Evidence class:** user-returned Mac offline verifier output. Exact pinned source blob identities separately checked against GitHub. This report does not claim that the assistant independently ran the Mac test suite or inspected a physical Docker Engine.

## Pinned lineage

- Repository: `abeycity4u/Renee-Farms-V2.2.50-Batch3B1`.
- Exact parent branch: `c137-gc24-k2p03-b12-ws52li-unix-transport-hardoff` at `f75113e56dc084758c5a959725ca3e951ace1a74`.
- Documentation-only successor: `c137-gc24-k2p03-b12-ws52lj-unix-transport-offline-pass-evidence`.
- Hard-disabled Unix transport: `tools/b12_ws52li_docker_unix_transport_hardoff.py`, Git blob `df1804eb86b002b216abf2558d554bb37f729488`.
- Offline fake-socket tests: `tests/test_b12_ws52li_docker_unix_transport_hardoff.py`, Git blob `f36047388c08dace0c8ea11658c5907708b80553`.
- WS52-LI source-review report: `docs/C137_GC24_K2_P03_B12_WS52LI_UNIX_TRANSPORT_SOURCE_REVIEW.md`, Git blob `b15a5281776d9716184ac07a4e1c2e3e1bce4fc2`.
- Existing WS52-LE raw projection blob `857c7e1bc0a9c85a26a3918b9a751f1d52102ba4`, KZ request policy blob `6bf2e8c2bf6a70838ffafe3d560e3f205c5da2ea` unchanged.
- Critical B12 hard-off gate `lib/daily_record_revision_cutover_gate.php` blob `6e88c913ad5e5b371a9d0c92ce08870214ac1268`, CAS authority `lib/daily_record_revision_authority.php` blob `5fbb90903b0cc801be45c684f6c3db85c660979a`, disabled GH RC01 `tests/integration/k2p03_b12_gh_rc01_preliminary_synthetic.php` blob `e78318509e270ccd24684bf41f5e854b74765f76` unchanged.
- Protected original B12 branch `c137-gc24-k2p03-b12-cas-writer-cutover-source` HEAD `2327a22a3cd7a95ffea42fc76dd94805f5b1e7af`; protected production `v320-renee-agrisuite-branding` HEAD `10041da3a3c4e4a7b8101f8183c243d056c7b128`.

## Verbatim user-returned offline verification

~~~text
#####COPY FROM HERE#####
AUDIT=B12_WS52LI_OFFLINE_UNIX_TRANSPORT
RESULT=PASS_OFFLINE_ONLY
REASON=NONE
SOURCE_COMMIT=f75113e56dc084758c5a959725ca3e951ace1a74
EXACT_SOURCE_BLOBS=PASS
TESTS_EXECUTED=32
OFFLINE_TESTS=PASS_32_OF_32
FAILED_TEST_NAMES=NONE
REAL_DOCKER_SOCKET_CONTACT=NO
PHYSICAL_DOCKER_INSPECTION=NO
DATABASE_CONNECTIONS=0
SQL_EXECUTED=NO
RUNTIME_APPROVAL=NOT_GRANTED
REPAIRED_RC01_RC14_CERTIFIED_PASSES=0
MIGRATION_092=NOT_AUTHORIZED
B12_CUTOVER=HARD_OFF
#####COPY TO HERE#####
~~~

## Interpretation

- The supplied output reports exact-source-blob verification and all **32/32 new offline fake-socket tests** PASS. No failures were returned. This is source-behavior verification, NOT actual Docker Engine, security-boundary, physical network, volume, MariaDB, database-schema or race certification.
- The source includes an HTTP/1.1 Unix-socket reader and parser, but public entrypoint and private socket-opening primitive both enforce literal HARD-OFF gates; no independent approver or host/engine attestation is implemented. No live Docker socket was contacted.
- A Docker Unix socket generally carries powerful host-management authority. An internal GET allowlist cannot substitute for an independently isolated, permission-enforced broker. Do not flip runtime guards, give application workers the socket, or route this code into an existing Docker host.
- Original GH B12 race workflow run `38233373699` concluded failure. The older `.github/workflows/k2p03-mariadb-b12.yml` is NOT the repaired RC01 candidate. The newer `.github/workflows/c137-b12-ws52kf-rc01-gh-candidate-disabled.yml` is explicitly gated by `if: false`, and the RC01 harness is hard-disabled.

## Next actionable stage and exact safety boundary

- The source-only transport/fake-socket milestone is closed. Next work should establish a **specific fresh, disposable, synthetic-only runner/host**, independent host and engine identity, complete immutable OCI image/layer chain, separately controlled privilege boundary, preexisting resource inventory, network/egress rules and a one-use approval manifest. Until these exist, **physical isolation certification remains BLOCKED**.
- A fresh synthetic MariaDB 10.11 RC01 test should be considered only after those guards are independently verified and explicit approval is granted for the named host, source, image, exact container, named data volume and one test attempt; production backup and historical clone are excluded.
- Stage 3 historical backup audit is a separate authorized data-access workflow, not a prerequisite for creating a completely synthetic isolated RC01 fixture and not permission to restore production data.
- Existing source-only tests must not be needlessly repeated. No physical Docker, SQL, GitHub Actions workflow, production/staging or historical backup access is approved by the user-returned 32/32 PASS.
- `RUNTIME_APPROVAL=NOT_GRANTED`, `REPAIRED_RC01_RC14_CERTIFIED_PASSES=0`, `MIGRATION_092=NOT_AUTHORIZED`, `B12_CUTOVER=HARD_OFF`.
- Remote GitHub acceptance: one new Markdown only, no modified/deleted preexisting source/workflow/migration; exactly one commit ahead/zero behind pinned parent; protected HEADs/blobs unchanged, GitHub Actions runs zero.
