<?php
/**
 * Renee Farms V3.0.1
 *
 * Customer receivable/debt inherited parent Sale reference verifier.
 *
 * customer_ledger_entries retain numeric sale_id relationships.
 * Human-facing debt surfaces inherit sales_records.public_reference.
 */

$root = dirname(__DIR__);

$paths = [
    'page' =>
        $root . '/management/sales_records.php',

    'sales_pdf' =>
        $root . '/management/sales_report_pdf.php',

    'debt_pdf' =>
        $root . '/management/debt_history_pdf.php',
];

$src = [];

foreach ($paths as $key => $path) {
    $src[$key] =
        is_file($path)
            ? (string)file_get_contents($path)
            : '';
}

$checks = 0;
$failures = 0;

$check =
    static function (
        bool $ok,
        string $label
    ) use (
        &$checks,
        &$failures
    ): void {
        $checks++;

        echo
            ($ok ? '[PASS] ' : '[FAIL] ')
            . $label
            . PHP_EOL;

        if (!$ok) {
            $failures++;
        }
    };


$check(
    strpos(
        $src['page'],
        'LEFT JOIN sales_records s ON s.id = l.sale_id AND s.farm_id = l.farm_id'
    ) !== false,
    'Customer debt ledger joins tenant-scoped parent Sale'
);

$check(
    strpos(
        $src['page'],
        's.public_reference AS sale_public_reference'
    ) !== false,
    'Customer debt ledger selects parent Sale reference'
);

$check(
    substr_count(
        $src['page'],
        '<th>Sale Reference</th>'
    ) === 1,
    'Customer debt ledger has one Sale Reference column'
);

$check(
    strpos(
        $src['page'],
        "\$entry['sale_public_reference']"
    ) !== false,
    'Customer debt ledger renders inherited parent reference'
);

$check(
    strpos(
        $src['page'],
        "? '8' : '7';"
    ) !== false,
    'Customer debt empty-state colspan includes Sale Reference'
);

$check(
    strpos(
        $src['page'],
        'name="settle_sale_id"'
    ) !== false,
    'Payment form preserves internal settle_sale_id'
);

$check(
    strpos(
        $src['page'],
        'value="<?php echo (int)$creditSale[\'id\']; ?>"'
    ) !== false,
    'Payment selector preserves internal Sale ID value'
);

$check(
    strpos(
        $src['page'],
        "\$creditSale['public_reference']"
    ) !== false,
    'Payment selector displays public Sale reference'
);

$check(
    strpos(
        $src['page'],
        'Sale #'
    ) === false,
    'Payment UI and generated notes no longer expose numeric Sale labels'
);

$check(
    strpos(
        $src['page'],
        'SELECT public_reference'
    ) !== false
    &&
    strpos(
        $src['page'],
        'WHERE id = ? AND farm_id = ?'
    ) !== false,
    'Specific payment resolves parent reference tenant-safely'
);

$check(
    strpos(
        $src['page'],
        '" | Applied to Sale {$salePublicReference}"'
    ) !== false,
    'Specific payment note uses parent Sale reference'
);

$check(
    strpos(
        $src['page'],
        'SELECT s.id, s.public_reference, s.sale_date, SUM(l.amount) AS balance'
    ) !== false,
    'FIFO payment query carries parent Sale reference'
);

$check(
    strpos(
        $src['page'],
        'GROUP BY s.id, s.public_reference, s.sale_date'
    ) !== false,
    'FIFO payment grouping includes parent Sale reference'
);

$check(
    strpos(
        $src['page'],
        '" | FIFO Auto-allocation Sale {$salePublicReference}"'
    ) !== false,
    'FIFO payment note uses parent Sale reference'
);


$check(
    strpos(
        $src['sales_pdf'],
        'LEFT JOIN sales_records s ON s.id=l.sale_id AND s.farm_id=l.farm_id'
    ) !== false,
    'Sales PDF debt ledger joins tenant-scoped parent Sale'
);

$check(
    strpos(
        $src['sales_pdf'],
        's.public_reference AS sale_public_reference'
    ) !== false,
    'Sales PDF debt ledger selects parent Sale reference'
);

$check(
    substr_count(
        $src['sales_pdf'],
        '<th>Sale Reference</th>'
    ) === 1,
    'Sales PDF debt ledger has Sale Reference column'
);

$check(
    strpos(
        $src['sales_pdf'],
        "\$entry['sale_public_reference']"
    ) !== false,
    'Sales PDF debt ledger renders parent Sale reference'
);

$check(
    strpos(
        $src['sales_pdf'],
        'colspan="7"'
    ) !== false,
    'Sales PDF debt ledger empty state has seven columns'
);


$check(
    strpos(
        $src['debt_pdf'],
        'LEFT JOIN sales_records s ON s.id = cle.sale_id AND s.farm_id = cle.farm_id'
    ) !== false,
    'Debt History PDF joins tenant-scoped parent Sale'
);

$check(
    strpos(
        $src['debt_pdf'],
        's.public_reference AS sale_public_reference'
    ) !== false,
    'Debt History PDF selects parent Sale reference'
);

$check(
    substr_count(
        $src['debt_pdf'],
        '<th>Sale Reference</th>'
    ) === 1,
    'Debt History PDF has Sale Reference column'
);

$check(
    strpos(
        $src['debt_pdf'],
        "\$row['sale_public_reference']"
    ) !== false,
    'Debt History PDF renders parent Sale reference'
);

$check(
    strpos(
        $src['debt_pdf'],
        'colspan="7"'
    ) !== false,
    'Debt History PDF empty state has seven columns'
);


$assignment =
    'record_reference_persistence_assign_existing(';

$check(
    substr_count(
        $src['page'],
        $assignment
    ) === 1
    &&
    strpos(
        $src['sales_pdf'],
        $assignment
    ) === false
    &&
    strpos(
        $src['debt_pdf'],
        $assignment
    ) === false,
    'Only established Sale writer assignment remains'
);

$combined =
    $src['page']
    . "\n"
    . $src['sales_pdf']
    . "\n"
    . $src['debt_pdf'];

$check(
    strpos(
        $combined,
        'record_reference_generate('
    ) === false,
    'Receivable parent-reference surfaces never generate references'
);

$check(
    strpos(
        $combined,
        'SET public_reference'
    ) === false,
    'Receivable parent-reference surfaces never update public_reference'
);

$check(
    strpos(
        $src['page'],
        '<th>Reference</th>'
    ) !== false
    &&
    strpos(
        $src['page'],
        "\$sale['public_reference']"
    ) !== false,
    'Direct Sale Reference display remains intact'
);


echo PHP_EOL
    . $checks
    . ' checks, '
    . $failures
    . ' failure(s).'
    . PHP_EOL;

exit(
    $failures === 0
        ? 0
        : 1
);
