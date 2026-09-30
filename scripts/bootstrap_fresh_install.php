<?php
declare(strict_types=1);

/**
 * Renee AgriSuite fresh-install bootstrap.
 *
 * Intended only for a brand-new empty database.
 *
 * Flow:
 *   1. verify database is empty
 *   2. import database_schema.sql
 *   3. import database_seed.sql
 *   4. register baseline migration filenames
 *   5. leave future migrations for scripts/run_migrations.php
 */

require_once __DIR__ . '/../config.php';

$root = dirname(__DIR__);

$schemaFile = $root . '/database_schema.sql';
$seedFile = $root . '/database_seed.sql';
$baselineManifestFile = $root . '/database_baseline_migrations.txt';

foreach ([$schemaFile, $seedFile, $baselineManifestFile] as $requiredFile) {
    if (!is_file($requiredFile)) {
        throw new RuntimeException(
            'Missing fresh-install source file: ' . basename($requiredFile)
        );
    }
}

function fetchBaseTableCount(PDO $pdo): int
{
    $stmt = $pdo->query(
        "SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_type = 'BASE TABLE'"
    );

    return (int) $stmt->fetchColumn();
}

function executeSqlFile(PDO $pdo, string $file): void
{
    $sql = file_get_contents($file);

    if ($sql === false) {
        throw new RuntimeException(
            'Unable to read SQL file: ' . basename($file)
        );
    }

    /*
     * database_schema.sql is a mysqldump-style file.
     * PDO::exec() can execute the complete multi-statement payload
     * with MySQL emulated prepares / native connection handling.
     */
    $pdo->exec($sql);
}

$tableCountBefore = fetchBaseTableCount($pdo);

if ($tableCountBefore !== 0) {
    throw new RuntimeException(
        'Fresh-install bootstrap requires an empty database. '
        . 'Existing base table count: '
        . $tableCountBefore
    );
}

echo "Fresh-install empty database gate passed.\n";

executeSqlFile($pdo, $schemaFile);

$tableCountAfterSchema = fetchBaseTableCount($pdo);

if ($tableCountAfterSchema !== 69) {
    throw new RuntimeException(
        'Fresh-install schema table count mismatch. '
        . 'Expected 69, got '
        . $tableCountAfterSchema
    );
}

echo "Imported database_schema.sql: 69 tables.\n";

executeSqlFile($pdo, $seedFile);

$roleCount = (int) $pdo
    ->query("SELECT COUNT(*) FROM roles")
    ->fetchColumn();

$globalPermissionCount = (int) $pdo
    ->query("SELECT COUNT(*) FROM permissions WHERE farm_id = 0")
    ->fetchColumn();

if ($roleCount !== 6) {
    throw new RuntimeException(
        'Fresh-install role seed mismatch. '
        . 'Expected 6, got '
        . $roleCount
    );
}

if ($globalPermissionCount !== 71) {
    throw new RuntimeException(
        'Fresh-install global permission seed mismatch. '
        . 'Expected 71, got '
        . $globalPermissionCount
    );
}

echo "Imported database_seed.sql: 6 roles, 71 global permissions.\n";

$manifestLines = file(
    $baselineManifestFile,
    FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
);

if ($manifestLines === false) {
    throw new RuntimeException(
        'Unable to read baseline migration manifest.'
    );
}

$manifest = array_values(
    array_unique(
        array_map('trim', $manifestLines)
    )
);

sort($manifest);

if (count($manifest) !== 92) {
    throw new RuntimeException(
        'Baseline migration manifest must contain exactly 92 filenames.'
    );
}

$insert = $pdo->prepare(
    "INSERT INTO schema_migrations (filename)
     VALUES (?)
     ON DUPLICATE KEY UPDATE filename = VALUES(filename)"
);

$pdo->beginTransaction();

try {
    foreach ($manifest as $filename) {
        if (!preg_match('/^[0-9]{3}_.+\.sql$/', $filename)) {
            throw new RuntimeException(
                'Invalid baseline migration filename: ' . $filename
            );
        }

        $sourceMigration = $root . '/migrations/' . $filename;

        if (!is_file($sourceMigration)) {
            throw new RuntimeException(
                'Baseline migration missing from source: ' . $filename
            );
        }

        $insert->execute([$filename]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    throw $e;
}

$historyCount = (int) $pdo
    ->query("SELECT COUNT(*) FROM schema_migrations")
    ->fetchColumn();

if ($historyCount !== 92) {
    throw new RuntimeException(
        'Baseline migration history mismatch. '
        . 'Expected 92, got '
        . $historyCount
    );
}

echo "Registered 92 baseline migration filenames.\n";

$tenantFarmCount = (int) $pdo
    ->query("SELECT COUNT(*) FROM farms")
    ->fetchColumn();

$userCount = (int) $pdo
    ->query("SELECT COUNT(*) FROM users")
    ->fetchColumn();

if ($tenantFarmCount !== 0 || $userCount !== 0) {
    throw new RuntimeException(
        'Fresh-install bootstrap unexpectedly created tenant/user state.'
    );
}

echo "No tenant farms or users seeded.\n";
echo "Fresh-install bootstrap complete.\n";
