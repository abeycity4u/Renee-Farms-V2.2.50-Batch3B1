#!/bin/sh

umask 077

HOME_DIR="${HOME:?HOME is unavailable}"
PRIVATE_ROOT="${RENEE_PRIVATE_ROOT:-$HOME_DIR/renee-private}"

BACKUP="$PRIVATE_ROOT/production-backup/run_production_backup.sh"
RETENTION="$PRIVATE_ROOT/production-backup/run_backup_retention.php"
PHP="/usr/local/bin/php"
LOCK="$PRIVATE_ROOT/production-backup/.backup-cycle.lock"

fail() {
    echo "BACKUP_CYCLE_STATUS=FAIL"
    echo "BACKUP_CYCLE_FAILURE_STAGE=$1"
    exit 1
}

[ -x "$BACKUP" ] || fail "backup_runner_missing"
[ -f "$RETENTION" ] || fail "retention_executor_missing"
[ -x "$PHP" ] || fail "php_missing"

if ! mkdir "$LOCK" 2>/dev/null; then
    echo "BACKUP_CYCLE_STATUS=SKIPPED"
    echo "BACKUP_CYCLE_REASON=ALREADY_RUNNING"
    exit 0
fi

cleanup() {
    rmdir "$LOCK" 2>/dev/null || true
}
trap cleanup EXIT HUP INT TERM

echo "BACKUP_CYCLE_STARTED_UTC=$(date -u '+%Y-%m-%dT%H:%M:%SZ')"

echo "=== BACKUP PHASE ==="

if /bin/sh "$BACKUP"; then
    echo "BACKUP_PHASE=PASS"
else
    echo "BACKUP_PHASE=FAIL"
    fail "backup"
fi

echo "=== RETENTION PHASE ==="

if "$PHP" "$RETENTION" --execute-delete; then
    echo "RETENTION_PHASE=PASS"
else
    echo "RETENTION_PHASE=FAIL"
    fail "retention"
fi

echo "BACKUP_CYCLE_COMPLETED_UTC=$(date -u '+%Y-%m-%dT%H:%M:%SZ')"
echo "BACKUP_CYCLE_STATUS=PASS"

exit 0
