<?php

$root = dirname(__DIR__);
$page = file_get_contents(
    $root . '/management/poultry_cycle.php'
);

$passes = 0;
$failures = 0;

$check = static function (
    bool $condition,
    string $label
) use (&$passes, &$failures): void {
    if ($condition) {
        $passes++;
        echo "PASS: {$label}\n";
        return;
    }

    $failures++;
    echo "FAIL: {$label}\n";
};

$topStart = strpos(
    (string)$page,
    '<div class="row g-3 mb-3">'
);

$overviewStart = strpos(
    (string)$page,
    '<strong>Cycle Overview</strong>'
);

$workspaceStart = strpos(
    (string)$page,
    '<strong>Cycle Workspace</strong>'
);

$top = (
    $topStart !== false
    && $overviewStart !== false
    && $overviewStart > $topStart
)
    ? substr(
        (string)$page,
        $topStart,
        $overviewStart - $topStart
    )
    : '';

$overview = (
    $overviewStart !== false
    && $workspaceStart !== false
    && $workspaceStart > $overviewStart
)
    ? substr(
        (string)$page,
        $overviewStart,
        $workspaceStart - $overviewStart
    )
    : '';

$check(
    $top !== '',
    'Manage Cycle top intelligence region found.'
);

$check(
    substr_count(
        $top,
        'workspace-stat'
    ) === 4,
    'Top intelligence remains four focused cards.'
);

$check(
    strpos($top, 'Status') !== false,
    'Top intelligence keeps Status.'
);

$check(
    strpos($top, 'Biological Stage') !== false,
    'Top intelligence keeps Biological Stage.'
);

$check(
    strpos(
        $top,
        '$populationLabel'
    ) !== false,
    'Top intelligence keeps canonical population card.'
);

$check(
    strpos(
        $top,
        'Bird Cost Basis'
    ) !== false,
    'Top intelligence now exposes Bird Cost Basis.'
);

$check(
    strpos(
        $top,
        "\$cycle['bird_unit_cost']"
    ) !== false
    && strpos(
        $top,
        '$moneyOrDash('
    ) !== false,
    'Bird Cost Basis reads and formats the canonical cycle value.'
);

$check(
    strpos(
        $top,
        'Mortality valuation basis'
    ) !== false,
    'Bird Cost Basis remains explicitly tied to mortality valuation.'
);

$check(
    strpos(
        $top,
        'Expected End'
    ) === false,
    'Expected End no longer occupies a top intelligence card.'
);

$check(
    $overview !== '',
    'Cycle Overview region found.'
);

foreach (
    [
        'Cycle Start',
        'Expected End',
        'Opening Headcount',
        'Production End',
        'Closing Headcount',
    ] as $label
) {
    $check(
        strpos(
            $overview,
            $label
        ) !== false,
        "Cycle Overview contains {$label}."
    );
}

$check(
    strpos(
        $overview,
        "\$cycle['expected_end_date']"
    ) !== false,
    'Expected End remains sourced from the cycle record.'
);

$check(
    substr_count(
        $overview,
        'col-6 col-lg'
    ) === 5,
    'Cycle Overview presents five responsive facts.'
);


// ------------------------------------------------------------
// Golden economics regression contract.
// Preserves farmer-facing Manage Cycle behavior already proven
// through the earlier multi-version economic-basis QA.
// ------------------------------------------------------------

$check(
    strpos(
        (string)$page,
        'Layer Rearing & Production-Entry Economics'
    ) !== false,
    'Layer rearing economics remains farmer-visible.'
);

$check(
    strpos(
        (string)$page,
        'Rearing-window rule:'
    ) !== false
    && strpos(
        (string)$page,
        'effective/source date'
    ) !== false
    && strpos(
        (string)$page,
        'including its closing date'
    ) !== false,
    'Rearing economics explains its effective-date window to the farmer.'
);

$check(
    strpos(
        (string)$page,
        'Bird acquisition basis'
    ) !== false
    && strpos(
        (string)$page,
        "\$rearingEconomics['acquisition_cost']"
    ) !== false,
    'Rearing economics retains bird acquisition basis.'
);

$check(
    strpos(
        (string)$page,
        'Feed actually consumed during Rearing'
    ) !== false
    && strpos(
        (string)$page,
        "\$rearingEconomics['feed_consumed_cost']"
    ) !== false,
    'Rearing economics retains actual feed-consumption cost.'
);

$check(
    strpos(
        (string)$page,
        'Medication/Vaccine, supplements & consumables USED'
    ) !== false
    && strpos(
        (string)$page,
        "\$rearingEconomics['inventory_operating_cost']"
    ) !== false,
    'Rearing economics retains consumed health and inventory cost.'
);

