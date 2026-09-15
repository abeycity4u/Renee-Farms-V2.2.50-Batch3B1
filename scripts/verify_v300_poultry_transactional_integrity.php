<?php
/**
 * V3.0 Step 5B1A — Poultry Transactional Integrity verifier.
 *
 * Static/pure verifier:
 * - no database connection;
 * - no database writes;
 * - verifies migration 059 is a narrowly bounded engine/FK repair.
 */

$root = dirname(__DIR__);
$migrationPath = $root . '/migrations/059_poultry_transactional_integrity.sql';

$checks = 0;
$failures = 0;

$check = static function (
    bool $ok,
    string $label
) use (&$checks, &$failures): void {
    $checks++;
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;

    if (!$ok) {
        $failures++;
    }
};

$sql = is_file($migrationPath)
    ? (string)file_get_contents($migrationPath)
    : '';

$check(
    $sql !== '',
    'migration 059 exists and is readable'
);

$check(
    substr_count(
        $sql,
        'ALTER TABLE poultry_cycle_acquisitions ENGINE=InnoDB;'
    ) === 1,
    'acquisition table is converted to InnoDB exactly once'
);

$check(
    substr_count(
        $sql,
        'ALTER TABLE production_cycle_phases ENGINE=InnoDB;'
    ) === 1,
    'lifecycle table is converted to InnoDB exactly once'
);

$expectedForeignKeys = [
    'fk_poultry_acquisition_farm FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE CASCADE',
    'fk_poultry_acquisition_cycle FOREIGN KEY (cycle_id) REFERENCES production_cycles(id) ON DELETE RESTRICT',
    'fk_poultry_acquisition_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL',
    'fk_poultry_phase_farm FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE CASCADE',
    'fk_poultry_phase_cycle FOREIGN KEY (cycle_id) REFERENCES production_cycles(id) ON DELETE RESTRICT',
    'fk_poultry_phase_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL',
];

foreach ($expectedForeignKeys as $foreignKey) {
    $check(
        substr_count($sql, $foreignKey) === 1,
        'migration restores ' . strtok($foreignKey, ' ')
    );
}

$check(
    substr_count(
        strtolower($sql),
        'from information_schema.referential_constraints'
    ) === 6,
    'all six foreign-key restores are existence-gated'
);

$prepareCount = preg_match_all(
    '/^PREPARE poultry_fk_stmt FROM @poultry_fk_sql;$/m',
    $sql
);

$executeCount = preg_match_all(
    '/^EXECUTE poultry_fk_stmt;$/m',
    $sql
);

$deallocateCount = preg_match_all(
    '/^DEALLOCATE PREPARE poultry_fk_stmt;$/m',
    $sql
);

$check(
    $prepareCount === 6
    && $executeCount === 6
    && $deallocateCount === 6,
    'all six conditional foreign-key statements are executed and deallocated'
);

$forbiddenPatterns = [
    'DROP TABLE',
    'TRUNCATE TABLE',
    'DELETE FROM',
    'INSERT INTO',
    'UPDATE poultry_cycle_acquisitions',
    'UPDATE production_cycle_phases',
    'FOREIGN_KEY_CHECKS',
    'DEFAULT_STORAGE_ENGINE',
    'SET GLOBAL',
    'SET SESSION',
    'CHARSET=',
    'CHARACTER SET',
    'COLLATE=',
];

$forbiddenFound = [];

foreach ($forbiddenPatterns as $pattern) {
    if (stripos($sql, $pattern) !== false) {
        $forbiddenFound[] = $pattern;
    }
}

$check(
    count($forbiddenFound) === 0,
    'migration contains no data rewrite, FK-disable, charset, or server-default change'
);

$check(
    stripos($sql, 'ALTER TABLE production_cycles') === false
    && stripos($sql, 'ALTER TABLE farms') === false
    && stripos($sql, 'ALTER TABLE users') === false,
    'parent production, farm, and user tables are not altered'
);

$check(
    stripos($sql, 'poultry_cycle_acquisitions') !== false
    && stripos($sql, 'production_cycle_phases') !== false,
    'migration scope is limited to the two intended poultry tables'
);

echo PHP_EOL;
echo "Checks: {$checks}" . PHP_EOL;
echo "Failures: {$failures}" . PHP_EOL;

if ($failures > 0) {
    echo "V3.0 POULTRY TRANSACTIONAL INTEGRITY: FAILED" . PHP_EOL;
    echo "DATABASE_CONNECTION_USED=NO" . PHP_EOL;
    echo "DATABASE_WRITE_PERFORMED=NO" . PHP_EOL;
    exit(1);
}

echo "V3.0 POULTRY TRANSACTIONAL INTEGRITY: PASSED" . PHP_EOL;
echo "DATABASE_CONNECTION_USED=NO" . PHP_EOL;
echo "DATABASE_WRITE_PERFORMED=NO" . PHP_EOL;
