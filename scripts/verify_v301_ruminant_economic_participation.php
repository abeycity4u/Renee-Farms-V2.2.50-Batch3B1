<?php

$root =
    dirname(__DIR__);

$file =
    $root
    . '/lib/ruminant_economic_participation.php';

$checks = [];

$check =
    static function (
        bool $condition,
        string $label
    ) use (&$checks): void {
        $checks[] = [
            'ok' => $condition,
            'label' => $label,
        ];
    };

$source =
    is_file($file)
        ? (string)file_get_contents($file)
        : '';

$check(
    $source !== '',
    'economic participation service exists'
);

$check(
    strpos(
        $source,
        'daily_population_boundary_snapshot'
    ) !== false,
    'canonical population boundary is reused'
);

$check(
    strpos(
        $source,
        'ruminant_daily_records'
    ) !== false,
    'exact legacy Daily Record fallback exists'
);

$check(
    strpos(
        $source,
        'opening_headcount'
    ) !== false,
    'cycle-start physical cohort evidence exists'
);

$check(
    strpos(
        $source,
        '$opening'
        . "\n"
        . '        +'
        . "\n"
        . '        $additions'
    ) !== false,
    'same-day exposure is opening plus additions'
);

$check(
    strpos(
        $source,
        'registered_population_exceeds_physical'
    ) !== false,
    'registered above physical fails closed'
);

$check(
    strpos(
        $source,
        'physical_population_evidence_missing'
    ) !== false,
    'missing physical evidence becomes review'
);

$check(
    strpos(
        $source,
        'aggregate_unregistered_headcount'
    ) !== false,
    'aggregate unregistered livestock are preserved'
);

$check(
    strpos(
        $source,
        'ruminant_economic_participation_first_physical_cohort'
    ) !== false,
    'pre-cycle preparation can use first physical cohort'
);

$check(
    !preg_match(
        '/\b(?:INSERT|UPDATE|DELETE|REPLACE)\s+/i',
        preg_replace(
            '/\/\*.*?\*\/|\/\/[^\n]*/s',
            '',
            $source
        )
    ),
    'service is read-only'
);

require_once $file;

$share =
    ruminant_economic_participation_share_for_animal(
        117.65,
        200,
        [27],
        27
    );

$check(
    abs(
        $share
        -
        0.58
    ) < 0.00001,
    'partial registration keeps remainder with aggregate herd'
);

$named =
    ruminant_economic_participation_named_total(
        117.65,
        200,
        [27]
    );

$check(
    abs(
        $named
        -
        0.58
    ) < 0.00001,
    'named total cannot consume the aggregate herd share'
);

$fullShares = [
    ruminant_economic_participation_share_for_animal(
        1.00,
        3,
        [10,20,30],
        10
    ),
    ruminant_economic_participation_share_for_animal(
        1.00,
        3,
        [10,20,30],
        20
    ),
    ruminant_economic_participation_share_for_animal(
        1.00,
        3,
        [10,20,30],
        30
    ),
];

$check(
    abs(
        array_sum($fullShares)
        -
        1.00
    ) < 0.00001,
    'fully registered herd conserves pool cents'
);

$failed = 0;

foreach ($checks as $row) {
    if (!$row['ok']) {
        $failed++;
        echo
            'FAIL: '
            . $row['label']
            . PHP_EOL;
    }
}

if ($failed === 0) {
    echo
        'RUMINANT_ECONOMIC_PARTICIPATION=PASS CHECKS='
        . count($checks)
        . ' FAILED=0'
        . PHP_EOL;

    exit(0);
}

echo
    'RUMINANT_ECONOMIC_PARTICIPATION=FAIL CHECKS='
    . count($checks)
    . ' FAILED='
    . $failed
    . PHP_EOL;

exit(1);
