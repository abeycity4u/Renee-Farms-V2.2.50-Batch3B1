<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$helper =
    file_get_contents(
        $root
        . '/includes/platform_owner_tenant_workspace.php'
    );

$farms =
    file_get_contents(
        $root
        . '/management/farms.php'
    );

$trial =
    file_get_contents(
        $root
        . '/management/trial_onboarding_reviews.php'
    );

$fail = 0;

function check(bool $ok, string $message): void
{
    global $fail;

    echo ($ok ? 'PASS: ' : 'FAIL: ')
        . $message
        . PHP_EOL;

    if (!$ok) {
        $fail++;
    }
}

check(
    is_string($helper)
    && preg_match(
        "/WHERE\\s+status\\s+IN\\s*\\(\\s*'pending_review'\\s*,\\s*'approved'\\s*\\)/is",
        $helper
    ) === 1,
    'shared workspace centrally counts actionable trial requests'
);

check(
    is_string($helper)
    && strpos(
        $helper,
        '/management/farms.php'
    ) !== false
    && strpos(
        $helper,
        '/management/trial_onboarding_reviews.php'
    ) !== false,
    'shared workspace links Tenants and Trial Requests'
);

check(
    is_string($helper)
    && strpos(
        $helper,
        "['tenants', 'trial_requests']"
    ) !== false,
    'shared workspace has explicit active-tab contract'
);

check(
    is_string($farms)
    && strpos(
        $farms,
        "platform_owner_tenant_workspace_render(\$pdo, 'tenants')"
    ) !== false,
    'Farms page renders Tenants as active workspace tab'
);

check(
    is_string($trial)
    && strpos(
        $trial,
        "'trial_requests'"
    ) !== false
    && strpos(
        $trial,
        'platform_owner_tenant_workspace_render'
    ) !== false,
    'Trial Requests page renders Trial Requests as active workspace tab'
);

check(
    is_string($trial)
    && strpos(
        $trial,
        'Trial Requests'
    ) !== false
    && strpos(
        $trial,
        'Trial Onboarding Reviews'
    ) === false,
    'trial review surface uses Trial Requests visible naming'
);

check(
    is_string($trial)
    && strpos(
        $trial,
        'Farm Accounts'
    ) === false,
    'redundant Farm Accounts back link is retired'
);

check(
    is_string($farms)
    && strpos(
        $farms,
        '/includes/platform_owner_tenant_workspace.php'
    ) !== false
    && is_string($trial)
    && strpos(
        $trial,
        '/includes/platform_owner_tenant_workspace.php'
    ) !== false,
    'both pages consume the same shared workspace helper'
);

check(
    is_string($trial)
    && strpos(
        $trial,
        'trial_onboarding_review_approve('
    ) !== false
    && strpos(
        $trial,
        'trial_onboarding_review_reject('
    ) !== false
    && strpos(
        $trial,
        'trial_onboarding_provision_approved_request('
    ) !== false,
    'trial approve reject and provision authorities remain unchanged'
);

check(
    is_string($trial)
    && strpos(
        $trial,
        'requirePlatformOwner();'
    ) !== false
    && is_string($farms)
    && strpos(
        $farms,
        'requirePlatformOwner();'
    ) !== false,
    'both workspace routes remain Platform Owner protected'
);

if ($fail === 0) {
    echo "PLATFORM_OWNER_TENANT_WORKSPACE_VERIFIER=PASS\n";
    exit(0);
}

echo "PLATFORM_OWNER_TENANT_WORKSPACE_VERIFIER=FAIL\n";
exit(1);
