<?php
/**
 * Renee Farms V3.0.1
 * Sale human-facing record-reference display verifier.
 *
 * Direct Sale surfaces only.
 * Receivables / debt inherited parent references are a later cut.
 */

$root =
    dirname(__DIR__);

$paths = [
    'page' =>
        $root . '/management/sales_records.php',

    'pdf' =>
        $root . '/management/sales_report_pdf.php',
];

$source = [];

foreach ($paths as $key => $path) {
    $source[$key] =
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
        $source['page'],
        'SELECT s.*'
    ) !== false,
    'Sales Records query carries canonical Sale row'
);

$check(
    substr_count(
        $source['page'],
        '<th>Reference</th>'
    ) === 1,
    'Sales Records has exactly one direct Reference column'
);

$check(
    substr_count(
        $source['page'],
        "\$sale['public_reference']"
    ) === 1,
    'Sales Records renders Sale public reference exactly once'
);

$check(
    strpos(
        $source['page'],
        "? '17' : '16';"
    ) !== false,
    'Sales Records empty state accounts for Reference column'
);

$check(
    strpos(
        $source['page'],
        'data-id="<?php echo $sale[\'id\']; ?>"'
    ) !== false,
    'Sale Edit action preserves internal numeric ID'
);

$check(
    strpos(
        $source['page'],
        'data-sale-delete-id="<?php echo (int)$sale[\'id\']; ?>"'
    ) !== false,
    'Sale Delete action preserves internal numeric ID'
);


$check(
    strpos(
        $source['pdf'],
        'SELECT s.*'
    ) !== false,
    'Sales PDF query carries canonical Sale row'
);

$check(
    substr_count(
        $source['pdf'],
        '<th>Reference</th>'
    ) === 1,
    'Sales PDF has exactly one Reference column'
);

$check(
    substr_count(
        $source['pdf'],
        "\$sale['public_reference']"
    ) === 1,
    'Sales PDF renders Sale public reference exactly once'
);

$check(
    strpos(
        $source['pdf'],
        'colspan="13"'
    ) !== false,
    'Sales PDF empty state matches thirteen columns'
);


$assignment =
    'record_reference_persistence_insert_new(';

$check(
    substr_count(
        $source['page'],
        $assignment
    ) === 1,
    'Sales Records preserves established canonical Sale writer assignment'
);

$check(
    strpos(
        $source['pdf'],
        $assignment
    ) === false,
    'Sales PDF contains no reference assignment writer'
);


$combined =
    $source['page']
    . "\n"
    . $source['pdf'];

$check(
    strpos(
        $combined,
        'record_reference_generate('
    ) === false,
    'Sale display surfaces never generate references'
);

$check(
    strpos(
        $combined,
        'SET public_reference'
    ) === false,
    'Sale display surfaces never update public_reference'
);

$check(
    preg_match(
        '/(?:sale_id|data-sale-delete-id|data-id)[^\\n>]*public_reference/i',
        $combined
    ) !== 1,
    'Public reference never replaces internal Sale routing IDs'
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
