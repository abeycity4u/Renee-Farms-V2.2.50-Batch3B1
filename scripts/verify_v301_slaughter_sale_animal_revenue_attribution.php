<?php

declare(strict_types=1);

$root = dirname(__DIR__);

require_once
    $root
    . '/lib/ruminant_sale_animal_allocation.php';

$salesPath =
    $root
    . '/management/sales_records.php';

$servicePath =
    $root
    . '/lib/ruminant_sale_animal_allocation.php';

$sales =
    is_readable($salesPath)
        ? (string)file_get_contents($salesPath)
        : '';

$service =
    is_readable($servicePath)
        ? (string)file_get_contents($servicePath)
        : '';

$checks = 0;
$failures = 0;

$check =
    static function (
        string $label,
        bool $ok
    ) use (
        &$checks,
        &$failures
    ): void {
        $checks++;

        echo ($ok ? 'PASS: ' : 'FAIL: ')
            . $label
            . PHP_EOL;

        if (!$ok) {
            $failures++;
        }
    };

$check(
    'Shared slaughter-output animal attribution helper exists',
    function_exists(
        'ruminant_sale_build_slaughter_output_allocations'
    )
);

$single =
    ruminant_sale_build_slaughter_output_allocations(
        [
            'mode' =>
                'slaughter_output',

            'rows' => [
                1 => [
                    'animal_id' => 22,
                    'quantity' => 10.00,
                ],
            ],
        ],
        20000.00
    );

$check(
    'Single source animal receives 100 percent of slaughter sale revenue',
    count($single['rows'] ?? []) === 1
    && (int)$single['rows'][0]['animal_id'] === 22
    && abs(
        (float)$single['rows'][0]['allocated_amount']
        - 20000.00
    ) < 0.00001
    && abs(
        (float)$single['rows'][0]['allocation_percent']
        - 100.0
    ) < 0.00001
);

$sameAnimal =
    ruminant_sale_build_slaughter_output_allocations(
        [
            'mode' =>
                'slaughter_output',

            'rows' => [
                1 => [
                    'animal_id' => 22,
                    'quantity' => 4.00,
                ],

                2 => [
                    'animal_id' => 22,
                    'quantity' => 6.00,
                ],
            ],
        ],
        20000.00
    );

$check(
    'Multiple lots from same animal collapse to one animal revenue row',
    count($sameAnimal['rows'] ?? []) === 1
    && (int)$sameAnimal['rows'][0]['animal_id'] === 22
    && abs(
        (float)$sameAnimal['rows'][0]['allocated_amount']
        - 20000.00
    ) < 0.00001
);

$multi =
    ruminant_sale_build_slaughter_output_allocations(
        [
            'mode' =>
                'slaughter_output',

            'rows' => [
                1 => [
                    'animal_id' => 22,
                    'quantity' => 2.50,
                ],

                2 => [
                    'animal_id' => 23,
                    'quantity' => 7.50,
                ],
            ],
        ],
        20000.00
    );

$multiByAnimal = [];

foreach (
    $multi['rows']
    as $row
) {
    $multiByAnimal[
        (int)$row['animal_id']
    ] =
        (float)$row['allocated_amount'];
}

$check(
    'Multiple source animals receive quantity-proportional revenue',
    abs(
        ($multiByAnimal[22] ?? 0)
        - 5000.00
    ) < 0.00001
    && abs(
        ($multiByAnimal[23] ?? 0)
        - 15000.00
    ) < 0.00001
);

$rounding =
    ruminant_sale_build_slaughter_output_allocations(
        [
            'mode' =>
                'slaughter_output',

            'rows' => [
                1 => [
                    'animal_id' => 21,
                    'quantity' => 1.00,
                ],
                2 => [
                    'animal_id' => 22,
                    'quantity' => 1.00,
                ],
                3 => [
                    'animal_id' => 23,
                    'quantity' => 1.00,
                ],
            ],
        ],
        100.00
    );

$roundingTotal = 0.0;

foreach (
    $rounding['rows']
    as $row
) {
    $roundingTotal +=
        (float)$row['allocated_amount'];
}

$check(
    'Largest-remainder allocation conserves sale total exactly',
    abs(
        $roundingTotal
        - 100.00
    ) < 0.00001
);

$financialOnly =
    ruminant_sale_build_slaughter_output_allocations(
        [
            'mode' =>
                'financial_only',

            'rows' => [],
        ],
        100.00
    );

$check(
    'Non-slaughter sale keeps ordinary shared attribution path available',
    ($financialOnly['mode'] ?? '') === 'shared'
    && empty(
        $financialOnly['rows']
    )
);

$check(
    'Add and Edit both call canonical slaughter animal attribution helper',
    substr_count(
        $sales,
        'ruminant_sale_build_slaughter_output_allocations('
    ) === 2
);

$check(
    'Ordinary ruminant Add and Edit still use existing manual allocation builder',
    substr_count(
        $sales,
        'ruminant_sale_build_animal_allocations('
    ) === 2
);

$check(
    'Shared slaughter-output UI stays livestock-neutral while Ruminant attribution remains service-owned',
    !str_contains(
        $sales,
        'source-animal revenue attribution'
    )
    &&
    !str_contains(
        $sales,
        'Animal revenue attribution follows the selected source lot automatically.'
    )
    &&
    str_contains(
        $sales,
        'recorded Poultry or Ruminant slaughter output'
    )
    &&
    str_contains(
        $sales,
        'Changing lot, quantity or livestock domain restores the previous active lot usage append-only'
    )
);

$check(
    'Animal allocation service contains no population ledger writer',
    !str_contains(
        $service,
        'production_population_'
    )
);

$check(
    'Animal allocation service contains no stock writer',
    !str_contains(
        $service,
        'stock_apply_movement('
    )
);

echo PHP_EOL;
echo 'CHECK_COUNT=' . $checks . PHP_EOL;
echo 'FAILED_COUNT=' . $failures . PHP_EOL;
echo 'DATABASE_CONNECTION_USED=NO' . PHP_EOL;
echo 'DATABASE_WRITE_PERFORMED=NO' . PHP_EOL;
echo 'RESULT='
    . (
        $failures === 0
            ? 'PASS'
            : 'FAIL'
    )
    . PHP_EOL;

exit(
    $failures === 0
        ? 0
        : 1
);
