<?php
require_once __DIR__ . '/shared_cost_contract.php';

/*
 * V3.0.1 Financial Allocation Source Contract.
 *
 * This service centralizes eligibility and causal allocation policy before
 * any runtime financial-allocation writer is introduced.
 *
 * Current cut intentionally contains NO INSERT/UPDATE/DELETE against
 * financial_allocations.
 *
 * Core contract:
 * - only genuinely shared/no-specific-cycle expenses are allocatable;
 * - malformed historical attribution fails closed;
 * - historical manual Feed expenses are not allocatable;
 * - financial cycle allocation and ruminant animal allocation cannot overlap;
 * - allocated_amount is economic authority;
 * - allocation_percent is derived metadata;
 * - target cycles must stay inside the parent's farm/type boundary;
 * - closed cycles remain eligible for legitimate historical correction;
 * - total allocated amount cannot exceed parent expense gross value.
 *
 * A later mutation layer must execute allocation writes inside the parent
 * expense revision transaction so financial allocation state remains part of
 * the immutable expense provenance chain.
 */

if (!function_exists('financial_allocation_service_normalize')) {
function financial_allocation_service_normalize($value): string
{
    return strtolower(
        trim(
            (string)$value
        )
    );
}
}


if (!function_exists('financial_allocation_service_positive_number')) {
function financial_allocation_service_positive_number(
    $value,
    string $label
): float {
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

    $number = (float)$value;

    if (
        !is_finite($number)
        ||
        $number <= 0
    ) {
        throw new InvalidArgumentException(
            $label . ' must be greater than zero.'
        );
    }

    return $number;
}
}


if (!function_exists('financial_allocation_service_money_cents')) {
function financial_allocation_service_money_cents(
    $value,
    string $label = 'Allocated amount'
): int {
    $number =
        financial_allocation_service_positive_number(
            $value,
            $label
        );

    $cents =
        (int)round(
            $number * 100,
            0,
            PHP_ROUND_HALF_UP
        );

    if ($cents < 1) {
        throw new InvalidArgumentException(
            $label . ' must be at least 0.01.'
        );
    }

    return $cents;
}
}


if (!function_exists('financial_allocation_service_money_string')) {
function financial_allocation_service_money_string(
    int $cents
): string {
    return number_format(
        $cents / 100,
        2,
        '.',
        ''
    );
}
}


if (!function_exists('financial_allocation_service_gross_cents')) {
function financial_allocation_service_gross_cents(
    array $expense
): int {
    $amount =
        financial_allocation_service_positive_number(
            $expense['amount'] ?? null,
            'Expense amount'
        );

    $unit =
        financial_allocation_service_positive_number(
            $expense['unit'] ?? null,
            'Expense quantity'
        );

    $grossCents =
        (int)round(
            $amount
            * $unit
            * 100,
            0,
            PHP_ROUND_HALF_UP
        );

    if ($grossCents < 1) {
        throw new RuntimeException(
            'Expense gross value must be greater than zero.'
        );
    }

    return $grossCents;
}
}


