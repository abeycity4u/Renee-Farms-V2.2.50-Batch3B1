#!/usr/bin/env bash

set -u
set -o pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
LIVE_ROOT="${RENEE_DEPLOY_LIVE:-/home/renee/public_html}"
BACKUP_ROOT="${RENEE_DEPLOY_BACKUP:-/home/renee/renee-backups}"
MODE="dry-run"
BASE_REF=""
FILES=()

say() {
    printf '%s\n' "$*"
}

die() {
    say "DEPLOY_STATUS=FAIL"
    say "DEPLOY_ERROR=$1"
    exit 1
}

usage() {
    cat <<'EOF'
Usage:
  deployment/deploy_runtime_files.sh --dry-run [--base-ref REF] FILE...
  deployment/deploy_runtime_files.sh --apply --base-ref REF FILE...

Rules:
  - Deployment is targeted only.
  - --apply requires an explicit --base-ref.
  - config.php and root .htaccess are protected.
  - scripts/, migrations/, deployment/ and development artifacts are rejected.
  - unrelated/live-only files are never deleted.
EOF
}

while [ "$#" -gt 0 ]; do
    case "$1" in
        --dry-run)
            MODE="dry-run"
            shift
            ;;
        --apply)
            MODE="apply"
            shift
            ;;
        --base-ref)
            [ "$#" -ge 2 ] || die "BASE_REF_VALUE_REQUIRED"
            BASE_REF="$2"
            shift 2
            ;;
        --help|-h)
            usage
            exit 0
            ;;
        --)
            shift
            while [ "$#" -gt 0 ]; do
                FILES+=("$1")
                shift
            done
            ;;
        -*)
            die "UNKNOWN_OPTION:$1"
            ;;
        *)
            FILES+=("$1")
            shift
            ;;
    esac
done

cd "$REPO_ROOT" || die "REPO_ROOT_UNAVAILABLE"

[ "${#FILES[@]}" -gt 0 ] || die "NO_TARGET_FILES"

if [ "$MODE" = "apply" ] && [ -z "$BASE_REF" ]; then
    die "APPLY_REQUIRES_BASE_REF"
fi

if [ -z "$BASE_REF" ]; then
    BASE_REF="HEAD^"
fi

git rev-parse --is-inside-work-tree >/dev/null 2>&1 \
    || die "NOT_A_GIT_WORKTREE"

git rev-parse --verify "${BASE_REF}^{commit}" >/dev/null 2>&1 \
    || die "INVALID_BASE_REF:$BASE_REF"

CURRENT_HEAD="$(git rev-parse HEAD)" || die "HEAD_UNAVAILABLE"
CURRENT_USER="$(id -un)" || die "USER_UNAVAILABLE"
CURRENT_GROUP="$(id -gn)" || die "GROUP_UNAVAILABLE"

if [ -n "$(git status --short)" ]; then
    die "WORKTREE_NOT_CLEAN"
fi

if ! git diff --check >/dev/null; then
    die "DIFF_CHECK_FAILED"
fi

case "$LIVE_ROOT" in
    /|""|"$REPO_ROOT")
        die "UNSAFE_LIVE_ROOT"
        ;;
esac

case "$BACKUP_ROOT" in
    /|""|"$LIVE_ROOT")
        die "UNSAFE_BACKUP_ROOT"
        ;;
esac

