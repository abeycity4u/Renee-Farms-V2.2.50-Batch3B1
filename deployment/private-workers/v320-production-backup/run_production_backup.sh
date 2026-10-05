#!/bin/sh

umask 077

HOME_DIR="${HOME:?HOME is unavailable}"

PROD_ROOT="${RENEE_APP_ROOT:-$HOME_DIR/public_html}"
LOCAL_ROOT="${RENEE_BACKUP_ROOT:-$HOME_DIR/renee-production-backups}"
PRIVATE_ROOT="${RENEE_PRIVATE_ROOT:-$HOME_DIR/renee-private}"
B2_ENV="${RENEE_B2_ENV:-$PRIVATE_ROOT/b2-backup/credentials.env}"

PHP_BIN="/usr/local/bin/php"
MARIADB_DUMP="/bin/mariadb-dump"
CURL="/bin/curl"
TAR="/bin/tar"
GZIP="/bin/gzip"
SHA256="/usr/bin/sha256sum"

fail() {
    echo "BACKUP_STATUS=FAIL"
    echo "BACKUP_FAILURE_STAGE=$1"
    exit 1
}

[ -d "$PROD_ROOT" ] || fail "production_root_missing"
[ -f "$PROD_ROOT/.htaccess" ] || fail "production_env_missing"
[ -f "$B2_ENV" ] || fail "b2_credentials_missing"

DB_HOST="$(
    sed -nE \
      's/^[[:space:]]*SetEnv[[:space:]]+DB_HOST[[:space:]]+"?([^"]+)"?[[:space:]]*$/\1/p' \
      "$PROD_ROOT/.htaccess" |
    tail -1
)"

DB_USER="$(
    sed -nE \
      's/^[[:space:]]*SetEnv[[:space:]]+DB_USER[[:space:]]+"?([^"]+)"?[[:space:]]*$/\1/p' \
      "$PROD_ROOT/.htaccess" |
    tail -1
)"

DB_PASS="$(
    sed -nE \
      's/^[[:space:]]*SetEnv[[:space:]]+DB_PASS[[:space:]]+"?([^"]*)"?[[:space:]]*$/\1/p' \
      "$PROD_ROOT/.htaccess" |
    tail -1
)"

DB_NAME="$(
    sed -nE \
      's/^[[:space:]]*SetEnv[[:space:]]+DB_NAME[[:space:]]+"?([^"]+)"?[[:space:]]*$/\1/p' \
      "$PROD_ROOT/.htaccess" |
    tail -1
)"

[ -n "$DB_HOST" ] || fail "db_host_missing"
[ -n "$DB_USER" ] || fail "db_user_missing"
[ -n "$DB_PASS" ] || fail "db_password_missing"
[ -n "$DB_NAME" ] || fail "db_name_missing"

[ "$DB_NAME" = "renee_testdb" ] || fail "unexpected_production_database"

. "$B2_ENV"

[ -n "${B2_KEY_ID:-}" ] || fail "b2_key_id_missing"
[ -n "${B2_APPLICATION_KEY:-}" ] || fail "b2_application_key_missing"
[ -n "${B2_BUCKET:-}" ] || fail "b2_bucket_missing"
[ -n "${B2_PREFIX:-}" ] || fail "b2_prefix_missing"

STAMP="$(date -u '+%Y%m%dT%H%M%SZ')"
RESTORE_ID="production-$STAMP"
WORK="$LOCAL_ROOT/.work-$RESTORE_ID"
FINAL="$LOCAL_ROOT/$RESTORE_ID"

mkdir -p "$LOCAL_ROOT" || fail "local_root_create"
mkdir -p "$WORK" || fail "work_create"

cleanup() {
    rm -rf "$WORK"
}
trap cleanup EXIT HUP INT TERM

echo "BACKUP_RESTORE_ID=$RESTORE_ID"
echo "BACKUP_STARTED_UTC=$(date -u '+%Y-%m-%dT%H:%M:%SZ')"
echo "BACKUP_DATABASE=$DB_NAME"

MYSQL_PWD="$DB_PASS" \
"$MARIADB_DUMP" \
  --host="$DB_HOST" \
  --user="$DB_USER" \
  --single-transaction \
  --quick \
  --routines \
  --triggers \
  --events \
  --hex-blob \
  "$DB_NAME" |
"$GZIP" -c > "$WORK/database.sql.gz" ||
fail "database_dump"

[ -s "$WORK/database.sql.gz" ] || fail "database_dump_empty"

PROD_PARENT="$(dirname "$PROD_ROOT")"
PROD_NAME="$(basename "$PROD_ROOT")"

"$TAR" \
  -czf "$WORK/files.tgz" \
  -C "$PROD_PARENT" \
  "$PROD_NAME" ||
fail "file_archive"

[ -s "$WORK/files.tgz" ] || fail "file_archive_empty"

(
    cd "$WORK" || exit 1
    "$SHA256" database.sql.gz files.tgz > SHA256SUMS
) || fail "checksums"

cat > "$WORK/manifest.txt" <<EOF
RESTORE_ID=$RESTORE_ID
CREATED_UTC=$(date -u '+%Y-%m-%dT%H:%M:%SZ')
SOURCE_HOST=$(hostname)
SOURCE_ROOT=$PROD_ROOT
DATABASE_NAME=$DB_NAME
BACKUP_CLASS=FULL
RPO_POLICY_HOURS=6
EOF

AUTH_JSON="$WORK/.b2-auth.json"

