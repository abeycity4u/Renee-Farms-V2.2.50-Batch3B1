<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$inventory =
    (string)file_get_contents(
        $root . '/inventory.php'
    );

$js =
    (string)file_get_contents(
        $root . '/assets/js/inventory.js'
    );

$bridge =
    (string)file_get_contents(
        $root
        . '/includes/inventory_permission_hardening.php'
    );

$checks = [];
$failed = 0;

function ux_check(
    string $name,
    bool $condition
): void {
    global $checks, $failed;

    $checks[] = [
        $name,
        $condition,
    ];

    if (!$condition) {
        $failed++;
    }

    echo
        $name
        . '='
        . (
            $condition
                ? 'PASS'
                : 'FAIL'
        )
        . PHP_EOL;
}


ux_check(
    'CATEGORY_SELECT_HAS_STABLE_ID',
    strpos(
        $inventory,
        'id="addItemCategory"'
    ) !== false
);

ux_check(
    'CATEGORY_OPTIONS_EMIT_FARM_TYPE',
    strpos(
        $inventory,
        'data-farm-type='
    ) !== false
);

ux_check(
    'CATEGORY_OPTIONS_EMIT_FINANCIAL_TYPE',
    strpos(
        $inventory,
        'data-financial-type='
    ) !== false
);

ux_check(
    'CATEGORY_OPTIONS_EMIT_CENTRAL_FINANCIAL_LABEL',
    strpos(
        $inventory,
        'inventory_financial_classification_label('
    ) !== false
);

ux_check(
    'CATEGORY_OPTIONS_EMIT_CENTRAL_FINANCIAL_GUIDANCE',
    strpos(
        $inventory,
        'inventory_financial_classification_guidance_text('
    ) !== false
);

ux_check(
    'CATEGORY_HELP_TARGET_EXISTS',
    strpos(
        $inventory,
        'id="addItemCategoryHelp"'
    ) !== false
);

ux_check(
    'JS_CATEGORY_FILTER_EXISTS',
    strpos(
        $js,
        'function refreshAddItemCategoryOptions()'
    ) !== false
);

ux_check(
    'LAYER_AND_BROILER_FORCE_POULTRY_UI',
    strpos(
        $js,
        "usage === 'layer'"
    ) !== false
    &&
    strpos(
        $js,
        "usage === 'broiler'"
    ) !== false
    &&
    strpos(
        $js,
        "farmSelect.value =\n                    'poultry';"
    ) !== false
);

ux_check(
    'RUMINANT_FEED_FORCES_RUMINANT_UI',
    strpos(
        $js,
        "usage === 'ruminant'"
    ) !== false
    &&
    strpos(
        $js,
        "farmSelect.value = 'ruminant'"
    ) !== false
        ||
    strpos(
        $js,
        "farmSelect.value =\n                    'ruminant';"
    ) !== false
);

ux_check(
    'GENERAL_USAGE_EXCLUDES_FEED_FINANCIAL_TYPE',
    strpos(
        $js,
        "usage === 'general'"
    ) !== false
    &&
    strpos(
        $js,
        "financialType !== 'feed'"
    ) !== false
);

ux_check(
    'SPECIALIZED_FEED_REQUIRES_FEED_FINANCIAL_TYPE',
    strpos(
        $js,
        "financialType === 'feed'"
    ) !== false
);

ux_check(
    'CATEGORY_FARM_COMPATIBILITY_FILTER_EXISTS',
    strpos(
        $js,
        "categoryFarmType === itemFarmType"
    ) !== false
    &&
    strpos(
        $js,
        "categoryFarmType === 'both'"
    ) !== false
);

ux_check(
    'INCOMPATIBLE_CATEGORY_OPTIONS_HIDDEN',
    strpos(
        $js,
        'option.hidden ='
    ) !== false
);

ux_check(
    'INCOMPATIBLE_CATEGORY_OPTIONS_DISABLED',
    strpos(
        $js,
        'option.disabled ='
    ) !== false
);

ux_check(
    'STALE_CATEGORY_SELECTION_IS_CLEARED',
    strpos(
        $js,
        "categorySelect.value = '';"
    ) !== false
);

ux_check(
    'CATEGORY_HELP_DISPLAYS_FINANCIAL_TYPE',
    strpos(
        $js,
        'Financial Type: ${label}.'
    ) !== false
);

ux_check(
    'FORM_EVENTS_USE_SINGLE_REFRESH_COORDINATOR',
    strpos(
        $js,
        'function refreshAddItemFormContract()'
    ) !== false
);

ux_check(
    'OWNER_ADMIN_BACKEND_USES_SHARED_CONTRACT',
    strpos(
        $inventory,
        'inventory_category_item_contract_errors('
    ) !== false
);

ux_check(
    'DELEGATED_BACKEND_USES_SHARED_CONTRACT',
    strpos(
        $bridge,
        'inventory_category_item_contract_errors('
    ) !== false
);

ux_check(
    'CATEGORY_MODAL_STILL_HAS_NO_CYCLE_FIELD',
    preg_match(
        '/id="addCategoryModal"[\s\S]*?<\/div>\s*<\/div>\s*<\/div>\s*<\?php endif; \?>/',
        $inventory,
        $match
    ) === 1
    &&
    stripos(
        $match[0],
        'cycle'
    ) === false
);


echo
    'CHECK_COUNT='
    . count($checks)
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
