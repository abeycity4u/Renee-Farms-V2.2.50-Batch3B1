<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$files = [
    'financial' => $root . '/lib/inventory_financial.php',
    'inventory' => $root . '/inventory.php',
    'js' => $root . '/assets/js/inventory.js',
];

$failed = 0;
$count = 0;

function check_contract(
    string $label,
    bool $condition
): void {
    global $failed, $count;

    $count++;

    echo
        $label
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

foreach ($files as $key => $path) {
    check_contract(
        'FILE_' . strtoupper($key) . '_PRESENT',
        is_file($path)
    );
}

$financial =
    is_file($files['financial'])
        ? (string)file_get_contents($files['financial'])
        : '';

$inventory =
    is_file($files['inventory'])
        ? (string)file_get_contents($files['inventory'])
        : '';

$js =
    is_file($files['js'])
        ? (string)file_get_contents($files['js'])
        : '';


/*
 * Central shared policy.
 */
check_contract(
    'CENTRAL_GUIDANCE_POLICY_EXISTS',
    strpos(
        $financial,
        'function inventory_financial_classification_guidance()'
    ) !== false
);

check_contract(
    'CENTRAL_GUIDANCE_READER_EXISTS',
    strpos(
        $financial,
        'function inventory_financial_classification_guidance_text('
    ) !== false
);

check_contract(
    'CENTRAL_CATEGORY_FARM_POLICY_EXISTS',
    strpos(
        $financial,
        'function inventory_category_allows_item_farm_type('
    ) !== false
);

check_contract(
    'CENTRAL_CATEGORY_ITEM_CONTRACT_EXISTS',
    strpos(
        $financial,
        'function inventory_category_item_contract_errors('
    ) !== false
);


/*
 * All seven controlled Financial Types have farmer guidance.
 */
foreach (
    [
        'feed',
        'medication_vaccine',
        'supplement',
        'consumables',
        'equipment_tools',
        'spare_parts',
        'other_stock',
    ]
    as $type
) {
    check_contract(
        'GUIDANCE_' . strtoupper($type),
        preg_match(
            "/['\\\"]"
            . preg_quote($type, '/')
            . "['\\\"]\\s*=>/",
            $financial
        ) === 1
    );
}

check_contract(
    'GUIDANCE_CONSUMABLES_HAS_EXAMPLES',
    stripos(
        $financial,
        'Hydrogen Peroxide'
    ) !== false
);

check_contract(
    'GUIDANCE_MEDICATION_HAS_EXAMPLES',
    stripos(
        $financial,
        'EDS vaccine'
    ) !== false
);

check_contract(
    'GUIDANCE_FEED_PRESERVES_FEED_ACCOUNTING',
    stripos(
        $financial,
        'Feed cost enters Profitability when consumed'
    ) !== false
);


/*
 * Feed consistency is bidirectional inside the shared contract.
 */
check_contract(
    'CONTRACT_REJECTS_FEED_FINANCIAL_GENERAL_USAGE',
    strpos(
        $financial,
        "Financial Type Feed requires Layer Feed, Broiler Feed or Ruminant Feed usage."
    ) !== false
);

check_contract(
    'CONTRACT_REJECTS_FEED_USAGE_NONFEED_FINANCIAL',
    strpos(
        $financial,
        "Layer, Broiler and Ruminant Feed usage requires Financial Type Feed."
    ) !== false
);


/*
 * Category update and Add Item must both use the same policy.
 */
$contractCallCount =
    substr_count(
        $inventory,
        'inventory_category_item_contract_errors('
    );

check_contract(
    'INVENTORY_USES_SHARED_CONTRACT_TWICE',
    $contractCallCount === 2
);

check_contract(
    'ADD_ITEM_LOADS_CATEGORY_FARM_AND_FINANCIAL',
    preg_match(
        "/SELECT\\s+id,\\s*farm_type,\\s*financial_type\\s+FROM\\s+inventory_categories/s",
        $inventory
    ) === 1
);

check_contract(
    'CATEGORY_UPDATE_LOCKS_CATEGORY',
    preg_match(
        "/SELECT\\s+id,\\s*farm_type\\s+FROM\\s+inventory_categories.*FOR UPDATE/s",
        $inventory
    ) === 1
);

check_contract(
    'CATEGORY_UPDATE_READS_EXISTING_ITEMS',
    preg_match(
        "/SELECT\\s+id,\\s*item_name,\\s*farm_type,\\s*feed_category\\s+FROM\\s+stock_items/s",
        $inventory
    ) === 1
);


/*
 * Financial Type guidance is dynamic, but policy text remains PHP-owned.
 */
check_contract(
    'FINANCIAL_TYPE_SELECT_HAS_DYNAMIC_HELP_SOURCE',
    strpos(
        $inventory,
        'data-help="<?php echo htmlspecialchars(inventory_financial_classification_guidance_text($financialKey), ENT_QUOTES); ?>"'
    ) !== false
);

check_contract(
    'FINANCIAL_TYPE_HELP_TARGET_EXISTS',
    strpos(
        $inventory,
        'id="categoryFinancialTypeHelp"'
    ) !== false
);

check_contract(
    'JS_DYNAMIC_GUIDANCE_EXISTS',
    strpos(
        $js,
        'function refreshCategoryFinancialTypeGuidance()'
    ) !== false
);

check_contract(
    'JS_DYNAMIC_GUIDANCE_CHANGE_LISTENER',
    strpos(
        $js,
        "'categoryFinancialTypeSelect'"
    ) !== false
    &&
    strpos(
        $js,
        'refreshCategoryFinancialTypeGuidance'
    ) !== false
);


/*
 * User decisions preserved.
 */
$modalStart =
    strpos(
        $inventory,
        'id="addCategoryModal"'
    );

$modalEnd =
    $modalStart === false
        ? false
        : strpos(
            $inventory,
            '<!-- Add Item Modal -->',
            $modalStart
        );

$categoryModal =
    (
        $modalStart !== false
        &&
        $modalEnd !== false
    )
        ? substr(
            $inventory,
            $modalStart,
            $modalEnd - $modalStart
        )
        : '';

check_contract(
    'MANAGE_CATEGORY_HAS_NO_CYCLE_FIELD',
    preg_match(
        '/name=["\'][^"\']*cycle/i',
        $categoryModal
    ) !== 1
);

check_contract(
    'MANAGE_CATEGORY_STILL_PRESERVES_BOTH',
    strpos(
        $categoryModal,
        "type === 'both'"
    ) !== false
);


/*
 * This step must not change Feed financial type into Consumables.
 */
check_contract(
    'FEED_FINANCIAL_TYPE_STILL_EXISTS',
    strpos(
        $financial,
        "'feed' => 'Feed'"
    ) !== false
);

check_contract(
    'CONSUMABLES_REMAINS_SEPARATE',
    strpos(
        $financial,
        "'consumables' => 'Consumables'"
    ) !== false
);


/*
 * This phase intentionally does not solve the two later audit items yet.
 */
check_contract(
    'LEGACY_FEED_READER_NOT_TOUCHED_BY_THIS_VERIFIER',
    true
);

check_contract(
    'LEGACY_CATEGORY_ROUTES_NOT_TOUCHED_BY_THIS_VERIFIER',
    true
);


echo 'CHECK_COUNT=' . $count . PHP_EOL;
echo 'FAILED_COUNT=' . $failed . PHP_EOL;
echo 'RESULT=' . ($failed === 0 ? 'PASS' : 'FAIL') . PHP_EOL;

exit(
    $failed === 0
        ? 0
        : 1
);
