<?php

require_once dirname(__DIR__)
    . '/lib/general_expense_entry.php';

$fail = 0;

$assert = static function (
    bool $condition,
    string $label
) use (&$fail): void {
    if ($condition) {
        echo "PASS: {$label}\n";
        return;
    }

    echo "FAIL: {$label}\n";
    $fail = 1;
};


/*
 * Canonical General attribution.
 */
$attribution =
    general_expense_entry_attribution();

$assert(
    ($attribution['farm_type'] ?? null) === 'general',
    'General expense farm type is general'
);

$assert(
    ($attribution['production_type'] ?? null) === 'general',
    'General expense production type is general'
);

$assert(
    ($attribution['attribution_scope'] ?? null) === 'farm',
    'General expense attribution scope is farm'
);

$assert(
    array_key_exists('cycle_id', $attribution)
    && $attribution['cycle_id'] === null,
    'General expense has no production cycle'
);


/*
 * Canonical General operating-expense category authority.
 */
foreach (
    [
        'salary' => 'Salary / Wages',
        'rent' => 'Rent / Lease',
        'utilities' => 'Utilities',
        'marketing' => 'Marketing / Advertising',
        'repairs_maintenance' => 'Repairs / Maintenance',
        'bank_charges' => 'Bank / Payment Charges',
        'communication' => 'Internet / Phone',
        'taxes_levies' => 'Taxes / Levies',
        'professional_fees' => 'Professional Fees',
        'misc' => 'Miscellaneous',
    ]
    as $category => $label
) {
    try {
        $normalized =
            general_expense_entry_category(
                $category
            );

        $assert(
            $normalized === $category,
            "{$label} is accepted for General operating expenses"
        );

    } catch (Throwable $e) {
        $assert(
            false,
            "{$label} is accepted for General operating expenses"
        );
    }
}

foreach (
    [
        'feeds' => 'Feed',
        'medication' => 'Medication',
        'processing_materials' => 'Processing Materials',
    ]
    as $category => $label
) {
    try {
        general_expense_entry_category(
            $category
        );

        $assert(
            false,
            "{$label} is excluded from General operating-expense entry"
        );

    } catch (InvalidArgumentException $e) {
        $assert(
            true,
            "{$label} is excluded from General operating-expense entry"
        );
    }
}


/*
 * Creation service must participate in caller-owned transaction.
 */
$pdo =
    new PDO(
        'sqlite::memory:'
    );

try {
    general_expense_entry_require_transaction(
        $pdo
    );

    $assert(
        false,
        'General expense creation requires active transaction'
    );

} catch (RuntimeException $e) {
    $assert(
        true,
        'General expense creation requires active transaction'
    );
}

$pdo->beginTransaction();

try {
    general_expense_entry_require_transaction(
        $pdo
    );

    $assert(
        true,
        'General expense service accepts caller-owned transaction'
    );

} catch (Throwable $e) {
    $assert(
        false,
        'General expense service accepts caller-owned transaction'
    );
}

$pdo->rollBack();


/*
 * Positive monetary inputs.
 */
$assert(
    general_expense_entry_positive_decimal(
        '2500',
        'Expense amount'
    ) === '2500.00',
    'General expense amount is normalized'
);

try {
    general_expense_entry_positive_decimal(
        '0',
        'Expense amount'
    );

    $assert(
        false,
        'Zero expense amount is rejected'
    );

} catch (InvalidArgumentException $e) {
    $assert(
        true,
        'Zero expense amount is rejected'
    );
}


if ($fail) {
    exit(1);
}

echo "GENERAL_EXPENSE_ENTRY_CONTRACT=PASS\n";