validate_relative_path() {
    local rel="$1"
    local part

    [ -n "$rel" ] || return 1

    case "$rel" in
        /*|.|..|../*|*/../*|*/..|./*|*//*)
            return 1
            ;;
    esac

    case "$rel" in
        *$'\n'*|*$'\r'*)
            return 1
            ;;
    esac

    IFS='/' read -r -a _parts <<< "$rel"

    for part in "${_parts[@]}"; do
        [ -n "$part" ] || return 1
        [ "$part" != "." ] || return 1
        [ "$part" != ".." ] || return 1
    done

    return 0
}

reject_non_runtime_path() {
    local rel="$1"
    local base

    base="$(basename "$rel")"

    case "$rel" in
        config.php|.htaccess)
            return 0
            ;;
        scripts/*|migrations/*|deployment/*|.github/*|tests/*|test/*)
            return 0
            ;;
    esac

    case "$base" in
        verify_*.php|apply_*.php|BATCH_NOTES.txt|RELEASE_NOTES.txt|database_schema.sql)
            return 0
            ;;
    esac

    case "$rel" in
        *.md)
            return 0
            ;;
    esac

    return 1
}

path_has_symlink_component() {
    local root="$1"
    local rel="$2"
    local current="$root"
    local part

    IFS='/' read -r -a _parts <<< "$rel"

    for part in "${_parts[@]}"; do
        current="$current/$part"

        if [ -L "$current" ]; then
            return 0
        fi
    done

    return 1
}

sha_file() {
    sha256sum "$1" | awk '{print $1}'
}

sha_git_file() {
    local ref="$1"
    local rel="$2"

    git show "${ref}:${rel}" 2>/dev/null | sha256sum | awk '{print $1}'
}

php_lint_isolated() {
    local src="$1"
    local lint_dir
    local lint_file
    local rc

    lint_dir="$(mktemp -d "${TMPDIR:-/tmp}/renee-php-lint.XXXXXX")"         || return 1

    lint_file="$lint_dir/lint.php"

    if ! cp -- "$src" "$lint_file"; then
        rm -rf -- "$lint_dir"
        return 1
    fi

    php -l "$lint_file" >/dev/null 2>&1
    rc=$?

    rm -rf -- "$lint_dir"
    return "$rc"
}

declare -A SOURCE_SHA=()
declare -A LIVE_SHA=()
declare -A BASE_SHA=()
declare -A ACTION=()

for rel in "${FILES[@]}"; do
    validate_relative_path "$rel" \
        || die "INVALID_PATH:$rel"

    if reject_non_runtime_path "$rel"; then
        die "NON_RUNTIME_OR_PROTECTED_PATH:$rel"
    fi

    git ls-files --error-unmatch -- "$rel" >/dev/null 2>&1 \
        || die "UNTRACKED_SOURCE_PATH:$rel"

    src="$REPO_ROOT/$rel"
    dst="$LIVE_ROOT/$rel"

    [ -f "$src" ] \
        || die "SOURCE_NOT_REGULAR_FILE:$rel"

    [ ! -L "$src" ] \
        || die "SOURCE_SYMLINK_REJECTED:$rel"

    if path_has_symlink_component "$REPO_ROOT" "$rel"; then
        die "SOURCE_SYMLINK_COMPONENT_REJECTED:$rel"
    fi

    if path_has_symlink_component "$LIVE_ROOT" "$rel"; then
        die "LIVE_SYMLINK_COMPONENT_REJECTED:$rel"
    fi

    if [ -e "$dst" ] && [ ! -f "$dst" ]; then
        die "LIVE_TARGET_NOT_REGULAR_FILE:$rel"
    fi

    if [ -L "$dst" ]; then
        die "LIVE_TARGET_SYMLINK_REJECTED:$rel"
    fi

    dst_dir="$(dirname "$dst")"

    [ -d "$dst_dir" ] \
        || die "LIVE_PARENT_MISSING:$rel"

    [ ! -L "$dst_dir" ] \
        || die "LIVE_PARENT_SYMLINK_REJECTED:$rel"

    SOURCE_SHA["$rel"]="$(sha_file "$src")"

    if [[ "$rel" == *.php ]]; then
        if ! php_lint_isolated "$src"; then
            die "PHP_LINT_FAILED:$rel"
        fi
    fi

    if git cat-file -e "${BASE_REF}:${rel}" 2>/dev/null; then
        BASE_SHA["$rel"]="$(sha_git_file "$BASE_REF" "$rel")"
    else
        BASE_SHA["$rel"]="ABSENT"
    fi

    if [ -f "$dst" ]; then
        LIVE_SHA["$rel"]="$(sha_file "$dst")"
        live_mode="$(stat -c '%a' "$dst" 2>/dev/null || true)"
        live_owner="$(stat -c '%U' "$dst" 2>/dev/null || true)"
        live_group="$(stat -c '%G' "$dst" 2>/dev/null || true)"

        if [ "${LIVE_SHA[$rel]}" = "${SOURCE_SHA[$rel]}" ]; then
            if [ "$live_mode" != "644" ] ||
               [ "$live_owner" != "$CURRENT_USER" ] ||
               [ "$live_group" != "$CURRENT_GROUP" ]; then
                ACTION["$rel"]="NORMALIZE"
            else
                ACTION["$rel"]="NOOP"
            fi
        else
            if [ "${BASE_SHA[$rel]}" = "ABSENT" ]; then
                die "LIVE_DRIFT_NEW_SOURCE_PATH:$rel"
            fi

            if [ "${LIVE_SHA[$rel]}" != "${BASE_SHA[$rel]}" ]; then
                die "LIVE_DRIFT_DETECTED:$rel"
            fi

            ACTION["$rel"]="REPLACE"
        fi
    else
        LIVE_SHA["$rel"]="ABSENT"

        if [ "${BASE_SHA[$rel]}" != "ABSENT" ]; then
            die "LIVE_FILE_UNEXPECTEDLY_MISSING:$rel"
        fi

        ACTION["$rel"]="CREATE"
    fi
done

say "DEPLOY_MODE=$MODE"
say "DEPLOY_HEAD=$CURRENT_HEAD"
say "DEPLOY_BASE_REF=$BASE_REF"
say "DEPLOY_TARGET_COUNT=${#FILES[@]}"

replace_count=0
create_count=0
normalize_count=0
noop_count=0

for rel in "${FILES[@]}"; do
    case "${ACTION[$rel]}" in
        REPLACE)   replace_count=$((replace_count + 1)) ;;
        CREATE)    create_count=$((create_count + 1)) ;;
        NORMALIZE) normalize_count=$((normalize_count + 1)) ;;
        NOOP)      noop_count=$((noop_count + 1)) ;;
    esac
done

say "DEPLOY_REPLACE_COUNT=$replace_count"
say "DEPLOY_CREATE_COUNT=$create_count"
say "DEPLOY_NORMALIZE_COUNT=$normalize_count"
say "DEPLOY_NOOP_COUNT=$noop_count"

if [ "$MODE" = "dry-run" ]; then
    say "DEPLOY_MUTATIONS=NONE"
    say "DEPLOY_STATUS=PASS"
    exit 0
fi

mkdir -p "$BACKUP_ROOT" \
    || die "BACKUP_ROOT_UNAVAILABLE"

LOCK_FILE="$BACKUP_ROOT/.renee-runtime-deploy.lock"

exec 9>"$LOCK_FILE" \
    || die "LOCK_OPEN_FAILED"

if ! flock -n 9; then
    die "DEPLOYMENT_ALREADY_RUNNING"
fi

STAMP="$(date +%Y%m%d-%H%M%S)"
SHORT_HEAD="$(git rev-parse --short=12 HEAD)"
BACKUP_DIR="$BACKUP_ROOT/runtime-deploy-${STAMP}-${SHORT_HEAD}"

mkdir -p "$BACKUP_DIR" \
    || die "BACKUP_CREATE_FAILED"

changed=()
created=()
temps=()

cleanup_temps() {
    local tmp

    for tmp in "${temps[@]:-}"; do
        [ -n "$tmp" ] || continue
        [ ! -e "$tmp" ] || rm -f -- "$tmp"
    done
}

rollback() {
    local rel
    local dst
    local backup

    for ((i=${#changed[@]}-1; i>=0; i--)); do
        rel="${changed[$i]}"
        dst="$LIVE_ROOT/$rel"
        backup="$BACKUP_DIR/$rel"

        if [ -f "$backup" ]; then
            mkdir -p "$(dirname "$dst")" 2>/dev/null || true
            cp -p -- "$backup" "$dst" 2>/dev/null || true
        fi
    done

    for ((i=${#created[@]}-1; i>=0; i--)); do
        rel="${created[$i]}"
        dst="$LIVE_ROOT/$rel"
        rm -f -- "$dst" 2>/dev/null || true
    done

    cleanup_temps
}

apply_fail() {
    rollback
    say "DEPLOY_BACKUP_DIR=$BACKUP_DIR"
    say "DEPLOY_ROLLBACK=ATTEMPTED"
    die "$1"
}

trap cleanup_temps EXIT

for rel in "${FILES[@]}"; do
    [ "${ACTION[$rel]}" != "NOOP" ] || continue

    src="$REPO_ROOT/$rel"
    dst="$LIVE_ROOT/$rel"
    dst_dir="$(dirname "$dst")"

    tmp="$dst_dir/.renee-deploy.$$.${RANDOM}.tmp"
    temps+=("$tmp")

    cp -- "$src" "$tmp" \
        || apply_fail "STAGING_COPY_FAILED:$rel"

    chmod 0644 "$tmp" \
        || apply_fail "STAGING_MODE_FAILED:$rel"

    staged_owner="$(stat -c '%U' "$tmp" 2>/dev/null || true)"
    staged_group="$(stat -c '%G' "$tmp" 2>/dev/null || true)"
    staged_mode="$(stat -c '%a' "$tmp" 2>/dev/null || true)"

    [ "$staged_owner" = "$CURRENT_USER" ] \
        || apply_fail "STAGING_OWNER_MISMATCH:$rel"

    [ "$staged_group" = "$CURRENT_GROUP" ] \
        || apply_fail "STAGING_GROUP_MISMATCH:$rel"

    [ "$staged_mode" = "644" ] \
        || apply_fail "STAGING_MODE_MISMATCH:$rel"

    [ "$(sha_file "$tmp")" = "${SOURCE_SHA[$rel]}" ] \
        || apply_fail "STAGING_SHA_MISMATCH:$rel"
done

for rel in "${FILES[@]}"; do
    [ "${ACTION[$rel]}" != "NOOP" ] || continue

    dst="$LIVE_ROOT/$rel"

    if [ -f "$dst" ]; then
        backup="$BACKUP_DIR/$rel"

        mkdir -p "$(dirname "$backup")" \
            || apply_fail "BACKUP_PARENT_FAILED:$rel"

        cp -p -- "$dst" "$backup" \
            || apply_fail "BACKUP_COPY_FAILED:$rel"
    fi
done

temp_index=0

for rel in "${FILES[@]}"; do
    [ "${ACTION[$rel]}" != "NOOP" ] || continue

    dst="$LIVE_ROOT/$rel"
    tmp="${temps[$temp_index]}"
    temp_index=$((temp_index + 1))

    if [ "${ACTION[$rel]}" = "CREATE" ]; then
        created+=("$rel")
    else
        changed+=("$rel")
    fi

    mv -f -- "$tmp" "$dst" \
        || apply_fail "ATOMIC_REPLACE_FAILED:$rel"

    [ "$(sha_file "$dst")" = "${SOURCE_SHA[$rel]}" ] \
        || apply_fail "FINAL_SHA_MISMATCH:$rel"

    final_mode="$(stat -c '%a' "$dst" 2>/dev/null || true)"
    final_owner="$(stat -c '%U' "$dst" 2>/dev/null || true)"
    final_group="$(stat -c '%G' "$dst" 2>/dev/null || true)"

    [ "$final_mode" = "644" ] \
        || apply_fail "FINAL_MODE_MISMATCH:$rel"

    [ "$final_owner" = "$CURRENT_USER" ] \
        || apply_fail "FINAL_OWNER_MISMATCH:$rel"

    [ "$final_group" = "$CURRENT_GROUP" ] \
        || apply_fail "FINAL_GROUP_MISMATCH:$rel"
done

cleanup_temps
trap - EXIT

say "DEPLOY_BACKUP_DIR=$BACKUP_DIR"
say "DEPLOY_PERMISSION_POLICY=0644"
say "DEPLOY_UNRELATED_FILES_DELETED=0"
say "DEPLOY_ROLLBACK=NOT_REQUIRED"
say "DEPLOY_STATUS=PASS"
