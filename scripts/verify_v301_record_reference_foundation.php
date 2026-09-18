<?php
/**
 * Renee Farms V3.0.1 — human-facing record reference foundation verifier.
 *
 * Source/runtime-library only.
 * No database connection.
 * No database writes.
 */

$root =
    dirname(__DIR__);

$servicePath =
    $root
    . '/lib/record_reference.php';

$service =
    is_file($servicePath)
        ? (string)file_get_contents(
            $servicePath
        )
        : '';

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
    $service !== '',
    'Central record-reference service exists'
);

$check(
    substr_count(
        $service,
        "function record_reference_generate("
    ) === 1,
    'Reference generation has one central implementation'
);

$check(
    strpos(
        $service,
        "'stock_movement'"
    ) !== false
    &&
    strpos(
        $service,
        "'code' => 'SM'"
    ) !== false,
    'Stock movement uses canonical SM entity code'
);

$check(
    strpos(
        $service,
        "'expense'"
    ) !== false
    &&
    strpos(
        $service,
        "'code' => 'EX'"
    ) !== false,
    'Expense uses canonical EX entity code'
);

$check(
    strpos(
        $service,
        "'sale'"
    ) !== false
    &&
    strpos(
        $service,
        "'code' => 'SA'"
    ) !== false,
    'Sale uses canonical SA entity code'
);

$check(
    strpos(
        $service,
        'random_int('
    ) !== false,
    'Reference suffix uses server-side cryptographic randomness'
);

$check(
    strpos(
        $service,
        '23456789ABCDEFGHJKLMNPQRSTUVWXYZ'
    ) !== false,
    'Reference alphabet excludes ambiguous characters'
);

$check(
    strpos(
        $service,
        'PDO'
    ) === false
    &&
    stripos(
        $service,
        'SELECT '
    ) === false
    &&
    stripos(
        $service,
        'INSERT '
    ) === false
    &&
    stripos(
        $service,
        'UPDATE '
    ) === false,
    'Reference foundation owns no database SQL'
);

require_once
    $servicePath;

$sampleDate =
    '2026-09-18 10:07:35';

$stockReference =
    record_reference_generate(
        'stock_movement',
        $sampleDate
    );

$expenseReference =
    record_reference_generate(
        'expense',
        $sampleDate
    );

$saleReference =
    record_reference_generate(
        'sale',
        $sampleDate
    );

$check(
    record_reference_date_token(
        $sampleDate
    ) === '20260918',
    'Reference date is derived from immutable creation timestamp'
);

$check(
    record_reference_is_valid(
        $stockReference,
        'stock_movement'
    ),
    'Generated stock movement reference is valid'
);

$check(
    record_reference_is_valid(
        $expenseReference,
        'expense'
    ),
    'Generated expense reference is valid'
);

$check(
    record_reference_is_valid(
        $saleReference,
        'sale'
    ),
    'Generated sale reference is valid'
);

$check(
    str_starts_with(
        $stockReference,
        'RA-SM-20260918-'
    ),
    'Stock movement reference has canonical prefix and date'
);

$check(
    str_starts_with(
        $expenseReference,
        'RA-EX-20260918-'
    ),
    'Expense reference has canonical prefix and date'
);

$check(
    str_starts_with(
        $saleReference,
        'RA-SA-20260918-'
    ),
    'Sale reference has canonical prefix and date'
);

$check(
    !record_reference_is_valid(
        $stockReference,
        'sale'
    ),
    'Entity validation rejects a reference from another record type'
);

$check(
    !record_reference_is_valid(
        'RA-SM-20261340-ABCDEFGHJKLMNPQR',
        'stock_movement'
    ),
    'Reference validation rejects impossible dates'
);

$check(
    !record_reference_is_valid(
        'RA-SM-20260918-0000000000',
        'stock_movement'
    ),
    'Reference validation rejects ambiguous/noncanonical suffix alphabet'
);

$unsupportedRejected = false;

try {
    record_reference_generate(
        'user',
        $sampleDate
    );
} catch (InvalidArgumentException $error) {
    $unsupportedRejected = true;
}

$check(
    $unsupportedRejected,
    'Internal-only entity cannot receive an RA reference'
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
