<?php
declare(strict_types=1);

/**
 * B12 source-only, read-only Daily Record writer inventory.
 * Not imported by application routes and not connected to GitHub Actions.
 * Designed for explicitly authorized local source-only verification.
 * Never imports application runtime, configuration, database or routes.
 * The formerly duplicated privileged and delegated Ruminant delete
 * statements are now centralized in lib/ruminant_daily_record_deletion.php.
 * The delegated runtime and privileged route remain source-level callers,
 * but no longer contain direct DELETE FROM ruminant_daily_records SQL.
 * Cycle-start seed INSERTs in management/production_cycles.php remain.
 * Static SQL matching does not prove completeness: dynamic SQL, stored
 * routines and external callers require an independent manual audit.
 */
$root = dirname(__DIR__, 2);
$dirs = ['account', 'admin', 'api', 'billing', 'includes', 'inventory', 'lib', 'management', 'poultry', 'ruminant'];
$expected = [
    'api/delete_record.php',
    'lib/daily_population_continuity.php',
    'lib/ruminant_daily_record_deletion.php',
    'management/production_cycles.php',
    'poultry/broiler_daily_record.php',
    'poultry/layers_daily_record.php',
    'ruminant/ruminant_daily_record.php',
];
sort($expected, SORT_STRING);
$pattern = '~\b(?:INSERT\s+(?:IGNORE\s+)?INTO|REPLACE\s+INTO|UPDATE|DELETE\s+FROM|TRUNCATE\s+TABLE|ALTER\s+TABLE)\s+[\x60"]?(?:layer_daily_records|broiler_daily_records|ruminant_daily_records)\b~i';
$identity = '~\b(?:layer_daily_records|broiler_daily_records|ruminant_daily_records)\b~i';
$found = [];
$errors = [];
$read = 0;
$revisionCallers = [];

/*
 * Named exceptions to the direct-table SQL match rule.
 * The tenant purge uses a closed table-list plus dynamic DELETE; the dormant
 * revision authority uses closed table definitions with dynamic UPDATE/DELETE.
 * Record these explicitly, and fail closed if their inspected signatures move.
 * This is NOT a guarantee against unrecognized dynamic or external writers.
 */
$dynamicExceptions = [
    'management/farms.php' => [
        'classification' => 'PLATFORM_OWNER_TENANT_PURGE',
        'signatures' => [
            'function deleteFarmRows(',
            'DELETE FROM {$table} WHERE farm_id = ?',
            'function deleteFarmData(',
            'deleteFarmRows($pdo, $table, $farmId);',
            'deleteFarmData($pdo, $farmId);',
            'requirePlatformOwner();',
            'farmHasProtectedCommercialHistory(',
            "'layer_daily_records'",
            "'broiler_daily_records'",
            "'ruminant_daily_records'",
        ],
    ],
    'lib/daily_record_revision_authority.php' => [
        'classification' => 'DORMANT_SHARED_REVISION_SERVICE',
        'signatures' => [
            'function daily_record_revision_definitions(',
            'function daily_record_revision_lock_and_compare(',
            'function daily_record_revision_write(',
            'function daily_record_revision_delete_locked(',
            "'UPDATE ' . \$config['table']",
            "'DELETE FROM ' . \$config['table']",
            "'layer_daily_records'",
            "'broiler_daily_records'",
            "'ruminant_daily_records'",
        ],
    ],
];

// Also inspect top-level runtime entry points; they are outside module dirs.
$topLevel = glob($root . DIRECTORY_SEPARATOR . '*.php') ?: [];
$rootCandidates = [];
foreach ($topLevel as $fileName) {
    if (is_file($fileName)) {
        $rootCandidates[] = $fileName;
    }
}

