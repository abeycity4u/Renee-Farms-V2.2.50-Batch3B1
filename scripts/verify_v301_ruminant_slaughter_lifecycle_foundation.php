<?php

declare(strict_types=1);

/**
 * V3.0.1 — tagged-ruminant slaughter lifecycle foundation verifier.
 *
 * Source-only.
 * No database connection.
 * No database writes.
 */

$root = dirname(__DIR__);

$paths = [
    'migration' =>
        $root . '/migrations/062_ruminant_slaughter_lifecycle.sql',
    'service' =>
        $root . '/lib/ruminant_lifecycle_service.php',
    'integrity' =>
        $root . '/lib/ruminant_lifecycle_integrity.php',
    'registry' =>
        $root . '/ruminant/animal_registry.php',
    'boundary' =>
        $root . '/lib/daily_population_boundary.php',
];

$sources = [];
foreach ($paths as $key => $path) {
    $sources[$key] =
        is_file($path) && is_readable($path)
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
    'Slaughter lifecycle sources are readable',
    !in_array('', $sources, true)
);

$check(
    'Migration extends registry status with slaughtered',
    str_contains(
        $sources['migration'],
        "'slaughtered'"
    )
    && str_contains(
        $sources['migration'],
        'MODIFY COLUMN status ENUM'
    )
);

$check(
    'Migration preserves all existing registry statuses',
    str_contains($sources['migration'], "'active'")
    && str_contains($sources['migration'], "'sold'")
    && str_contains($sources['migration'], "'dead'")
    && str_contains($sources['migration'], "'culled'")
    && str_contains($sources['migration'], "'transferred'")
);

$check(
    'Migration records schema checkpoint 062',
    str_contains(
        $sources['migration'],
        '062_ruminant_slaughter_lifecycle.sql'
    )
);

$check(
    'Central lifecycle service maps manual slaughter to canonical slaughter',
    str_contains(
        $sources['service'],
        "'manual_slaughtered' => 'slaughter'"
    )
);

$check(
    'Legacy culled/slaughtered sale outcome remains historical cull',
    str_contains(
        $sources['service'],
        "'manual_culled', 'culled_slaughtered' => 'cull'"
    )
);

$check(
    'Manual exit service accepts slaughtered as a terminal status',
    str_contains(
        $sources['integrity'],
        "'slaughtered'=>'slaughtered'"
    )
);

$check(
    'Exit-history display names manual slaughter correctly',
    str_contains(
        $sources['integrity'],
        "'manual_slaughtered'=>'Slaughtered'"
    )
);

$check(
    'Registry exposes Slaughtered as an explicit Record Exit outcome',
    str_contains(
        $sources['registry'],
        '<option value="slaughtered">Slaughtered</option>'
    )
);

$check(
    'Registry status validation and filtering know slaughtered',
    str_contains(
        $sources['registry'],
        "['active','sold','dead','culled','slaughtered','transferred']"
    )
);

$check(
    'Canonical Daily Record movement vocabulary already labels slaughter',
    str_contains(
        $sources['boundary'],
        "'slaughter' => 'Slaughtered'"
    )
);

$check(
    'Slaughter foundation does not add direct population-ledger SQL',
    !str_contains(
        $sources['service'],
        'INSERT INTO production_population_movements'
    )
    && !str_contains(
        $sources['integrity'],
        'INSERT INTO production_population_movements'
    )
);

$check(
    'Slaughter migration does not rewrite existing exit history',
    !str_contains(
        strtoupper($sources['migration']),
        'UPDATE RUMINANT_ANIMAL_EXIT_EVENTS'
    )
);

echo PHP_EOL . 'CHECK_COUNT=' . $checks . PHP_EOL;
echo 'FAILED_COUNT=' . $failures . PHP_EOL;
echo 'DATABASE_CONNECTION_USED=NO' . PHP_EOL;
echo 'DATABASE_WRITE_PERFORMED=NO' . PHP_EOL;
echo 'RESULT='
    . ($failures === 0 ? 'PASS' : 'FAIL')
    . PHP_EOL;

exit($failures === 0 ? 0 : 1);
