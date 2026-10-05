#!/bin/bash

HOME="${HOME:-/home/renee}"

MON_DIR="$HOME/renee-private/v320-monitoring"
STATE_DIR="$MON_DIR/state"
MAILER="$MON_DIR/send_monitoring_alert.php"
PHP_BIN="/usr/local/bin/php"
CURL_BIN="/usr/bin/curl"

THRESHOLD_SECONDS="3.000"
MAX_TIME_SECONDS="8"

mkdir -p "$STATE_DIR"
chmod 700 "$STATE_DIR"

check_endpoint() {
    NAME="$1"
    URL="$2"

    RESULT="$(
        "$CURL_BIN" \
            -sS \
            -o /dev/null \
            --max-time "$MAX_TIME_SECONDS" \
            -w '%{http_code} %{time_total}' \
            "$URL" 2>/dev/null
    )"

    CURL_RC=$?

    if [ "$CURL_RC" -ne 0 ]; then
        HTTP="curl_error"
        LATENCY="$MAX_TIME_SECONDS"
        CURRENT="FAIL"
    else
        HTTP="$(printf '%s' "$RESULT" | awk '{print $1}')"
        LATENCY="$(printf '%s' "$RESULT" | awk '{print $2}')"

        if [ -z "$LATENCY" ]; then
            LATENCY="$MAX_TIME_SECONDS"
        fi

        SLOW="$(
            awk \
                -v actual="$LATENCY" \
                -v limit="$THRESHOLD_SECONDS" \
                'BEGIN { print (actual > limit) ? 1 : 0 }'
        )"

        if [ "$HTTP" = "200" ] && [ "$SLOW" -eq 0 ]; then
            CURRENT="OK"
        else
            CURRENT="FAIL"
        fi
    fi

    STATE_FILE="$STATE_DIR/${NAME}.state"

    if [ -f "$STATE_FILE" ]; then
        PREVIOUS="$(cat "$STATE_FILE" 2>/dev/null)"
    else
        PREVIOUS="UNKNOWN"
    fi

    ALERT="NONE"
    PERSIST_STATE=1

    if [ "$CURRENT" = "FAIL" ] && [ "$PREVIOUS" != "FAIL" ]; then
        if "$PHP_BIN" "$MAILER" \
            --endpoint="$NAME" \
            --state="FAIL" \
            --http="$HTTP" \
            --latency="$LATENCY" >/dev/null 2>&1
        then
            ALERT="FAILURE_SENT"
        else
            ALERT="FAILURE_SEND_FAILED"
            PERSIST_STATE=0
        fi
    elif [ "$CURRENT" = "OK" ] && [ "$PREVIOUS" = "FAIL" ]; then
        if "$PHP_BIN" "$MAILER" \
            --endpoint="$NAME" \
            --state="RECOVERED" \
            --http="$HTTP" \
            --latency="$LATENCY" >/dev/null 2>&1
        then
            ALERT="RECOVERY_SENT"
        else
            ALERT="RECOVERY_SEND_FAILED"
            PERSIST_STATE=0
        fi
    fi

    if [ "$PERSIST_STATE" -eq 1 ]; then
        printf '%s\n' "$CURRENT" > "$STATE_FILE"
        chmod 600 "$STATE_FILE"
    fi

    printf '%s endpoint=%s state=%s http=%s latency=%ss alert=%s\n' \
        "$(date '+%Y-%m-%dT%H:%M:%S%z')" \
        "$NAME" \
        "$CURRENT" \
        "$HTTP" \
        "$LATENCY" \
        "$ALERT"
}

check_endpoint \
    "production" \
    "https://reneefarms.com/sign.php"

check_endpoint \
    "staging" \
    "https://staging.reneefarms.com/sign.php"
