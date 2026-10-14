#!/usr/bin/env bash
# B12 source-only verification of the hard-OFF branch.
# No application bootstrap, customer DB, network, migrations, or GitHub Actions.
# Execute ONLY from a disposable checkout of the named B12 source branch.
set -uo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT" || exit 1
echo '#####COPY FROM HERE#####'
echo 'B12_OFFLINE_VERIFIER=READONLY_SOURCE'
if ! command -v php >/dev/null 2>&1 || ! command -v node >/dev/null 2>&1; then
  echo 'B12_OFFLINE_RESULT=BLOCKED_MISSING_PHP_OR_NODE'
  echo '#####COPY TO HERE#####'
  exit 1
fi
if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  echo 'B12_OFFLINE_RESULT=BLOCKED_NOT_GIT_WORKTREE'
  echo '#####COPY TO HERE#####'
  exit 1
fi
BRANCH="$(git branch --show-current)"
HEAD="$(git rev-parse HEAD)"
echo "BRANCH=$BRANCH"
echo "HEAD=$HEAD"
if [ "$BRANCH" != "c137-gc24-k2p03-b12-cas-writer-cutover-source" ]; then
  echo 'B12_OFFLINE_RESULT=BLOCKED_WRONG_BRANCH'
  echo '#####COPY TO HERE#####'
  exit 1
fi
# A dirty checkout would make this evidence unrepeatable. This script never
# cleans, resets, fetches, or modifies files; it only refuses to run.
if [ -n "$(git status --porcelain --untracked-files=normal)" ]; then
  echo 'WORKTREE=DIRTY'
  echo 'B12_OFFLINE_RESULT=BLOCKED_DIRTY_SOURCE'
  echo '#####COPY TO HERE#####'
  exit 1
fi
echo 'WORKTREE=CLEAN'
# Refuse stale local source evidence; the caller may refresh refs separately.
# This runner makes no network requests itself.
REMOTE_REF='refs/remotes/origin/c137-gc24-k2p03-b12-cas-writer-cutover-source'
REMOTE_HEAD="$(git rev-parse --verify "$REMOTE_REF" 2>/dev/null || true)"
if [ -z "$REMOTE_HEAD" ] || [ "$HEAD" != "$REMOTE_HEAD" ]; then
  echo 'REMOTE_TRACKING_HEAD_MATCH=FAIL'
  echo 'B12_OFFLINE_RESULT=BLOCKED_SOURCE_NOT_SYNCHRONIZED'
  echo '#####COPY TO HERE#####'
  exit 1
fi
echo 'REMOTE_TRACKING_HEAD_MATCH=PASS'
if [ "$(php -r 'require "lib/daily_record_revision_cutover_gate.php"; echo daily_record_revision_cutover_enabled() ? "ON" : "OFF";' 2>/dev/null)" != 'OFF' ]; then
  echo 'B12_OFFLINE_RESULT=BLOCKED_GATE_NOT_OFF'
  echo '#####COPY TO HERE#####'
  exit 1
fi
echo 'CUTOVER_GATE=OFF'
PASS=0
FAIL=0
run_case() {
  local label="$1"
  shift
  local detail
  if detail="$("$@" 2>&1)"; then
    echo "$label=PASS"
    PASS=$((PASS + 1))
  else
    echo "$label=FAIL"
    FAIL=$((FAIL + 1))
    echo "FAIL_DETAIL=$(printf '%s\n' "$detail" | tail -n 2 | cut -c1-180 | tr '\r\n' ' ')"
  fi
}
for file in \
  lib/daily_record_revision_authority.php \
  lib/daily_record_revision_save_gate.php \
  lib/daily_record_revision_persistence.php \
  lib/daily_record_revision_save_pipeline.php \
  lib/daily_record_revision_route_adapter.php \
  lib/daily_record_revision_continuity.php \
  lib/daily_record_revision_continuity_apply.php \
  lib/daily_record_revision_delete_pipeline.php \
  lib/daily_record_revision_delete_http.php \
  lib/daily_record_revision_cycle_seed.php \
  lib/daily_record_revision_api_adapter.php \
  lib/daily_record_revision_cutover_gate.php \
  api/daily_record_revision_read.php \
  api/delete_record.php \
  lib/ruminant_daily_record_deletion.php \
  management/production_cycles.php \
  poultry/layers_daily_record.php \
  poultry/broiler_daily_record.php \
  ruminant/ruminant_daily_record.php \
  includes/permission_runtime.php
