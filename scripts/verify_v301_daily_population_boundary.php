<?php

declare(strict_types=1);

/**
 * V3.0.1 — canonical Daily Record population boundary verifier.
 *
 * Source/pure-logic only.
 * No database connection.
 * No database writes.
 */

$root = dirname(__DIR__);
$servicePath =
    $root
    . '/lib/daily_population_boundary.php';

$source = is_file($servicePath)
    ? (string)file_get_contents($servicePath)
    : '';

require_once $servicePath;

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
    'boundary service is readable',
    $source !== ''
);

$check(
    'boundary service loads canonical population authority',
    str_contains(
        $source,
        "require_once __DIR__ . '/production_population.php';"
    )
    && str_contains(
        $source,
        "require_once __DIR__ . '/production_population_projection.php';"
    )
);

$check(
    'boundary service exposes batched and single-date readers',
    substr_count(
        $source,
        'function daily_population_boundary_snapshots('
    ) === 1
    && substr_count(
        $source,
        'function daily_population_boundary_snapshot('
    ) === 1
);

$check(
    'boundary service reads only effective active movements',
    str_contains(
        $source,
        'm.reversal_of_id IS NULL'
    )
    && str_contains(
        $source,
        'r.reversal_of_id = m.id'
    )
);

$check(
    'boundary service does not own population writes',
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
        'production_population_projection_sync('
    )
    && !preg_match(
        '/\b(?:INSERT|UPDATE|DELETE)\s+(?:INTO\s+|FROM\s+)?production_population_/i',
        $source
    )
);

$snapshots =
    daily_population_boundary_build_snapshots(
        '2026-09-01',
        1500,
        [
            '2026-09-01',
            '2026-09-10',
            '2026-09-11',
        ],
        [
            '2026-09-10' => [
                'mortality' => -2,
                'sale' => -500,
            ],
            '2026-09-11' => [
                'transfer_in' => 100,
            ],
        ]
    );

$check(
    'baseline date opens at canonical baseline',
    ($snapshots['2026-09-01']['opening_quantity'] ?? null)
        === 1500
    && ($snapshots['2026-09-01']['closing_quantity'] ?? null)
        === 1500
);

$check(
    'same-day sale and mortality reduce canonical closing population',
    ($snapshots['2026-09-10']['opening_quantity'] ?? null)
        === 1500
    && ($snapshots['2026-09-10']['closing_quantity'] ?? null)
        === 998
    && ($snapshots['2026-09-10']['removals'] ?? null)
        === 502
);

$check(
    'next-day opening inherits all prior physical movements',
    ($snapshots['2026-09-11']['opening_quantity'] ?? null)
        === 998
    && ($snapshots['2026-09-11']['closing_quantity'] ?? null)
        === 1098
    && ($snapshots['2026-09-11']['additions'] ?? null)
        === 100
);

$movementMap = [
    '2026-09-10' => [
        'mortality' => -5,
        'sale' => -500,
    ],
];

$movementMap =
    daily_population_boundary_apply_pending_mortality(
        $movementMap,
        9,
        [
            'cycle_id' => 9,
            'movement_date' => '2026-09-10',
            'movement_type' => 'mortality',
            'quantity_delta' => -5,
        ],
        'daily_layer_record',
        77,
        '2026-09-10',
        2
    );

$check(
    'pending mortality replaces old source projection without touching sale',
    ($movementMap['2026-09-10']['mortality'] ?? null)
        === -2
    && ($movementMap['2026-09-10']['sale'] ?? null)
        === -500
);

$movementMap =
    daily_population_boundary_apply_pending_mortality(
        $movementMap,
        9,
        [
            'cycle_id' => 9,
            'movement_date' => '2026-09-10',
            'movement_type' => 'mortality',
            'quantity_delta' => -2,
        ],
        'daily_layer_record',
        77,
        '2026-09-10',
        0
    );

$check(
    'zero pending mortality removes only mortality projection',
    !isset($movementMap['2026-09-10']['mortality'])
    && ($movementMap['2026-09-10']['sale'] ?? null)
        === -500
);

$check(
    'movement labels preserve business meaning',
    daily_population_boundary_movement_label('sale')
        === 'Sold'
    && daily_population_boundary_movement_label('cull')
        === 'Culled'
    && daily_population_boundary_movement_label('slaughter')
        === 'Slaughtered'
    && daily_population_boundary_movement_label('transfer_out')
        === 'Transferred out'
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
