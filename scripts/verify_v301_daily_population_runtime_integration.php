<?php

declare(strict_types=1);

/**
 * V3.0.1 — Daily Record canonical population runtime integration.
 *
 * Source-only.
 * No database connection.
 * No database writes.
 */

$root = dirname(__DIR__);

$paths = [
    'boundary' =>
        $root . '/lib/daily_population_boundary.php',
    'continuity' =>
        $root . '/lib/daily_population_continuity.php',
    'layer' =>
        $root . '/poultry/layers_daily_record.php',
    'broiler' =>
        $root . '/poultry/broiler_daily_record.php',
    'ruminant' =>
        $root . '/ruminant/ruminant_daily_record.php',
];

$sources = [];

foreach ($paths as $key => $path) {
    $sources[$key] = is_file($path)
        ? (string)file_get_contents($path)
        : '';
}

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

foreach ($sources as $key => $source) {
    $check(
        $key . ' source is readable',
        $source !== ''
    );
}

$check(
    'boundary snapshots expose cycle production identity',
    str_contains(
        $sources['boundary'],
        '$snapshot[\'production_type\']'
    )
    && str_contains(
        $sources['boundary'],
        '$canonical[\'production_type\']'
    )
);

$check(
    'boundary reader exposes distinct movement summary helper',
    substr_count(
        $sources['boundary'],
        'function daily_population_boundary_movement_summary('
    ) === 1
    && str_contains(
        $sources['boundary'],
        "'sale' => 'Sold'"
    )
    && str_contains(
        $sources['boundary'],
        "'cull' => 'Culled'"
    )
    && str_contains(
        $sources['boundary'],
        "'slaughter' => 'Slaughtered'"
    )
    && str_contains(
        $sources['boundary'],
        "'transfer_out' => 'Transferred out'"
    )
);

$check(
    'movement summary does not disguise exits as mortality',
    str_contains(
        $sources['boundary'],
        '$movementType === \'mortality\''
    )
    && str_contains(
        $sources['boundary'],
        '!$includeMortality'
    )
);

$check(
    'shared continuity exposes canonical opening guard',
    substr_count(
        $sources['continuity'],
        'function daily_population_continuity_expected_opening('
    ) === 1
    && str_contains(
        $sources['continuity'],
        'daily_population_boundary_snapshot('
    )
);

$check(
    'shared continuity exposes canonical read-model enrichment',
    substr_count(
        $sources['continuity'],
        'function daily_population_continuity_enrich_records('
    ) === 1
    && str_contains(
        $sources['continuity'],
        '$record[\'stored_opening_stock\']'
    )
    && str_contains(
        $sources['continuity'],
        '$record[\'population_closing_stock\']'
    )
);

$check(
    'shared current stock uses canonical population with legacy fallback',
    substr_count(
        $sources['continuity'],
        'function daily_population_continuity_current_stock('
    ) === 1
    && str_contains(
        $sources['continuity'],
        'production_population_state('
    )
    && str_contains(
        $sources['continuity'],
        'ORDER BY record_date DESC, id DESC LIMIT 1'
    )
);

$check(
    'ruminant canonical opening is species/cycle safe',
    str_contains(
        $sources['continuity'],
        '$config[\'key\'] === \'ruminant\''
    )
    && str_contains(
        $sources['continuity'],
        '$snapshot[\'production_type\']'
    )
    && str_contains(
        $sources['continuity'],
        'The selected ruminant cycle belongs to'
    )
);

foreach (['layer', 'broiler', 'ruminant'] as $type) {
    $source = $sources[$type];

    $check(
        ucfirst($type)
            . ' enriches Daily Record read models centrally',
        str_contains(
            $source,
            'daily_population_continuity_enrich_records('
        )
    );

    $check(
        ucfirst($type)
            . ' Current Stock uses shared canonical current population',
        str_contains(
            $source,
            'daily_population_continuity_current_stock('
        )
    );

    $check(
        ucfirst($type)
            . ' validates V3 opening through shared canonical guard',
        str_contains(
            $source,
            'daily_population_continuity_expected_opening('
        )
        && str_contains(
            $source,
            'Opening stock must match canonical live population before'
        )
    );

    $check(
        ucfirst($type)
            . ' preserves legacy previous-record fallback',
        str_contains(
            $source,
            "Opening stock must match the previous day's closing"
        )
    );

    $check(
        ucfirst($type)
            . ' displays canonical closing population',
        str_contains(
            $source,
            "population_closing_stock"
        )
    );

    $check(
        ucfirst($type)
            . ' surfaces distinct non-mortality population movements',
        str_contains(
            $source,
            'daily_population_boundary_movement_summary('
        )
    );

    $check(
        ucfirst($type)
            . ' passes durable Daily Record id to continuity apply',
        preg_match(
            '/daily_population_continuity_apply\s*\(.*?\$dailyRecordId\s*\)/s',
            $source
        ) === 1
    );

    $check(
        ucfirst($type)
            . ' does not duplicate canonical population ledger SQL',
        !str_contains(
            $source,
            'production_population_movements'
        )
        && !str_contains(
            $source,
            'production_population_baselines'
        )
    );
}

$check(
    'runtime pages retain existing feed synchronization ownership',
    str_contains(
        $sources['layer'],
        'sync_daily_feed_usage('
    )
    && str_contains(
        $sources['broiler'],
        'sync_daily_feed_usage('
    )
    && str_contains(
        $sources['ruminant'],
        'sync_daily_feed_usage('
    )
);

$check(
    'runtime pages retain existing mortality projection ownership',
    str_contains(
        $sources['layer'],
        'daily_population_sync_mortality('
    )
    && str_contains(
        $sources['broiler'],
        'daily_population_sync_mortality('
    )
    && str_contains(
        $sources['ruminant'],
        'daily_population_sync_mortality('
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
