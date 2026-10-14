#!/usr/bin/env bash
# B12 WS07 consolidated SOURCE-ONLY verification manifest and runner.
# This is a NEW runner; the historical B12 runner/result stays unchanged.
# Invoke ONLY in a disposable, exact-HEAD Git checkout with offline PHP/Node.
# No migrations, app bootstrap, network calls, real MariaDB or RC01.
set -uo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)" || exit 1
cd "$ROOT" || exit 1
echo '#####COPY FROM HERE#####'
echo 'AUDIT=B12_WS07_CONSOLIDATED_OFFLINE'
stop() {
  echo "RESULT=BLOCKED"
  echo "REASON=$1"
  echo 'DATABASE_TESTS=NOT_RUN'
  echo 'APPLICATION_MIGRATIONS=NOT_RUN'
  echo 'RC01_RUNTIME_TESTS=0'
  echo 'B12_CUTOVER=NOT_TOUCHED'
  echo '#####COPY TO HERE#####'
  exit 1
}
for command_name in git php node; do
  command -v "$command_name" >/dev/null 2>&1 || stop "RUNTIME_MISSING_${command_name}"
done
BRANCH='c137-gc24-k2p03-b12-ws07-consolidated-source'
BASE='9b2b94f842e04f49423fa4e97b22444da00c758c'
RUNNER='scripts/verify_k2p03_b12_ws07_offline.sh'
[ "$(git branch --show-current 2>/dev/null)" = "$BRANCH" ] || stop 'BRANCH_GUARD_FAILED'
HEAD="$(git rev-parse HEAD 2>/dev/null)" || stop 'HEAD_UNAVAILABLE'
[ "$(git rev-parse HEAD^ 2>/dev/null)" = "$BASE" ] || stop 'PARENT_COMMIT_CHANGED'
[ -z "$(git status --porcelain --untracked-files=normal)" ] || stop 'WORKTREE_DIRTY'
REMOTE_REF="refs/remotes/origin/$BRANCH"
[ "$(git rev-parse --verify "$REMOTE_REF" 2>/dev/null)" = "$HEAD" ] || stop 'REMOTE_HEAD_MISMATCH'
[ "$(git diff --name-only "$BASE" "$HEAD")" = "$RUNNER" ] || stop 'UNEXPECTED_WS07_PRODUCT_CHANGE'
[ "$(git hash-object scripts/verify_k2p03_b12_offline.sh 2>/dev/null)" = '28bb189122a8c713469ef6d4e48d4001e3447b8f' ] || stop 'HISTORICAL_RUNNER_CHANGED'
[ "$(git hash-object lib/daily_record_revision_cutover_gate.php 2>/dev/null)" = '6e88c913ad5e5b371a9d0c92ce08870214ac1268' ] || stop 'CUTOVER_GATE_CHANGED'
[ "$(php -r 'require "lib/daily_record_revision_cutover_gate.php"; echo daily_record_revision_cutover_enabled() ? "ON" : "OFF";' 2>/dev/null)" = 'OFF' ] || stop 'CUTOVER_RUNTIME_NOT_OFF'
echo "BRANCH_GUARD=PASS"
echo "PINNED_PARENT=PASS"
echo "REMOTE_HEAD_MATCH=PASS"
echo "SOURCE_DELTA=ONLY_WS07_RUNNER"
echo "ORIGINAL_RUNNER=UNCHANGED"
echo "CUTOVER_GATE=HARD_OFF"