foreach ($dirs as $dir) {
    $base = $root . DIRECTORY_SEPARATOR . $dir;
    if (!is_dir($base)) {
        $errors[] = 'Directory unavailable: ' . $dir;
        continue;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
            continue;
        }
        $relative = str_replace(
            DIRECTORY_SEPARATOR, '/',
            substr($file->getPathname(), strlen($root) + 1)
        );
        $source = file_get_contents($file->getPathname());
        if (!is_string($source)) {
            $errors[] = 'Unreadable file: ' . $relative;
            continue;
        }
        ++$read;
        if ($relative !== 'lib/daily_record_revision_authority.php'
            && preg_match('~\bdaily_record_revision_(?:write|delete_locked|lock_and_compare)\s*\(~', $source)) {
            $revisionCallers[] = $relative;
        }
        if (!preg_match($identity, $source)) {
            continue;
        }
        $literals = [];
        foreach (token_get_all($source) as $token) {
            if (!is_array($token)) {
                continue;
            }
            if ($token[0] === T_CONSTANT_ENCAPSED_STRING) {
                $literals[] = substr($token[1], 1, -1);
            } elseif ($token[0] === T_ENCAPSED_AND_WHITESPACE) {
                $literals[] = $token[1];
            }
        }
        $sourceSql = implode(' ', $literals);
        if (preg_match($pattern, $sourceSql) || preg_match($pattern, $source)) {
            preg_match_all($pattern, $sourceSql . "\n" . $source, $matches);
            $found[$relative] = array_values(array_unique($matches[0] ?? []));
        }
    }
}
// Root PHP files are inspected by the same textual SQL rule. No includes
// or application routes are executed by this verifier.
foreach ($rootCandidates as $fileName) {
    $relative = basename($fileName);
    $source = file_get_contents($fileName);
    if (!is_string($source)) {
        $errors[] = 'Unreadable root file: ' . $relative;
        continue;
    }
    ++$read;
    if (preg_match('~\bdaily_record_revision_(?:write|delete_locked|lock_and_compare)\s*\(~', $source)) {
        $revisionCallers[] = $relative;
    }
    if (preg_match($identity, $source) && preg_match($pattern, $source)) {
        preg_match_all($pattern, $source, $matches);
        $found[$relative] = array_values(array_unique($matches[0] ?? []));
    }
}
$dynamicErrors = [];
foreach ($dynamicExceptions as $path => $contract) {
    $source = file_get_contents($root . DIRECTORY_SEPARATOR . $path);
    if (!is_string($source)) {
        $dynamicErrors[] = 'Unreadable named dynamic exception: ' . $path;
        continue;
    }
    foreach ($contract['signatures'] as $signature) {
        if (!str_contains($source, $signature)) {
            $dynamicErrors[] = 'Dynamic exception signature missing: '
                . $path . ' | ' . $signature;
        }
    }
    echo 'DYNAMIC_EXCEPTION=' . $path . ' | '
        . $contract['classification'] . PHP_EOL;
}
$revisionCallers = array_values(array_unique($revisionCallers));
sort($revisionCallers, SORT_STRING);
foreach ($revisionCallers as $path) {
    $dynamicErrors[] = 'Unexpected active revision-service caller: ' . $path;
    echo 'UNEXPECTED_REVISION_CALLER=' . $path . PHP_EOL;
}
echo 'DORMANT_REVISION_CALLER_COUNT=' . count($revisionCallers) . PHP_EOL;
ksort($found, SORT_STRING);
$actual = array_keys($found);
$new = array_values(array_diff($actual, $expected));
$missing = array_values(array_diff($expected, $actual));
echo 'B12_WRITER_INVENTORY_MODE=READ_ONLY' . PHP_EOL;
echo 'PHP_FILES_INSPECTED=' . $read . PHP_EOL;
echo 'MUTATION_PATH_COUNT=' . count($actual) . PHP_EOL;
foreach ($found as $path => $hits) {
    echo 'WRITER=' . $path . ' | ' . implode(', ', $hits) . PHP_EOL;
}
foreach ($new as $path) {
    echo 'UNREVIEWED_WRITER=' . $path . PHP_EOL;
}
foreach ($missing as $path) {
    echo 'EXPECTED_WRITER_MISSING=' . $path . PHP_EOL;
}
foreach (array_merge($errors, $dynamicErrors) as $error) {
    echo 'SCAN_ERROR=' . $error . PHP_EOL;
}
if ($new || $missing || $errors || $dynamicErrors || $read === 0) {
    echo 'B12_WRITER_INVENTORY=FAIL_CLOSED' . PHP_EOL;
    exit(1);
}
echo 'B12_WRITER_INVENTORY=KNOWN_PATHS_MATCH' . PHP_EOL;
echo 'NAMED_DYNAMIC_EXCEPTION_AUDIT=PASS' . PHP_EOL;
echo 'DYNAMIC_SQL_AND_EXTERNAL_CALLERS=MANUAL_REVIEW_REQUIRED' . PHP_EOL;
