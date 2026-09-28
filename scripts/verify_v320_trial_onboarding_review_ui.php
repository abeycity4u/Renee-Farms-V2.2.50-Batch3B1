<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$pagePath =
    $root
    . '/management/trial_onboarding_reviews.php';

$source =
    is_file($pagePath)
        ? (string)file_get_contents(
            $pagePath
        )
        : '';

$failures = 0;

function ui_check(
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

ui_check(
    $source !== '',
    'Platform Owner trial-review page exists'
);

ui_check(
    str_contains(
        $source,
        'requireLogin();'
    ),
    'review page requires authentication'
);

ui_check(
    str_contains(
        $source,
        'requirePlatformOwner();'
    ),
    'review page requires Platform Owner'
);

ui_check(
    str_contains(
        $source,
        'require_valid_csrf_post();'
    ),
    'POST uses shared CSRF enforcement'
);

ui_check(
    str_contains(
        $source,
        'isset($_POST[\'approve_request\'])'
    )
    && str_contains(
        $source,
        '(string)$_POST[\'approve_request\'] === \'1\''
    ),
    'POST requires explicit approve_request action'
);

ui_check(
    str_contains(
        $source,
        'csrf_field()'
    ),
    'approval form uses shared CSRF field'
);

ui_check(
    str_contains(
        $source,
        "header("
    )
    && str_contains(
        $source,
        '303'
    ),
    'POST uses PRG redirect'
);

ui_check(
    str_contains(
        $source,
        'trial_onboarding_review_approve('
    ),
    'page delegates approval to shared authority'
);

ui_check(
    str_contains(
        $source,
        "'manual'"
    ),
    'browser approval is explicitly manual'
);

ui_check(
    str_contains(
        $source,
        'subscription_plan_catalog()'
    ),
    'plan choices come from central plan catalog'
);

ui_check(
    str_contains(
        $source,
        'farm_entitlement_module_labels()'
    ),
    'module labels come from central entitlement authority'
);

ui_check(
    str_contains(
        $source,
        'requested_modules_snapshot'
    ),
    'page renders requested module scope'
);

ui_check(
    str_contains(
        $source,
        'approved_modules[]'
    ),
    'browser may narrow requested modules'
);

ui_check(
    str_contains(
        $source,
        'manual_review'
    ),
    'page supplies controlled review reason code'
);

ui_check(
    !preg_match(
        '/\bUPDATE\s+trial_onboarding_requests\b/i',
        $source
    ),
    'page does not duplicate approval UPDATE SQL'
);

ui_check(
    !preg_match(
        '/\bINSERT\s+INTO\s+trial_onboarding_requests\b/i',
        $source
    ),
    'page does not create onboarding requests'
);

ui_check(
    !preg_match(
        '/\bDELETE\s+FROM\s+trial_onboarding_requests\b/i',
        $source
    ),
    'page does not delete onboarding requests'
);

ui_check(
    !str_contains(
        $source,
        'trial_onboarding_provision_approved_request('
    )
    && !str_contains(
        $source,
        'tenant_provisioning_create('
    ),
    'page cannot provision tenants'
);

ui_check(
    !preg_match(
        '/INSERT\s+INTO\s+(farms|users)\b/i',
        $source
    ),
    'page cannot create farms or users'
);

ui_check(
    !str_contains(
        $source,
        'subscription_starts_at'
    )
    && !str_contains(
        $source,
        'trial_ends_at'
    ),
    'page does not start trial clocks'
);

ui_check(
    !preg_match(
        '/password|activation_token|credential_outbox/i',
        $source
    ),
    'page owns no credential material'
);

ui_check(
    !preg_match(
        '/<style\b/i',
        $source
    ),
    'page contains no inline style block'
);

ui_check(
    str_contains(
        $source,
        '/assets/css/management-workspaces.css'
    ),
    'page reuses existing management workspace stylesheet'
);

ui_check(
    !str_contains(
        $source,
        'reject_request'
    )
    && !preg_match(
        '/status\s*=\s*[\'"]rejected[\'"]/i',
        $source
    ),
    'page does not invent rejection mutation'
);

exit(
    $failures === 0
        ? 0
        : 1
);
