<?php

declare(strict_types=1);

require_once __DIR__
    . '/../includes/functions.php';

require_once __DIR__
    . '/../includes/permission_catalog.php';

require_once __DIR__
    . '/attribution.php';

require_once __DIR__
    . '/expense_revision_service.php';

require_once __DIR__
    . '/record_reference_persistence.php';


/*
 * V3.0.1 Canonical Poultry Expense Entry Foundation.
 *
 * One entry authority for:
 * - Layer
 * - Broiler
 * - Poultry Shared
 *
 * This service owns creation semantics only.
 *
 * It intentionally does not begin or commit a transaction. The calling route
 * owns the outer transaction boundary.
 *
 * Poultry Shared canonical representation:
 *
 * farm_type         = poultry
 * production_type   = shared
 * attribution_scope = farm
 * cycle_id           = NULL
 * poultry_category   = NULL
 *
 * Layer/Broiler with no cycle remain production-level shared parents.
 */


if (!function_exists(
    'poultry_expense_entry_supported_production_types'
)) {
function poultry_expense_entry_supported_production_types(): array
{
    return [
        'layer',
        'broiler',
        'shared',
    ];
}
}


if (!function_exists(
    'poultry_expense_entry_production_type'
)) {
function poultry_expense_entry_production_type(
    $value
): string {
    $productionType =
        strtolower(
            trim(
                (string)$value
            )
        );

    if (
        !in_array(
            $productionType,
            poultry_expense_entry_supported_production_types(),
            true
        )
    ) {
        throw new InvalidArgumentException(
            'Select Layer, Broiler or Shared for the Poultry expense.'
        );
    }

    return $productionType;
}
}


if (!function_exists(
    'poultry_expense_entry_can'
)) {
function poultry_expense_entry_can(
    string $productionType,
    string $action
): bool {
    $productionType =
        poultry_expense_entry_production_type(
            $productionType
        );

    if (
        isPlatformOwner()
        ||
        hasRole(
            'farm_admin'
        )
    ) {
        return true;
    }

    $required =
        permission_catalog_poultry_expense_required_permissions(
            $productionType,
            $action
        );

    if ($required === []) {
        return false;
    }

    foreach ($required as $permission) {
        if (
            !hasPermission(
                getUserType(),
                $permission
            )
        ) {
            return false;
        }
    }

    return true;
}
}


if (!function_exists(
    'poultry_expense_entry_require_transaction'
)) {
function poultry_expense_entry_require_transaction(
    PDO $pdo
): void {
    if (!$pdo->inTransaction()) {
        throw new RuntimeException(
            'Poultry expense creation requires an active transaction.'
        );
    }
}
}


if (!function_exists(
    'poultry_expense_entry_date'
)) {
function poultry_expense_entry_date(
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
    'poultry_expense_entry_positive_decimal'
)) {
function poultry_expense_entry_positive_decimal(
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

    return number_format(
        $number,
        2,
        '.',
        ''
    );
}
}


if (!function_exists(
    'poultry_expense_entry_category'
)) {
function poultry_expense_entry_category(
    $value
): string {
    $category =
        strtolower(
            trim(
                (string)$value
            )
        );

    $allowed = [
        'salary',
        'logistic',
        'fuel',
        'misc',
    ];

    if (
        !in_array(
            $category,
            $allowed,
            true
        )
    ) {
        throw new InvalidArgumentException(
            'Stock purchases such as feed, medication and vaccines are recorded through Inventory. Choose a non-stock expense category.'
        );
    }

    return $category;
}
}


