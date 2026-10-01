<?php

$root =
    dirname(__DIR__);

$fail = 0;

$assert =
    static function (
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
 * Migration 090:
 * application category catalog is authoritative;
 * DB storage must not duplicate category policy in ENUM.
 */
$migrationPath =
    $root
    . '/migrations/090_general_expense_category_storage.sql';

$migration =
    is_file($migrationPath)
        ? file_get_contents($migrationPath)
        : false;

$assert(
    is_string($migration),
    'Migration 090 exists'
);

if (is_string($migration)) {
    $assert(
        stripos(
            $migration,
            'VARCHAR(64)'
        ) !== false,
        'Migration 090 moves expense category storage to VARCHAR'
    );

    $assert(
        stripos(
            $migration,
            'MODIFY COLUMN category'
        ) !== false,
        'Migration 090 modifies only category storage contract'
    );

    $assert(
        stripos(
            $migration,
            'ENUM('
        ) === false,
        'Migration 090 does not duplicate category catalog as ENUM'
    );

    $assert(
        strpos(
            $migration,
            '090_general_expense_category_storage.sql'
        ) !== false,
        'Migration 090 records schema migration marker'
    );
}


/*
 * General creation service must fail closed if DB storage
 * silently coerces a category.
 */
$servicePath =
    $root
    . '/lib/general_expense_entry.php';

$service =
    is_file($servicePath)
        ? file_get_contents($servicePath)
        : false;

$assert(
    is_string($service),
    'General expense service is readable'
);

if (is_string($service)) {
    $assert(
        strpos(
            $service,
            'SELECT category'
        ) !== false
        &&
        strpos(
            $service,
            'General expense category storage contract is inconsistent.'
        ) !== false,
        'General creator verifies persisted category storage'
    );

    $assert(
        strpos(
            $service,
            'expense_revision_service_record_created'
        ) !== false,
        'General creator retains shared revision authority'
    );

    $storageCheckPosition =
        strpos(
            $service,
            'General expense category storage contract is inconsistent.'
        );

    $revisionPosition =
        strpos(
            $service,
            'expense_revision_service_record_created'
        );

    $assert(
        $storageCheckPosition !== false
        &&
        $revisionPosition !== false
        &&
        $storageCheckPosition < $revisionPosition,
        'Category storage is verified before created revision is recorded'
    );
}


/*
 * Management report:
 * Sales-only is canonically General and has no production scope.
 */
$pagePath =
    $root
    . '/management/expenses.php';

$page =
    is_file($pagePath)
        ? file_get_contents($pagePath)
        : false;

$assert(
    is_string($page),
    'Expense Report page is readable'
);

if (is_string($page)) {
    $assert(
        preg_match(
            '/if\s*\(\s*\$salesOnlyWorkspace\s*\)\s*\{.*?\$farmType\s*=\s*[\'"]general[\'"].*?\$productionType\s*=\s*[\'"]all[\'"]/s',
            $page
        ) === 1,
        'Sales-only Expense Report pins General farm scope'
    );

    $assert(
        strpos(
            $page,
            'id="farmTypeFilter"'
        ) !== false
        &&
        strpos(
            $page,
            'value="general"'
        ) !== false,
        'Sales-only browser filter contract retains hidden General scope'
    );

    $assert(
        strpos(
            $page,
            'id="productionTypeFilter"'
        ) !== false
        &&
        strpos(
            $page,
            'value="all"'
        ) !== false,
        'Sales-only browser filter contract retains hidden production all scope'
    );

    $assert(
        preg_match(
            '/<\?php\s+if\s*\(\s*!\$salesOnlyWorkspace\s*\)\s*:\s*\?>\s*<th>Farm Type<\/th>\s*<th>Production Type<\/th>/s',
            $page
        ) === 1,
        'Sales-only table hides livestock scope columns'
    );

    $assert(
        strpos(
            $page,
            '$expenseTableColumnCount'
        ) !== false,
        'Expense table colspan adapts to Sales-only presentation'
    );
}


/*
 * PDF report must use the same Sales-only scope contract.
 */
$pdfPath =
    $root
    . '/management/expense_report_pdf.php';

$pdf =
    is_file($pdfPath)
        ? file_get_contents($pdfPath)
        : false;

$assert(
    is_string($pdf),
    'Expense Report PDF is readable'
);

if (is_string($pdf)) {
    $assert(
        strpos(
            $pdf,
            'current_farm_is_sales_only()'
        ) !== false,
        'Expense PDF detects Sales-only workspace'
    );

    $assert(
        preg_match(
            '/if\s*\(\s*\$salesOnlyWorkspace\s*\)\s*\{.*?\$farmType\s*=\s*[\'"]general[\'"].*?\$productionType\s*=\s*[\'"]all[\'"]/s',
            $pdf
        ) === 1,
        'Sales-only Expense PDF pins General farm scope'
    );
}


if ($fail) {
    exit(1);
}

echo "SALES_ONLY_EXPENSE_STORAGE_SCOPE_CONTRACT=PASS\n";
