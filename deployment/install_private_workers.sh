#!/bin/sh

set -eu

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
REPO_ROOT="$(CDPATH= cd -- "$SCRIPT_DIR/.." && pwd)"

HOME_DIR="${HOME:?HOME is unavailable}"

APP_ROOT="${RENEE_APP_ROOT:-$HOME_DIR/public_html}"
PRIVATE_ROOT="${RENEE_PRIVATE_ROOT:-$HOME_DIR/renee-private}"
QA_LOG_ROOT="${RENEE_QA_LOG_ROOT:-$HOME_DIR/renee-qa-logs}"

if [ ! -d "$APP_ROOT" ]; then
    echo "FAIL: application root does not exist: $APP_ROOT" >&2
    exit 1
fi

if [ ! -f "$APP_ROOT/.htaccess" ]; then
    echo "FAIL: production .htaccess is unavailable." >&2
    exit 1
fi

mkdir -p \
    "$PRIVATE_ROOT/v310-credential-worker" \
    "$PRIVATE_ROOT/v320-subscription-lifecycle" \
    "$PRIVATE_ROOT/v320-subscription-reminder" \
    "$PRIVATE_ROOT/v320-monitoring" \
    "$QA_LOG_ROOT"

chmod 700 \
    "$PRIVATE_ROOT" \
    "$PRIVATE_ROOT/v310-credential-worker" \
    "$PRIVATE_ROOT/v320-subscription-lifecycle" \
    "$PRIVATE_ROOT/v320-subscription-reminder" \
    "$PRIVATE_ROOT/v320-monitoring" \
    "$QA_LOG_ROOT"

#
# Credential worker — canonical repository source.
#

install -m 600 \
    "$REPO_ROOT/scripts/run_v310_account_credential_outbox.php" \
    "$PRIVATE_ROOT/v310-credential-worker/run_v310_account_credential_outbox.php"

install -m 700 \
    "$REPO_ROOT/scripts/run_v310_credential_worker_production.sh" \
    "$PRIVATE_ROOT/v310-credential-worker/run_v310_credential_worker_production.sh"

install -m 600 \
    "$REPO_ROOT/scripts/v310_private_cli_bridge.php" \
    "$PRIVATE_ROOT/v310-credential-worker/v310_private_cli_bridge.php"

#
# Subscription lifecycle private wrapper + launcher.
#

install -m 600 \
    "$REPO_ROOT/deployment/private-workers/v320-subscription-lifecycle/run_v320_subscription_lifecycle.php" \
    "$PRIVATE_ROOT/v320-subscription-lifecycle/run_v320_subscription_lifecycle.php"

install -m 700 \
    "$REPO_ROOT/deployment/private-workers/v320-subscription-lifecycle/run_v320_subscription_lifecycle_production.sh" \
    "$PRIVATE_ROOT/v320-subscription-lifecycle/run_v320_subscription_lifecycle_production.sh"

#
# Renewal reminder private wrapper + launcher.
#

install -m 600 \
    "$REPO_ROOT/deployment/private-workers/v320-subscription-reminder/run_v320_subscription_renewal_reminders.php" \
    "$PRIVATE_ROOT/v320-subscription-reminder/run_v320_subscription_renewal_reminders.php"

install -m 700 \
    "$REPO_ROOT/deployment/private-workers/v320-subscription-reminder/run_v320_subscription_renewal_reminders_production.sh" \
    "$PRIVATE_ROOT/v320-subscription-reminder/run_v320_subscription_renewal_reminders_production.sh"

#
# Availability monitor — canonical repository source.
#

install -m 700 \
    "$REPO_ROOT/deployment/private-workers/v320-monitoring/run_availability_monitor.sh" \
    "$PRIVATE_ROOT/v320-monitoring/run_availability_monitor.sh"

install -m 700 \
    "$REPO_ROOT/deployment/private-workers/v320-monitoring/send_monitoring_alert.php" \
    "$PRIVATE_ROOT/v320-monitoring/send_monitoring_alert.php"

echo "PASS: Renee AgriSuite private workers installed."
echo "APP_ROOT=$APP_ROOT"
echo "PRIVATE_ROOT=$PRIVATE_ROOT"
echo "QA_LOG_ROOT=$QA_LOG_ROOT"
