<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$actor =
    (string)file_get_contents(
        $root . '/includes/billing_tenant_actor.php'
    );

$page =
    (string)file_get_contents(
        $root . '/no_access.php'
    );

$cssPath =
    $root . '/assets/css/access-denied.css';

$css =
    is_file($cssPath)
        ? (string)file_get_contents($cssPath)
        : '';

$fail = 0;

function verify_access_ui(
    bool $condition,
    string $message
): void {
    global $fail;

    echo ($condition ? 'PASS: ' : 'FAIL: ')
        . $message
        . PHP_EOL;

    if (!$condition) {
        $fail++;
    }
}

verify_access_ui(
    str_contains(
        $actor,
        "'Oops! Access restricted'"
    ),
    'billing actor supplies friendly access-restricted title'
);

verify_access_ui(
    str_contains(
        $actor,
        "'Farm Admin access is required for subscription billing.'"
    ),
    'billing actor preserves canonical billing restriction message'
);

verify_access_ui(
    str_contains(
        $actor,
        "require dirname(__DIR__)"
    )
    && str_contains(
        $actor,
        ". '/no_access.php';"
    ),
    'billing denial delegates to shared no-access surface'
);

verify_access_ui(
    !str_contains(
        $actor,
        "exit('Farm Admin access is required for subscription billing.')"
    ),
    'billing actor does not emit raw plain-text denial'
);

verify_access_ui(
    str_contains(
        $page,
        'http_response_code(403);'
    ),
    'shared no-access surface returns HTTP 403'
);

verify_access_ui(
    !str_contains(
        $page,
        '<style>'
    ),
    'shared no-access surface contains no inline style block'
);

verify_access_ui(
    str_contains(
        $page,
        "versioned_asset('/assets/css/access-denied.css')"
    ),
    'shared no-access surface loads versioned external stylesheet'
);

verify_access_ui(
    is_file($cssPath),
    'shared access-denied stylesheet exists'
);

verify_access_ui(
    str_contains(
        $css,
        '.access-denied-shell'
    )
    && str_contains(
        $css,
        'justify-content: center'
    )
    && str_contains(
        $css,
        'width: 100%'
    ),
    'external stylesheet centers access-denied surface'
);

verify_access_ui(
    str_contains(
        $css,
        'padding: clamp(4.5rem, 11vh, 8rem)'
    ),
    'external stylesheet positions card toward upper-middle viewport'
);

verify_access_ui(
    str_contains(
        $css,
        '.access-denied-card'
    )
    && str_contains(
        $css,
        'width: min(100%, 720px)'
    )
    && str_contains(
        $css,
        'border-radius: 1.25rem'
    ),
    'external stylesheet defines centered access card'
);

verify_access_ui(
    str_contains(
        $page,
        'Back to Dashboard'
    ),
    'shared denial surface provides dashboard recovery action'
);

verify_access_ui(
    str_contains(
        $css,
        'var(--bs-body-bg)'
    )
    && str_contains(
        $css,
        'var(--bs-body-color)'
    ),
    'external stylesheet follows active theme variables'
);

echo $fail === 0
    ? "V320_BILLING_ACCESS_DENIED_UI=PASS\n"
    : "V320_BILLING_ACCESS_DENIED_UI=FAIL\n";

exit($fail === 0 ? 0 : 1);