AUTH_HTTP="$(
    "$CURL" -sS \
      -o "$AUTH_JSON" \
      -w '%{http_code}' \
      -u "${B2_KEY_ID}:${B2_APPLICATION_KEY}" \
      https://api.backblazeb2.com/b2api/v2/b2_authorize_account
)"

[ "$AUTH_HTTP" = "200" ] || fail "b2_authorize"

API_URL="$(
    "$PHP_BIN" -r '
        $j=json_decode(file_get_contents($argv[1]),true);
        echo $j["apiUrl"] ?? "";
    ' "$AUTH_JSON"
)"

AUTH_TOKEN="$(
    "$PHP_BIN" -r '
        $j=json_decode(file_get_contents($argv[1]),true);
        echo $j["authorizationToken"] ?? "";
    ' "$AUTH_JSON"
)"

BUCKET_ID="$(
    "$PHP_BIN" -r '
        $j=json_decode(file_get_contents($argv[1]),true);
        echo $j["allowed"]["bucketId"] ?? "";
    ' "$AUTH_JSON"
)"

AUTH_BUCKET="$(
    "$PHP_BIN" -r '
        $j=json_decode(file_get_contents($argv[1]),true);
        echo $j["allowed"]["bucketName"] ?? "";
    ' "$AUTH_JSON"
)"

AUTH_PREFIX="$(
    "$PHP_BIN" -r '
        $j=json_decode(file_get_contents($argv[1]),true);
        echo $j["allowed"]["namePrefix"] ?? "";
    ' "$AUTH_JSON"
)"

[ -n "$API_URL" ] || fail "b2_api_url"
[ -n "$AUTH_TOKEN" ] || fail "b2_auth_token"
[ -n "$BUCKET_ID" ] || fail "b2_bucket_id"
[ "$AUTH_BUCKET" = "$B2_BUCKET" ] || fail "b2_bucket_scope"
[ "$AUTH_PREFIX" = "$B2_PREFIX" ] || fail "b2_prefix_scope"

upload_file() {
    LOCAL_FILE="$1"
    REMOTE_FILE="$2"

    URL_JSON="$WORK/.upload-url.json"
    RESULT_JSON="$WORK/.upload-result.json"

    HTTP="$(
        "$CURL" -sS \
          -o "$URL_JSON" \
          -w '%{http_code}' \
          -H "Authorization: $AUTH_TOKEN" \
          -H "Content-Type: application/json" \
          -d "{\"bucketId\":\"$BUCKET_ID\"}" \
          "$API_URL/b2api/v2/b2_get_upload_url"
    )"

    [ "$HTTP" = "200" ] || return 1

    UPLOAD_URL="$(
        "$PHP_BIN" -r '
            $j=json_decode(file_get_contents($argv[1]),true);
            echo $j["uploadUrl"] ?? "";
        ' "$URL_JSON"
    )"

    UPLOAD_TOKEN="$(
        "$PHP_BIN" -r '
            $j=json_decode(file_get_contents($argv[1]),true);
            echo $j["authorizationToken"] ?? "";
        ' "$URL_JSON"
    )"

    [ -n "$UPLOAD_URL" ] || return 1
    [ -n "$UPLOAD_TOKEN" ] || return 1

    FILE_SHA1="$(sha1sum "$LOCAL_FILE" | awk '{print $1}')"

    HTTP="$(
        "$CURL" -sS \
          -o "$RESULT_JSON" \
          -w '%{http_code}' \
          -X POST \
          -H "Authorization: $UPLOAD_TOKEN" \
          -H "X-Bz-File-Name: $REMOTE_FILE" \
          -H "Content-Type: application/octet-stream" \
          -H "X-Bz-Content-Sha1: $FILE_SHA1" \
          --data-binary @"$LOCAL_FILE" \
          "$UPLOAD_URL"
    )"

    [ "$HTTP" = "200" ] || return 1

    RETURNED_NAME="$(
        "$PHP_BIN" -r '
            $j=json_decode(file_get_contents($argv[1]),true);
            echo $j["fileName"] ?? "";
        ' "$RESULT_JSON"
    )"

    [ "$RETURNED_NAME" = "$REMOTE_FILE" ]
}

REMOTE_BASE="${B2_PREFIX}${RESTORE_ID}/"

upload_file "$WORK/database.sql.gz" "${REMOTE_BASE}database.sql.gz" ||
fail "b2_upload_database"

upload_file "$WORK/files.tgz" "${REMOTE_BASE}files.tgz" ||
fail "b2_upload_files"

upload_file "$WORK/SHA256SUMS" "${REMOTE_BASE}SHA256SUMS" ||
fail "b2_upload_checksums"

upload_file "$WORK/manifest.txt" "${REMOTE_BASE}manifest.txt" ||
fail "b2_upload_manifest"

rm -f \
  "$WORK/.b2-auth.json" \
  "$WORK/.upload-url.json" \
  "$WORK/.upload-result.json"

mv "$WORK" "$FINAL" || fail "finalize_local_restore_point"

trap - EXIT HUP INT TERM

unset DB_PASS B2_KEY_ID B2_APPLICATION_KEY AUTH_TOKEN UPLOAD_TOKEN

echo "BACKUP_LOCAL_PATH=$FINAL"
echo "BACKUP_REMOTE_PREFIX=$REMOTE_BASE"
echo "BACKUP_COMPLETED_UTC=$(date -u '+%Y-%m-%dT%H:%M:%SZ')"
echo "BACKUP_STATUS=PASS"

exit 0
