<?php
/**
 * Renee Farms V3.0.1 — human-facing record-reference persistence.
 *
 * This layer owns persistence mechanics only:
 * - fixed entity/table mapping;
 * - database unique-collision recognition;
 * - bounded collision retry;
 * - immutable assignment to an existing NULL reference;
 * - controlled legacy backfill batches.
 *
 * It does not replace tenant authorization, primary keys, foreign keys,
 * source identity, reversal identity, or economic/provenance identity.
 */

require_once __DIR__ . '/record_reference.php';

if (!function_exists('record_reference_persistence_storage')) {
    function record_reference_persistence_storage(
        string $entity
    ): array {
        /*
         * Validate against the pure reference contract first.
         */
        record_reference_entity_config(
            $entity
        );

        $storage = [
            'stock_movement' => [
                'table' =>
                    'stock_transactions',

                'unique_index' =>
                    'uniq_stock_transaction_public_reference',
            ],

            'expense' => [
                'table' =>
                    'farm_expenses',

                'unique_index' =>
                    'uniq_farm_expense_public_reference',
            ],

            'sale' => [
                'table' =>
                    'sales_records',

                'unique_index' =>
                    'uniq_sale_public_reference',
            ],
        ];

        $entity =
            strtolower(
                trim($entity)
            );

        if (!isset($storage[$entity])) {
            throw new InvalidArgumentException(
                'Unsupported record reference storage entity.'
            );
        }

        return [
            'entity' =>
                $entity,

            'table' =>
                (string)$storage[$entity]['table'],

            'unique_index' =>
                (string)$storage[$entity]['unique_index'],
        ];
    }
}

if (!function_exists('record_reference_persistence_is_collision')) {
    function record_reference_persistence_is_collision(
        PDOException $error,
        string $entity
    ): bool {
        $storage =
            record_reference_persistence_storage(
                $entity
            );

        $sqlState =
            (string)(
                $error->errorInfo[0]
                ?? $error->getCode()
            );

        $driverCode =
            (int)(
                $error->errorInfo[1]
                ?? 0
            );

        $message =
            (string)$error->getMessage();

        return
            $sqlState === '23000'
            && $driverCode === 1062
            && stripos(
                $message,
                (string)$storage['unique_index']
            ) !== false;
    }
}

if (!function_exists('record_reference_persistence_with_retry')) {
    function record_reference_persistence_with_retry(
        string $entity,
        callable $operation,
        ?string $createdAt = null,
        int $maxAttempts = 5
    ) {
        record_reference_persistence_storage(
            $entity
        );

        if (
            $maxAttempts < 1
            || $maxAttempts > 20
        ) {
            throw new InvalidArgumentException(
                'Record reference retry limit is invalid.'
            );
        }

        for (
            $attempt = 1;
            $attempt <= $maxAttempts;
            $attempt++
        ) {
            $reference =
                record_reference_generate(
                    $entity,
                    $createdAt
                );

            try {
                return
                    $operation(
                        $reference
                    );
            } catch (PDOException $error) {
                if (
                    !record_reference_persistence_is_collision(
                        $error,
                        $entity
                    )
                ) {
                    throw $error;
                }

                if ($attempt >= $maxAttempts) {
                    throw new RuntimeException(
                        'Unable to assign a unique record reference after repeated collisions.',
                        0,
                        $error
                    );
                }
            }
        }

        throw new RuntimeException(
            'Unable to assign a record reference.'
        );
    }
}

if (!function_exists('record_reference_persistence_require_transaction')) {
    function record_reference_persistence_require_transaction(
        PDO $pdo
    ): void {
        if (!$pdo->inTransaction()) {
            throw new RuntimeException(
                'Record reference assignment requires an active database transaction.'
            );
        }
    }
}

if (!function_exists('record_reference_persistence_database_created_at')) {
    function record_reference_persistence_database_created_at(
        PDO $pdo
    ): string {
        /*
         * New business references must derive their date token from the
         * database posting timestamp, not an editable business/effective
         * date and not a separately sampled PHP clock.
         *
         * The same timestamp is handed to the insert callback so the
         * persisted created_at value and public-reference date token have
         * one canonical clock source.
         */
        record_reference_persistence_require_transaction(
            $pdo
        );

        $createdAt =
            trim(
                (string)$pdo
                    ->query(
                        'SELECT CURRENT_TIMESTAMP'
                    )
                    ->fetchColumn()
            );

        if ($createdAt === '') {
            throw new RuntimeException(
                'Unable to establish the record creation timestamp.'
            );
        }

        try {
            $date =
                new DateTimeImmutable(
                    $createdAt
                );

        } catch (Throwable $error) {
            throw new RuntimeException(
                'The database record creation timestamp is invalid.',
                0,
                $error
            );
        }

        if (
            $date->format(
                'Y-m-d H:i:s'
            ) !== $createdAt
        ) {
            throw new RuntimeException(
                'The database record creation timestamp format is invalid.'
            );
        }

        return $createdAt;
    }
}

