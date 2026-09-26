<?php
declare(strict_types=1);

$root = dirname(__DIR__);

$checks = 0;
$failures = 0;

$check = static function (
    bool $ok,
    string $label
) use (&$checks, &$failures): void {
    $checks++;

    echo ($ok ? 'PASS: ' : 'FAIL: ')
        . $label
        . PHP_EOL;

    if (!$ok) {
        $failures++;
    }
};

$helperPath =
    $root . '/lib/daily_population_continuity.php';

$helper = is_file($helperPath)
    ? (string)file_get_contents($helperPath)
    : '';

$check(
    str_contains(
        $helper,
        'function daily_population_continuity_activity_records('
    ),
    'shared Daily Record population activity read model exists'
);

$check(
    str_contains(
        $helper,
        "'is_population_activity_only' => true"
    ),
    'synthetic population activity rows are explicitly marked read-only'
);

$check(
    str_contains(
        $helper,
        'daily_population_boundary_active_movements('
    )
    && str_contains(
        $helper,
        'daily_population_boundary_snapshots('
    ),
    'activity rows read from canonical population movement authority'
);

$check(
    !str_contains(
        $helper,
        'INSERT INTO layer_daily_records'
    )
    && !str_contains(
        $helper,
        'INSERT INTO broiler_daily_records'
    )
    && !str_contains(
        $helper,
        'INSERT INTO ruminant_daily_records'
    ),
    'activity read model does not create fake Daily Record rows'
);

$pages = [
    'layer' => $root . '/poultry/layers_daily_record.php',
    'broiler' => $root . '/poultry/broiler_daily_record.php',
    'ruminant' => $root . '/ruminant/ruminant_daily_record.php',
];

foreach ($pages as $label => $path) {
    $content = is_file($path)
        ? (string)file_get_contents($path)
        : '';

    $check(
        str_contains(
            $content,
            'daily_population_continuity_activity_records('
        ),
        ucfirst($label)
            . ' Daily Record uses shared population activity read model'
    );

    $check(
        str_contains(
            $content,
            'is_population_activity_only'
        ),
        ucfirst($label)
            . ' Daily Record suppresses normal actions for activity-only rows'
    );

    $check(
        str_contains(
            $content,
            'population_movement_summary_include_mortality'
        ),
        ucfirst($label)
            . ' Daily Record can label canonical mortality on activity-only dates'
    );
}

$layer = (string)file_get_contents(
    $pages['layer']
);

$broiler = (string)file_get_contents(
    $pages['broiler']
);

$ruminant = (string)file_get_contents(
    $pages['ruminant']
);

$check(
    str_contains(
        $layer,
        'foreach ($displayRecords as $record)'
    ),
    'Layer table renders display activity rows'
);

$check(
    str_contains(
        $broiler,
        'foreach ($displayRecords as $record)'
    ),
    'Broiler table renders display activity rows'
);

$check(
    str_contains(
        $ruminant,
        'foreach ($displayRecords as $record)'
    ),
    'Ruminant animal-type tabs include display activity rows'
);

$check(
    substr_count(
        $layer,
        'foreach ($records as $record)'
    ) >= 1,
    'Layer operational totals remain based on real Daily Records'
);

$check(
    substr_count(
        $broiler,
        'foreach ($records as $record)'
    ) >= 1,
    'Broiler operational totals remain based on real Daily Records'
);

$check(
    substr_count(
        $ruminant,
        'foreach ($records as $record)'
    ) >= 3,
    'Ruminant operational summaries and calendars retain real Daily Records'
);

if ($failures > 0) {
    fwrite(
        STDERR,
        "FAILURES={$failures}; CHECKS={$checks}\n"
    );
    exit(1);
}

echo "CHECKS={$checks}\n";
echo "STATUS=PASS\n";
