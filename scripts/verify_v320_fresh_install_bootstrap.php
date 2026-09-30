<?php
declare(strict_types=1);

$root = dirname(__DIR__);

$schema = $root . '/database_schema.sql';
$seed = $root . '/database_seed.sql';
$manifest = $root . '/database_baseline_migrations.txt';
$bootstrap = $root . '/scripts/bootstrap_fresh_install.php';

$failures = [];

function pass(string $message): void
{
    echo "PASS: {$message}\n";
}

function failCheck(array &$failures, string $message): void
{
    $failures[] = $message;
    echo "FAIL: {$message}\n";
}

foreach (
    [
        'database_schema.sql' => $schema,
        'database_seed.sql' => $seed,
        'database_baseline_migrations.txt' => $manifest,
        'scripts/bootstrap_fresh_install.php' => $bootstrap,
    ] as $label => $path
) {
    if (is_file($path)) {
        pass("{$label} exists");
    } else {
        failCheck($failures, "{$label} exists");
    }
}

if ($failures) {
    exit(1);
}

$schemaSql = (string) file_get_contents($schema);
$seedSql = (string) file_get_contents($seed);
$bootstrapPhp = (string) file_get_contents($bootstrap);

$schemaTableCount = preg_match_all(
    '/^CREATE TABLE /m',
    $schemaSql
);

if ($schemaTableCount === 69) {
    pass('schema contains exactly 69 CREATE TABLE statements');
} else {
    failCheck(
        $failures,
        'schema contains exactly 69 CREATE TABLE statements'
    );
}

if (!preg_match('/^\s*INSERT\s+INTO/im', $schemaSql)) {
    pass('schema contains no row inserts');
} else {
    failCheck($failures, 'schema contains no row inserts');
}

$seedTargets = [];

preg_match_all(
    '/INSERT\s+INTO\s+`?([A-Za-z0-9_]+)`?/i',
    $seedSql,
    $targetMatches
);

foreach ($targetMatches[1] ?? [] as $target) {
    $seedTargets[$target] = true;
}

ksort($seedTargets);

if (array_keys($seedTargets) === ['permissions', 'roles']) {
    pass('seed targets only roles and permissions');
} else {
    failCheck(
        $failures,
        'seed targets only roles and permissions'
    );
}

$forbiddenTargets = [
    'farms',
    'users',
    'user_roles',
    'farm_modules',
    'financial_settings',
    'stock_transactions',
    'farm_subscription_seat_addons',
    'billing_payment_attempts',
    'account_credential_delivery_outbox',
];

$forbiddenFound = [];

foreach ($forbiddenTargets as $table) {
    if (preg_match(
        '/INSERT\s+INTO\s+`?'
        . preg_quote($table, '/')
        . '`?/i',
        $seedSql
    )) {
        $forbiddenFound[] = $table;
    }
}

if (!$forbiddenFound) {
    pass('seed contains no tenant or production-state inserts');
} else {
    failCheck(
        $failures,
        'seed contains no tenant or production-state inserts'
    );
}

$manifestLines = file(
    $manifest,
    FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
);

$manifestLines = $manifestLines === false
    ? []
    : array_values(array_map('trim', $manifestLines));

$manifestUnique = array_values(array_unique($manifestLines));

if (count($manifestLines) === 92) {
    pass('baseline manifest contains exactly 92 entries');
} else {
    failCheck(
        $failures,
        'baseline manifest contains exactly 92 entries'
    );
}

if (count($manifestUnique) === 92) {
    pass('baseline manifest contains no duplicate entries');
} else {
    failCheck(
        $failures,
        'baseline manifest contains no duplicate entries'
    );
}

$missingMigrationFiles = [];

foreach ($manifestUnique as $filename) {
    if (!preg_match('/^[0-9]{3}_.+\.sql$/', $filename)) {
        $missingMigrationFiles[] = 'invalid:' . $filename;
        continue;
    }

    if (!is_file($root . '/migrations/' . $filename)) {
        $missingMigrationFiles[] = $filename;
    }
}

if (!$missingMigrationFiles) {
    pass('every baseline manifest entry exists under migrations/');
} else {
    failCheck(
        $failures,
        'every baseline manifest entry exists under migrations/'
    );
}

$sourceMigrationFiles = glob($root . '/migrations/*.sql') ?: [];

$sourceMigrationNames = array_map(
    'basename',
    $sourceMigrationFiles
);

sort($sourceMigrationNames);

$manifestSorted = $manifestUnique;
sort($manifestSorted);

$missingBaselineMigrations = array_values(
    array_diff($manifestSorted, $sourceMigrationNames)
);

if (!$missingBaselineMigrations) {
    pass('every frozen baseline migration remains present in migration source');
} else {
    failCheck(
        $failures,
        'every frozen baseline migration remains present in migration source'
    );
}

$futureMigrationNames = array_values(
    array_diff($sourceMigrationNames, $manifestSorted)
);

if (count($manifestSorted) === 92) {
    pass('baseline manifest remains frozen at exactly 92 historical migrations');
} else {
    failCheck(
        $failures,
        'baseline manifest remains frozen at exactly 92 historical migrations'
    );
}

echo 'INFO: future migrations outside baseline manifest: '
    . count($futureMigrationNames)
    . "\n";

$requiredBootstrapMarkers = [
    'Fresh-install bootstrap requires an empty database.',
    'Expected 69',
    'Expected 6',
    'Expected 71',
    'exactly 92 filenames',
    'No tenant farms or users seeded.',
];

foreach ($requiredBootstrapMarkers as $marker) {
    if (str_contains($bootstrapPhp, $marker)) {
        pass("bootstrap contains guard: {$marker}");
    } else {
        failCheck(
            $failures,
            "bootstrap contains guard: {$marker}"
        );
    }
}

if ($failures) {
    echo "Fresh-install bootstrap verifier failed.\n";
    exit(1);
}

echo "Fresh-install bootstrap verifier passed.\n";
