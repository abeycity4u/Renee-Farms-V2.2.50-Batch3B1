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
    'billing actor no longer emits raw plain-text denial'
);

verify_access_ui(
    str_contains(
        $page,
        'http_response_code(403);'
    ),
    'shared no-access surface returns HTTP 403'
);

verify_access_ui(
    str_contains(
        $page,
        'access-denied-shell'
    )
    && str_contains(
        $page,
        'justify-content: center'
    ),
    'access-denied content is centered horizontally'
);

verify_access_ui(
    str_contains(
        $page,
        'padding: clamp(4.5rem, 11vh, 8rem)'
    ),
    'access-denied card is positioned toward upper-middle viewport'
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
        $page,
        'var(--bs-body-bg)'
    )
    && str_contains(
        $page,
        'var(--bs-body-color)'
    ),
    'shared denial surface follows active theme variables'
);

echo $fail === 0
    ? "V320_BILLING_ACCESS_DENIED_UI=PASS\n"
    : "V320_BILLING_ACCESS_DENIED_UI=FAIL\n";

exit($fail === 0 ? 0 : 1);
