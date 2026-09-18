<?php

$root =
    dirname(__DIR__);

$checks = [];

function verify_check(
    $label,
    $ok
) {
    global $checks;

    $checks[] = [
        $label,
        (bool)$ok,
    ];

    echo
        ($ok ? 'PASS' : 'FAIL')
        . ' - '
        . $label
        . PHP_EOL;
}

$api =
    file_get_contents(
        $root
        . '/api/get_stock_history.php'
    );

$page =
    file_get_contents(
        $root
        . '/api/stock_history.php'
    );

$js =
    file_get_contents(
        $root
        . '/assets/js/stock-history.js'
    );

verify_check(
    'Stock history API joins production cycles',
    strpos(
        $api,
        'LEFT JOIN production_cycles pc'
    ) !== false
);

verify_check(
    'Stock history API exposes cycle code',
    strpos(
        $api,
        'pc.cycle_code'
    ) !== false
);

verify_check(
    'History table has Attributed To column',
    strpos(
        $page,
        '<th>Attributed To</th>'
    ) !== false
);

verify_check(
    'History table has Production Cycle column',
    strpos(
        $page,
        '<th>Production Cycle</th>'
    ) !== false
);

/*
 * Browser rendering was externalized for CSP compatibility.
 * Attribution labels therefore belong to stock-history.js,
 * not the PHP page template.
 */
verify_check(
    'Poultry Layer attribution is rendered',
    strpos(
        $js,
        "Poultry · Layer"
    ) !== false
);

verify_check(
    'Poultry shared attribution is rendered',
    strpos(
        $js,
        "Shared Poultry"
    ) !== false
);

verify_check(
    'Ruminant attribution is rendered',
    strpos(
        $js,
        "Ruminant · "
    ) !== false
);

verify_check(
    'Shared/no-cycle state is explicit',
    strpos(
        $js,
        'Shared / No specific cycle'
    ) !== false
);

verify_check(
    'History initial row matches thirteen columns',
    strpos(
        $page,
        'colspan="13"'
    ) !== false
);

verify_check(
    'History dynamic rows match thirteen columns',
    substr_count(
        $js,
        'colspan="13"'
    ) === 2
);

verify_check(
    'No migration is introduced by this display change',
    !file_exists(
        $root
        . '/migrations/028_stock_history_attribution.sql'
    )
);

$failed =
    array_filter(
        $checks,
        static function ($check) {
            return !$check[1];
        }
    );

echo PHP_EOL
    . count($checks)
    . ' checks, '
    . count($failed)
    . ' failures.'
    . PHP_EOL;

exit(
    $failed ? 1 : 0
);
