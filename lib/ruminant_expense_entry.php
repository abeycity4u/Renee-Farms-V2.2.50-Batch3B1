<?php

declare(strict_types=1);

require_once __DIR__
    . '/../includes/expense_category_catalog.php';

require_once __DIR__
    . '/attribution.php';

require_once __DIR__
    . '/expense_revision_service.php';

require_once __DIR__
    . '/record_reference_persistence.php';

require_once __DIR__
    . '/ruminant_expense_allocation.php';


/*
 * V3.0.1 Canonical Ruminant Expense Entry Foundation.
 *
 * One persistence authority for new non-stock Ruminant expenses.
 *
 * Financial authority:
 *     farm_expenses
 *
 * Optional individual-animal attribution:
 *     ruminant_expense_animal_allocations
 *
 * Immutable provenance:
 *     farm_expense_revisions
 *
 * The caller owns:
 * - authorization;
 * - CSRF/request validation;
 * - the outer transaction boundary;
 * - success/error presentation.
 */


if (!function_exists(
    'ruminant_expense_entry_require_transaction'
)) {
function ruminant_expense_entry_require_transaction(
    PDO $pdo
): void {
    if (!$pdo->inTransaction()) {
        throw new RuntimeException(
            'Ruminant expense creation requires an active transaction.'
        );
    }
}
}


if (!function_exists(
    'ruminant_expense_entry_date'
)) {
function ruminant_expense_entry_date(
    $value
): string {
    $date =
        trim(
            (string)$value
        );

    $dateObject =
        DateTime::createFromFormat(
            'Y-m-d',
            $date
        );

    if (
        !$dateObject
        ||
        $dateObject->format(
            'Y-m-d'
        ) !== $date
    ) {
        throw new InvalidArgumentException(
            'Provide a valid expense date.'
        );
    }

    return $date;
}
}


if (!function_exists(
    'ruminant_expense_entry_positive_decimal'
)) {
function ruminant_expense_entry_positive_decimal(
    $value,
    string $label
): string {
    if (
        $value === null
        ||
        $value === ''
        ||
        !is_numeric(
            $value
        )
    ) {
        throw new InvalidArgumentException(
            $label
            . ' must be a valid number.'
        );
    }

    $number =
        (float)$value;

    if (
        !is_finite(
            $number
        )
        ||
        $number <= 0
    ) {
        throw new InvalidArgumentException(
            $label
            . ' must be greater than zero.'
        );
    }

    return
        number_format(
            $number,
            2,
            '.',
            ''
        );
}
}


if (!function_exists(
    'ruminant_expense_entry_category'
)) {
function ruminant_expense_entry_category(
    $value,
    string $surface = 'manual'
): string {
    return
        expense_category_normalize(
            $value,
            $surface
        );
}
}


if (!function_exists(
    'ruminant_expense_entry_create'
)) {
function ruminant_expense_entry_create(
    PDO $pdo,
    int $farmId,
    int $actorUserId,
    array $input,
    string $categorySurface = 'manual'
): array {
    ruminant_expense_entry_require_transaction(
        $pdo
    );

    if (
        $farmId < 1
        ||
        $actorUserId < 1
    ) {
        throw new InvalidArgumentException(
            'Ruminant expense actor or farm identity is invalid.'
        );
    }

    $expenseDate =
        ruminant_expense_entry_date(
            $input[
                'expense_date'
            ]
            ?? ''
        );

    $productionType =
        attribution_normalize_production_type(
            'ruminant',
            $input[
                'production_type'
            ]
            ?? 'shared'
        );

    $cycleId =
        (int)(
            $input[
                'cycle_id'
            ]
            ?? 0
        );

    if ($cycleId > 0) {
        try {
            attribution_validate_cycle(
                $pdo,
                $farmId,
                $cycleId,
                'ruminant',
                $productionType
            );

        } catch (PDOException $e) {
            throw $e;

        } catch (RuntimeException $e) {
            throw new InvalidArgumentException(
                $e->getMessage(),
                0,
                $e
            );
        }
    }

    $storedCycleId =
        $cycleId > 0
            ? $cycleId
            : null;

    $scope =
        attribution_scope(
            $storedCycleId,
            'ruminant',
            $productionType
        );

    $category =
        ruminant_expense_entry_category(
            $input[
                'category'
            ]
            ?? '',
            $categorySurface
        );

    $amount =
        ruminant_expense_entry_positive_decimal(
            $input[
                'amount'
            ]
            ?? null,
            'Expense amount'
        );

    $unit =
        ruminant_expense_entry_positive_decimal(
            $input[
                'unit'
            ]
            ?? 1,
            'Expense quantity'
        );

    $expenseTotal =
        round(
            (float)$amount
            *
            (float)$unit,
            2
        );

    if ($expenseTotal <= 0) {
        throw new InvalidArgumentException(
            'Expense total must be greater than zero.'
        );
    }

    $description =
        trim(
            (string)(
                $input[
                    'description'
                ]
                ?? ''
            )
        );

    $animalAllocation =
        ruminant_expense_build_animal_allocations(
            $pdo,
            $farmId,
            $productionType,
            $expenseTotal,
            $input
        );

    $expenseId =
        record_reference_persistence_insert_new(
            $pdo,
            'expense',
            static function (
                string $publicReference,
                string $createdAt
            ) use (
                $pdo,
                $farmId,
                $expenseDate,
                $productionType,
                $scope,
                $storedCycleId,
                $category,
                $amount,
                $unit,
                $description,
                $actorUserId
            ): int {
                $stmt =
                    $pdo->prepare(
                        "INSERT INTO farm_expenses
                            (
                                public_reference,
                                farm_id,
                                expense_date,
                                farm_type,
                                production_type,
                                attribution_scope,
                                cycle_id,
                                category,
                                amount,
                                unit,
                                description,
                                user_id,
                                created_at
                            )
                         VALUES
                            (
                                ?, ?, ?,
                                'ruminant',
                                ?, ?, ?, ?, ?, ?, ?, ?, ?
                            )"
                    );

                $stmt->execute([
                    $publicReference,
                    $farmId,
                    $expenseDate,
                    $productionType,
                    $scope,
                    $storedCycleId,
                    $category,
                    $amount,
                    $unit,
                    $description,
                    $actorUserId,
                    $createdAt,
                ]);

                return
                    (int)$pdo->lastInsertId();
            }
        );

    ruminant_expense_save_animal_allocations(
        $pdo,
        $farmId,
        $expenseId,
        $animalAllocation,
        $actorUserId
    );

    expense_revision_service_record_created(
        $pdo,
        $farmId,
        $expenseId,
        $actorUserId
    );

    return [
        'expense_id' =>
            $expenseId,

        'farm_type' =>
            'ruminant',

        'production_type' =>
            $productionType,

        'attribution_scope' =>
            $scope,

        'cycle_id' =>
            $storedCycleId,

        'category' =>
            $category,

        'amount' =>
            $amount,

        'unit' =>
            $unit,

        'expense_total' =>
            number_format(
                $expenseTotal,
                2,
                '.',
                ''
            ),

        'animal_allocation' =>
            $animalAllocation,
    ];
}
}