if (!function_exists(
    'poultry_expense_entry_create'
)) {
function poultry_expense_entry_create(
    PDO $pdo,
    int $farmId,
    int $actorUserId,
    array $input
): array {
    poultry_expense_entry_require_transaction(
        $pdo
    );

    if (
        $farmId < 1
        ||
        $actorUserId < 1
    ) {
        throw new InvalidArgumentException(
            'Poultry expense actor or farm identity is invalid.'
        );
    }

    $productionType =
        poultry_expense_entry_production_type(
            $input[
                'production_type'
            ]
            ?? ''
        );

    if (
        !poultry_expense_entry_can(
            $productionType,
            'add'
        )
    ) {
        throw new RuntimeException(
            'You do not have permission to record this Poultry expense.'
        );
    }

    $expenseDate =
        poultry_expense_entry_date(
            $input[
                'expense_date'
            ]
            ?? ''
        );

    $category =
        poultry_expense_entry_category(
            $input[
                'category'
            ]
            ?? ''
        );

    $amount =
        poultry_expense_entry_positive_decimal(
            $input[
                'amount'
            ]
            ?? null,
            'Expense amount'
        );

    $unit =
        poultry_expense_entry_positive_decimal(
            $input[
                'unit'
            ]
            ?? 1,
            'Expense quantity'
        );

    $description =
        trim(
            (string)(
                $input[
                    'description'
                ]
                ?? ''
            )
        );

    $cycleId =
        (int)(
            $input[
                'cycle_id'
            ]
            ?? 0
        );

    if (
        $productionType === 'shared'
        &&
        $cycleId > 0
    ) {
        throw new InvalidArgumentException(
            'Poultry-wide shared expenses cannot be assigned directly to a production cycle.'
        );
    }

    if (
        $productionType !== 'shared'
        &&
        $cycleId > 0
    ) {
        try {
            attribution_validate_cycle(
                $pdo,
                $farmId,
                $cycleId,
                'poultry',
                $productionType
            );

        } catch (PDOException $e) {
            /*
             * Database failures remain internal. Do not convert database
             * exception details into user-visible validation text.
             */
            throw $e;

        } catch (RuntimeException $e) {
            /*
             * attribution_validate_cycle() uses RuntimeException for its
             * expected farm/type/cycle validation outcomes. Promote only
             * those domain validation messages to the caller's safe
             * validation branch.
             */
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

    $attributionScope =
        attribution_scope(
            $storedCycleId,
            'poultry',
            $productionType
        );

    $poultryCategory =
        $productionType === 'shared'
            ? null
            : $productionType;

    /*
     * Fail closed if attribution helpers ever stop producing the canonical
     * Poultry Shared representation.
     */
    if (
        $productionType === 'shared'
        &&
        (
            $storedCycleId !== null
            ||
            $attributionScope !== 'farm'
            ||
            $poultryCategory !== null
        )
    ) {
        throw new RuntimeException(
            'Poultry Shared expense attribution is inconsistent.'
        );
    }

    $stmt =
        $pdo->prepare(
            "INSERT INTO farm_expenses
                (
                    farm_id,
                    expense_date,
                    farm_type,
                    production_type,
                    attribution_scope,
                    cycle_id,
                    poultry_category,
                    category,
                    amount,
                    unit,
                    description,
                    user_id
                )
             VALUES
                (
                    ?,?,
                    'poultry',
                    ?,?,?,?,?,?,?,?,?
                )"
        );

    $stmt->execute([
        $farmId,
        $expenseDate,
        $productionType,
        $attributionScope,
        $storedCycleId,
        $poultryCategory,
        $category,
        $amount,
        $unit,
        $description,
        $actorUserId,
    ]);

    $expenseId =
        (int)$pdo->lastInsertId();

    if ($expenseId < 1) {
        throw new RuntimeException(
            'Poultry expense creation did not return a valid record identity.'
        );
    }

    record_reference_persistence_assign_existing(
        $pdo,
        'expense',
        $farmId,
        $expenseId
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
            'poultry',

        'production_type' =>
            $productionType,

        'attribution_scope' =>
            $attributionScope,

        'cycle_id' =>
            $storedCycleId,

        'poultry_category' =>
            $poultryCategory,

        'category' =>
            $category,

        'amount' =>
            $amount,

        'unit' =>
            $unit,
    ];
}
}
