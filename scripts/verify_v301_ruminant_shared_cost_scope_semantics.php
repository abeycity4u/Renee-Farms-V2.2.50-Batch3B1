<?php

$root = dirname(__DIR__);

$shared =
    file_get_contents(
        $root
        . '/lib/ruminant_shared_cost_economics.php'
    );

$animal =
    file_get_contents(
        $root
        . '/lib/ruminant_animal_economics.php'
    );

$view =
    file_get_contents(
        $root
        . '/ruminant/animal_view.php'
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
    $has(
        $shared,
        'species_allocation_exception_cost'
    ),
    'shared economics exposes species exception total'
);

$check(
    $has(
        $shared,
        'species_allocation_exception_rows'
    ),
    'shared economics exposes exact exception rows'
);

$check(
    $has(
        $shared,
        'species_allocation_exception_amount'
    )
    &&
    $has(
        $shared,
        "'uncovered_amount'"
    ),
    'exception rows retain new semantics and legacy amount alias'
);

$check(
    $has(
        $shared,
        'species_allocation_exception_reason'
    )
    &&
    $has(
        $shared,
        'cycle_has_no_eligible_animals'
    )
    &&
    $has(
        $shared,
        'species_scope_has_no_eligible_animals'
    ),
    'shared economics classifies exception scope'
);

$check(
    $has(
        $shared,
        'uncovered_species_shared_cost'
    )
    &&
    $has(
        $shared,
        'uncovered_shared_cost_rows'
    ),
    'top-level compatibility aliases remain available'
);

$check(
    $has(
        $shared,
        "'allocated_shared_cost'=>round(\$total,2)"
    ),
    'animal shared-cost allocation formula remains unchanged'
);

$check(
    $has(
        $animal,
        'species_allocation_exception_cost'
    )
    &&
    $has(
        $animal,
        'species_allocation_exception_rows'
    ),
    'animal economics passes exception contract through'
);

$check(
    $has(
        $view,
        'species_allocation_exception_cost'
    )
    &&
    $has(
        $view,
        'species_allocation_exception_rows'
    ),
    'animal profile consumes exception contract'
);

$check(
    $has(
        $view,
        'shared-cost allocation issue:'
    ),
    'animal profile labels issue correctly'
);

$check(
    $has(
        $view,
        'individual economics and is not assigned to this animal'
    ),
    'animal profile states exception is outside animal economics'
);

$check(
    $has(
        $view,
        'Affected source/cycle scope(s):'
    )
    &&
    $has(
        $view,
        'species_allocation_exception_amount'
    )
    &&
    $has(
        $view,
        'cycle_code'
    ),
    'animal profile itemizes exception provenance'
);

$blockStart =
    strpos(
        $view,
        "<?php if ((\$economics['species_allocation_exception_cost'] ?? 0) > 0): ?>"
    );

$blockEnd =
    $blockStart === false
        ? false
        : strpos(
            $view,
            '<div class="row g-3">',
            $blockStart
        );

$exceptionBlock =
    (
        $blockStart !== false
        &&
        $blockEnd !== false
    )
        ? substr(
            $view,
            $blockStart,
            $blockEnd - $blockStart
        )
        : '';

$check(
    $exceptionBlock !== ''
    &&
    strpos(
        $exceptionBlock,
        'Review cycle membership'
    ) === false
    &&
    strpos(
        $exceptionBlock,
        'href="#cycle-membership"'
    ) === false,
    'species exception does not blame target animal membership'
);

$check(
    $has(
        $view,
        '<strong>Cycle membership required.</strong>'
    )
    &&
    $has(
        $view,
        'Review cycle membership'
    ),
    'real no-membership warning remains separately available'
);

$check(
    $has(
        $view,
        'shared_allocation_navigation_actions'
    )
    &&
    $has(
        $view,
        "\$action['url']"
    ),
    'exact allocation workspace remains actionable'
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
    'RUMINANT_SHARED_SCOPE='
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
