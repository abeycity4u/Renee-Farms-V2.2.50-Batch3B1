<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$route =
    $root . '/trial.php';

$failures = 0;

function route_check(
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

$source =
    is_file($route)
        ? (string)file_get_contents($route)
        : '';

route_check(
    $source !== '',
    'public trial route exists'
);

foreach ([
    '/init.php',
    '/api/api_helpers.php',
    '/includes/trial_onboarding_intake.php',
    '/includes/platform_brand.php',
] as $dependency) {
    route_check(
        str_contains(
            $source,
            $dependency
        ),
        'route loads '
        . $dependency
    );
}

route_check(
    str_contains(
        $source,
        "header('Cache-Control: no-store, max-age=0');"
    ),
    'route prevents browser caching'
);

route_check(
    str_contains(
        $source,
        'csrf_request_is_valid()'
    ),
    'route validates CSRF'
);

route_check(
    str_contains(
        $source,
        'rate_limit_attempt('
    ),
    'route delegates throttling to shared limiter'
);

route_check(
    str_contains(
        $source,
        "'public_trial_request'"
    ),
    'route uses a dedicated public trial rate-limit key'
);

route_check(
    str_contains(
        $source,
        'trial_onboarding_intake_create('
    ),
    'route delegates persistence to shared intake authority'
);

$csrfPos =
    strpos(
        $source,
        'csrf_request_is_valid()'
    );

$ratePos =
    strpos(
        $source,
        'rate_limit_attempt('
    );

$createPos =
    strpos(
        $source,
        'trial_onboarding_intake_create('
    );

route_check(
    $csrfPos !== false
    && $ratePos !== false
    && $createPos !== false
    && $csrfPos < $ratePos
    && $ratePos < $createPos,
    'POST order is CSRF then rate limit then intake'
);

route_check(
    str_contains(
        $source,
        '303'
    )
    && str_contains(
        $source,
        "'Location: '"
    ),
    'route uses POST/Redirect/GET'
);

route_check(
    str_contains(
        $source,
        'csrf_token()'
    )
    && str_contains(
        $source,
        'name="csrf_token"'
    ),
    'form renders CSRF token'
);

foreach ([
    'farm_name',
    'requested_workspace_id',
    'admin_full_name',
    'admin_username',
    'admin_email',
    'contact_name',
    'contact_email',
    'modules[]',
] as $field) {
    route_check(
        str_contains(
            $source,
            'name="' . $field . '"'
        ),
        'form contains '
        . $field
    );
}

route_check(
    str_contains(
        $source,
        'farm_entitlement_module_labels()'
    ),
    'module labels come from entitlement authority'
);

route_check(
    str_contains(
        $source,
        'Your 14-day trial begins only after successful account activation.'
    ),
    'success copy preserves activation-triggered trial semantics'
);

route_check(
    !preg_match(
        '/\\bINSERT\\s+INTO\\b/i',
        $source
    )
    && !preg_match(
        '/\\bUPDATE\\s+(?:farms|users|subscriptions)\\b/i',
        $source
    )
    && !preg_match(
        '/\\bDELETE\\s+FROM\\b/i',
        $source
    ),
    'route owns no SQL persistence'
);

route_check(
    !str_contains(
        $source,
        'tenant_provisioning_create('
    )
    && !str_contains(
        $source,
        'account_credential_issue_token('
    )
    && !str_contains(
        $source,
        'account_activation_complete('
    ),
    'route owns no provisioning credential or activation mutation'
);

route_check(
    !str_contains(
        $source,
        'approved_plan_code'
    )
    && !str_contains(
        $source,
        'approved_modules_snapshot'
    )
    && !str_contains(
        $source,
        'approved_role_limits_snapshot'
    )
    && !str_contains(
        $source,
        'approved_trial_days'
    ),
    'browser route cannot author approval policy'
);

route_check(
    !str_contains(
        $source,
        'subscription_starts_at'
    )
    && !str_contains(
        $source,
        'trial_ends_at'
    )
    && !str_contains(
        $source,
        'subscription_ends_at'
    ),
    'browser route cannot start trial clocks'
);

route_check(
    str_contains(
        $source,
        'No payment is taken on this form.'
    ),
    'route clearly separates public intake from billing'
);

route_check(
    str_contains(
        $source,
        "versioned_asset(\n                    '/assets/css/trial-page.css'"
    ),
    'trial page loads dedicated external stylesheet'
);

route_check(
    !str_contains(
        $source,
        'app_csp_nonce'
    ),
    'trial route does not depend on nonexistent CSP nonce helper'
);

route_check(
    !preg_match(
        '/<style\\b/i',
        $source
    ),
    'trial route contains no inline style block'
);

route_check(
    str_contains(
        $source,
        'log_app_error('
    )
    && !str_contains(
        $source,
        "'message' => \$exception->getMessage()"
    ),
    'unexpected exception logging does not copy raw exception message'
);


route_check(
    preg_match(
        '/catch\s*\(\s*InvalidArgumentException\s+\$exception\s*\)/s',
        $source
    ) === 1,
    'validation exceptions have a dedicated public catch'
);

route_check(
    preg_match(
        '/catch\s*\(\s*DomainException\s+\$exception\s*\)/s',
        $source
    ) === 1,
    'domain conflicts have a dedicated public catch'
);

route_check(
    preg_match(
        '/InvalidArgumentException\s*\|\s*DomainException|DomainException\s*\|\s*InvalidArgumentException/s',
        $source
    ) !== 1,
    'validation and domain conflicts are not combined'
);

$domainCatchStart =
    strpos(
        $source,
        'catch (DomainException $exception)'
    );

$throwableCatchStart =
    strpos(
        $source,
        'catch (Throwable $exception)',
        $domainCatchStart === false
            ? 0
            : $domainCatchStart
    );

$domainCatch =
    $domainCatchStart !== false
    && $throwableCatchStart !== false
    && $throwableCatchStart > $domainCatchStart
        ? substr(
            $source,
            $domainCatchStart,
            $throwableCatchStart - $domainCatchStart
        )
        : '';

route_check(
    $domainCatch !== ''
    && !str_contains(
        $domainCatch,
        '$exception->getMessage()'
    ),
    'domain conflict does not expose raw exception message'
);

route_check(
    str_contains(
        $domainCatch,
        'Your trial request could not be submitted with those details.'
    ),
    'domain conflict renders neutral public feedback'
);

route_check(
    str_contains(
        $domainCatch,
        "'public_trial_request_conflict'"
    )
    && str_contains(
        $domainCatch,
        'get_class($exception)'
    ),
    'domain conflict logs only non-sensitive exception classification'
);

route_check(
    !str_contains(
        $domainCatch,
        'admin_email'
    )
    && !str_contains(
        $domainCatch,
        'requested_workspace_id'
    )
    && !str_contains(
        $domainCatch,
        'source_fingerprint'
    ),
    'domain conflict logging contains no request identity or fingerprint'
);


exit(
    $failures === 0
        ? 0
        : 1
);
