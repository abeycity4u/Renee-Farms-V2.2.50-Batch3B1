<?php

$root = dirname(__DIR__);

$pagePath = $root . '/management/sales_records.php';
$jsPath = $root . '/assets/js/management-sales-records.js';
$eggPath = $root . '/lib/layer_egg_inventory.php';

$page = file_get_contents($pagePath);
$js = file_get_contents($jsPath);
$egg = file_get_contents($eggPath);

if ($page === false || $js === false || $egg === false) {
    fwrite(STDERR, "VERIFY_SETUP_FAILED\n");
    exit(2);
}

$passes = 0;
$failures = 0;

function verify_contract(bool $condition, string $label): void
{
    global $passes, $failures;

    if ($condition) {
        $passes++;
        echo "[PASS] {$label}\n";
        return;
    }

    $failures++;
    echo "[FAIL] {$label}\n";
}

/*
 * Canonical recognition:
 * browser guidance must consume the same product rules used by the
 * authoritative Layer egg sale recognizer.
 */
verify_contract(
    strpos(
        $egg,
        'function layer_egg_sale_product_match_rules'
    ) !== false
    &&
    strpos(
        $egg,
        '$rules = layer_egg_sale_product_match_rules();'
    ) !== false,
    'Layer egg recognition uses one shared rule source'
);

verify_contract(
    strpos(
        $page,
        'data-layer-egg-product-rules='
    ) !== false
    &&
    strpos(
        $page,
        'layer_egg_sale_product_match_rules()'
    ) !== false,
    'Sales browser receives canonical Layer egg rules'
);

/* Add/Edit presentation symmetry. */
verify_contract(
    substr_count(
        $page,
        '<label>Revenue Attribution</label>'
    ) === 2,
    'Add and Edit use Revenue Attribution label'
);

verify_contract(
    strpos(
        $page,
        'id="addRevenueAttributionHelp"'
    ) !== false
    &&
    strpos(
        $page,
        'id="editRevenueAttributionHelp"'
    ) !== false,
    'Add and Edit expose revenue attribution guidance'
);

/* Shared browser behavior, not duplicated modal logic. */
verify_contract(
    strpos(
        $js,
        'function revenueAttributionSelectors'
    ) !== false
    &&
    strpos(
        $js,
        'function refreshRevenueAttributionGuidance'
    ) !== false,
    'Revenue attribution guidance is shared across Add and Edit'
);

verify_contract(
    strpos(
        $js,
        'function isLayerEggSaleProduct'
    ) !== false
    &&
    strpos(
        $js,
        'salesRecordsConfig.layerEggProductRules'
    ) !== false,
    'Browser Layer egg recognition consumes server rules'
);

/* Layer egg pooled attribution. */
verify_contract(
    strpos(
        $js,
        'Automatic — based on production history'
    ) !== false
    &&
    strpos(
        $js,
        'eligible unsold egg production records'
    ) !== false
    &&
    strpos(
        $js,
        'Cycles without recorded egg production are excluded.'
    ) !== false,
    'Shared Layer egg sale explains automatic unsold-pool allocation'
);

/* Layer non-egg shared sale, including spent/old layers. */
verify_contract(
    strpos(
        $js,
        'Not tied to one cycle'
    ) !== false
    &&
    strpos(
        $js,
        'record the exact source cycle(s) and headcount under Live Animal Impact below'
    ) !== false,
    'Shared Layer non-egg sale explains separate physical animal sourcing'
);

/* Direct attribution. */
verify_contract(
    strpos(
        $js,
        'All sale revenue is attributed directly to this production cycle.'
    ) !== false,
    'Direct cycle selection explains direct revenue attribution'
);

/* General/Sales-only semantics remain neutral. */
verify_contract(
    strpos(
        $js,
        "sharedOption.text('Not applicable')"
    ) !== false
    &&
    strpos(
        $js,
        'Revenue is not attributed to a livestock production cycle.'
    ) !== false,
    'General revenue attribution remains non-livestock'
);

/*
 * Guidance must react to the fields that change its meaning.
 * Exact event formatting is intentionally not asserted.
 */
verify_contract(
    strpos(
        $js,
        '#addProductType'
    ) !== false
    &&
    strpos(
        $js,
        '#editSaleProduct'
    ) !== false
    &&
    strpos(
        $js,
        "refreshRevenueAttributionGuidance('add')"
    ) !== false
    &&
    strpos(
        $js,
        "refreshRevenueAttributionGuidance('edit')"
    ) !== false,
    'Product and cycle changes can refresh Add/Edit guidance'
);

/*
 * UI text may change, but cycle identity must remain the existing
 * numeric cycle id with 0 representing no direct cycle.
 */
verify_contract(
    strpos(
        $js,
        "cycle.find('option[value=\"0\"]')"
    ) !== false
    &&
    strpos(
        $js,
        "new Option(sharedCycleLabel, '0')"
    ) !== false,
    'Shared presentation preserves cycle_id zero contract'
);

verify_contract(
    strpos(
        $js,
        "'automatic'"
    ) === false
    &&
    strpos(
        $js,
        '"automatic"'
    ) === false,
    'UX introduces no synthetic automatic cycle value'
);

echo "\n=== RESULT ===\n";
echo "PASS={$passes}\n";
echo "FAIL={$failures}\n";

exit($failures === 0 ? 0 : 1);
