<?php

declare(strict_types=1);

$home =
    getenv('HOME') !== false
        ? (string)getenv('HOME')
        : '';

if ($home === '') {
    fwrite(STDERR, "RETENTION_HOME=FAIL\n");
    exit(1);
}

$privateRoot =
    getenv('RENEE_PRIVATE_ROOT') !== false
        ? (string)getenv('RENEE_PRIVATE_ROOT')
        : $home . '/renee-private';

$localRoot =
    getenv('RENEE_BACKUP_ROOT') !== false
        ? (string)getenv('RENEE_BACKUP_ROOT')
        : $home . '/renee-production-backups';

$b2Env =
    getenv('RENEE_B2_ENV') !== false
        ? (string)getenv('RENEE_B2_ENV')
        : $privateRoot . '/b2-backup/credentials.env';

$recentLimit = 8;
$dailyLimit = 7;
$weeklyLimit = 4;

$deleteEnabled = in_array('--execute-delete', $argv, true);

function fail(string $stage): never
{
    fwrite(STDERR, "RETENTION_STATUS=FAIL\n");
    fwrite(STDERR, "RETENTION_FAILURE_STAGE={$stage}\n");
    exit(1);
}

function buildRetentionPlan(array $entries, int $recent, int $daily, int $weekly): array
{
    usort(
        $entries,
        static fn(array $a, array $b): int =>
            $b['time']->getTimestamp() <=> $a['time']->getTimestamp()
    );

    $keep = [];
    $reasons = [];

    $mark = static function (string $id, string $reason) use (&$keep, &$reasons): void {
        $keep[$id] = true;
        $reasons[$id] ??= [];
        $reasons[$id][] = $reason;
    };

    foreach (array_slice($entries, 0, $recent) as $entry) {
        $mark($entry['id'], 'recent');
    }

    $days = [];

    foreach ($entries as $entry) {
        $day = $entry['time']->format('Y-m-d');

        if (isset($days[$day])) {
            continue;
        }

        if (count($days) >= $daily) {
            break;
        }

        $days[$day] = true;
        $mark($entry['id'], 'daily');
    }

    $weeks = [];

    foreach ($entries as $entry) {
        $week = $entry['time']->format('o-\WW');

        if (isset($weeks[$week])) {
            continue;
        }

        if (count($weeks) >= $weekly) {
            break;
        }

        $weeks[$week] = true;
        $mark($entry['id'], 'weekly');
    }

    $delete = [];

    foreach ($entries as $entry) {
        if (!isset($keep[$entry['id']])) {
            $delete[] = $entry['id'];
        }
    }

    return [
        'keep' => $keep,
        'reasons' => $reasons,
        'delete' => $delete,
    ];
}

if (!is_dir($localRoot)) {
    fail('local_root_missing');
}

if (!is_file($b2Env)) {
    fail('b2_env_missing');
}

$localEntries = [];

foreach (scandir($localRoot) ?: [] as $name) {
    if (!preg_match('/^production-(\d{8}T\d{6}Z)$/', $name, $m)) {
        continue;
    }

    $path = $localRoot . '/' . $name;

    if (!is_dir($path)) {
        continue;
    }

    $dt = DateTimeImmutable::createFromFormat(
        '!Ymd\THis\Z',
        $m[1],
        new DateTimeZone('UTC')
    );

    if (!$dt) {
        continue;
    }

    $localEntries[] = [
        'id' => $name,
        'time' => $dt,
        'path' => $path,
    ];
}

$plan = buildRetentionPlan(
    $localEntries,
    $recentLimit,
    $dailyLimit,
    $weeklyLimit
);

echo 'RETENTION_MODE=' . ($deleteEnabled ? 'EXECUTE' : 'DRY_RUN') . PHP_EOL;
echo "RETENTION_POLICY_RECENT={$recentLimit}" . PHP_EOL;
echo "RETENTION_POLICY_DAILY={$dailyLimit}" . PHP_EOL;
echo "RETENTION_POLICY_WEEKLY={$weeklyLimit}" . PHP_EOL;
echo 'LOCAL_RESTORE_POINT_COUNT=' . count($localEntries) . PHP_EOL;

foreach ($localEntries as $entry) {
    $id = $entry['id'];

    if (isset($plan['keep'][$id])) {
        echo 'LOCAL_KEEP=' . $id .
            '|REASON=' .
            implode(',', array_unique($plan['reasons'][$id])) .
            PHP_EOL;
    }
}

foreach ($plan['delete'] as $id) {
    echo "LOCAL_DELETE_CANDIDATE={$id}" . PHP_EOL;
}

