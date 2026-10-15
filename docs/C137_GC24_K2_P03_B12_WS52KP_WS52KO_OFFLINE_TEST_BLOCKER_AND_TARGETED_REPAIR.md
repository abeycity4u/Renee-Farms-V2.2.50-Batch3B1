# C137 / GC24 / K2-P03 / B12 — WS52-KP targeted offline WS52-KO test repair

**Date:** 2026-10-15. **Classification:** SOURCE-ONLY test correction and user-returned failed output preservation; no public Docker Hub/CDN request and no actual config-blob bytes checked during this correction.

## 1. Pinned identities

- Repository: abeycity4u/Renee-Farms-V2.2.50-Batch3B1.
- Exact WS52-KO parent branch: c137-gc24-k2p03-b12-ws52ko-allowlisted-redirect-config-source at 7aa490508516393d568fbfacd17d9d7aaf52551c.
- New repair branch: c137-gc24-k2p03-b12-ws52kp-offline-verifier-repair.
- WS52-KO existing functional verifier unchanged: tools/b12_ws52ko_allowlisted_redirect_config_readonly.py; Git blob ad32147f4e6f847071bec318f86edec75d5089f9.
- WS52-KO offline test file previous blob 064e6c2450cead89aa7e4b3b2194a72dfe089159; repaired test blob f3cf8aa58bc529c7589d47a3cf564ac5ad9218a9.
- Repaired test file still contains 16 test functions, with only an AST import guard replacing the substring assertion and one new ast module import.
- Protected original B12 branch c137-gc24-k2p03-b12-cas-writer-cutover-source HEAD 2327a22a3cd7a95ffea42fc76dd94805f5b1e7af.
- Protected production branch v320-renee-agrisuite-branding HEAD 10041da3a3c4e4a7b8101f8183c243d056c7b128.
- B12 always-OFF gate Git blob 6e88c913ad5e5b371a9d0c92ce08870214ac1268; shared CAS authority blob 5fbb90903b0cc801be45c684f6c3db85c660979a.

## 2. User-returned failed offline output — preserve verbatim

User attempted fixture-free Mac offline verification of the pinned WS52-KO source. Its bootstrap proceeded to run 16 tests. It did NOT print individual test exceptions; therefore this record does not claim all failed cases are known. Its EXACT sanitized copyback follows.

~~~text
#####COPY FROM HERE#####
AUDIT=B12_WS52KO_OFFLINE_SOURCE_GATE
RESULT=BLOCKED
REASON=OFFLINE_TEST_CONTRACT_FAILED
SOURCE_COMMIT=7aa490508516393d568fbfacd17d9d7aaf52551c
EXACT_SOURCE_BLOBS=NOT_ATTESTED
OFFLINE_TESTS=NOT_PASSED
TESTS_EXECUTED=16
REGISTRY_REQUESTS=0
CDN_REQUESTS=0
CONFIG_BYTES_FETCHED=NO
DOCKER_OPERATIONS=NO
DATABASE_CONNECTIONS=0
SQL_EXECUTED=NO
REPAIRED_RC01_RC14_CERTIFIED_PASSES=0
MIGRATION_092=NOT_AUTHORIZED
B12_CUTOVER=HARD_OFF
#####COPY TO HERE#####
~~~

Important nuance: the Mac wrapper's reporting code sets EXACT_SOURCE_BLOBS=NOT_ATTESTED for ANY non-PASS overall result. The submitted output therefore does NOT prove a Git blob mismatch. The wrapper successfully reached the 16-test execution stage. The failed result is real and must not be relabeled PASS.

## 3. Independently demonstrated deterministic false-positive

- The pinned WS52-KO verifier's leading Python documentation text contains the phrase 'or subprocess.' when explaining forbidden behavior. The verifier source does not import the subprocess module.
- The original 16th test used a textual assertion to require 'subprocess.' be absent anywhere in the complete source text. That assertion necessarily matches the documentation string and fails independently of whether there are any executable subprocess calls.
- This is a **deterministic false-positive test contract** as established by independent GitHub source inspection. It accounts for at least one failed assertion. Because the Mac wrapper suppressed detailed unittest logs, OTHER failures have not been ruled out.
- Targeted correction: import Python ast in the existing test file, parse the actual candidate source, and inspect AST Import and ImportFrom nodes to reject forbidden executable module imports for subprocess, os, docker, pymysql, mysql. This avoids treating comments/docstrings as code. No change to the candidate network runtime source or production application code.
- Do not claim the repaired 16 tests have passed: only the source edit and Git blob identity are confirmed. The corrected suite still needs one new fixture-only verification using the repaired test blob and pinned new branch.

## 4. Preserved hard-stop release status

- B12_CUTOVER=HARD_OFF. Repaired RC01–RC14 certified passes=0. Original real MariaDB B12 race remains FAILED_TWO_STALE_COMMITS.
- MIGRATION_092=NOT_AUTHORIZED. Actual legacy-data NULL-cycle/cross-farm schema audit incomplete. Historical production-backup restore NO_GO.
- No Docker pull/engine, container, database connection, SQL/DDL, RC01 workers, sensitive backup access, production/staging deployment, public registry or CloudFront GET, or GitHub Actions run/approval was authorized or performed for this patch.

## 5. Exact controlled next step

- Independent remote GitHub semantic compare with exact parent WS52-KO SHA should show one changed existing fixture-only test and one new Markdown evidence file; no executable product/OCI checker edits and no unexpected files.
- Confirm protected original/production branches and B12/CAS blobs unchanged and zero GitHub Actions triggered.
- Run corrected suite as a separate read-only Mac offline unit test, with source and test bytes verified against exact pinned Git blob IDs. Do NOT invoke the candidate main(), request Docker Hub/CloudFront, or mutate a fixture.
- Preserve PASS or BLOCKED result verbatim. If further failures occur, obtain only sanitized **test names and exception categories**, never credentials, URLs with signatures, secret values or verbose raw traces. Do not rerun a genuine PASS without regression evidence.
