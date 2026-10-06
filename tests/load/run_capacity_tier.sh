#!/bin/sh

FAIL=0

HARNESS_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
HARNESS="$HARNESS_DIR/k6_capacity_single_tier.js"

BASE_URL="${K6_BASE_URL:-}"
ALLOW="${K6_ALLOW_ISOLATED_CAPACITY:-}"
TARGET_CLASS="${K6_CAPACITY_TARGET_CLASS:-}"
VUS="${K6_CAPACITY_VUS:-}"
DURATION="${K6_CAPACITY_DURATION:-2m}"
EVIDENCE_DIR="${K6_CAPACITY_EVIDENCE_DIR:-}"

case "$VUS" in
    25|50|75|100|125|150|200)
        ;;
    *)
        echo "CAPACITY_TIER_GUARD=FAIL"
        echo "REASON=K6_CAPACITY_VUS must be one of 25,50,75,100,125,150,200"
        exit 1
        ;;
esac

if [ "$ALLOW" != "YES" ]; then
    echo "EXECUTION_AUTHORIZATION=FAIL"
    echo "REASON=K6_ALLOW_ISOLATED_CAPACITY must equal YES"
    exit 1
fi

if [ "$TARGET_CLASS" != "isolated" ]; then
    echo "TARGET_CLASS_GUARD=FAIL"
    echo "REASON=K6_CAPACITY_TARGET_CLASS must equal isolated"
    exit 1
fi

BASE_URL_LC="$(printf '%s\n' "$BASE_URL" | tr '[:upper:]' '[:lower:]')"

case "$BASE_URL_LC" in
    https://*)
        ;;
    *)
        echo "TARGET_HTTPS_GUARD=FAIL"
        echo "REASON=K6_BASE_URL must be an isolated HTTPS target"
        exit 1
        ;;
esac

TARGET_AUTHORITY="$(
    printf '%s\n' "$BASE_URL_LC" |
    sed -n 's#^https://\([^/?#]*\).*$#\1#p'
)"

TARGET_HOST="${TARGET_AUTHORITY%%:*}"

case "$TARGET_HOST" in
    reneefarms.com|www.reneefarms.com|staging.reneefarms.com)
        echo "TARGET_SAFETY_GUARD=FAIL"
        echo "REASON=production/shared-host staging target prohibited"
        exit 1
        ;;
esac

if [ -z "${K6_SESSION_COOKIES:-}" ]; then
    echo "SESSION_GUARD=FAIL"
    echo "REASON=K6_SESSION_COOKIES is required"
    exit 1
fi

case "$K6_SESSION_COOKIES" in
    *,*)
        ;;
    *)
        echo "SESSION_GUARD=FAIL"
        echo "REASON=at least two independent session cookies are required"
        exit 1
        ;;
esac

if [ -z "$EVIDENCE_DIR" ]; then
    echo "EVIDENCE_DIR_GUARD=FAIL"
    echo "REASON=K6_CAPACITY_EVIDENCE_DIR is required"
    exit 1
fi

if ! command -v k6 >/dev/null 2>&1; then
    echo "K6_AVAILABLE=NO"
    exit 1
fi

if ! command -v curl >/dev/null 2>&1; then
    echo "CURL_AVAILABLE=NO"
    exit 1
fi

if [ ! -f "$HARNESS" ]; then
    echo "HARNESS_PRESENT=NO"
    exit 1
fi

mkdir -p "$EVIDENCE_DIR" || exit 1

STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
RUN_ID="capacity-${VUS}vu-${STAMP}"

RUN_LOG="$EVIDENCE_DIR/${RUN_ID}.log"
SUMMARY_JSON="$EVIDENCE_DIR/${RUN_ID}-summary.json"
BASELINE_BEFORE="$EVIDENCE_DIR/${RUN_ID}-baseline-before.txt"
BASELINE_AFTER="$EVIDENCE_DIR/${RUN_ID}-baseline-after.txt"

