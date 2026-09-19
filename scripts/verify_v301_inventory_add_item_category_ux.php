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

$checks = 0;
$failed = 0;

function ux_check(
    string $name,
    bool $condition
): void {
    global $checks, $failed;

    $checks++;

    echo
        $name
        . '='
        . (
            $condition
                ? 'PASS'
                : 'FAIL'
        )
        . PHP_EOL;

    if (!$condition) {
        $failed++;
    }
}


$itemNamePos =
    strpos(
        $inventory,
        'name="item_name"'
    );

$categoryPos =
    strpos(
        $inventory,
        'id="addItemCategory"'
    );

$farmTypePos =
    strpos(
        $inventory,
        'id="addItemFarmType"'
    );

$usagePos =
    strpos(
        $inventory,
        'id="addItemUsageClassification"'
    );


ux_check(
    'FORM_ORDER_ITEM_CATEGORY_FARM_USAGE',
    $itemNamePos !== false
    && $categoryPos !== false
    && $farmTypePos !== false
    && $usagePos !== false
    && $itemNamePos < $categoryPos
    && $categoryPos < $farmTypePos
    && $farmTypePos < $usagePos
);


ux_check(
    'CATEGORY_OPTIONS_REMAIN_VISIBLE_FIRST',
    strpos(
        $js,
        'categorySelect.options'
    ) === false
    &&
    strpos(
        $js,
        'refreshAddItemCategoryOptions'
    ) === false
);


ux_check(
    'CATEGORY_EMITS_FINANCIAL_TYPE',
    strpos(
        $inventory,
        'data-financial-type='
    ) !== false
);


ux_check(
    'CATEGORY_EMITS_FARM_TYPE',
    strpos(
        $inventory,
        'data-farm-type='
    ) !== false
);


ux_check(
    'CATEGORY_SHOWS_CENTRAL_FINANCIAL_GUIDANCE',
    strpos(
        $inventory,
        'inventory_financial_classification_guidance_text('
    ) !== false
    &&
    strpos(
        $js,
        'Financial Type: ${label}.'
    ) !== false
);


ux_check(
    'FARM_TYPE_STARTS_EXPLICIT',
    strpos(
        $inventory,
        '<option value="">Select Farm Type</option>'
    ) !== false
);


ux_check(
    'USAGE_STARTS_EXPLICIT',
    strpos(
        $inventory,
        '<option value="">Select Usage Classification</option>'
    ) !== false
);


ux_check(
    'CATEGORY_CONTROLS_FARM_TYPE_AVAILABILITY',
    strpos(
        $js,
        "categoryFarmType === 'both'"
    ) !== false
    &&
    strpos(
        $js,
        'option.value === categoryFarmType'
    ) !== false
);


ux_check(
    'FEED_ITEM_CANNOT_REMAIN_FARM_TYPE_BOTH',
    strpos(
        $js,
        "financialType === 'feed'"
    ) !== false
    &&
    strpos(
        $js,
        "option.value === 'both'"
    ) !== false
);


ux_check(
    'POULTRY_FEED_USAGE_IS_LAYER_OR_BROILER',
    strpos(
        $js,
        "farmType === 'poultry'"
    ) !== false
    &&
    strpos(
        $js,
        "'layer'"
    ) !== false
    &&
    strpos(
        $js,
        "'broiler'"
    ) !== false
);


ux_check(
    'RUMINANT_FEED_USAGE_IS_RUMINANT_ONLY',
    strpos(
        $js,
        "farmType === 'ruminant'"
    ) !== false
    &&
    strpos(
        $js,
        "'ruminant'"
    ) !== false
);


ux_check(
    'NONFEED_USAGE_IS_GENERAL_ONLY',
    strpos(
        $js,
        "allowedUsage = [\n                'general',"
    ) !== false
);


ux_check(
    'USAGE_FILTER_DEPENDS_ON_CATEGORY_FINANCIAL_TYPE',
    strpos(
        $js,
        "const financialType ="
    ) !== false
    &&
    strpos(
        $js,
        "if (financialType === 'feed')"
    ) !== false
);


ux_check(
    'FARM_TYPE_DOES_NOT_DEPEND_ON_USAGE',
    strpos(
        $js,
        "farmSelect.value =\n                    'poultry';"
    ) === false
    &&
    strpos(
        $js,
        "farmSelect.value =\n                    'ruminant';"
    ) === false
);


ux_check(
    'OWNER_BACKEND_DOES_NOT_REWRITE_FARM_FROM_USAGE',
    strpos(
        $inventory,
        "if (\$feedCategory === 'ruminant')"
    ) === false
    &&
    strpos(
        $inventory,
        "in_array(\$feedCategory, ['layer', 'broiler'], true)"
    ) === false
);


ux_check(
    'DELEGATED_BACKEND_DOES_NOT_REWRITE_FARM_FROM_USAGE',
    strpos(
        $bridge,
        "if (\$feedCategory === 'ruminant')"
    ) === false
    &&
    strpos(
        $bridge,
        "in_array(\$feedCategory, ['layer', 'broiler'], true)"
    ) === false
);


ux_check(
    'OWNER_BACKEND_USES_SHARED_CONTRACT',
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
    'OWNER_REQUIRES_EXPLICIT_USAGE',
    strpos(
        $inventory,
        "\$_POST['feed_category'] ?? ''"
    ) !== false
);


ux_check(
    'DELEGATED_REQUIRES_EXPLICIT_USAGE',
    strpos(
        $bridge,
        "\$_POST['feed_category'] ?? ''"
    ) !== false
);


ux_check(
    'DEFAULT_ATTRIBUTION_WAITS_FOR_FARM_AND_USAGE',
    strpos(
        $js,
        '!farmType'
    ) !== false
    &&
    strpos(
        $js,
        '!usage'
    ) !== false
);


ux_check(
    'GENERAL_BOTH_REMAINS_SHARED_FARM_WIDE',
    strpos(
        $js,
        "['shared', 'Shared / Farm-wide']"
    ) !== false
);


ux_check(
    'MANAGE_CATEGORY_CYCLE_NOT_ADDED',
    strpos(
        $inventory,
        'name="category_cycle'
    ) === false
);


ux_check(
    'BOTH_LABEL_CONTRACT_UNCHANGED',
    strpos(
        $inventory,
        'allowedFarmTypes()'
    ) !== false
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
