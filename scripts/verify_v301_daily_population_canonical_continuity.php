<?php

declare(strict_types=1);

/**
 * V3.0.1 — canonical Daily Record continuity adapter verifier.
 *
 * Source-only.
 * No database connection.
 * No database writes.
 */

$root = dirname(__DIR__);
$servicePath =
    $root . '/lib/daily_population_continuity.php';

$source = is_file($servicePath)
    ? (string)file_get_contents($servicePath)
    : '';

$checks = 0;
$failures = 0;

$check =
    static function (
        string $label,
        bool $ok
    ) use (
        &$checks,
        &$failures
    ): void {
        $checks++;

        echo
            ($ok ? 'PASS: ' : 'FAIL: ')
            . $label
            . PHP_EOL;

        if (!$ok) {
            $failures++;
        }
    };

$check(
    'continuity service is readable',
    $source !== ''
);

$check(
    'continuity service loads canonical boundary reader',
    str_contains(
        $source,
        "require_once __DIR__ . '/daily_population_boundary.php';"
    )
);

$check(
    'canonical continuity adapter exists exactly once',
    substr_count(
        $source,
        'function daily_population_continuity_canonical_preview('
    ) === 1
);

$check(
    'canonical adapter batches target and later record dates',
    str_contains(
        $source,
        '$dates = [$recordDate];'
    )
    && str_contains(
        $source,
        '$dates[] = (string)$row[\'record_date\'];'
    )
);

$check(
    'canonical adapter overlays pending Daily Record mortality',
    str_contains(
        $source,
        'daily_population_boundary_snapshots('
    )
    && str_contains(
        $source,
        '\'source_type\' => $config[\'source_type\']'
    )
    && str_contains(
        $source,
        '\'source_id\' => $sourceId'
    )
    && str_contains(
        $source,
        '\'mortality\' => $mortality'
    )
);

$check(
    'canonical opening is authoritative for target Daily Record',
    str_contains(
        $source,
        '$canonicalOpening = (int)$target[\'opening_quantity\'];'
    )
    && str_contains(
        $source,
        '$openingStock !== $canonicalOpening'
    )
    && str_contains(
        $source,
        'Opening stock must match canonical live population before'
    )
);

$check(
    'later openings and closings come from canonical boundaries',
    str_contains(
        $source,
        '$expectedOpening = (int)$snapshot[\'opening_quantity\'];'
    )
    && str_contains(
        $source,
        '$newClosing = (int)$snapshot[\'closing_quantity\'];'
    )
);

$check(
    'canonical continuity preserves Layer derived-rate validation',
    str_contains(
        $source,
        '$eggProduction > $expectedOpening'
    )
    && str_contains(
        $source,
        '($eggProduction / $expectedOpening) * 100'
    )
);

$check(
    'legacy mortality-only continuity remains as fallback',
    str_contains(
        $source,
        '$expectedOpening = $openingStock - $mortality;'
    )
    && str_contains(
        $source,
        "'tracking_status' => 'legacy'"
    )
    && str_contains(
        $source,
        "'source' => 'daily_record_chain'"
    )
);

$check(
    'canonical plan identifies V3 ledger provenance',
    str_contains(
        $source,
        "'tracking_status' => 'canonical'"
    )
    && str_contains(
        $source,
        "'source' => 'v3_population_ledger'"
    )
);

$check(
    'transactional apply accepts source identity',
    preg_match(
        '/function\s+daily_population_continuity_apply\s*\([^)]*\?int\s+\$sourceId\s*=\s*null/s',
        $source
    ) === 1
);

$check(
    'transactional apply forwards source identity to locked preview',
    preg_match(
        '/daily_population_continuity_preview\s*\(.*?\$animalType\s*,\s*true\s*,\s*\$sourceId\s*\)/s',
        $source
    ) === 1
);

$check(
    'continuity service does not write canonical population ledger',
    !str_contains(
        $source,
        'production_population_record_movement('
    )
    && !str_contains(
        $source,
        'production_population_reverse_movement('
    )
    && !str_contains(
        $source,
        'daily_population_sync_mortality('
    )
    && !preg_match(
        '/\b(?:INSERT|UPDATE|DELETE)\s+(?:INTO\s+|FROM\s+)?production_population_/i',
        $source
    )
);

$check(
    'continuity service still never rewrites mortality facts',
    !str_contains(
        $source,
        'SET mortality'
    )
    && !str_contains(
        $source,
        'mortality = ?'
    )
);

echo PHP_EOL
    . 'CHECK_COUNT='
    . $checks
    . PHP_EOL;

echo 'FAILED_COUNT='
    . $failures
    . PHP_EOL;

echo 'DATABASE_CONNECTION_USED=NO'
    . PHP_EOL;

echo 'DATABASE_WRITE_PERFORMED=NO'
    . PHP_EOL;

echo 'RESULT='
    . ($failures === 0 ? 'PASS' : 'FAIL')
    . PHP_EOL;

exit($failures === 0 ? 0 : 1);