do
  label="$(basename "$file" .php | tr '[:lower:]-' '[:upper:]_')"
  run_case "PHP_LINT_$label" php -l "$file"
done
for file in \
  tests/integration/k2p03_b12_daily_writer_inventory.php \
  tests/integration/k2p03_b12_shared_revision_pure.php \
  tests/integration/k2p03_b12_form_tokens_pure.php \
  tests/integration/k2p03_b12_form_controls_pure.php \
  tests/integration/k2p03_b12_create_guard_pure.php \
  tests/integration/k2p03_b12_reader_pure.php \
  tests/integration/k2p03_b12_api_adapter_pure.php \
  tests/integration/k2p03_b12_save_gate_pure.php \
  tests/integration/k2p03_b12_persistence_pure.php \
  tests/integration/k2p03_b12_continuity_revision_pure.php \
  tests/integration/k2p03_b12_continuity_apply_pure.php \
  tests/integration/k2p03_b12_save_pipeline_pure.php \
  tests/integration/k2p03_b12_staged_routes_pure.php \
  tests/integration/k2p03_b12_route_adapter_pure.php \
  tests/integration/k2p03_b12_delete_source_lock_pure.php \
  tests/integration/k2p03_b12_delete_pipeline_pure.php \
  tests/integration/k2p03_b12_delete_staged_source_pure.php \
  tests/integration/k2p03_b12_delete_browser_source.php \
  tests/integration/k2p03_b12_ruminant_delete_http_pure.php \
  tests/integration/k2p03_b12_ruminant_delete_http_source.php \
  tests/integration/k2p03_b12_page_switch_contract.php \
  tests/integration/k2p03_b12_thin_page_adapters_source.php \
  tests/integration/k2p03_b12_page_fields_parity_source.php \
  tests/integration/k2p03_b12_cycle_seed_pure.php \
  tests/integration/k2p03_b12_cycle_seed_staging_source.php \
  tests/integration/k2p03_b12_reader_endpoint_source.php
do
  label="$(basename "$file" .php | tr '[:lower:]-' '[:upper:]_')"
  run_case "PHP_TEST_$label" php "$file"
done
for file in \
  tests/integration/k2p03_b12_modal_async_tokens.test.cjs \
  tests/integration/k2p03_b12_scope_exact_modal.test.cjs \
  tests/integration/k2p03_b12_editor_bridge.test.cjs \
  tests/integration/k2p03_b12_reader_transport.test.cjs \
  tests/integration/k2p03_b12_submit_transport.test.cjs \
  tests/integration/k2p03_b12_workspace_composition.test.cjs \
  tests/integration/k2p03_b12_delete_browser_transport.test.cjs \
  tests/integration/k2p03_b12_ruminant_delete_browser.test.cjs \
  tests/integration/k2p03_b12_page_lifecycle.test.cjs \
  tests/integration/k2p03_b12_thin_page_adapters.test.cjs \
  tests/integration/k2p03_b12_page_fields_parity.test.cjs \
  tests/integration/k2p03_b12_page_delete_ownership.test.cjs
do
  label="$(basename "$file" .test.cjs | tr '[:lower:]-' '[:upper:]_')"
  run_case "NODE_TEST_$label" node "$file"
done
echo "TEST_PASS_COUNT=$PASS"
echo "TEST_FAIL_COUNT=$FAIL"
echo 'DATABASE_QUERIES_EXECUTED=NO'
echo 'MIGRATIONS_EXECUTED=NO'
echo 'B12_RACE_CERTIFICATION=NOT_PERFORMED'
if [ "$FAIL" -ne 0 ]; then
  echo 'B12_OFFLINE_RESULT=FAIL_CLOSED'
  echo '#####COPY TO HERE#####'
  exit 1
fi
echo 'B12_OFFLINE_RESULT=SOURCE_ONLY_PASS'
echo '#####COPY TO HERE#####'