if (!function_exists('record_reference_persistence_insert_new')) {
    function record_reference_persistence_insert_new(
        PDO $pdo,
        string $entity,
        callable $operation
    ): int {
        /*
         * This is the canonical path for creating a new business parent once
         * public_reference becomes a database NOT NULL invariant.
         *
         * The caller still owns business SQL and the surrounding transaction.
         * This helper owns:
         * - active-transaction enforcement;
         * - one database-sourced immutable created_at timestamp;
         * - reference generation;
         * - bounded unique-collision retry;
         * - validation that the successful insert returned a row identity.
         *
         * The operation receives:
         *   1. generated public reference;
         *   2. canonical database created_at timestamp.
         *
         * The operation must persist both values in its INSERT and return the
         * new integer primary key.
         */
        record_reference_persistence_require_transaction(
            $pdo
        );

        record_reference_persistence_storage(
            $entity
        );

        $createdAt =
            record_reference_persistence_database_created_at(
                $pdo
            );

        $recordId =
            record_reference_persistence_with_retry(
                $entity,
                static function (
                    string $reference
                ) use (
                    $operation,
                    $createdAt
                ): int {
                    $insertedId =
                        (int)$operation(
                            $reference,
                            $createdAt
                        );

                    if ($insertedId < 1) {
                        throw new RuntimeException(
                            'Record creation did not return a valid identity.'
                        );
                    }

                    return $insertedId;
                },
                $createdAt
            );

        return (int)$recordId;
    }
}

if (!function_exists('record_reference_persistence_assign_existing')) {
    function record_reference_persistence_assign_existing(
        PDO $pdo,
        string $entity,
        int $farmId,
        int $recordId
    ): string {
        record_reference_persistence_require_transaction(
            $pdo
        );

        if (
            $farmId <= 0
            || $recordId <= 0
        ) {
            throw new InvalidArgumentException(
                'A valid farm and record are required for reference assignment.'
            );
        }

        $storage =
            record_reference_persistence_storage(
                $entity
            );

        $table =
            (string)$storage['table'];

        /*
         * Table identifier comes only from the fixed internal mapping above.
         * It is never supplied by a request or caller.
         */
        $lock =
            $pdo->prepare(
                "SELECT
                     id,
                     farm_id,
                     public_reference,
                     created_at
                 FROM {$table}
                 WHERE id=?
                   AND farm_id=?
                 LIMIT 1
                 FOR UPDATE"
            );

        $lock->execute([
            $recordId,
            $farmId,
        ]);

        $row =
            $lock->fetch(
                PDO::FETCH_ASSOC
            );

        if (!$row) {
            throw new RuntimeException(
                'Record reference source row was not found in this farm.'
            );
        }

        $existing =
            trim(
                (string)(
                    $row['public_reference']
                    ?? ''
                )
            );

        if ($existing !== '') {
            if (
                !record_reference_is_valid(
                    $existing,
                    $entity
                )
            ) {
                throw new RuntimeException(
                    'Existing record reference is invalid and was not rewritten.'
                );
            }

            return $existing;
        }

        /*
         * The reference date is always derived from the database row's
         * immutable creation timestamp. Callers cannot override it with an
         * editable business/effective date.
         */
        $referenceCreatedAt =
            trim(
                (string)(
                    $row['created_at']
                    ?? ''
                )
            );

        if ($referenceCreatedAt === '') {
            throw new RuntimeException(
                'Record creation timestamp is missing; reference was not assigned.'
            );
        }

        return
            record_reference_persistence_with_retry(
                $entity,
                static function (
                    string $candidate
                ) use (
                    $pdo,
                    $table,
                    $recordId,
                    $farmId
                ): string {
                    $update =
                        $pdo->prepare(
                            "UPDATE {$table}
                             SET public_reference=?
                             WHERE id=?
                               AND farm_id=?
                               AND public_reference IS NULL"
                        );

                    $update->execute([
                        $candidate,
                        $recordId,
                        $farmId,
                    ]);

                    if ($update->rowCount() !== 1) {
                        throw new RuntimeException(
                            'Record reference could not be assigned immutably.'
                        );
                    }

                    return $candidate;
                },
                $referenceCreatedAt
            );
    }
}

if (!function_exists('record_reference_persistence_backfill_batch')) {
    function record_reference_persistence_backfill_batch(
        PDO $pdo,
        string $entity,
        int $batchSize = 250
    ): int {
        if (
            $batchSize < 1
            || $batchSize > 1000
        ) {
            throw new InvalidArgumentException(
                'Record reference backfill batch size must be between 1 and 1000.'
            );
        }

        $storage =
            record_reference_persistence_storage(
                $entity
            );

        $table =
            (string)$storage['table'];

        $startedTransaction =
            !$pdo->inTransaction();

        if ($startedTransaction) {
            $pdo->beginTransaction();
        }

        try {
            /*
             * LIMIT is interpolated only after strict integer range validation.
             * Table name comes only from the fixed internal mapping.
             */
            $select =
                $pdo->prepare(
                    "SELECT
                         id,
                         farm_id
                     FROM {$table}
                     WHERE public_reference IS NULL
                     ORDER BY id
                     LIMIT {$batchSize}
                     FOR UPDATE"
                );

            $select->execute();

            $rows =
                $select->fetchAll(
                    PDO::FETCH_ASSOC
                ) ?: [];

            foreach ($rows as $row) {
                record_reference_persistence_assign_existing(
                    $pdo,
                    $entity,
                    (int)$row['farm_id'],
                    (int)$row['id']
                );
            }

            if ($startedTransaction) {
                $pdo->commit();
            }

            return count($rows);

        } catch (Throwable $error) {
            if (
                $startedTransaction
                && $pdo->inTransaction()
            ) {
                $pdo->rollBack();
            }

            throw $error;
        }
    }
}
