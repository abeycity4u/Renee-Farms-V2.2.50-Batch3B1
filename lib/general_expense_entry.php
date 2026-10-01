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


/*
 * Canonical General Operating Expense Entry.
 *
 * Financial authority:
 *     farm_expenses
 *
 * Canonical General attribution:
 *     farm_type         = general
 *     production_type   = general
 *     attribution_scope = farm
 *     cycle_id           = NULL
 *
 * This service owns validation and persistence only.
 *
 * The caller owns:
 * - tenant/product authorization;
 * - user authorization;
 * - CSRF/request validation;
 * - the outer transaction boundary;
 * - success/error presentation.
 */

if (!function_exists('general_expense_entry_require_transaction')) {
    function general_expense_entry_require_transaction(PDO $pdo): void
    {
        if (!$pdo->inTransaction()) {
            throw new RuntimeException(
                'General expense creation requires an active transaction.'
            );
        }
    }
}


if (!function_exists('general_expense_entry_date')) {
    function general_expense_entry_date($value): string
    {
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
            $dateObject->format('Y-m-d') !== $date
        ) {
            throw new InvalidArgumentException(
                'Provide a valid expense date.'
            );
        }

        return $date;
    }
}


if (!function_exists('general_expense_entry_positive_decimal')) {
    function general_expense_entry_positive_decimal(
        $value,
        string $label
    ): string {
        if (
            $value === null
            ||
            $value === ''
            ||
            !is_numeric($value)
        ) {
            throw new InvalidArgumentException(
                $label . ' must be a valid number.'
            );
        }

        $number =
            (float)$value;

        if (
            !is_finite($number)
            ||
            $number <= 0
        ) {
            throw new InvalidArgumentException(
                $label . ' must be greater than zero.'
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


if (!function_exists('general_expense_entry_category')) {
    function general_expense_entry_category($value): string
    {
        /*
         * Manual surface deliberately excludes Inventory-owned historical
         * categories such as Feed and Medication.
         */
        return expense_category_normalize(
            $value,
            'general_operating'
        );
    }
}


if (!function_exists('general_expense_entry_attribution')) {
    function general_expense_entry_attribution(): array
    {
        $farmType =
            'general';

        $productionType =
            attribution_normalize_production_type(
                $farmType,
                null
            );

        $cycleId =
            null;

        $scope =
            attribution_scope(
                $cycleId,
                $farmType,
                $productionType
            );

        if (
            $productionType !== 'general'
            ||
            $scope !== 'farm'
        ) {
            throw new RuntimeException(
                'General expense attribution is inconsistent.'
            );
        }

        return [
            'farm_type' =>
                $farmType,

            'production_type' =>
                $productionType,

            'attribution_scope' =>
                $scope,

            'cycle_id' =>
                $cycleId,
        ];
    }
}


if (!function_exists('general_expense_entry_create')) {
    function general_expense_entry_create(
        PDO $pdo,
        int $farmId,
        int $actorUserId,
        array $input
    ): array {
        general_expense_entry_require_transaction(
            $pdo
        );

        if (
            $farmId < 1
            ||
            $actorUserId < 1
        ) {
            throw new InvalidArgumentException(
                'General expense actor or farm identity is invalid.'
            );
        }

        $expenseDate =
            general_expense_entry_date(
                $input['expense_date']
                ?? ''
            );

        $category =
            general_expense_entry_category(
                $input['category']
                ?? ''
            );

        $amount =
            general_expense_entry_positive_decimal(
                $input['amount']
                ?? null,
                'Expense amount'
            );

        $unit =
            general_expense_entry_positive_decimal(
                $input['unit']
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
                    $input['description']
                    ?? ''
                )
            );

        $attribution =
            general_expense_entry_attribution();

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
                    $attribution,
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
                                    poultry_category,
                                    category,
                                    amount,
                                    unit,
                                    description,
                                    user_id,
                                    created_at
                                )
                             VALUES
                                (
                                    ?, ?, ?, ?, ?, ?, NULL, NULL,
                                    ?, ?, ?, ?, ?, ?
                                )"
                        );

                    $stmt->execute([
                        $publicReference,
                        $farmId,
                        $expenseDate,
                        $attribution['farm_type'],
                        $attribution['production_type'],
                        $attribution['attribution_scope'],
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
                $attribution['farm_type'],

            'production_type' =>
                $attribution['production_type'],

            'attribution_scope' =>
                $attribution['attribution_scope'],

            'cycle_id' =>
                null,

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
        ];
    }
}
