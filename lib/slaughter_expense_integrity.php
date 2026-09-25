<?php

declare(strict_types=1);

/*
 * V3.0.1 Shared Slaughter Expense Mutation Integrity.
 *
 * Canonical farm_expenses linked to slaughter processing carry immutable
 * revision provenance used for physical-output valuation.
 *
 * Generic expense edit/delete surfaces must therefore fail closed for linked
 * processing expenses. Corrections belong to the slaughter-processing domain,
 * not to unrestricted mutation of the current financial projection.
 *
 * This service never begins, commits or rolls back a transaction.
 */


if (!function_exists(
    'slaughter_expense_integrity_require_transaction'
)) {
function slaughter_expense_integrity_require_transaction(
    PDO $pdo
): void {
    if (!$pdo->inTransaction()) {
        throw new RuntimeException(
            'Slaughter expense integrity validation requires an active transaction.'
        );
    }
}
}


if (!function_exists(
    'slaughter_expense_integrity_table_exists'
)) {
function slaughter_expense_integrity_table_exists(
    PDO $pdo,
    string $tableName
): bool {
    $stmt =
        $pdo->prepare(
            'SELECT COUNT(*)
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA=DATABASE()
               AND TABLE_NAME=?'
        );

    $stmt->execute([
        $tableName,
    ]);

    return
        (int)$stmt->fetchColumn()
        === 1;
}
}


if (!function_exists(
    'slaughter_expense_integrity_link'
)) {
function slaughter_expense_integrity_link(
    PDO $pdo,
    string $tableName,
    string $batchTable,
    int $farmId,
    int $expenseId
): ?array {
    if (
        !preg_match(
            '/^[a-z0-9_]+$/',
            $tableName
        )
        ||
        !preg_match(
            '/^[a-z0-9_]+$/',
            $batchTable
        )
    ) {
        throw new InvalidArgumentException(
            'Slaughter expense integrity table identity is invalid.'
        );
    }

    if (
        !slaughter_expense_integrity_table_exists(
            $pdo,
            $tableName
        )
    ) {
        return null;
    }

    $sql =
        "SELECT
             l.id AS link_id,
             l.batch_id,
             b.batch_code,
             b.status
         FROM {$tableName} l
         INNER JOIN {$batchTable} b
           ON b.id=l.batch_id
          AND b.farm_id=l.farm_id
         WHERE l.farm_id=?
           AND l.expense_id=?
         ORDER BY l.id
         LIMIT 1
         FOR UPDATE";

    $stmt =
        $pdo->prepare(
            $sql
        );

    $stmt->execute([
        $farmId,
        $expenseId,
    ]);

    $row =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    return
        $row
            ?: null;
}
}


if (!function_exists(
    'slaughter_expense_integrity_assert_mutable'
)) {
function slaughter_expense_integrity_assert_mutable(
    PDO $pdo,
    int $farmId,
    int $expenseId
): void {
    slaughter_expense_integrity_require_transaction(
        $pdo
    );

    if (
        $farmId < 1
        ||
        $expenseId < 1
    ) {
        throw new InvalidArgumentException(
            'Expense identity is invalid for slaughter integrity validation.'
        );
    }

    $poultryLink =
        slaughter_expense_integrity_link(
            $pdo,
            'poultry_slaughter_batch_expenses',
            'poultry_slaughter_batches',
            $farmId,
            $expenseId
        );

    if ($poultryLink !== null) {
        throw new RuntimeException(
            'This expense is linked to Poultry slaughter processing and cannot be edited or deleted from the general expense workflow.'
        );
    }

    $ruminantLink =
        slaughter_expense_integrity_link(
            $pdo,
            'ruminant_slaughter_batch_expenses',
            'ruminant_slaughter_batches',
            $farmId,
            $expenseId
        );

    if ($ruminantLink !== null) {
        throw new RuntimeException(
            'This expense is linked to Ruminant slaughter processing and cannot be edited or deleted from the general expense workflow.'
        );
    }
}
}
