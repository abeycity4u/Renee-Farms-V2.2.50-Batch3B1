<?php
/**
 * Renee Farms V3.0.1 — legacy public-reference backfill worker.
 *
 * SAFETY:
 * - CLI only.
 * - Dry-run by default.
 * - --apply is required for mutation.
 * - Migration 066 must already be recorded.
 * - Required columns must exist before processing.
 * - Assignment delegates to the canonical persistence service.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not available.');
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/record_reference_persistence.php';

$apply =
    in_array(
        '--apply',
        $argv,
        true
    );

$batchSize = 250;

foreach ($argv as $argument) {
    if (
        strpos(
            $argument,
            '--batch-size='
        ) === 0
    ) {
        $batchSize =
            (int)substr(
                $argument,
                strlen('--batch-size=')
            );
    }
}

if (
    $batchSize < 1
    || $batchSize > 1000
) {
    fwrite(
        STDERR,
        "Batch size must be between 1 and 1000.\n"
    );

    exit(2);
}

$migration =
    '066_human_facing_record_references.sql';

$migrationStmt =
    $pdo->prepare(
        'SELECT COUNT(*)
         FROM schema_migrations
         WHERE filename=?'
    );

$migrationStmt->execute([
    $migration,
]);

if (
    (int)$migrationStmt->fetchColumn()
    !== 1
) {
    fwrite(
        STDERR,
        "Migration 066 has not been applied. No reference backfill was attempted.\n"
    );

    exit(2);
}

$entities = [
    'stock_movement',
    'expense',
    'sale',
];

foreach ($entities as $entity) {
    $storage =
        record_reference_persistence_storage(
            $entity
        );

    $table =
        (string)$storage['table'];

    $columnStmt =
        $pdo->prepare(
            'SELECT COUNT(*)
             FROM information_schema.columns
             WHERE table_schema=DATABASE()
               AND table_name=?
               AND column_name=\'public_reference\''
        );

    $columnStmt->execute([
        $table,
    ]);

    if (
        (int)$columnStmt->fetchColumn()
        !== 1
    ) {
        fwrite(
            STDERR,
            "Missing public_reference on {$table}. No backfill was attempted.\n"
        );

        exit(2);
    }
}

echo
    $apply
        ? "MODE=APPLY\n"
        : "MODE=DRY_RUN\n";

echo
    "BATCH_SIZE="
    . $batchSize
    . PHP_EOL;

if (!$apply) {
    foreach ($entities as $entity) {
        $storage =
            record_reference_persistence_storage(
                $entity
            );

        $table =
            (string)$storage['table'];

        $countStmt =
            $pdo->query(
                "SELECT COUNT(*)
                 FROM {$table}
                 WHERE public_reference IS NULL"
            );

        echo
            strtoupper($entity)
            . '_PENDING='
            . (int)$countStmt->fetchColumn()
            . PHP_EOL;
    }

    echo
        "DRY_RUN_ONLY=YES\n"
        . "DATABASE_WRITE=NONE\n"
        . "Use --apply only after backup and explicit deployment approval.\n";

    exit(0);
}

$totalAssigned = 0;

foreach ($entities as $entity) {
    $entityAssigned = 0;

    do {
        $processed =
            record_reference_persistence_backfill_batch(
                $pdo,
                $entity,
                $batchSize
            );

        $entityAssigned +=
            $processed;

        $totalAssigned +=
            $processed;

        if ($processed > 0) {
            echo
                strtoupper($entity)
                . '_ASSIGNED='
                . $entityAssigned
                . PHP_EOL;
        }
    } while ($processed > 0);
}

echo
    "TOTAL_ASSIGNED="
    . $totalAssigned
    . PHP_EOL;

echo
    "RESULT=PASS\n";
