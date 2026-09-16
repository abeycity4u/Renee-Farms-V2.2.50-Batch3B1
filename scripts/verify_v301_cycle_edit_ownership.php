<?php
/**
 * V3.0.1 — Edit Cycle ownership and correction verifier.
 * Static only: no DB connection and no DB writes.
 */

$root = dirname(__DIR__);

$manage = (string)file_get_contents(
    $root . '/management/poultry_cycle.php'
);

$overview = (string)file_get_contents(
    $root . '/management/production_cycles.php'
);

$edit = (string)file_get_contents(
    $root . '/management/production_cycle_edit.php'
);

$service = (string)file_get_contents(
    $root . '/lib/production_cycle_service.php'
);

$onboarding = (string)file_get_contents(
    $root . '/lib/poultry_cycle_onboarding.php'
);

$acquisition = (string)file_get_contents(
    $root . '/lib/poultry_cycle_acquisition.php'
);

$population = (string)file_get_contents(
    $root . '/lib/production_population.php'
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

$check(
    strpos(
        $overview,
        '/management/production_cycle_edit.php?id='
    ) !== false
    && strpos(
        $overview,
        '>Edit</a>'
    ) !== false,
    'Production Cycles exposes Edit beside cycle actions.'
);

$check(
    strpos(
        $manage,
        'Correct an Erroneous Entry'
    ) === false
    && strpos(
        $manage,
        'Correction available'
    ) === false
    && strpos(
        $manage,
        'void_poultry_acquisition'
    ) === false
    && strpos(
        $manage,
        'poultry_acquisition_void('
    ) === false,
    'Manage Cycle no longer exposes routine acquisition correction.'
);

$check(
    strpos(
        $manage,
        'poultry_acquisition_history('
    ) !== false
    && strpos(
        $manage,
        'poultry_acquisition_summary('
    ) !== false,
    'Manage Cycle retains acquisition history and intelligence.'
);

$check(
    strpos(
        $acquisition,
        'function poultry_acquisition_void('
    ) !== false,
    'Audit-safe acquisition void primitive remains available internally.'
);

foreach (
    [
        'name="cycle_code"',
        'name="opening_headcount"',
        'name="poultry_total_cost"',
        'name="expected_end_date"',
        'name="notes"',
    ] as $field
) {
    $check(
        strpos($edit, $field) !== false,
        "Edit Cycle exposes locked editable field {$field}."
    );
}

$check(
    strpos(
        $edit,
        'name="correction_reason"'
    ) !== false
    && strpos(
        $edit,
        'Required when either opening/acquisition value changes.'
    ) !== false,
    'Initial-fact corrections require an explicit reason.'
);

foreach (
    [
        'name="bird_unit_cost"',
        'name="farm_type"',
        'name="production_type"',
        'name="status"',
        'name="start_date"',
        'name="close_date"',
        'name="closing_headcount"',
    ] as $field
) {
    $check(
        strpos($edit, $field) === false,
        "Edit Cycle does not expose protected field {$field}."
    );
}

$check(
    strpos(
        $edit,
        'production_cycle_update_metadata('
    ) !== false
    && strpos(
        $edit,
        'production_cycle_correct_opening_headcount('
    ) !== false
    && strpos(
        $edit,
        'poultry_cycle_onboarding_correct_initial_acquisition('
    ) !== false,
    'Edit Cycle delegates correction work to shared services.'
);

$editOwnsSql =
    strpos($edit, '->prepare(') !== false
    || strpos($edit, '->query(') !== false
    || stripos($edit, 'INSERT INTO') !== false
    || stripos($edit, 'UPDATE production_cycles') !== false
    || stripos($edit, 'DELETE FROM') !== false;

$check(
    !$editOwnsSql,
    'Edit Cycle remains a thin route with no direct SQL.'
);

$check(
    strpos(
        $edit,
        "isPlatformOwner() || hasRole('farm_admin')"
    ) !== false,
    'Edit Cycle keeps the Owner/Farm Admin write boundary.'
);

$check(
    strpos(
        $edit,
        'poultry_acquisition_cost_per_bird('
    ) !== false
    && strpos(
        $edit,
        'production_cycle_update_bird_cost_basis('
    ) !== false,
    'Bird Cost Basis is derived from corrected Total Acquisition Cost and Opening Headcount.'
);

$check(
    strpos(
        $edit,
        'name="poultry_unit_price"'
    ) === false
    && strpos(
        $edit,
        'Price / Bird'
    ) === false,
    'Edit Cycle does not reintroduce a second acquisition-price input.'
);

$check(
    strpos(
        $service,
        'function production_cycle_correct_opening_headcount('
    ) !== false,
    'Canonical opening-headcount correction service exists.'
);

$openingStart = strpos(
    $service,
    "if (!function_exists('production_cycle_correct_opening_headcount'))"
);

$openingEnd = strpos(
    $service,
    "if (!function_exists('production_cycle_cutover_population_v3'))",
    $openingStart === false
        ? 0
        : $openingStart
);

$openingBlock = (
    $openingStart !== false
    && $openingEnd !== false
    && $openingEnd > $openingStart
)
    ? substr(
        $service,
        $openingStart,
        $openingEnd - $openingStart
    )
    : '';

$check(
    strpos(
        $openingBlock,
        'production_population_record_movement('
    ) !== false
    && strpos(
        $openingBlock,
        "'adjustment_in'"
    ) !== false
    && strpos(
        $openingBlock,
        "'adjustment_out'"
    ) !== false,
    'Opening-headcount correction uses the canonical population ledger.'
);

$check(
    stripos(
        $openingBlock,
        'UPDATE production_population_baselines'
    ) === false,
    'Opening-headcount correction never rewrites the immutable V3 baseline.'
);

$check(
    strpos(
        $population,
        'Use a population adjustment instead of rewriting the baseline.'
    ) !== false,
    'Existing V3 population contract explicitly protects baseline immutability.'
);

$check(
    strpos(
        $onboarding,
        'function poultry_cycle_onboarding_correct_initial_acquisition('
    ) !== false
    && strpos(
        $onboarding,
        'poultry_acquisition_void('
    ) !== false
    && strpos(
        $onboarding,
        'poultry_acquisition_record('
    ) !== false,
    'Initial acquisition correction is void-and-replace, never silent overwrite.'
);

$check(
    strpos(
        $onboarding,
        'previous_acquisition_id'
    ) !== false
    && strpos(
        $onboarding,
        'correction_reason'
    ) !== false,
    'Acquisition correction preserves an explicit audit link and reason.'
);

$check(
    strpos(
        $overview,
        'corrections to Opening Headcount or Total Acquisition Cost'
    ) !== false
    && strpos(
        $overview,
        'the erroneous row is voided rather than deleted'
    ) !== false,
    'Production Cycles explains the new correction ownership and audit behavior.'
);

$check(
    strpos(
        $edit,
        '$pdo->beginTransaction();'
    ) !== false
    && strpos(
        $edit,
        '$pdo->commit();'
    ) !== false
    && strpos(
        $edit,
        '$pdo->rollBack();'
    ) !== false,
    'Metadata, opening population, acquisition and Bird Cost Basis correction are atomic.'
);

$check(
    strpos(
        $edit,
        'Existing Daily Records are separate historical source'
    ) !== false
    && strpos(
        $edit,
        'are not silently rewritten by this correction.'
    ) !== false,
    'Opening-headcount correction preserves Daily Records as separate source history.'
);

$acquisitionTokenStart = strpos(
    $acquisition,
    "if (\$requestToken !== '') {"
);

$acquisitionInsertStart = strpos(
    $acquisition,
    'INSERT INTO poultry_cycle_acquisitions',
    $acquisitionTokenStart === false
        ? 0
        : $acquisitionTokenStart
);

$acquisitionTokenBlock = (
    $acquisitionTokenStart !== false
    && $acquisitionInsertStart !== false
    && $acquisitionInsertStart > $acquisitionTokenStart
)
    ? substr(
        $acquisition,
        $acquisitionTokenStart,
        $acquisitionInsertStart - $acquisitionTokenStart
    )
    : '';

$check(
    $acquisitionTokenBlock !== ''
    && strpos(
        $acquisitionTokenBlock,
        'cycle_id'
    ) !== false
    && strpos(
        $acquisitionTokenBlock,
        'voided_at'
    ) !== false
    && strpos(
        $acquisitionTokenBlock,
        'belongs to an entry that has since been corrected or voided'
    ) !== false
    && strpos(
        $acquisitionTokenBlock,
        'FOR UPDATE'
    ) !== false,
    'Canonical acquisition idempotency locks and never resolves a voided token as active success.'
);

$check(
    $acquisitionTokenBlock !== ''
    && strpos(
        $acquisitionTokenBlock,
        'has already been used for different flock-entry details'
    ) !== false
    && strpos(
        $acquisitionTokenBlock,
        '$sameCost'
    ) !== false
    && strpos(
        $acquisitionTokenBlock,
        '$same ='
    ) !== false,
    'Canonical acquisition token reuse must match the original acquisition payload.'
);

$check(
    strpos(
        $edit,
        '<?php if ($isPoultry && $currentAcquisition === null): ?>'
    ) !== false
    && substr_count(
        $edit,
        'name="opening_headcount"'
    ) === 2,
    'Metadata-only edits retain the existing Opening Headcount when acquisition correction is unavailable.'
);

$check(
    strpos(
        $edit,
        'Cycle details and opening headcount updated. The correction remains traceable in cycle audit history.'
    ) !== false
    && strpos(
        $edit,
        'Cycle details and initial flock facts updated. The previous acquisition entry remains in audit history.'
    ) !== false,
    'Edit Cycle uses acquisition-history success copy only for poultry and generic audit copy for non-poultry opening correction.'
);

$check(
    strpos(
        $edit,
        "require_once(__DIR__ . '/../includes/notifications.php');"
    ) !== false
    && strpos(
        $edit,
        'redirectWithNotification('
    ) !== false
    && strpos(
        $edit,
        "'success',"
    ) !== false
    && strpos(
        $edit,
        "'/management/production_cycles.php#recent-cycles'"
    ) !== false
    && strpos(
        $edit,
        "production_cycle_edit_success"
    ) === false
    && strpos(
        $edit,
        'alert alert-success'
    ) === false,
    'Successful Edit Cycle save uses the shared notification system and returns to the Production Cycles table.'
);

echo "RESULT={$passes}_PASS_{$failures}_FAIL\n";

exit(
    $failures > 0
        ? 1
        : 0
);