if (!function_exists('_v301_financial_allocation_parent_contract_legacy')) {
function _v301_financial_allocation_parent_contract_legacy(
    array $expense
): array {
    $farmId =
        (int)(
            $expense['farm_id']
            ?? 0
        );

    if ($farmId < 1) {
        throw new RuntimeException(
            'Financial allocation parent farm is invalid.'
        );
    }

    $cycleId =
        $expense['cycle_id']
        ?? null;

    if (
        $cycleId !== null
        &&
        trim((string)$cycleId) !== ''
        &&
        (int)$cycleId > 0
    ) {
        throw new RuntimeException(
            'Only expenses without a specific production cycle can be allocated.'
        );
    }

    $farmType =
        financial_allocation_service_normalize(
            $expense['farm_type']
            ?? ''
        );

    $productionType =
        financial_allocation_service_normalize(
            $expense['production_type']
            ?? ''
        );

    $scope =
        financial_allocation_service_normalize(
            $expense['attribution_scope']
            ?? ''
        );

    $category =
        financial_allocation_service_normalize(
            $expense['category']
            ?? ''
        );

    $poultryCategory =
        financial_allocation_service_normalize(
            $expense['poultry_category']
            ?? ''
        );

    $supported = [
        'poultry' => [
            'layer',
            'broiler',
            'shared',
        ],
        'ruminant' => [
            'cattle',
            'goat',
            'sheep',
            'other',
            'shared',
        ],
        'both' => [
            'shared',
        ],
    ];

    if (!isset($supported[$farmType])) {
        throw new RuntimeException(
            'Financial allocation supports Poultry and Ruminant shared expenses only.'
        );
    }

    if (
        !in_array(
            $productionType,
            $supported[$farmType],
            true
        )
    ) {
        throw new RuntimeException(
            'The expense production type is not eligible for financial allocation.'
        );
    }

    $expectedScope =
        $productionType === 'shared'
            ? 'farm'
            : 'production_type';

    if ($scope !== $expectedScope) {
        throw new RuntimeException(
            'The expense attribution scope is inconsistent with a shared financial-allocation parent.'
        );
    }

    if (
        in_array(
            $category,
            [
                'feed',
                'feeds',
            ],
            true
        )
    ) {
        throw new RuntimeException(
            'Historical manual Feed expenses cannot be financially allocated.'
        );
    }

    if ($farmType === 'poultry') {
        if (
            in_array(
                $productionType,
                [
                    'layer',
                    'broiler',
                ],
                true
            )
            &&
            $poultryCategory !== ''
            &&
            $poultryCategory !== $productionType
        ) {
            throw new RuntimeException(
                'The Poultry expense category does not match its production type.'
            );
        }

        if (
            $productionType === 'shared'
            &&
            $poultryCategory !== ''
            &&
            $poultryCategory !== 'shared'
        ) {
            throw new RuntimeException(
                'A shared Poultry expense cannot carry a conflicting Poultry category.'
            );
        }
    }

    $grossCents =
        financial_allocation_service_gross_cents(
            $expense
        );

    return [
        'expense_id' =>
            (int)(
                $expense['id']
                ?? $expense['expense_id']
                ?? 0
            ),

        'farm_id' =>
            $farmId,

        'farm_type' =>
            $farmType,

        'production_type' =>
            $productionType,

        'attribution_scope' =>
            $scope,

        'category' =>
            $category,

        'gross_cents' =>
            $grossCents,

        'gross_amount' =>
            financial_allocation_service_money_string(
                $grossCents
            ),
    ];
}
}


if (!function_exists('_v301_financial_allocation_target_contract_legacy')) {
function _v301_financial_allocation_target_contract_legacy(
    array $parentContract,
    array $cycle
): array {
    $cycleId =
        (int)(
            $cycle['id']
            ?? 0
        );

    if ($cycleId < 1) {
        throw new RuntimeException(
            'Select a valid target production cycle.'
        );
    }

    $cycleFarmId =
        (int)(
            $cycle['farm_id']
            ?? 0
        );

    if (
        $cycleFarmId < 1
        ||
        $cycleFarmId
            !== (int)$parentContract['farm_id']
    ) {
        throw new RuntimeException(
            'The target production cycle does not belong to the expense farm.'
        );
    }

    $cycleFarmType =
        financial_allocation_service_normalize(
            $cycle['farm_type']
            ?? ''
        );

    $cycleProductionType =
        financial_allocation_service_normalize(
            $cycle['production_type']
            ?? ''
        );

    $parentFarmType =
        (string)$parentContract[
            'farm_type'
        ];

    $crossModuleParent =
        $parentFarmType === 'both';

    if (
        (
            !$crossModuleParent
            &&
            $cycleFarmType
                !== $parentFarmType
        )
        ||
        (
            $crossModuleParent
            &&
            !in_array(
                $cycleFarmType,
                [
                    'poultry',
                    'ruminant',
                ],
                true
            )
        )
    ) {
        throw new RuntimeException(
            'The target production cycle does not match the expense farm type.'
        );
    }

    $targetProduction = [
        'poultry' => [
            'layer',
            'broiler',
        ],
        'ruminant' => [
            'cattle',
            'goat',
            'sheep',
            'other',
        ],
    ];

    if (
        !isset(
            $targetProduction[$cycleFarmType]
        )
        ||
        !in_array(
            $cycleProductionType,
            $targetProduction[$cycleFarmType],
            true
        )
    ) {
        throw new RuntimeException(
            'The target cycle production type is not eligible for financial allocation.'
        );
    }

    if (
        (string)$parentContract[
            'production_type'
        ] !== 'shared'
        &&
        $cycleProductionType
            !== (string)$parentContract[
                'production_type'
            ]
    ) {
        throw new RuntimeException(
            'The target cycle production type does not match the expense production type.'
        );
    }

    /*
     * Cycle status is deliberately not restricted here.
     * Closed cycles may need legitimate historical corrections.
     */
    return [
        'id' =>
            $cycleId,

        'farm_id' =>
            $cycleFarmId,

        'farm_type' =>
            $cycleFarmType,

        'production_type' =>
            $cycleProductionType,

        'status' =>
            financial_allocation_service_normalize(
                $cycle['status']
                ?? ''
            ),
    ];
}
}


