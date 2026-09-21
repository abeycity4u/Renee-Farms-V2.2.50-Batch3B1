<?php

declare(strict_types=1);

/**
 * V3.0.1 — canonical Daily Record opening-stock API verifier.
 *
 * Source-only.
 * No database connection.
 * No database writes.
 */

$root = dirname(__DIR__);
$apiPath = $root . '/api/get_previous_stock.php';
$api = is_file($apiPath)
    ? (string)file_get_contents($apiPath)
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
    'previous-stock API is readable',
    $api !== ''
);

$check(
    'previous-stock API loads shared canonical continuity service',
    str_contains(
        $api,
        "daily_population_continuity.php"
    )
);

$check(
    'previous-stock API resolves V3 target-date opening centrally',
    str_contains(
        $api,
        'daily_population_continuity_expected_opening('
    )
    && str_contains(
        $api,
        "'tracking_status' => 'canonical'"
    )
    && str_contains(
        $api,
        "'source' => 'v3_population_ledger'"
    )
);

$check(
    'canonical response preserves existing closing_stock browser contract',
    preg_match(
        '/\'closing_stock\'\s*=>\s*\(int\)\$canonicalOpening\[\'opening_stock\'\]/',
        $api
    ) === 1
);

$canonicalPos =
    strpos(
        $api,
        'daily_population_continuity_expected_opening('
    );

$legacyPos =
    strpos(
        $api,
        'ORDER BY record_date DESC, id DESC LIMIT 1'
    );

$check(
    'canonical lookup runs before legacy previous-record fallback',
    $canonicalPos !== false
    && $legacyPos !== false
    && $canonicalPos < $legacyPos
);

$check(
    'legacy previous-record fallback remains available',
    str_contains(
        $api,
        "max(0, (float)$record['opening_stock'] - (float)$record['mortality'])"
    )
);

$check(
    'ruminant request still carries animal type into shared guard',
    str_contains(
        $api,
        "$tableMap[$type]['animal']"
    )
    && str_contains(
        $api,
        '? $animalType'
    )
);

$check(
    'API does not duplicate canonical population ledger SQL',
    !str_contains(
        $api,
        'production_population_movements'
    )
    && !str_contains(
        $api,
        'production_population_baselines'
    )
);

$check(
    'API remains read-only',
    !preg_match(
        '/\b(?:INSERT|UPDATE|DELETE)\s+/i',
        $api
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