echo 'LOCAL_DELETE_CANDIDATE_COUNT=' . count($plan['delete']) . PHP_EOL;

if (!$deleteEnabled) {
    echo "DELETE_PERFORMED=NO" . PHP_EOL;
    echo "RETENTION_STATUS=PASS" . PHP_EOL;
    exit(0);
}

/*
 * Deletion is deliberately fail-closed.
 * Remote deletion must succeed before the matching local restore point
 * can be removed.
 */

$env = [];

foreach (file($b2Env, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    if (!str_contains($line, '=')) {
        continue;
    }

    [$k, $v] = explode('=', $line, 2);
    $env[$k] = $v;
}

foreach (['B2_KEY_ID', 'B2_APPLICATION_KEY', 'B2_BUCKET', 'B2_PREFIX'] as $key) {
    if (empty($env[$key])) {
        fail("missing_{$key}");
    }
}

$authRaw = shell_exec(
    'curl -sS -u ' .
    escapeshellarg($env['B2_KEY_ID'] . ':' . $env['B2_APPLICATION_KEY']) .
    ' https://api.backblazeb2.com/b2api/v2/b2_authorize_account'
);

$auth = json_decode((string)$authRaw, true);

if (
    !is_array($auth) ||
    empty($auth['apiUrl']) ||
    empty($auth['authorizationToken']) ||
    empty($auth['allowed']['bucketId'])
) {
    fail('b2_authorize');
}

if (
    ($auth['allowed']['bucketName'] ?? '') !== $env['B2_BUCKET'] ||
    ($auth['allowed']['namePrefix'] ?? '') !== $env['B2_PREFIX']
) {
    fail('b2_scope_guard');
}

$apiUrl = $auth['apiUrl'];
$authToken = $auth['authorizationToken'];
$bucketId = $auth['allowed']['bucketId'];

foreach ($plan['delete'] as $id) {
    $prefix = $env['B2_PREFIX'] . $id . '/';

    $request = json_encode([
        'bucketId' => $bucketId,
        'prefix' => $prefix,
        'maxFileCount' => 1000,
    ], JSON_UNESCAPED_SLASHES);

    $cmd =
        'curl -sS ' .
        '-H ' . escapeshellarg('Authorization: ' . $authToken) . ' ' .
        '-H ' . escapeshellarg('Content-Type: application/json') . ' ' .
        '-d ' . escapeshellarg($request) . ' ' .
        escapeshellarg($apiUrl . '/b2api/v2/b2_list_file_names');

    $listRaw = shell_exec($cmd);
    $list = json_decode((string)$listRaw, true);

    if (!is_array($list) || !isset($list['files'])) {
        fail("b2_list_{$id}");
    }

    foreach ($list['files'] as $file) {
        $fileName = $file['fileName'] ?? '';
        $fileId = $file['fileId'] ?? '';

        if (
            $fileName === '' ||
            $fileId === '' ||
            !str_starts_with($fileName, $prefix)
        ) {
            fail("b2_file_guard_{$id}");
        }

        $deletePayload = json_encode([
            'fileName' => $fileName,
            'fileId' => $fileId,
        ], JSON_UNESCAPED_SLASHES);

        $deleteCmd =
            'curl -sS ' .
            '-H ' . escapeshellarg('Authorization: ' . $authToken) . ' ' .
            '-H ' . escapeshellarg('Content-Type: application/json') . ' ' .
            '-d ' . escapeshellarg($deletePayload) . ' ' .
            escapeshellarg($apiUrl . '/b2api/v2/b2_delete_file_version');

        $deleteRaw = shell_exec($deleteCmd);
        $deleteResult = json_decode((string)$deleteRaw, true);

        if (
            !is_array($deleteResult) ||
            ($deleteResult['fileName'] ?? '') !== $fileName
        ) {
            fail("b2_delete_{$id}");
        }
    }

    $localPath = $localRoot . '/' . $id;

    if (!is_dir($localPath)) {
        fail("local_path_missing_{$id}");
    }

    $realRoot = realpath($localRoot);
    $realPath = realpath($localPath);

    if (
        $realRoot === false ||
        $realPath === false ||
        !str_starts_with($realPath, $realRoot . '/')
    ) {
        fail("local_delete_guard_{$id}");
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $realPath,
            FilesystemIterator::SKIP_DOTS
        ),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }

    rmdir($realPath);

    echo "REMOTE_DELETE={$id}=PASS" . PHP_EOL;
    echo "LOCAL_DELETE={$id}=PASS" . PHP_EOL;
}

echo "DELETE_PERFORMED=YES" . PHP_EOL;
echo "RETENTION_STATUS=PASS" . PHP_EOL;
