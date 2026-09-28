<?php

declare(strict_types=1);

require_once dirname(__DIR__)
    . '/includes/trial_onboarding_review.php';

$root =
    dirname(__DIR__);

$path =
    $root
    . '/includes/trial_onboarding_review.php';

$source =
    (string)file_get_contents($path);

$failures = 0;

function review_check(
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

review_check(
    function_exists(
        'trial_onboarding_review_approve'
    ),
    'shared approval entry point exists'
);

review_check(
    function_exists(
        'trial_onboarding_review_role_limits'
    ),
    'shared commercial snapshot helper exists'
);

$request = [
    'requested_modules_snapshot' =>
        json_encode([
            'ruminant',
            'poultry',
        ]),
];

$allModules =
    trial_onboarding_review_approved_modules(
        $request,
        null
    );

review_check(
    $allModules === [
        'poultry',
        'ruminant',
    ],
    'requested modules normalize canonically'
);

$poultryOnly =
    trial_onboarding_review_approved_modules(
        $request,
        [
            'poultry',
        ]
    );

review_check(
    $poultryOnly === [
        'poultry',
    ],
    'approval may narrow requested modules'
);

$unrequestedBlocked = false;

try {
    trial_onboarding_review_approved_modules(
        $request,
        [
            'sales',
        ]
    );
} catch (InvalidArgumentException $e) {
    $unrequestedBlocked = true;
}

review_check(
    $unrequestedBlocked,
    'approval cannot grant an unrequested module'
);

$limits =
    trial_onboarding_review_role_limits(
        'starter',
        [
            'poultry',
        ]
    );

review_check(
    isset(
        $limits['poultry_manager'],
        $limits['ruminant_manager'],
        $limits['sales_rep'],
        $limits['viewer']
    ),
    'approved role snapshot contains every seat role'
);

review_check(
    $limits['ruminant_manager'] === 0,
    'disabled livestock role receives zero seats'
);

review_check(
    $limits['poultry_manager'] >= 1
        && $limits['sales_rep'] >= 1
        && $limits['viewer'] >= 1,
    'enabled/shared roles derive plan allowances'
);

review_check(
    trial_onboarding_review_normalize_reason_code(
        'manual_review'
    ) === 'manual_review',
    'review reason code normalizes'
);

$badReasonBlocked = false;

try {
    trial_onboarding_review_normalize_reason_code(
        'Bad reason with spaces'
    );
} catch (InvalidArgumentException $e) {
    $badReasonBlocked = true;
}

review_check(
    $badReasonBlocked,
    'free-form reason text is not stored as reason code'
);

review_check(
    str_contains(
        $source,
        'FOR UPDATE'
    ),
    'approval locks request'
);

review_check(
    str_contains(
        $source,
        "status = 'approved'"
    ),
    'approval writes approved lifecycle state'
);

foreach ([
    'approved_plan_code',
    'approved_modules_snapshot',
    'approved_role_limits_snapshot',
    'approved_trial_days',
    'approved_by_user_id',
    'approved_at',
] as $field) {
    review_check(
        str_contains(
            $source,
            $field
        ),
        'approval freezes ' . $field
    );
}

review_check(
    str_contains(
        $source,
        'trial_onboarding_request_assert_transition'
    ),
    'approval consumes shared lifecycle authority'
);

review_check(
    str_contains(
        $source,
        'subscription_plan_effective_role_limits'
    ),
    'approval consumes central plan policy'
);

review_check(
    str_contains(
        $source,
        'subscription_seat_role_relevant'
    ),
    'approval consumes shared seat/module relevance'
);

review_check(
    !str_contains(
        $source,
        'trial_onboarding_provision_approved_request('
    )
    && !str_contains(
        $source,
        'tenant_provisioning_create('
    ),
    'approval does not provision a tenant'
);

review_check(
    !preg_match(
        '/INSERT\s+INTO\s+(farms|users)\b/i',
        $source
    ),
    'approval does not create farms or users'
);

review_check(
    !str_contains(
        $source,
        'trial_ends_at'
    )
    && !str_contains(
        $source,
        'subscription_starts_at'
    ),
    'approval does not start trial clocks'
);

review_check(
    !preg_match(
        '/password|activation_token|credential_outbox/i',
        $source
    ),
    'approval owns no credential material'
);

exit(
    $failures === 0
        ? 0
        : 1
);
