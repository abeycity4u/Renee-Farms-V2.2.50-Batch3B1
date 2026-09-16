<?php
/**
 * V3.0.1 — Cycle form PRG + pointer-safe arrow UX verifier.
 *
 * Static only:
 * - no database connection;
 * - no database writes.
 */

$root = dirname(__DIR__);

$cycles = (string)file_get_contents(
    $root . '/management/production_cycles.php'
);

$edit = (string)file_get_contents(
    $root . '/management/production_cycle_edit.php'
);

$behaviors = (string)file_get_contents(
    $root . '/assets/js/app-behaviors.js'
);

$compactCycles =
    (string)preg_replace(
        '/\s+/',
        ' ',
        $cycles
    );

$compactEdit =
    (string)preg_replace(
        '/\s+/',
        ' ',
        $edit
    );

$compactBehaviors =
    (string)preg_replace(
        '/\s+/',
        ' ',
        $behaviors
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
        $cycles,
        '$productionCyclesPrgRedirect = static function'
    ) !== false
    && strpos(
        $compactCycles,
        "header( 'Location: ' . BASE_URL . '/management/production_cycles.php' . \$anchor, true, 303 );"
    ) !== false,
    'Production Cycles owns one page-level HTTP 303 PRG redirect contract.'
);

foreach (
    [
        'create_cycle',
        'update_bird_cost_basis',
        'post_batch',
        'confirm_population_cutover',
    ]
    as $action
) {
    $check(
        strpos(
            $cycles,
            "'{$action}' =>"
        ) !== false,
        "Production Cycles PRG maps handled action {$action}."
    );
}

$check(
    strpos(
        $cycles,
        "'create_form' =>"
    ) !== false
    && strpos(
        $cycles,
        "'cutover_form' =>"
    ) !== false
    && strpos(
        $cycles,
        'array_keys($createCycleForm)'
    ) !== false
    && strpos(
        $cycles,
        'array_keys($cutoverForm)'
    ) !== false,
    'Production Cycles PRG preserves Create Cycle and population-cutover form state.'
);

$check(
    strpos(
        $cycles,
        '$productionCyclesPrgAction'
    ) !== false
    && strpos(
        $compactCycles,
        'in_array( $productionCyclesPrgAction,'
    ) !== false,
    'Advanced Maintenance reopens from persisted PRG action rather than POST state.'
);

$redirectCallCount =
    substr_count(
        $cycles,
        '$productionCyclesPrgRedirect('
    );

$check(
    $redirectCallCount === 2
    && strpos(
        $compactCycles,
        '$productionCyclesPrgRedirect( (string)$action,'
    ) !== false
    && strpos(
        $compactCycles,
        '$productionCyclesPrgRedirect( $failedAction,'
    ) !== false,
    'All normal handled actions share one PRG call and unexpected POST exceptions share the second.'
);

$check(
    strpos(
        $edit,
        "'production_cycle_edit_prg_' . \$cycleId"
    ) !== false
    && strpos(
        $compactEdit,
        "'form' => \$form"
    ) !== false
    && strpos(
        $compactEdit,
        "'error' => \$flashError"
    ) !== false,
    'Edit Cycle failed submissions preserve form state and error through session PRG.'
);

$edit303Needle =
    "header( 'Location: ' . BASE_URL "
    . ". '/management/production_cycle_edit.php?id=' "
    . ". \$cycleId, true, 303 );";

$check(
    substr_count(
        $compactEdit,
        $edit303Needle
    ) === 2,
    'Edit Cycle success and handled-error paths both use HTTP 303 without whitespace-sensitive matching.'
);

$check(
    strpos(
        $cycles,
        '<body data-arrow-scroll-safe-scope>'
    ) !== false
    && strpos(
        $edit,
        '<body data-arrow-scroll-safe-scope>'
    ) !== false,
    'Production Cycles and Edit Cycle opt into shared arrow-scroll safety.'
);

$check(
    strpos(
        $behaviors,
        'data-arrow-scroll-safe-scope'
    ) !== false
    && strpos(
        $behaviors,
        "document.addEventListener('pointerdown'"
    ) !== false
    && strpos(
        $behaviors,
        "control.dataset.arrowScrollPointer = '1'"
    ) !== false,
    'Shared behavior distinguishes pointer-origin interaction from keyboard-only interaction.'
);

$check(
    strpos(
        $compactBehaviors,
        "control.tagName !== 'SELECT'"
    ) !== false
    && strpos(
        $behaviors,
        'control.blur();'
    ) !== false,
    'Pointer-selected dropdown releases focus after committing its choice.'
);

$check(
    strpos(
        $behaviors,
        'input[type="number"]'
    ) !== false
    && strpos(
        $behaviors,
        "'ArrowUp', 'ArrowDown'"
    ) !== false
    && strpos(
        $behaviors,
        'event.preventDefault();'
    ) !== false
    && strpos(
        $behaviors,
        'window.scrollBy('
    ) !== false,
    'Pointer-focused number controls use Up/Down for scrolling instead of silent value mutation.'
);

$check(
    strpos(
        $behaviors,
        'beforeunload'
    ) === false,
    'Native refresh warning is solved by PRG rather than suppressing browser unload behavior.'
);

echo "RESULT={$passes}_PASS_{$failures}_FAIL\n";

exit(
    $failures > 0
        ? 1
        : 0
);
