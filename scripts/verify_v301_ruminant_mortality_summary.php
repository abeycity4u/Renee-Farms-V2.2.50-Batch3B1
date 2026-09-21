<?php

declare(strict_types=1);

/**
 * V3.0.1 — Ruminant mortality summary verifier.
 *
 * Source-only.
 * No database connection.
 * No database writes.
 */

$root = dirname(__DIR__);

$paths = [
    'helper' =>
        $root . '/lib/ruminant_daily_record_mortality_summary.php',
    'page' =>
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

$check = static function (
    string $label,
    bool $ok
) use (&$checks, &$failures): void {
    $checks++;

    echo ($ok ? 'PASS: ' : 'FAIL: ')
        . $label
        . PHP_EOL;

    if (!$ok) {
        $failures++;
    }
};

$check(
    'Ruminant mortality summary sources are readable',
    !in_array('', $sources, true)
);

if ($sources['helper'] !== '') {
    require_once $paths['helper'];
}

$check(
    'Daily Record mortality combiner keeps group losses',
    ruminant_daily_record_mortality_from_records([
        ['mortality' => 2],
        ['mortality' => 0],
        ['mortality' => 3],
    ]) === 5
);

$check(
    'Helper reads canonical population movements',
    str_contains(
        $sources['helper'],
        'production_population_movements'
    )
);

$check(
    'Tagged mortality is restricted to mortality movements',
    str_contains(
        $sources['helper'],
        "m.movement_type = 'mortality'"
    )
);

$check(
    'Tagged mortality is restricted to ruminant lifecycle exits',
    str_contains(
        $sources['helper'],
        "m.source_type = 'ruminant_exit'"
    )
);

$check(
    'Tagged mortality excludes reversed population movements',
    str_contains(
        $sources['helper'],
        'm.reversal_of_id IS NULL'
    )
    && str_contains(
        $sources['helper'],
        'r.reversal_of_id = m.id'
    )
);

$check(
    'Tagged mortality is scoped to displayed month',
    str_contains(
        $sources['helper'],
        'm.movement_date >= ?'
    )
    && str_contains(
        $sources['helper'],
        'm.movement_date <= ?'
    )
);

$check(
    'Selected-cycle mortality can be scoped to one cycle',
    str_contains(
        $sources['helper'],
        "AND m.cycle_id = ?"
    )
);

$check(
    'Cull is not classified as mortality',
    !str_contains(
        $sources['helper'],
        "m.movement_type = 'cull'"
    )
);

$check(
    'Sale is not classified as mortality',
    !str_contains(
        $sources['helper'],
        "m.movement_type = 'sale'"
    )
);

$check(
    'Transfer is not classified as mortality',
    !str_contains(
        $sources['helper'],
        "m.movement_type = 'transfer_out'"
    )
    && !str_contains(
        $sources['helper'],
        "m.movement_type = 'transfer_in'"
    )
);

$check(
    'Ruminant Daily Record loads shared mortality helper',
    str_contains(
        $sources['page'],
        'ruminant_daily_record_mortality_summary.php'
    )
    && str_contains(
        $sources['page'],
        'ruminant_daily_record_mortality_summary('
    )
);

$check(
    'Mortality card uses combined mortality total',
    str_contains(
        $sources['page'],
        '$summaryTotals[\'mortality\'] ='
    )
    && str_contains(
        $sources['page'],
        '$mortalitySummary[\'total_mortality\']'
    )
);

$check(
    'Mortality card exposes tagged and group breakdown',
    str_contains(
        $sources['page'],
        '$mortalitySummary[\'tagged_mortality\']'
    )
    && str_contains(
        $sources['page'],
        '$mortalitySummary[\'daily_record_mortality\']'
    )
);

$writeTokens = [
    'INSERT ',
    'UPDATE ',
    'DELETE ',
    'REPLACE ',
];

$hasWrite = false;

foreach ($writeTokens as $token) {
    if (str_contains($sources['helper'], $token)) {
        $hasWrite = true;
        break;
    }
}

$check(
    'Mortality summary helper is read-only',
    !$hasWrite
);

echo PHP_EOL . 'CHECK_COUNT=' . $checks . PHP_EOL;
echo 'FAILED_COUNT=' . $failures . PHP_EOL;
echo 'DATABASE_CONNECTION_USED=NO' . PHP_EOL;
echo 'DATABASE_WRITE_PERFORMED=NO' . PHP_EOL;
echo 'RESULT='
    . ($failures === 0 ? 'PASS' : 'FAIL')
    . PHP_EOL;

exit($failures === 0 ? 0 : 1);
