#!/usr/bin/env bash
# Renee AgriSuite v3.2 GA regression runner.
# Non-destructive by default. Environment/DB-dependent checks are opt-in.

set +e
set +H
export GIT_PAGER=cat
export PAGER=cat

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT" || exit 2

FAIL=0
RUN=0
SKIP=0

run_check() {
    local label="$1"
    shift
    RUN=$((RUN + 1))
    echo "=== ${label} ==="
    "$@"
    local status=$?
    if [ "$status" -eq 0 ]; then
        echo "PASS: ${label}"
    else
        echo "FAIL: ${label} status=${status}"
        FAIL=$((FAIL + 1))
    fi
    echo
}

run_php_verifier_if_present() {
    local file="$1"
    local label="$2"
    if [ -f "$file" ]; then
        run_check "$label" php "$file"
    else
        SKIP=$((SKIP + 1))
        echo "SKIP: ${label} (${file} not present)"
        echo
    fi
}

lint_all_php() {
    local lint_fail=0
    while IFS= read -r -d '' file; do
        php -l "$file" >/dev/null 2>&1
        if [ $? -ne 0 ]; then
            echo "PHP_LINT_FAIL=${file#./}"
            lint_fail=$((lint_fail + 1))
        fi
    done < <(
        find . -type f -name '*.php' \
            -not -path './vendor/*' \
            -not -path './uploads/*' \
            -not -path './logs/*' \
            -print0
    )

    echo "PHP_LINT_FAILURES=${lint_fail}"
    [ "$lint_fail" -eq 0 ]
}

run_check "PHP syntax lint" lint_all_php
run_check "GA source security scan" php scripts/ga_source_security_scan.php
run_php_verifier_if_present \
    scripts/verify_v320_password_session_invalidation.php \
    "Password session invalidation contract"
run_php_verifier_if_present \
    scripts/verify_v320_login_security_contract.php \
    "Login security contract"

# Existing closed-workstream verifiers included here only when they are source/static
# contracts. Destructive billing, migration, provisioning and live-state certification
# are intentionally not part of the default GA runner.
run_php_verifier_if_present \
    scripts/verify_v320_navigation_permission_parity.php \
    "Navigation permission parity"
run_php_verifier_if_present \
    scripts/verify_v320_login_password_recovery_ux.php \
    "Login password recovery entry"
run_php_verifier_if_present \
    scripts/verify_v320_password_recovery_confirmation_ux.php \
    "Password recovery confirmation"
run_php_verifier_if_present \
    scripts/verify_v320_credential_email_html_ux.php \
    "Credential email HTML contract"
run_php_verifier_if_present \
    scripts/verify_v320_credential_email_mobile_polish.php \
    "Credential email mobile contract"
run_php_verifier_if_present \
    scripts/verify_v320_inventory_category_list_ux.php \
    "Inventory category UX contract"

if command -v composer >/dev/null 2>&1; then
    run_check "Composer manifest validation" composer validate --no-check-publish --no-interaction
else
    SKIP=$((SKIP + 1))
    echo "SKIP: Composer manifest validation (composer unavailable)"
    echo
fi

if [ "${GA_RUN_DB:-0}" = "1" ]; then
    echo "GA_RUN_DB=1 supplied, but DB/live-state suites remain intentionally explicit."
    echo "Use the documented staging certification matrix rather than auto-running historical/destructive verifiers."
else
    SKIP=$((SKIP + 1))
    echo "SKIP: environment/DB integration suite (set up and run only against isolated staging)"
fi

echo
echo "GA_REGRESSION_RUN=${RUN}"
echo "GA_REGRESSION_SKIPPED=${SKIP}"
echo "GA_REGRESSION_FAILURES=${FAIL}"

if [ "$FAIL" -eq 0 ]; then
    echo "GA_REGRESSION=PASS"
    exit 0
fi

echo "GA_REGRESSION=FAIL"
exit "$FAIL"