# Closed manifest: only these source-only tests are executed.
# k2p03_b12_real_mariadb.php and k2p03_b12_repaired_rc01_real_mariadb.php
# are explicitly NOT eligible for this runner.
LINT_FILES=(
  lib/daily_record_revision_authority.php
  lib/daily_record_revision_save_gate.php
  lib/daily_record_revision_persistence.php
  lib/daily_record_revision_save_pipeline.php
  lib/daily_record_revision_route_adapter.php
  lib/daily_record_revision_reader.php
  lib/daily_record_revision_create_guard.php
  lib/daily_record_revision_parent_lock.php
  lib/daily_record_revision_continuity.php
  lib/daily_record_revision_continuity_apply.php
  lib/daily_record_revision_delete_continuity.php
  lib/daily_record_revision_delete_pipeline.php
  lib/daily_record_revision_delete_http.php
  lib/daily_record_revision_cycle_seed.php
  lib/daily_record_revision_api_adapter.php
  lib/daily_record_revision_cutover_gate.php
  api/daily_record_revision_read.php
  api/delete_record.php
  lib/ruminant_daily_record_deletion.php
  management/production_cycles.php
  poultry/layers_daily_record.php
  poultry/broiler_daily_record.php
  ruminant/ruminant_daily_record.php
  includes/permission_runtime.php
)
PHP_TESTS=(
  tests/integration/k2p03_b12_daily_writer_inventory.php
  tests/integration/k2p03_b12_shared_revision_pure.php
  tests/integration/k2p03_b12_two_actor_revision_pure.php
  tests/integration/k2p03_b12_form_tokens_pure.php
  tests/integration/k2p03_b12_form_controls_pure.php
  tests/integration/k2p03_b12_create_guard_pure.php
  tests/integration/k2p03_b12_reader_pure.php
  tests/integration/k2p03_b12_api_adapter_pure.php
  tests/integration/k2p03_b12_save_gate_pure.php
  tests/integration/k2p03_b12_persistence_pure.php
  tests/integration/k2p03_b12_continuity_revision_pure.php
  tests/integration/k2p03_b12_continuity_apply_pure.php
  tests/integration/k2p03_b12_save_pipeline_pure.php
  tests/integration/k2p03_b12_staged_routes_pure.php
  tests/integration/k2p03_b12_route_adapter_pure.php
  tests/integration/k2p03_b12_delete_source_lock_pure.php
  tests/integration/k2p03_b12_delete_pipeline_pure.php
  tests/integration/k2p03_b12_delete_continuity_pure.php
  tests/integration/k2p03_b12_parent_lock_pure.php
  tests/integration/k2p03_b12_delete_staged_source_pure.php
  tests/integration/k2p03_b12_delete_browser_source.php
  tests/integration/k2p03_b12_ruminant_delete_http_pure.php
  tests/integration/k2p03_b12_ruminant_delete_http_source.php
  tests/integration/k2p03_b12_page_switch_contract.php
  tests/integration/k2p03_b12_thin_page_adapters_source.php
  tests/integration/k2p03_b12_page_delete_owner_source.php
  tests/integration/k2p03_b12_page_asset_plan_pure.php
  tests/integration/k2p03_b12_page_bootstrap_source.php
  tests/integration/k2p03_b12_page_fields_parity_source.php
  tests/integration/k2p03_b12_cycle_seed_pure.php
  tests/integration/k2p03_b12_cycle_seed_staging_source.php
  tests/integration/k2p03_b12_reader_endpoint_source.php
)
NODE_TESTS=(
  tests/integration/k2p03_b12_modal_async_tokens.test.cjs
  tests/integration/k2p03_b12_scope_exact_modal.test.cjs
  tests/integration/k2p03_b12_editor_bridge.test.cjs
  tests/integration/k2p03_b12_reader_transport.test.cjs
  tests/integration/k2p03_b12_submit_transport.test.cjs
  tests/integration/k2p03_b12_workspace_composition.test.cjs
  tests/integration/k2p03_b12_delete_browser_transport.test.cjs
  tests/integration/k2p03_b12_ruminant_delete_browser.test.cjs
  tests/integration/k2p03_b12_page_lifecycle.test.cjs
  tests/integration/k2p03_b12_thin_page_adapters.test.cjs
  tests/integration/k2p03_b12_page_fields_parity.test.cjs
  tests/integration/k2p03_b12_page_delete_ownership.test.cjs
  tests/integration/k2p03_b12_page_bootstrap.test.cjs
)
for file in "${LINT_FILES[@]}" "${PHP_TESTS[@]}" "${NODE_TESTS[@]}"; do
  [ -f "$file" ] && [ ! -L "$file" ] || stop 'MANIFEST_FILE_MISSING_OR_LINKED'
  case "$file" in
    *real_mariadb*|*.sql|*/migrations/*) stop 'DATABASE_TEST_IN_MANIFEST' ;;
  esac
done
echo "PHP_LINT_MANIFEST_COUNT=${#LINT_FILES[@]}"
echo "PHP_TEST_MANIFEST_COUNT=${#PHP_TESTS[@]}"
echo "NODE_TEST_MANIFEST_COUNT=${#NODE_TESTS[@]}"

PASSED_LINT=0
PASSED_PHP=0
PASSED_NODE=0
FAILED=0
run_case() {
  local category="$1" file="$2" result
  shift 2
  if result="$("$@" 2>&1)"; then
    case "$category" in
      LINT) PASSED_LINT=$((PASSED_LINT+1)) ;;
      PHP) PASSED_PHP=$((PASSED_PHP+1)) ;;
      NODE) PASSED_NODE=$((PASSED_NODE+1)) ;;
    esac
  else
    FAILED=$((FAILED+1))
    echo "FAILED_CATEGORY=$category"
    echo "FAILED_FILE=$file"
    echo "FAIL_DETAIL=$(printf '%s\n' "$result" | tail -n 2 | cut -c1-160 | tr '\r\n' ' ')"
    echo "RESULT=FAIL_CLOSED"
    echo 'DATABASE_TESTS=NOT_RUN'
    echo 'APPLICATION_MIGRATIONS=NOT_RUN'
    echo 'RC01_RUNTIME_TESTS=0'
    echo 'B12_CUTOVER=OFF'
    echo '#####COPY TO HERE#####'
    exit 1
  fi
}
for file in "${LINT_FILES[@]}"; do run_case LINT "$file" php -l "$file"; done
for file in "${PHP_TESTS[@]}"; do run_case PHP "$file" php "$file"; done
for file in "${NODE_TESTS[@]}"; do run_case NODE "$file" node "$file"; done
[ -z "$(git status --porcelain --untracked-files=normal)" ] || stop 'WORKTREE_CHANGED'
echo "PHP_LINT_PASS_COUNT=$PASSED_LINT"
echo "PHP_TEST_PASS_COUNT=$PASSED_PHP"
echo "NODE_TEST_PASS_COUNT=$PASSED_NODE"
echo "TOTAL_PASS_COUNT=$((PASSED_LINT+PASSED_PHP+PASSED_NODE))"
echo "TEST_FAIL_COUNT=$FAILED"
echo 'SOURCE_WORKTREE=UNCHANGED'
echo 'DATABASE_TESTS=NOT_RUN'
echo 'APPLICATION_MIGRATIONS=NOT_RUN'
echo 'RC01_RUNTIME_TESTS=0'
echo 'B12_CUTOVER=OFF'
echo 'B12_RACE_CERTIFICATION=NOT_PERFORMED'
echo 'RESULT=WS07_CONSOLIDATED_SOURCE_ONLY_PASS'
echo '#####COPY TO HERE#####'
