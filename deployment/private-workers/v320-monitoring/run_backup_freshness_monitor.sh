#!/bin/bash

HOME_DIR="${HOME:?HOME is unavailable}"

PRIVATE_ROOT="${RENEE_PRIVATE_ROOT:-$HOME_DIR/renee-private}"
MON_DIR="$PRIVATE_ROOT/v320-monitoring"
STATE_DIR="$MON_DIR/state"
MAILER="$MON_DIR/send_monitoring_alert.php"

BACKUP_ROOT="${RENEE_BACKUP_ROOT:-$HOME_DIR/renee-production-backups}"

PHP_BIN="/usr/local/bin/php"
SHA256_BIN="/usr/bin/sha256sum"

THRESHOLD_HOURS="6"
STATE_FILE="$STATE_DIR/backup.state"

mkdir -p "$STATE_DIR"
chmod 700 "$STATE_DIR"

CURRENT="FAIL"
AGE_HOURS="unavailable"
RESTORE_ID="none"
INTEGRITY="FAIL"
INVALID_NEWER_COUNT=0

for CANDIDATE_ID in $(
    find "$BACKUP_ROOT" \
        -mindepth 1 \
        -maxdepth 1 \
        -type d \
        -name 'production-????????T??????Z' \
        -printf '%f\n' \
        2>/dev/null |
    sort -r
)
do
    CANDIDATE_PATH="$BACKUP_ROOT/$CANDIDATE_ID"

    if \
        [ -s "$CANDIDATE_PATH/database.sql.gz" ] &&
        [ -s "$CANDIDATE_PATH/files.tgz" ] &&
        [ -s "$CANDIDATE_PATH/SHA256SUMS" ] &&
        [ -s "$CANDIDATE_PATH/manifest.txt" ] &&
        grep -q "^RESTORE_ID=$CANDIDATE_ID$" \
            "$CANDIDATE_PATH/manifest.txt" &&
        grep -q '^DATABASE_NAME=renee_testdb$' \
            "$CANDIDATE_PATH/manifest.txt" &&
        grep -q '^RPO_POLICY_HOURS=6$' \
            "$CANDIDATE_PATH/manifest.txt" &&
        (
            cd "$CANDIDATE_PATH" &&
            "$SHA256_BIN" -c SHA256SUMS >/dev/null 2>&1
        )
    then
        RESTORE_ID="$CANDIDATE_ID"
        INTEGRITY="PASS"
        break
    fi

    INVALID_NEWER_COUNT="$((INVALID_NEWER_COUNT + 1))"
done

if [ "$INTEGRITY" = "PASS" ]; then
    STAMP="${RESTORE_ID#production-}"

    BACKUP_EPOCH="$(
        date -u -d \
            "${STAMP:0:4}-${STAMP:4:2}-${STAMP:6:2} ${STAMP:9:2}:${STAMP:11:2}:${STAMP:13:2}" \
            '+%s' \
            2>/dev/null
    )"

    NOW_EPOCH="$(date -u '+%s')"

    if [ -n "$BACKUP_EPOCH" ] &&
       [ "$BACKUP_EPOCH" -le "$NOW_EPOCH" ]; then
        AGE_SECONDS="$((NOW_EPOCH - BACKUP_EPOCH))"

        AGE_HOURS="$(
            awk \
                -v seconds="$AGE_SECONDS" \
                'BEGIN { printf "%.3f", seconds / 3600 }'
        )"

        STALE="$(
            awk \
                -v age="$AGE_HOURS" \
                -v limit="$THRESHOLD_HOURS" \
                'BEGIN { print (age > limit) ? 1 : 0 }'
        )"

        if [ "$STALE" -eq 0 ]; then
            CURRENT="OK"
        fi
    fi
fi

if [ -f "$STATE_FILE" ]; then
    PREVIOUS="$(cat "$STATE_FILE" 2>/dev/null)"
else
    PREVIOUS="UNKNOWN"
fi

ALERT="NONE"
PERSIST_STATE=1

if [ "$CURRENT" = "FAIL" ] && [ "$PREVIOUS" != "FAIL" ]; then
    if "$PHP_BIN" "$MAILER" \
        --endpoint="backup" \
        --state="FAIL" \
        --age_hours="$AGE_HOURS" \
        --threshold_hours="$THRESHOLD_HOURS" \
        >/dev/null 2>&1
    then
        ALERT="FAILURE_SENT"
    else
        ALERT="FAILURE_SEND_FAILED"
        PERSIST_STATE=0
    fi
elif [ "$CURRENT" = "OK" ] && [ "$PREVIOUS" = "FAIL" ]; then
    if "$PHP_BIN" "$MAILER" \
        --endpoint="backup" \
        --state="RECOVERED" \
        --age_hours="$AGE_HOURS" \
        --threshold_hours="$THRESHOLD_HOURS" \
        >/dev/null 2>&1
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

printf '%s endpoint=backup state=%s restore_id=%s age_hours=%s threshold_hours=%s integrity=%s invalid_newer=%s alert=%s\n' \
    "$(date '+%Y-%m-%dT%H:%M:%S%z')" \
    "$CURRENT" \
    "$RESTORE_ID" \
    "$AGE_HOURS" \
    "$THRESHOLD_HOURS" \
    "$INTEGRITY" \
    "$INVALID_NEWER_COUNT" \
    "$ALERT"