echo "CAPACITY_RUN_ID=$RUN_ID"
echo "TARGET_CLASS=$TARGET_CLASS"
echo "CAPACITY_TIER_VUS=$VUS"
echo "HOLD_DURATION=$DURATION"
echo "K6_VERSION=$(k6 version 2>/dev/null | head -1)"
echo "EXECUTION_AUTHORIZATION=PASS"
echo "TARGET_SAFETY_GUARD=PASS"
echo "SESSION_GUARD=PASS"

echo "=== PRE-RUN BASELINE ==="

BASELINE_OUTPUT="$(
  curl -sS \
    --connect-timeout 10 \
    --max-time 20 \
    -o /dev/null \
    -w 'HTTP_CODE=%{http_code} TTFB=%{time_starttransfer} TOTAL=%{time_total}\\n' \
    "${BASE_URL%/}/sign.php"
)"
BASELINE_RC=$?

printf '%s\n' "$BASELINE_OUTPUT" | tee "$BASELINE_BEFORE"

if [ "$BASELINE_RC" -ne 0 ]; then
    echo "PRE_RUN_BASELINE=FAIL"
    echo "REASON=baseline transport failure"
    exit 1
fi

BASELINE_HTTP_CODE="$(
    printf '%s\n' "$BASELINE_OUTPUT" |
    sed -n 's/.*HTTP_CODE=\([0-9][0-9][0-9]\).*/\1/p'
)"

case "$BASELINE_HTTP_CODE" in
    2??|3??)
        echo "PRE_RUN_BASELINE=PASS"
        ;;
    *)
        echo "PRE_RUN_BASELINE=FAIL"
        echo "REASON=baseline HTTP status is not 2xx/3xx"
        exit 1
        ;;
esac

echo "=== K6 SINGLE-TIER EXECUTION ==="

k6 run \
  --summary-export "$SUMMARY_JSON" \
  "$HARNESS" >"$RUN_LOG" 2>&1

K6_RC=$?

cat "$RUN_LOG"

echo "K6_EXIT_CODE=$K6_RC"

echo "=== POST-RUN RECOVERY BASELINE ==="

sleep 15

POST_OUTPUT="$(
  curl -sS \
    --connect-timeout 10 \
    --max-time 20 \
    -o /dev/null \
    -w 'HTTP_CODE=%{http_code} TTFB=%{time_starttransfer} TOTAL=%{time_total}\\n' \
    "${BASE_URL%/}/sign.php"
)"
POST_RC=$?

printf '%s\n' "$POST_OUTPUT" | tee "$BASELINE_AFTER"

if [ "$POST_RC" -ne 0 ]; then
    echo "POST_RUN_BASELINE=FAIL"
    echo "REASON=post-run baseline transport failure"
    FAIL=1
else
    POST_HTTP_CODE="$(
        printf '%s\n' "$POST_OUTPUT" |
        sed -n 's/.*HTTP_CODE=\([0-9][0-9][0-9]\).*/\1/p'
    )"

    case "$POST_HTTP_CODE" in
        2??|3??)
            echo "POST_RUN_BASELINE=PASS"
            ;;
        *)
            echo "POST_RUN_BASELINE=FAIL"
            echo "REASON=post-run baseline HTTP status is not 2xx/3xx"
            FAIL=1
            ;;
    esac
fi

if [ -s "$RUN_LOG" ]; then
    echo "RUN_LOG=PASS"
else
    echo "RUN_LOG=FAIL"
    FAIL=1
fi

if [ -s "$SUMMARY_JSON" ]; then
    echo "SUMMARY_JSON=PASS"
else
    echo "SUMMARY_JSON=FAIL"
    FAIL=1
fi

if [ "$K6_RC" -eq 0 ]; then
    echo "TIER_RESULT=STABLE_CANDIDATE"
else
    echo "TIER_RESULT=THRESHOLD_OR_EXECUTION_FAILURE"
    FAIL=1
fi

echo "NEXT_TIER_AUTOMATIC=NO"
echo "MANUAL_REVIEW_REQUIRED=YES"
echo "FINAL_FAIL_COUNT=$FAIL"

exit "$FAIL"