if (!function_exists('financial_allocation_service_notes')) {
function financial_allocation_service_notes(
    $value
): ?string {
    $notes =
        trim(
            (string)(
                $value
                ?? ''
            )
        );

    if ($notes === '') {
        return null;
    }

    $length =
        function_exists('mb_strlen')
            ? mb_strlen(
                $notes,
                'UTF-8'
            )
            : strlen(
                $notes
            );

    if ($length > 255) {
        throw new InvalidArgumentException(
            'Financial allocation notes cannot exceed 255 characters.'
        );
    }

    return $notes;
}
}


if (!function_exists('_v301_financial_allocation_validate_desired_rows_legacy')) {
function _v301_financial_allocation_validate_desired_rows_legacy(
    array $expense,
    array $cycles,
    array $desiredRows,
    int $animalAllocationCount = 0
): array {
    $parent =
        financial_allocation_service_parent_contract(
            $expense
        );

    if (
        $desiredRows
        &&
        $animalAllocationCount > 0
    ) {
        throw new RuntimeException(
            'An expense allocated to individual animals cannot also be financially allocated to production cycles.'
        );
    }

    $cycleMap = [];

    foreach ($cycles as $cycle) {
        if (!is_array($cycle)) {
            continue;
        }

        $id =
            (int)(
                $cycle['id']
                ?? 0
            );

        if ($id > 0) {
            $cycleMap[$id] =
                $cycle;
        }
    }

    $normalized = [];
    $seen = [];
    $totalCents = 0;

    foreach ($desiredRows as $row) {
        if (!is_array($row)) {
            throw new InvalidArgumentException(
                'Financial allocation rows must be arrays.'
            );
        }

        $cycleId =
            (int)(
                $row['cycle_id']
                ?? $row['target_cycle_id']
                ?? 0
            );

        if ($cycleId < 1) {
            throw new InvalidArgumentException(
                'Select a target production cycle for every allocation.'
            );
        }

        if (isset($seen[$cycleId])) {
            throw new RuntimeException(
                'An expense cannot be allocated to the same production cycle more than once.'
            );
        }

        $seen[$cycleId] = true;

        if (!isset($cycleMap[$cycleId])) {
            throw new RuntimeException(
                'One or more target production cycles are unavailable.'
            );
        }

        $target =
            financial_allocation_service_target_contract(
                $parent,
                $cycleMap[$cycleId]
            );

        $amountCents =
            financial_allocation_service_money_cents(
                $row['allocated_amount']
                ?? null
            );

        $totalCents +=
            $amountCents;

        if (
            $totalCents
            > (int)$parent['gross_cents']
        ) {
            throw new RuntimeException(
                'Total financial allocations cannot exceed the expense gross value.'
            );
        }

        $percent =
            round(
                (
                    $amountCents
                    /
                    (int)$parent['gross_cents']
                )
                * 100,
                4
            );

        $normalized[] = [
            'cycle_id' =>
                (int)$target['id'],

            'allocated_amount' =>
                financial_allocation_service_money_string(
                    $amountCents
                ),

            /*
             * Derived/display metadata only.
             * allocated_amount remains causal authority.
             */
            'allocation_percent' =>
                number_format(
                    $percent,
                    4,
                    '.',
                    ''
                ),

            'notes' =>
                financial_allocation_service_notes(
                    $row['notes']
                    ?? null
                ),
        ];
    }

    return [
        'parent' =>
            $parent,

        'rows' =>
            $normalized,

        'allocated_cents' =>
            $totalCents,

        'allocated_amount' =>
            financial_allocation_service_money_string(
                $totalCents
            ),

        'remaining_cents' =>
            (int)$parent['gross_cents']
            - $totalCents,

        'remaining_amount' =>
            financial_allocation_service_money_string(
                (int)$parent['gross_cents']
                - $totalCents
            ),
    ];
}
}


