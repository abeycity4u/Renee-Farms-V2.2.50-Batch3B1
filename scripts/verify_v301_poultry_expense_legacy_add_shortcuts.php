<?php

declare(strict_types=1);

$root =
    dirname(
        __DIR__
    );

$hub =
    (string)file_get_contents(
        $root
        . '/poultry/expenses.php'
    );

$hubJs =
    (string)file_get_contents(
        $root
        . '/assets/js/poultry-expenses.js'
    );

$service =
    (string)file_get_contents(
        $root
        . '/lib/poultry_expense_entry.php'
    );

$pages = [
    'layer' =>
        (string)file_get_contents(
            $root
            . '/poultry/layer_expenses.php'
        ),

    'broiler' =>
        (string)file_get_contents(
            $root
            . '/poultry/broiler_expenses.php'
        ),
];

$checks = 0;
$failed = 0;

function poultry_legacy_add_shortcut_check(
    string $name,
    bool $ok
): void {
    global $checks, $failed;

    $checks++;

    echo
        $name
        . '='
        . (
            $ok
                ? 'PASS'
                : 'FAIL'
        )
        . PHP_EOL;

    if (!$ok) {
        $failed++;
    }
}


foreach ($pages as $production => $page) {
    $upper =
        strtoupper(
            $production
        );

    poultry_legacy_add_shortcut_check(
        $upper
        . '_LOCAL_ADD_POST_RETIRED',
        strpos(
            $page,
            "isset(\$_POST['add_expense'])"
        ) === false
    );


    poultry_legacy_add_shortcut_check(
        $upper
        . '_LOCAL_ADD_MODAL_RETIRED',
        strpos(
            $page,
            'id="addExpenseModal"'
        ) === false
    );


    poultry_legacy_add_shortcut_check(
        $upper
        . '_LOCAL_CREATE_CALL_RETIRED',
        strpos(
            $page,
            'poultry_expense_entry_create('
        ) === false
    );


    poultry_legacy_add_shortcut_check(
        $upper
        . '_ADD_PERMISSION_STILL_CANONICAL',
        strpos(
            $page,
            "\$canAddExpenses = poultry_expense_entry_can('"
            . $production
            . "', 'add');"
        ) !== false
    );


    poultry_legacy_add_shortcut_check(
        $upper
        . '_SHORTCUT_POINTS_TO_HUB',
        strpos(
            $page,
            "/poultry/expenses.php?"
        ) !== false
    );


    poultry_legacy_add_shortcut_check(
        $upper
        . '_SHORTCUT_PRESERVES_MONTH',
        strpos(
            $page,
            "'month' =>\n                                                \$yearMonth"
        ) !== false
    );


    poultry_legacy_add_shortcut_check(
        $upper
        . '_SHORTCUT_PRESELECTS_TAB',
        strpos(
            $page,
            "'tab' =>\n                                                '"
            . $production
            . "'"
        ) !== false
    );


    poultry_legacy_add_shortcut_check(
        $upper
        . '_SHORTCUT_OPENS_ADD',
        strpos(
            $page,
            "'add' =>\n                                                '1'"
        ) !== false
    );


    poultry_legacy_add_shortcut_check(
        $upper
        . '_SHORTCUT_PRESELECTS_PRODUCTION',
        strpos(
            $page,
            "'production_type' =>\n                                                '"
            . $production
            . "'"
        ) !== false
    );


    poultry_legacy_add_shortcut_check(
        $upper
        . '_FILTERED_PAGE_REMAINS_AVAILABLE',
        strpos(
            $page,
            "AND e.poultry_category = '"
            . $production
            . "'"
        ) !== false
        &&
        strpos(
            $page,
            'PDF Report'
        ) !== false
        &&
        strpos(
            $page,
            'id="editExpenseModal"'
        ) !== false
    );
}


poultry_legacy_add_shortcut_check(
    'HUB_DEEP_LINK_OPENS_ADD_MODAL',
    strpos(
        $hubJs,
        "params.get(\n            'add'"
    ) !== false
    &&
    strpos(
        $hubJs,
        "'addPoultryExpenseModal'"
    ) !== false
);


poultry_legacy_add_shortcut_check(
    'HUB_DEEP_LINK_PRESELECTS_PRODUCTION',
    strpos(
        $hubJs,
        "params.get(\n                    'production_type'"
    ) !== false
    &&
    strpos(
        $hubJs,
        'addProductionSelect.value ='
    ) !== false
);


poultry_legacy_add_shortcut_check(
    'HUB_IS_SINGLE_UI_CREATE_DELEGATE',
    substr_count(
        $hub,
        'poultry_expense_entry_create('
    ) === 1
);


poultry_legacy_add_shortcut_check(
    'SERVICE_REMAINS_SINGLE_SQL_CREATE_AUTHORITY',
    substr_count(
        $service,
        'INSERT INTO farm_expenses'
    ) === 1
);


poultry_legacy_add_shortcut_check(
    'NO_FILTERED_PAGE_DIRECT_INSERT',
    strpos(
        $pages['layer'],
        'INSERT INTO farm_expenses'
    ) === false
    &&
    strpos(
        $pages['broiler'],
        'INSERT INTO farm_expenses'
    ) === false
);


echo
    'CHECK_COUNT='
    . $checks
    . PHP_EOL;

echo
    'FAILED_COUNT='
    . $failed
    . PHP_EOL;

echo
    'RESULT='
    . (
        $failed === 0
            ? 'PASS'
            : 'FAIL'
    )
    . PHP_EOL;

exit(
    $failed === 0
        ? 0
        : 1
);
