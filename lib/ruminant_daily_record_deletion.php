<?php
declare(strict_types=1);

/**
 * Canonical deletion of one tenant-scoped Ruminant Daily Record.
 *
 * The caller remains responsible for authenticated session, CSRF, farm/module
 * access and the exact Ruminant Daily Delete permission. Neither privileged
 * nor delegated routes may bypass this service's shared business transaction.
 *
 * Lock order: source Daily Record -> linked feed/stock -> population projection
 * -> source deletion. No standalone stock or population SQL is duplicated.
 *
 * NOTE: B12 client-loaded revision validation is NOT enabled here until the
 * three-table revision migration and browser delete tokens are integrated.
 */
require_once __DIR__ . '/daily_feed_sync.php';
require_once __DIR__ . '/daily_population_sync.php';

if (!function_exists('ruminant_daily_record_delete_canonical')) {
    function ruminant_daily_record_delete_canonical(
        PDO $pdo,
        int $farmId,
        int $recordId,
        ?int $actorId
    ): void {
        if ($farmId <= 0 || $recordId <= 0) {
            throw new InvalidArgumentException(
                'A valid farm and Ruminant Daily Record are required.'
            );
        }
        if ($pdo->inTransaction()) {
            throw new LogicException(
                'Ruminant Daily Record deletion owns its transaction.'
            );
        }

        try {
            $pdo->beginTransaction();

            /*
             * Lock the authoritative source before any linked feed/stock
             * movements. Re-validate species and tenant WITHIN the lock;
             * pretransaction read permissions or UI snapshots are not trusted.
             */
            $recordStmt = $pdo->prepare(
                'SELECT id, animal_type FROM ruminant_daily_records'
                . ' WHERE id = ? AND farm_id = ? LIMIT 1 FOR UPDATE'
            );
            $recordStmt->execute([$recordId, $farmId]);
            $record = $recordStmt->fetch(PDO::FETCH_ASSOC);

            if (!is_array($record) || !in_array(
                strtolower(trim((string)$record['animal_type'])),
                ['cattle', 'goat', 'sheep', 'other'],
                true
            )) {
                throw new RuntimeException(
                    'Daily record not found or you do not have permission to delete it.'
                );
            }

            delete_daily_feed_usage(
                $pdo,
                $farmId,
                $recordId,
                'daily_ruminant_record'
            );

            daily_population_remove_mortality(
                $pdo,
                $farmId,
                'daily_ruminant_record',
                $recordId,
                $actorId
            );

            $deleteStmt = $pdo->prepare(
                'DELETE FROM ruminant_daily_records'
                . ' WHERE id = ? AND farm_id = ?'
            );
            $deleteStmt->execute([$recordId, $farmId]);
            if ($deleteStmt->rowCount() !== 1) {
                throw new RuntimeException(
                    'The daily record could not be deleted.'
                );
            }

            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    }
}
