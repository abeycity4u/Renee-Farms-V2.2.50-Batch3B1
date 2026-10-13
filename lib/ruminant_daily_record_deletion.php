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
require_once __DIR__ . '/daily_record_revision_authority.php';
require_once __DIR__ . '/daily_record_revision_cutover_gate.php';

if (!function_exists('ruminant_daily_record_delete_canonical')) {
    function ruminant_daily_record_delete_canonical(
        PDO $pdo,
        int $farmId,
        int $recordId,
        ?int $actorId,
        ?array $post = null
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

        /*
         * B12 FUTURE cutover, hard-disabled until schema, browser identity
         * tokens and every writer move together. Both privileged and
         * delegated callers pass the originally submitted form values;
         * neither creates revision tokens from a fresh database read.
         */
        if (daily_record_revision_cutover_enabled()) {
            require_once __DIR__ . '/daily_record_revision_delete_pipeline.php';
            if ($post === null || $actorId === null) {
                throw new DailyRecordRevisionConflict(
                    'The Daily Record delete form is incomplete.'
                );
            }
            $authorized = static function(
                string $module, string $view, string $permission
            ): bool {
                return function_exists('checkAccess')
                    && function_exists('permission_runtime_has')
                    && checkAccess($module)
                    && permission_runtime_has($view)
                    && permission_runtime_has($permission);
            };
            daily_record_revision_delete_transaction(
                $pdo, 'ruminant', $farmId, $actorId, $post, $authorized
            );
            return;
        }

        try {
            if (!$pdo->beginTransaction()) {
                throw new RuntimeException(
                    'Ruminant Daily Record deletion could not begin its transaction.'
                );
            }

            /*
             * Lock the authoritative source before any linked feed/stock
             * movements. Re-validate species and tenant WITHIN the lock;
             * pretransaction read permissions or UI snapshots are not trusted.
             */
            daily_record_revision_lock_source_for_delete(
                $pdo,
                'ruminant',
                $farmId,
                $recordId
            );

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
