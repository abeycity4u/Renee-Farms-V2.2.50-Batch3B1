<?php

$root = dirname(__DIR__);

require_once
    $root
    . '/lib/shared_cost_contract.php';

$contract =
    file_get_contents(
        $root
        . '/lib/shared_cost_contract.php'
    );

$stock =
    file_get_contents(
        $root
        . '/lib/stock_consumption_economics.php'
    );

$membership =
    file_get_contents(
        $root
        . '/lib/ruminant_cycle_membership.php'
    );

$shared =
    file_get_contents(
        $root
        . '/lib/ruminant_shared_cost_economics.php'
    );

$checks = [];

$check =
    static function (
        bool $ok,
        string $label
    ) use (&$checks): void {
        $checks[] = [
            'ok' => $ok,
            'label' => $label,
        ];
    };

$has =
    static fn(
        string $text,
        string $needle
    ): bool =>
        strpos(
            $text,
            $needle
        ) !== false;

$check(
    shared_cost_contract_is_pre_cycle(
        '2026-09-01',
        [
            'start_date' =>
                '2026-09-18',
        ]
    ),
    'future-start target is pre-cycle'
);

$check(
    !shared_cost_contract_is_pre_cycle(
        '2026-09-01',
        [
            'start_date' =>
                '2026-09-01',
        ]
    ),
    'same-day target is not pre-cycle'
);

foreach (
    [
        '',
        'NA',
        'N/A',
        'none',
        'not applicable',
        '-',
    ]
    as $placeholder
) {
    $check(
        !shared_cost_contract_reason_is_meaningful(
            $placeholder
        ),
        'placeholder reason rejected: '
        . (
            $placeholder !== ''
                ? $placeholder
                : 'blank'
        )
    );
}

$check(
    shared_cost_contract_reason_is_meaningful(
        'Pen disinfected before livestock arrival'
    ),
    'meaningful preparation reason accepted'
);

$check(
    $has(
        $contract,
        'shared_cost_contract_reason_is_meaningful'
    )
    &&
    $has(
        $contract,
        'Placeholder reasons such as NA are not accepted.'
    ),
    'central contract owns meaningful pre-cycle reason'
);

$check(
    substr_count(
        $stock,
        'target_cycle_start_date'
    ) >= 2,
    'canonical consumed-stock economics preserves target start date'
);

$check(
    $has(
        $membership,
        'ruminant_cycle_first_eligible_cohort'
    )
    &&
    $has(
        $membership,
        'ruminant_cycle_eligible_animal_ids'
    ),
    'ruminant membership authority owns first real cohort'
);

$check(
    $has(
        $shared,
        'ruminant_shared_cost_eligibility_context'
    ),
    'shared economics centralizes row eligibility context'
);

$check(
    $has(
        $shared,
        "\$sourceType === 'allocated_expense'"
    )
    &&
    $has(
        $shared,
        "\$stockMode === 'explicit_allocation'"
    ),
    'pre-cycle economics requires explicit allocation provenance'
);

$check(
    $has(
        $shared,
        'Pre-cycle preparation · first eligible cycle cohort'
    ),
    'pre-cycle preparation uses first real cycle cohort'
);

$check(
    $has(
        $shared,
        'pre_cycle_target_has_no_eligible_animals'
    ),
    'empty pre-cycle target remains visible exception'
);

$check(
    $has(
        $shared,
        "'Active headcount on transaction date'"
    )
    &&
    $has(
        $shared,
        "'method'=>'Active headcount on each transaction date'"
    ),
    'ordinary shared-cost method remains compatible'
);

$check(
    $has(
        $shared,
        "'allocated_shared_cost'=>round(\$total,2)"
    )
    &&
    $has(
        $shared,
        'species_allocation_exception_cost'
    ),
    'allocation and exception totals remain canonical'
);

$failed = 0;

foreach ($checks as $result) {
    if ($result['ok']) {
        continue;
    }

    $failed++;

    fwrite(
        STDERR,
        'FAIL: '
        . $result['label']
        . PHP_EOL
    );
}

echo
    'PRE_CYCLE_PREPARATION='
    . (
        $failed === 0
            ? 'PASS'
            : 'FAIL'
    )
    . ' CHECKS='
    . count($checks)
    . ' FAILED='
    . $failed
    . PHP_EOL;

exit(
    $failed === 0
        ? 0
        : 1
);