$check(
    strpos(
        (string)$page,
        'Direct non-feed expenses'
    ) !== false
    && strpos(
        (string)$page,
        "\$rearingEconomics['direct_expenses']"
    ) !== false,
    'Rearing economics retains direct non-feed expenses.'
);

$check(
    strpos(
        (string)$page,
        'Explicit shared-expense allocations to this cycle'
    ) !== false
    && strpos(
        (string)$page,
        "\$rearingEconomics['allocated_shared_expenses']"
    ) !== false,
    'Explicit shared-expense allocations remain separately visible.'
);

$check(
    strpos(
        (string)$page,
        'Surviving flock at Production entry'
    ) !== false
    && strpos(
        (string)$page,
        "\$rearingEconomics['production_entry_headcount']"
    ) !== false,
    'Production-entry economics retains surviving flock headcount.'
);

$check(
    strpos(
        (string)$page,
        'Unallocated shared Layer expense pool in this rearing window:'
    ) !== false
    && strpos(
        (string)$page,
        'It is disclosed but not silently assigned to this cycle.'
    ) !== false,
    'Unallocated shared expenses remain disclosed without silent allocation.'
);

$check(
    strpos(
        (string)$page,
        'This is a read layer over recorded source transactions.'
    ) !== false
    && strpos(
        (string)$page,
        'It does not alter monthly profitability, Bird Cost Basis, inventory, expenses, mortality records, or lifecycle history.'
    ) !== false,
    'Rearing economics remains a read layer over canonical transactions.'
);

$check(
    strpos(
        (string)$page,
        '<strong>Production-Entry Economic Basis</strong>'
    ) !== false,
    'Production-Entry Economic Basis remains present.'
);

$check(
    strpos(
        (string)$page,
        'does not replace Bird Cost Basis used for mortality valuation.'
    ) !== false,
    'Production-Entry Economic Basis remains separate from Bird Cost Basis.'
);

$check(
    strpos(
        (string)$page,
        'Current source provenance and economics match the latest approved version.'
    ) !== false,
    'Manage Cycle retains approved-versus-current match state.'
);

$check(
    strpos(
        (string)$page,
        'Historical source economics changed after the latest approval.'
    ) !== false,
    'Manage Cycle retains source-correction detection after approval.'
);

$check(
    strpos(
        (string)$page,
        'Attributed investment changed:'
    ) !== false,
    'Manage Cycle retains attributed-investment correction disclosure.'
);

$check(
    strpos(
        (string)$page,
        'Production-entry flock changed:'
    ) !== false,
    'Manage Cycle retains entry-headcount correction disclosure.'
);

$check(
    strpos(
        (string)$page,
        'Approved Basis History'
    ) !== false
    && strpos(
        (string)$page,
        'previous versions remain immutable'
    ) !== false,
    'Approved Basis History remains visible and immutable.'
);

$check(
    strpos(
        (string)$page,
        '$productionEntrySnapshots as $snap'
    ) !== false,
    'Approved versions continue rendering from immutable snapshot history.'
);

$check(
    strpos(
        (string)$page,
        'source_transaction_correction'
    ) !== false
    && strpos(
        (string)$page,
        'missing_historical_transaction'
    ) !== false
    && strpos(
        (string)$page,
        'allocation_correction'
    ) !== false
    && strpos(
        (string)$page,
        'production_entry_headcount_correction'
    ) !== false
    && strpos(
        (string)$page,
        'administrative_correction'
    ) !== false,
    'Approved-basis revision categories remain available.'
);

$check(
    strpos(
        (string)$page,
        'revision_reason'
    ) !== false
    && strpos(
        (string)$page,
        'Explain the correction; do not replace source accounting here'
    ) !== false,
    'Revised versions retain an explicit correction reason.'
);

$check(
    strpos(
        (string)$page,
        'approve_production_entry_basis'
    ) !== false
    && strpos(
        (string)$page,
        'Approve this source-derived Production-Entry Economic Basis as an immutable version?'
    ) !== false,
    'Production-entry approval remains an explicit immutable-version action.'
);

$check(
    strpos(
        (string)$page,
        'Approval freezes this version only.'
    ) !== false
    && strpos(
        (string)$page,
        'Future source corrections are detected and require a new approved version'
    ) !== false
    && strpos(
        (string)$page,
        'Bird Cost Basis is not changed.'
    ) !== false,
    'Approval freezes only the snapshot and does not rewrite Bird Cost Basis.'
);


echo "RESULT={$passes}_PASS_{$failures}_FAIL\n";

exit(
    $failures > 0
        ? 1
        : 0
);
