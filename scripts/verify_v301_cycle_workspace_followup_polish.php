<?php
/**
 * V3.0.1 — Cycle workspace follow-up polish.
 *
 * Source-only verification.
 * No database connection.
 * No database writes.
 */

$root = dirname(__DIR__);

$read = static function (string $path) use ($root): string {
    $full = $root . '/' . ltrim($path, '/');

    return is_file($full)
        ? (string)file_get_contents($full)
        : '';
};

$snapshots =
    $read('lib/poultry_production_entry_snapshots.php');

$poultry =
    $read('management/poultry_cycle.php');

$ruminant =
    $read('management/ruminant_cycle.php');

$allocationPage =
    $read('management/expense_allocation.php');

$allocationJs =
    $read('assets/js/financial-allocation-workspace.js');

$cycles =
    $read('management/production_cycles.php');

$checks = [];
$failures = [];

$check = static function (
    string $name,
    bool $ok
) use (
    &$checks,
    &$failures
): void {
    $checks[$name] = $ok;

    if (!$ok) {
        $failures[] = $name;
    }
};

$check(
    'APPROVER_HISTORY_USES_FULL_NAME',
    strpos(
        $snapshots,
        'u.full_name approved_by_name'
    ) !== false
    &&
    strpos(
        $snapshots,
        'u.username approved_by_name'
    ) === false
);

$check(
    'APPROVER_SHARED_HELPER_FALLBACK',
    strpos(
        $snapshots,
        'transaction_actor_display.php'
    ) !== false
    &&
    strpos(
        $snapshots,
        'transaction_recorded_by_label_for_farm('
    ) !== false
);

$check(
    'POULTRY_SHARED_NOTIFICATION_RENDERER',
    strpos(
        $poultry,
        "renderNotification(\n            'success'"
    ) !== false
    &&
    strpos(
        $poultry,
        "renderNotification(\n            'error'"
    ) !== false
);

$check(
    'RUMINANT_SHARED_NOTIFICATION_RENDERER',
    strpos(
        $ruminant,
        "renderNotification(\n            'success'"
    ) !== false
    &&
    strpos(
        $ruminant,
        "renderNotification(\n            'error'"
    ) !== false
);

$check(
    'POULTRY_OWNER_AUTHORITY_PRESERVED',
    strpos(
        $poultry,
        'isPlatformOwner()'
    ) !== false
    &&
    strpos(
        $poultry,
        "hasRole('farm_admin')"
    ) !== false
);

$check(
    'POULTRY_TENANT_SAFE_COPY',
    strpos(
        $poultry,
        'Stage changes are available to a Farm Admin.'
    ) !== false
    &&
    strpos(
        $poultry,
        'Approval or revision is available to a Farm Admin.'
    ) !== false
    &&
    strpos(
        $poultry,
        'Only a Farm Admin can end production.'
    ) !== false
    &&
    strpos(
        $poultry,
        'Stage changes are available to the Platform Owner'
    ) === false
    &&
    strpos(
        $poultry,
        'Approval or revision is available to the Platform Owner or Farm Admin.'
    ) === false
    &&
    strpos(
        $poultry,
        'Only the Platform Owner or Farm Admin'
    ) === false
);

$check(
    'SHARED_COST_CLEAR_BUTTON_PRESENT',
    strpos(
        $allocationPage,
        'id="financialAllocationClearAmounts"'
    ) !== false
    &&
    strpos(
        $allocationPage,
        'Clear amounts'
    ) !== false
);

$check(
    'SHARED_COST_CLEAR_BEHAVIOR',
    strpos(
        $allocationJs,
        "'financialAllocationClearAmounts'"
    ) !== false
    &&
    strpos(
        $allocationJs,
        'clearAmountsButton.addEventListener('
    ) !== false
    &&
    strpos(
        $allocationJs,
        "input.value =\n                            '0.00';"
    ) !== false
    &&
    strpos(
        $allocationJs,
        'equalSplitCheckbox.checked ='
    ) !== false
);

$recentStart =
    strpos(
        $cycles,
        '$recentStmt ='
    );

$recentEnd =
    $recentStart !== false
        ? strpos(
            $cycles,
            '$recentCycles =',
            $recentStart
        )
        : false;

$recentBlock =
    $recentStart !== false
    && $recentEnd !== false
        ? substr(
            $cycles,
            $recentStart,
            $recentEnd - $recentStart
        )
        : '';

$check(
    'PRODUCTION_CYCLES_OPEN_TABLE_EXCLUDES_CLOSED',
    $recentBlock !== ''
    &&
    strpos(
        $recentBlock,
        "status <> 'closed'"
    ) !== false
);

$check(
    'PRODUCTION_CYCLES_OPEN_TABLE_NOT_CAPPED_AT_12',
    $recentBlock !== ''
    &&
    strpos(
        $recentBlock,
        'LIMIT 12'
    ) === false
);

$openSectionStart =
    strpos(
        $cycles,
        'id="recent-cycles"'
    );

$closedSectionStart =
    $openSectionStart !== false
        ? strpos(
            $cycles,
            'Close Cycle Details',
            $openSectionStart
        )
        : false;

$openSection =
    $openSectionStart !== false
    && $closedSectionStart !== false
        ? substr(
            $cycles,
            $openSectionStart,
            $closedSectionStart
                - $openSectionStart
        )
        : '';

$check(
    'OPEN_TABLE_HAS_NO_CLOSED_DATE_COLUMN',
    $openSection !== ''
    &&
    strpos(
        $openSection,
        '<th>Closed Date</th>'
    ) === false
);

$check(
    'CLOSED_DETAILS_REMAIN_CANONICAL_HOME',
    strpos(
        $cycles,
        "WHERE farm_id = ? AND status = 'closed'"
    ) !== false
    &&
    strpos(
        $cycles,
        'Close Cycle Details'
    ) !== false
    &&
    strpos(
        $cycles,
        '<th>Close Date</th>'
    ) !== false
    &&
    strpos(
        $cycles,
        '<th class="text-end">Closing Headcount</th>'
    ) !== false
);

foreach ($checks as $name => $ok) {
    echo
        $name
        . '='
        . ($ok ? 'PASS' : 'FAIL')
        . PHP_EOL;
}

echo
    'CHECK_COUNT='
    . count($checks)
    . PHP_EOL;

echo "DATABASE_CONNECTION=NONE\n";
echo "DATABASE_WRITE=NONE\n";

if ($failures) {
    echo
        'FAILED='
        . implode(',', $failures)
        . PHP_EOL;

    echo "RESULT=FAIL\n";
    exit(1);
}

echo "RESULT=PASS\n";
exit(0);
