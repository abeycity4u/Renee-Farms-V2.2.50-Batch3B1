<?php

$root =
    dirname(__DIR__);

$entryFile =
    $root
    . '/lib/ruminant_animal_entry.php';

$membershipFile =
    $root
    . '/lib/ruminant_cycle_membership.php';

$registryFile =
    $root
    . '/ruminant/animal_registry.php';

$viewFile =
    $root
    . '/ruminant/animal_view.php';

$jsFile =
    $root
    . '/assets/js/ruminant-animal-registry.js';

$migrationFile =
    $root
    . '/migrations/075_ruminant_farm_entry_date.sql';

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

$entry =
    is_file($entryFile)
        ? (string)file_get_contents($entryFile)
        : '';

$membership =
    is_file($membershipFile)
        ? (string)file_get_contents($membershipFile)
        : '';

$registry =
    is_file($registryFile)
        ? (string)file_get_contents($registryFile)
        : '';

$view =
    is_file($viewFile)
        ? (string)file_get_contents($viewFile)
        : '';

$js =
    is_file($jsFile)
        ? (string)file_get_contents($jsFile)
        : '';

$migration =
    is_file($migrationFile)
        ? (string)file_get_contents($migrationFile)
        : '';

$enforcementMigrationFile =
    $root
    . '/migrations/076_ruminant_farm_entry_not_null.sql';

$enforcementMigration =
    is_file($enforcementMigrationFile)
        ? (string)file_get_contents($enforcementMigrationFile)
        : '';

$check(
    $entry !== '',
    'central animal farm-entry helper exists'
);

$check(
    strpos(
        $entry,
        'farm_entry_date'
    ) !== false,
    'farm entry is distinct first-class provenance'
);

$check(
    strpos(
        $entry,
        'Farm entry date cannot be in the future.'
    ) !== false,
    'future physical entry is rejected'
);

$check(
    strpos(
        $entry,
        'Farm entry date cannot be earlier than the animal birth date.'
    ) !== false,
    'farm entry cannot predate birth'
);

$check(
    strpos(
        $membership,
        "ruminant_animal_entry.php"
    ) !== false,
    'membership uses central farm-entry helper'
);

$check(
    strpos(
        $membership,
        'farm_entry_date'
    ) !== false,
    'membership loader reads farm entry date'
);

$check(
    strpos(
        $membership,
        'ruminant_animal_farm_entry_date_from_row'
    ) !== false,
    'membership boundary resolves canonical physical entry'
);

$check(
    strpos(
        $registry,
        "name=\"farm_entry_date\""
    ) !== false,
    'registry captures physical farm entry'
);

$check(
    substr_count(
        $registry,
        'farm_entry_date'
    ) >= 5,
    'registry persists and exposes farm entry'
);

$check(
    strpos(
        $registry,
        'Farm entry date cannot be changed after operational/economic history exists.'
    ) !== false,
    'ordinary edit cannot silently rewrite historical farm entry'
);

$check(
    strpos(
        $view,
        'Physical Participation Start'
    ) !== false,
    'cycle membership UI uses physical participation language'
);

$check(
    strpos(
        $view,
        "\$animal['farm_entry_date']"
    ) !== false,
    'membership defaults from farm entry rather than tag date'
);

$check(
    strpos(
        $js,
        "'farm_entry_date'"
    ) !== false,
    'registry edit JavaScript preserves farm entry'
);

$check(
    strpos(
        $migration,
        'MIN(start_date) AS first_membership_date'
    ) !== false,
    'migration prefers earliest explicit membership evidence'
);

$check(
    strpos(
        $migration,
        'a.purchase_date'
    ) !== false
    &&
    strpos(
        $migration,
        'a.birth_date'
    ) !== false
    &&
    strpos(
        $migration,
        'DATE(a.created_at)'
    ) !== false,
    'historical backfill has conservative provenance fallbacks'
);

$check(
    strpos(
        $migration,
        'MODIFY farm_entry_date DATE NOT NULL'
    ) === false,
    'migration 075 remains additive and deployment compatible'
);

$check(
    strpos(
        $migration,
        'farm_entry_date DATE NULL'
    ) !== false,
    'migration 075 initially adds nullable farm entry'
);

$check(
    strpos(
        $enforcementMigration,
        'MODIFY farm_entry_date DATE NOT NULL'
    ) !== false,
    'migration 076 enforces mandatory farm entry after deployment'
);

$check(
    strpos(
        $migration,
        "075_ruminant_farm_entry_date.sql"
    ) !== false,
    'migration 075 records schema version'
);

$check(
    strpos(
        $enforcementMigration,
        "076_ruminant_farm_entry_not_null.sql"
    ) !== false,
    'migration 076 records schema version'
);

require_once $entryFile;

$check(
    ruminant_animal_farm_entry_date_from_row([
        'farm_entry_date' => '2026-09-06',
        'purchase_date' => '2026-09-14',
        'birth_date' => '2026-03-01',
        'created_at' => '2026-09-17 03:17:08',
    ]) === '2026-09-06',
    'explicit physical entry overrides later purchase/registration evidence'
);

$check(
    ruminant_animal_farm_entry_date_from_row([
        'farm_entry_date' => null,
        'purchase_date' => '2026-09-14',
        'birth_date' => '2026-03-01',
    ]) === '2026-09-14',
    'legacy purchase fallback remains transitional'
);

$check(
    ruminant_animal_assert_farm_entry_date(
        '2026-09-06',
        '2026-03-01',
        '2026-09-23'
    ) === '2026-09-06',
    'late tagging can retain earlier physical farm entry'
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
        'RUMINANT_FARM_ENTRY_CONTRACT=PASS CHECKS='
        . count($checks)
        . ' FAILED=0'
        . PHP_EOL;

    exit(0);
}

echo
    'RUMINANT_FARM_ENTRY_CONTRACT=FAIL CHECKS='
    . count($checks)
    . ' FAILED='
    . $failed
    . PHP_EOL;

exit(1);