if (!function_exists('financial_allocation_service_require_transaction')) {
function financial_allocation_service_require_transaction(
    PDO $pdo
): void {
    if (!$pdo->inTransaction()) {
        throw new RuntimeException(
            'Financial allocation mutation requires an active transaction.'
        );
    }
}
}


if (!function_exists('financial_allocation_service_parent')) {
function financial_allocation_service_parent(
    PDO $pdo,
    int $farmId,
    int $expenseId,
    bool $forUpdate = false
): array {
    if ($forUpdate) {
        financial_allocation_service_require_transaction(
            $pdo
        );
    }

    $sql =
        "SELECT *
         FROM farm_expenses
         WHERE farm_id=?
           AND id=?
         LIMIT 1";

    if ($forUpdate) {
        $sql .= " FOR UPDATE";
    }

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

    if (!$row) {
        throw new RuntimeException(
            'Expense record not found.'
        );
    }

    return $row;
}
}


if (!function_exists('financial_allocation_service_current_rows')) {
function financial_allocation_service_current_rows(
    PDO $pdo,
    int $farmId,
    int $expenseId,
    bool $forUpdate = false
): array {
    if ($forUpdate) {
        financial_allocation_service_require_transaction(
            $pdo
        );
    }

    $sql =
        "SELECT
             id,
             farm_id,
             expense_id,
             cycle_id,
             allocation_percent,
             allocated_amount,
             notes,
             created_by,
             created_at
         FROM financial_allocations
         WHERE farm_id=?
           AND expense_id=?
         ORDER BY cycle_id,id";

    if ($forUpdate) {
        $sql .= " FOR UPDATE";
    }

    $stmt =
        $pdo->prepare(
            $sql
        );

    $stmt->execute([
        $farmId,
        $expenseId,
    ]);

    return
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        ) ?: [];
}
}


if (!function_exists('financial_allocation_service_animal_count')) {
function financial_allocation_service_animal_count(
    PDO $pdo,
    int $farmId,
    int $expenseId,
    bool $forUpdate = false
): int {
    if ($forUpdate) {
        financial_allocation_service_require_transaction(
            $pdo
        );
    }

    $sql =
        "SELECT id
         FROM ruminant_expense_animal_allocations
         WHERE farm_id=?
           AND expense_id=?
         ORDER BY id";

    if ($forUpdate) {
        $sql .= " FOR UPDATE";
    }

    $stmt =
        $pdo->prepare(
            $sql
        );

    $stmt->execute([
        $farmId,
        $expenseId,
    ]);

    return count(
        $stmt->fetchAll(
            PDO::FETCH_COLUMN
        ) ?: []
    );
}
}


if (!function_exists('financial_allocation_service_target_cycles')) {
function financial_allocation_service_target_cycles(
    PDO $pdo,
    int $farmId,
    array $cycleIds,
    bool $forUpdate = false
): array {
    if ($forUpdate) {
        financial_allocation_service_require_transaction(
            $pdo
        );
    }

    $cycleIds =
        array_values(
            array_unique(
                array_filter(
                    array_map(
                        'intval',
                        $cycleIds
                    ),
                    static function (
                        int $id
                    ): bool {
                        return $id > 0;
                    }
                )
            )
        );

    if (!$cycleIds) {
        return [];
    }

    $placeholders =
        implode(
            ',',
            array_fill(
                0,
                count($cycleIds),
                '?'
            )
        );

    $sql =
        "SELECT
             id,
             farm_id,
             farm_type,
             production_type,
             status,
             start_date
         FROM production_cycles
         WHERE farm_id=?
           AND id IN ({$placeholders})
         ORDER BY id";

    if ($forUpdate) {
        $sql .= " FOR UPDATE";
    }

    $stmt =
        $pdo->prepare(
            $sql
        );

    $stmt->execute(
        array_merge(
            [
                $farmId,
            ],
            $cycleIds
        )
    );

    return
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        ) ?: [];
}
}


