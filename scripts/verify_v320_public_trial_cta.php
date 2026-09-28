<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$indexPath =
    $root . '/index.php';

$cssPath =
    $root . '/assets/css/home-page.css';

$index =
    is_file($indexPath)
        ? (string)file_get_contents($indexPath)
        : '';

$css =
    is_file($cssPath)
        ? (string)file_get_contents($cssPath)
        : '';

$failures = 0;

function cta_check(
    bool $condition,
    string $message
): void {
    global $failures;

    echo ($condition ? 'PASS: ' : 'FAIL: ')
        . $message
        . PHP_EOL;

    if (!$condition) {
        $failures++;
    }
}

cta_check(
    $index !== '',
    'homepage exists'
);

cta_check(
    $css !== '',
    'homepage stylesheet exists'
);

cta_check(
    substr_count(
        $index,
        'href="trial.php"'
    ) === 2,
    'homepage exposes exactly two public trial links'
);

cta_check(
    str_contains(
        $index,
        'Start 14-day trial'
    ),
    'top navigation exposes trial CTA'
);

cta_check(
    str_contains(
        $index,
        'Request a 14-day trial'
    ),
    'hero note exposes trial CTA'
);

cta_check(
    str_contains(
        $index,
        'href="sign.php"'
    ),
    'existing farm portal remains available'
);

cta_check(
    str_contains(
        $index,
        'Launch farm portal'
    ),
    'existing farm portal copy remains intact'
);

cta_check(
    str_contains(
        $index,
        'New to Renee AgriSuite?'
    ),
    'new-user context is explicit'
);

cta_check(
    str_contains(
        $css,
        '.nav-actions'
    ),
    'homepage owns CTA group styling'
);

cta_check(
    str_contains(
        $css,
        '.btn-secondary'
    ),
    'secondary CTA styling exists'
);

cta_check(
    !preg_match(
        '/<style\b/i',
        $index
    ),
    'homepage remains free of inline style blocks'
);

cta_check(
    !str_contains(
        $index,
        'trial_onboarding_intake_create('
    )
    && !str_contains(
        $index,
        'INSERT INTO'
    ),
    'homepage owns no intake persistence'
);

cta_check(
    !str_contains(
        $index,
        'approved_trial_days'
    )
    && !str_contains(
        $index,
        'approved_plan_code'
    ),
    'homepage owns no approval policy'
);

exit(
    $failures === 0
        ? 0
        : 1
);
