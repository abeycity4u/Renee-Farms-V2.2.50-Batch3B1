#!/bin/sh

set -eu

HOME_DIR="${HOME:?HOME is unavailable}"

APP_ROOT="${RENEE_APP_ROOT:-$HOME_DIR/public_html}"
PRIVATE_ROOT="${RENEE_PRIVATE_ROOT:-$HOME_DIR/renee-private}"
QA_LOG_ROOT="${RENEE_QA_LOG_ROOT:-$HOME_DIR/renee-qa-logs}"
ENV_SOURCE="${RENEE_ENV_SOURCE:-$APP_ROOT/.htaccess}"

if [ -n "${RENEE_PHP_BIN:-}" ]; then
    PHP_BIN="$RENEE_PHP_BIN"
elif [ -x /usr/local/bin/php ]; then
    PHP_BIN="/usr/local/bin/php"
else
    PHP_BIN="$(command -v php 2>/dev/null || true)"
fi

if [ -z "$PHP_BIN" ]; then
    echo "FAIL: PHP CLI could not be detected." >&2
    exit 1
fi

cat <<CRON
# Renee AgriSuite production cron definitions
#
# Review these paths before adding them in cPanel Cron Jobs.

0,5,10,15,20,25,30,35,40,45,50,55 * * * * /usr/bin/env RENEE_CREDENTIAL_APP_ROOT=$APP_ROOT RENEE_CREDENTIAL_ENV_SOURCE=$ENV_SOURCE RENEE_CREDENTIAL_PHP_BIN=$PHP_BIN /bin/sh $PRIVATE_ROOT/v310-credential-worker/run_v310_credential_worker_production.sh --send

17 * * * * /bin/sh $PRIVATE_ROOT/v320-subscription-lifecycle/run_v320_subscription_lifecycle_production.sh >> $QA_LOG_ROOT/subscription-lifecycle-worker.log 2>&1

15 4 * * * /bin/sh $PRIVATE_ROOT/v320-subscription-reminder/run_v320_subscription_renewal_reminders_production.sh --send >> $QA_LOG_ROOT/subscription-renewal-reminder-worker.log 2>&1

2-57/5 * * * * $PRIVATE_ROOT/v320-monitoring/run_availability_monitor.sh >> $QA_LOG_ROOT/v320-availability-monitor.log 2>&1

9,24,39,54 * * * * $PRIVATE_ROOT/v320-monitoring/run_backup_freshness_monitor.sh >> $QA_LOG_ROOT/v320-backup-freshness-monitor.log 2>&1

43 0,6,12,18 * * * /bin/sh $PRIVATE_ROOT/production-backup/run_production_backup_cycle.sh >> $QA_LOG_ROOT/v320-production-backup.log 2>&1
CRON
