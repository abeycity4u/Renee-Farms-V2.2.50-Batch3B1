<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$helper = $root . '/deployment/deploy_runtime_files.sh';

$failures = [];

function check(bool $condition, string $label): void
{
    global $failures;

    if ($condition) {
        echo "PASS: {$label}\n";
        return;
    }

    echo "FAIL: {$label}\n";
    $failures[] = $label;
}

check(is_file($helper), 'central deployment helper exists');

$content = is_file($helper)
    ? (string) file_get_contents($helper)
    : '';

check(
    str_contains($content, 'MODE="dry-run"')
        && str_contains($content, '--apply'),
    'dry-run default and explicit apply mode exist'
);

check(
    str_contains($content, 'APPLY_REQUIRES_BASE_REF'),
    'apply requires explicit deployed base reference'
);

check(
    str_contains($content, 'config.php|.htaccess'),
    'root config.php and root .htaccess are protected'
);

check(
    str_contains($content, 'scripts/*|migrations/*|deployment/*'),
    'scripts migrations and deployment source paths are rejected'
);

check(
    str_contains($content, 'verify_*.php')
        && str_contains($content, 'BATCH_NOTES.txt')
        && str_contains($content, 'RELEASE_NOTES.txt')
        && str_contains($content, 'database_schema.sql'),
    'verifiers and development artifacts are rejected'
);

check(
    str_contains($content, 'INVALID_PATH')
        && str_contains($content, 'SOURCE_SYMLINK_COMPONENT_REJECTED')
        && str_contains($content, 'LIVE_SYMLINK_COMPONENT_REJECTED'),
    'path traversal and symlink guards exist'
);

check(
    str_contains($content, 'LIVE_ROOT_SYMLINK_REJECTED')
        && str_contains($content, 'BACKUP_ROOT_SYMLINK_REJECTED')
        && str_contains($content, 'BACKUP_ROOT_NOT_DIRECTORY'),
    'deployment roots reject symlink and invalid-directory configuration'
);

check(
    str_contains($content, 'git ls-files --error-unmatch'),
    'source targets must be tracked'
);

check(
    str_contains($content, 'declare -A SOURCE_SHA=()')
        && str_contains($content, 'declare -A LIVE_SHA=()')
        && str_contains($content, 'declare -A BASE_SHA=()')
        && str_contains($content, 'declare -A ACTION=()'),
    'per-file deployment state uses associative arrays'
);

check(
    str_contains($content, 'declare -A SEEN_TARGET=()')
        && str_contains($content, 'DUPLICATE_TARGET:$rel'),
    'duplicate deployment targets are rejected explicitly'
);

check(
    str_contains($content, 'PHP_LINT_FAILED')
        && str_contains($content, 'php_lint_isolated')
        && str_contains($content, 'mktemp -d'),
    'PHP lint is isolated from the source worktree'
);

check(
    str_contains($content, 'LIVE_DRIFT_DETECTED')
        && str_contains($content, 'LIVE_FILE_UNEXPECTEDLY_MISSING'),
    'preflight live drift guards exist'
);

check(
    str_contains($content, 'flock -n 9'),
    'central deployment lock exists'
);

check(
    str_contains($content, 'runtime-deploy-${STAMP}-${SHORT_HEAD}'),
    'rollback backup is outside public runtime under backup root'
);

check(
    str_contains($content, 'chmod 0644 "$tmp"')
        && str_contains($content, 'FINAL_MODE_MISMATCH'),
    'central first-party runtime file mode policy is 0644'
);

check(
    str_contains($content, 'ACTION["$rel"]="NORMALIZE"')
        && str_contains($content, 'DEPLOY_NORMALIZE_COUNT='),
    'content-identical runtime files with policy drift are normalized centrally'
);

check(
    str_contains($content, 'ATOMIC_REPLACE_FAILED')
        && str_contains($content, 'mv -f -- "$tmp" "$dst"'),
    'same-directory atomic replacement path exists'
);

check(
    str_contains($content, 'FINAL_SHA_MISMATCH'),
    'final SHA verification exists'
);

check(
    str_contains($content, 'FINAL_OWNER_MISMATCH')
        && str_contains($content, 'FINAL_GROUP_MISMATCH'),
    'final owner and group policy verification exists'
);

check(
    str_contains($content, 'rollback()')
        && str_contains($content, 'DEPLOY_ROLLBACK=ATTEMPTED'),
    'multi-file rollback path exists'
);

check(
    str_contains($content, 'DEPLOY_UNRELATED_FILES_DELETED=0'),
    'helper explicitly preserves unrelated live-only files'
);

check(
    !str_contains($content, 'rsync --delete'),
    'delete-sync is absent'
);

check(
    !str_contains($content, 'git push --force'),
    'force-push behavior is absent'
);

if ($failures !== []) {
    echo 'V302_DEPLOYMENT_HELPER_CONTRACT=FAIL' . PHP_EOL;
    exit(1);
}

echo 'V302_DEPLOYMENT_HELPER_CONTRACT=PASS' . PHP_EOL;