if (!function_exists('financial_allocation_service_validate_current')) {
function financial_allocation_service_validate_current(
    PDO $pdo,
    int $farmId,
    int $expenseId,
    bool $forUpdate = false
): array {
    $parent =
        financial_allocation_service_parent(
            $pdo,
            $farmId,
            $expenseId,
            $forUpdate
        );

    $rows =
        financial_allocation_service_current_rows(
            $pdo,
            $farmId,
            $expenseId,
            $forUpdate
        );

    $cycleIds = [];

    foreach ($rows as $row) {
        $cycleIds[] =
            (int)$row['cycle_id'];
    }

    $cycles =
        financial_allocation_service_target_cycles(
            $pdo,
            $farmId,
            $cycleIds,
            $forUpdate
        );

    $animalCount =
        financial_allocation_service_animal_count(
            $pdo,
            $farmId,
            $expenseId,
            $forUpdate
        );

    return
        financial_allocation_service_validate_desired_rows(
            $parent,
            $cycles,
            $rows,
            $animalCount
        );
}
}

/*
 * V3.0.1 unified Shared Cost policy adapters.
 *
 * Legacy Financial Allocation behavior remains compatibility authority.
 * The shared-cost contract independently enforces common scope,
 * target and conservation policy.
 */

function financial_allocation_service_parent_contract(
    array $expense
): array {
    /*
     * Legacy validation runs first so established rejection messages
     * and return structure remain unchanged.
     */
    $legacy =
        _v301_financial_allocation_parent_contract_legacy(
            $expense
        );

    shared_cost_contract_parent(
        $expense
    );

    return $legacy;
}

function financial_allocation_service_target_contract(
    array $parentContract,
    array $cycle
): array {
    /*
     * Preserve all established target-validation behavior first.
     */
    $legacy =
        _v301_financial_allocation_target_contract_legacy(
            $parentContract,
            $cycle
        );

    $sharedParent =
        shared_cost_contract_parent(
            $parentContract
        );

    shared_cost_contract_target(
        $sharedParent,
        $cycle
    );

    return $legacy;
}

function financial_allocation_service_validate_desired_rows(
    array $expense,
    array $cycles,
    array $desiredRows,
    int $animalAllocationCount = 0
): array {
    /*
     * The original implementation remains authoritative for:
     * - exact messages,
     * - rows shape,
     * - allocation_percent formatting,
     * - allocated_amount summary,
     * - remaining_amount summary.
     */
    $legacy =
        _v301_financial_allocation_validate_desired_rows_legacy(
            $expense,
            $cycles,
            $desiredRows,
            $animalAllocationCount
        );

    $grossCents =
        financial_allocation_service_gross_cents(
            $expense
        );

    $parentAmount =
        financial_allocation_service_money_string(
            $grossCents
        );

    $allocatedAmounts = [];

    foreach (
        (
            $legacy['rows']
            ?? []
        )
        as $row
    ) {
        if (
            isset($row['allocated_amount'])
            &&
            $row['allocated_amount'] !== ''
        ) {
            $allocatedAmounts[] =
                $row['allocated_amount'];
        }
    }

    $conservation =
        shared_cost_contract_conservation(
            $parentAmount,
            $allocatedAmounts
        );

    /*
     * No transformation here. Fail closed if the two contracts
     * ever disagree rather than silently rewriting established output.
     */
    if (
        (string)(
            $legacy['allocated_amount']
            ?? ''
        )
            !==
        $conservation['allocated_amount']
        ||
        (string)(
            $legacy['remaining_amount']
            ?? ''
        )
            !==
        $conservation['unallocated_amount']
    ) {
        throw new RuntimeException(
            'Financial allocation conservation state is inconsistent.'
        );
    }

    return $legacy;
}
