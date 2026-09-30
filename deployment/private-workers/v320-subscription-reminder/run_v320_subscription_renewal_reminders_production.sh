#!/bin/sh

set -u

HOME_DIR="${HOME:?HOME is unavailable}"

APP_ROOT="${RENEE_APP_ROOT:-$HOME_DIR/public_html}"
PRIVATE_ROOT="${RENEE_PRIVATE_ROOT:-$HOME_DIR/renee-private}"
ENV_SOURCE="${RENEE_ENV_SOURCE:-$APP_ROOT/.htaccess}"

if [ -n "${RENEE_PHP_BIN:-}" ]; then
    PHP_BIN="$RENEE_PHP_BIN"
elif [ -x /usr/local/bin/php ]; then
    PHP_BIN="/usr/local/bin/php"
else
    PHP_BIN="$(command -v php 2>/dev/null || true)"
fi

PRIVATE_RUNNER="$PRIVATE_ROOT/v320-subscription-reminder/run_v320_subscription_renewal_reminders.php"

LOCK_FILE="$PRIVATE_ROOT/v320-subscription-reminder/reminder-worker.lock"

SEND=0

if [ "${1:-}" = "--send" ]; then
    SEND=1
elif [ "${1:-}" != "" ]; then
    echo "FAIL: unsupported argument." >&2
    exit 1
fi

if [ -z "$PHP_BIN" ] || [ ! -x "$PHP_BIN" ]; then
    echo "FAIL: PHP CLI is unavailable." >&2
    exit 1
fi

if [ ! -d "$APP_ROOT" ]; then
    echo "FAIL: application root is unavailable." >&2
    exit 1
fi

if [ ! -f "$ENV_SOURCE" ]; then
    echo "FAIL: environment authority is unavailable." >&2
    exit 1
fi

if [ ! -f "$PRIVATE_RUNNER" ]; then
    echo "FAIL: private reminder runner is unavailable." >&2
    exit 1
fi

exec 9>"$LOCK_FILE"

if ! flock -n 9; then
    echo "PASS: another subscription reminder worker is already running."
    exit 0
fi

export RENEE_APP_ROOT="$APP_ROOT"
export RENEE_PRIVATE_ROOT="$PRIVATE_ROOT"
export RENEE_ENV_SOURCE="$ENV_SOURCE"
export RENEE_PHP_BIN="$PHP_BIN"

if [ "$SEND" -eq 1 ]; then
    exec "$PHP_BIN" "$PRIVATE_RUNNER" --send
fi

exec "$PHP_BIN" "$PRIVATE_RUNNER"
