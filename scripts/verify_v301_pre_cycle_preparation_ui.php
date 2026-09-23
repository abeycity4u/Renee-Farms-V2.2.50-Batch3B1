<?php

$root =
    dirname(__DIR__);

$viewPath =
    $root
    . '/ruminant/animal_view.php';

if (!is_file($viewPath)) {
    fwrite(
        STDERR,
        "Missing Ruminant animal view.\n"
    );
    exit(1);
}

$view =
    file_get_contents(
        $viewPath
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
        string $needle
    ): bool =>
        strpos(
            $view,
            $needle
        ) !== false;

$check(
    $has(
        'Explicit pre-cycle preparation costs'
    ),
    'economics explanation names pre-cycle preparation'
);

$check(
    $has(
        'first eligible livestock'
    ),
    'economics explanation names first real livestock cohort'
);

$check(
    $has(
        'currently have no eligible'
    ),
    'exception summary describes unresolved cohort'
);

$check(
    !$has(
        'no eligible animals on their transaction date(s).'
    ),
    'old transaction-date-only exception wording is retired'
);

$check(
    $has(
        'pre_cycle_target_has_no_eligible_animals'
    ),
    'UI distinguishes empty pre-cycle target'
);

$check(
    $has(
        'Pre-cycle preparation · the selected future cycle has no eligible livestock cohort to receive this cost.'
    ),
    'empty pre-cycle exception has actionable explanation'
);

$check(
    $has(
        'In-cycle allocation · no eligible livestock membership covered the source date.'
    ),
    'ordinary in-cycle exception remains distinct'
);

$check(
    $has(
        'cycle_start_date'
    ),
    'pre-cycle exception exposes target start date'
);

$check(
    $has(
        'Pre-cycle preparation'
    )
    &&
    $has(
        'Preparation source date'
    ),
    'shared-cost history labels preparation provenance'
);

$check(
    $has(
        'allocation_effective_date'
    )
    &&
    $has(
        'First eligible cohort'
    ),
    'shared-cost history exposes effective cohort date'
);

$check(
    $has(
        'Active headcount on source date'
    ),
    'ordinary source-date allocation remains visible'
);

$check(
    $has(
        'shared_allocation_navigation_actions'
    )
    &&
    $has(
        "\$action['url']"
    ),
    'exact allocation navigation remains intact'
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
    'PRE_CYCLE_UI='
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
