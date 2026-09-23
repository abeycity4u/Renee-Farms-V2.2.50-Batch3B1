<?php

$root =
    dirname(__DIR__);

$shared =
    $root
    . '/lib/ruminant_shared_cost_economics.php';

$participation =
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
    is_file($shared)
        ? (string)file_get_contents($shared)
        : '';

$check(
    $source !== '',
    'shared-cost engine exists'
);

$check(
    is_file($participation),
    'economic participation service exists'
);

$check(
    strpos(
        $source,
        "ruminant_economic_participation.php"
    ) !== false,
    'shared-cost engine loads central participation service'
);

$check(
    strpos(
        $source,
        'ruminant_economic_participation_context'
    ) !== false,
    'shared-cost eligibility delegates to participation service'
);

$check(
    strpos(
        $source,
        'ruminant_economic_participation_share_for_animal'
    ) !== false,
    'individual share uses physical denominator service'
);

$check(
    strpos(
        $source,
        'ruminant_economic_participation_named_total'
    ) !== false,
    'named total is derived centrally'
);

$check(
    strpos(
        $source,
        'aggregate_unregistered_shared_cost'
    ) !== false,
    'aggregate unregistered herd cost is disclosed'
);

$check(
    strpos(
        $source,
        'aggregate_unregistered_headcount'
    ) !== false,
    'aggregate unregistered herd headcount is disclosed'
);

$check(
    strpos(
        $source,
        'registered_participant_count'
    ) !== false,
    'registered participant count is distinct from physical headcount'
);

$check(
    strpos(
        $source,
        'physical_headcount'
    ) !== false,
    'physical denominator is exposed'
);

$check(
    strpos(
        $source,
        'first physical cycle cohort'
    ) !== false,
    'pre-cycle method uses physical cohort'
);

$check(
    strpos(
        $source,
        'physical_population_review_required'
    ) !== false,
    'unresolved physical evidence remains review'
);

$check(
    strpos(
        $source,
        "if(!\$eligible) continue; // visible as exception below"
    ) === false,
    'zero tagged animals no longer automatically becomes exception'
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
        'RUMINANT_SHARED_COST_PHYSICAL_POPULATION=PASS CHECKS='
        . count($checks)
        . ' FAILED=0'
        . PHP_EOL;

    exit(0);
}

echo
    'RUMINANT_SHARED_COST_PHYSICAL_POPULATION=FAIL CHECKS='
    . count($checks)
    . ' FAILED='
    . $failed
    . PHP_EOL;

exit(1);
