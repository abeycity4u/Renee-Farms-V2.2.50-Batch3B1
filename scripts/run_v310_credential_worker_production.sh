#!/bin/sh

set -u

umask 077

SCRIPT_DIR=$(
    CDPATH= cd -- "$(dirname -- "$0")" 2>/dev/null \
        && pwd
)

if [ -z "${SCRIPT_DIR:-}" ]; then
    exit 1
fi

WORKER="$SCRIPT_DIR/run_v310_account_credential_outbox.php"

PHP_BIN="${RENEE_CREDENTIAL_PHP_BIN:-/usr/local/bin/php}"
APP_ROOT="${RENEE_CREDENTIAL_APP_ROOT:-}"
AUTHORITY_SOURCE="${RENEE_CREDENTIAL_ENV_SOURCE:-}"

LOG_FILE="$SCRIPT_DIR/worker.log"
ROTATED_LOG="$SCRIPT_DIR/worker.log.1"
LOCK_FILE="$SCRIPT_DIR/production-launcher.lock"

MAX_LOG_BYTES=1048576
MAX_JOBS=25

MODE="dry-run"

if [ "${1:-}" = "--send" ]; then
    MODE="send"
elif [ -n "${1:-}" ]; then
    exit 1
fi

if [ ! -x "$PHP_BIN" ]; then
    exit 1
fi

if [ ! -f "$WORKER" ]; then
    exit 1
fi

if [ -z "$APP_ROOT" ] || [ ! -d "$APP_ROOT" ]; then
    exit 1
fi

if [ -z "$AUTHORITY_SOURCE" ] || [ ! -r "$AUTHORITY_SOURCE" ]; then
    exit 1
fi

if ! command -v flock >/dev/null 2>&1; then
    exit 1
fi

# Protect rotation and invocation as one local operation.
exec 9>"$LOCK_FILE"

if ! flock -n 9; then
    # Another launcher invocation is already handling the worker.
    exit 0
fi

rotate_log_if_needed()
{
    if [ ! -f "$LOG_FILE" ]; then
        return 0
    fi

    SIZE=$(
        wc -c < "$LOG_FILE" 2>/dev/null \
            | tr -d '[:space:]'
    )

    case "$SIZE" in
        ''|*[!0-9]*)
            return 1
            ;;
    esac

    if [ "$SIZE" -ge "$MAX_LOG_BYTES" ]; then
        rm -f "$ROTATED_LOG"

        if ! mv "$LOG_FILE" "$ROTATED_LOG"; then
            return 1
        fi
    fi

    return 0
}

if ! rotate_log_if_needed; then
    exit 1
fi

touch "$LOG_FILE" || exit 1
chmod 600 "$LOG_FILE" || exit 1

{
    printf '%s\n' \
        "=== credential worker invocation $(date -u '+%Y-%m-%dT%H:%M:%SZ') ==="

    if [ "$MODE" = "send" ]; then
        printf '%s\n' "Launcher mode: SEND"

        env \
            RENEE_CREDENTIAL_APP_ROOT="$APP_ROOT" \
            RENEE_CREDENTIAL_ENV_SOURCE="$AUTHORITY_SOURCE" \
            "$PHP_BIN" \
            "$WORKER" \
            --send \
            --max="$MAX_JOBS"

        STATUS=$?
    else
        printf '%s\n' "Launcher mode: DRY-RUN"

        env \
            RENEE_CREDENTIAL_APP_ROOT="$APP_ROOT" \
            RENEE_CREDENTIAL_ENV_SOURCE="$AUTHORITY_SOURCE" \
            "$PHP_BIN" \
            "$WORKER"

        STATUS=$?
    fi

    printf '%s\n' \
        "Launcher exit status: $STATUS"

    printf '%s\n' \
        "=== credential worker invocation complete ==="

    exit "$STATUS"
} >>"$LOG_FILE" 2>&1
