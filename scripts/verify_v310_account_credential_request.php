<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$service =
    $root
    . '/includes/account_credential_request.php';

$failures = 0;

function check_v310_request(
    bool $condition,
    string $message
): void {
    global $failures;

    if ($condition) {
        echo 'PASS: ' . $message . PHP_EOL;
        return;
    }

    echo 'FAIL: ' . $message . PHP_EOL;
    $failures++;
}

check_v310_request(
    is_file($service),
    'shared credential request service exists'
);

if (!is_file($service)) {
    echo "V310_ACCOUNT_CREDENTIAL_REQUEST_CONTRACT=FAIL\n";
    exit(1);
}

$source =
    (string)file_get_contents(
        $service
    );

foreach ([
    'account_credential_request_text',
    'account_credential_request_public_result',
    'account_credential_request_log_failure',
    'account_credential_request_farm_candidate',
    'account_credential_request_platform_candidate',
    'account_credential_request_candidate_eligible',
    'account_credential_request_candidate_user_id',
    'account_credential_request_dispatch_candidate',
    'account_credential_request_farm_password_reset',
    'account_credential_request_platform_password_reset',
] as $function) {
    check_v310_request(
        str_contains(
            $source,
            'function ' . $function . '('
        ),
        $function . ' is centrally defined'
    );
}

check_v310_request(
    str_contains(
        $source,
        "require_once __DIR__\n"
        . "    . '/account_credential_outbox.php';"
    )
    || str_contains(
        $source,
        "account_credential_outbox.php"
    ),
    'request layer depends on shared credential outbox'
);

check_v310_request(
    str_contains(
        $source,
        'WHERE f.slug = ?'
    )
    && str_contains(
        $source,
        "AND f.slug <> 'owner'"
    )
    && str_contains(
        $source,
        'AND u.username = ?'
    ),
    'farm reset lookup is pinned to workspace plus username and excludes owner workspace'
);

check_v310_request(
    str_contains(
        $source,
        "WHERE f.slug = 'owner'"
    )
    && str_contains(
        $source,
        "'platform_owner'"
    )
    && str_contains(
        $source,
        "'platform_admin'"
    ),
    'platform reset lookup is pinned to owner workspace and platform identity'
);

check_v310_request(
    !preg_match(
        '/WHERE[\s\S]{0,400}\bu\.email\s*=/i',
        $source
    ),
    'email is not used as global account identity'
);

check_v310_request(
    str_contains(
        $source,
        "!== 'active'"
    ),
    'password reset eligibility requires active credential state'
);

check_v310_request(
    str_contains(
        $source,
        'account_credential_normalize_email('
    ),
    'reset eligibility validates canonical users.email'
);

check_v310_request(
    substr_count(
        $source,
        'account_credential_outbox_enqueue('
    ) === 1,
    'request policy has exactly one central durable enqueue call site'
);

check_v310_request(
    str_contains(
        $source,
        "'password_reset'"
    ),
    'request enqueue is restricted to password reset purpose'
);

$dispatchStart =
    strpos(
        $source,
        'function account_credential_request_dispatch_candidate('
    );

$dispatchEnd =
    $dispatchStart === false
        ? false
        : strpos(
            $source,
            "if (!function_exists('account_credential_request_farm_password_reset'))",
            $dispatchStart
        );

$dispatchSource =
    $dispatchStart === false
        ? ''
        : (
            $dispatchEnd === false
                ? substr($source, $dispatchStart)
                : substr(
                    $source,
                    $dispatchStart,
                    $dispatchEnd - $dispatchStart
                )
        );

$candidateIdPos =
    strpos(
        $dispatchSource,
        'account_credential_request_candidate_user_id('
    );

$enqueuePos =
    strpos(
        $dispatchSource,
        'account_credential_outbox_enqueue('
    );

check_v310_request(
    $candidateIdPos !== false
    && $enqueuePos !== false
    && $candidateIdPos < $enqueuePos,
    'eligible/ineligible identity decision remains centralized before enqueue'
);

check_v310_request(
    !str_contains(
        $source,
        'account_credential_send_password_reset('
    )
    && !str_contains(
        $source,
        'account_credential_send('
    )
    && !str_contains(
        $source,
        'platform_mail_send('
    ),
    'browser request policy contains no synchronous credential mail delivery'
);

check_v310_request(
    !str_contains(
        $source,
        'account_credential_issue_token('
    ),
    'browser request policy does not issue credential tokens'
);

check_v310_request(
    str_contains(
        $source,
        "'accepted' => true"
    )
    && str_contains(
        $source,
        'If the account details are valid'
    ),
    'public request result remains centralized and neutral'
);

check_v310_request(
    substr_count(
        $source,
        'return account_credential_request_public_result();'
    ) === 2,
    'farm and platform reset requests share the same public result'
);

$publicResultStart =
    strpos(
        $source,
        'function account_credential_request_public_result()'
    );

$publicResultEnd =
    $publicResultStart === false
        ? false
        : strpos(
            $source,
            "if (!function_exists('account_credential_request_log_failure'))",
            $publicResultStart
        );

$publicResultSource =
    $publicResultStart === false
        ? ''
        : (
            $publicResultEnd === false
                ? substr($source, $publicResultStart)
                : substr(
                    $source,
                    $publicResultStart,
                    $publicResultEnd - $publicResultStart
                )
        );

check_v310_request(
    str_contains(
        $publicResultSource,
        "'accepted' => true"
    )
    && str_contains(
        $publicResultSource,
        "'message' =>"
    )
    && !preg_match(
        '/[\'"](?:user_id|exists|matched|sent|transport|reason)[\'"]\s*=>/i',
        $publicResultSource
    ),
    'public request result exposes no account or delivery existence fields'
);

check_v310_request(
    str_contains(
        $source,
        'account_credential_request_log_failure('
    )
    && str_contains(
        $source,
        'get_class($error)'
    )
    && !str_contains(
        $source,
        '$error->getMessage()'
    ),
    'unexpected request failures provide non-secret operator observability'
);

check_v310_request(
    !preg_match(
        '/error_log\s*\([^;]*(workspaceId|username|email|token)/is',
        $source
    ),
    'operator diagnostics do not log supplied account identifiers'
);

check_v310_request(
    !preg_match(
        '/\$_(GET|POST|REQUEST|SERVER|SESSION|COOKIE)/',
        $source
    ),
    'HTTP state remains outside request service'
);

$executableSource =
    preg_replace(
        '#/\*.*?\*/#s',
        ' ',
        $source
    );

$executableSource =
    preg_replace(
        '/^[ \t]*\/\/.*$/m',
        ' ',
        (string)$executableSource
    );

check_v310_request(
    !preg_match(
        '/\b(?:csrf|guest_rate|rate_limit)\b/i',
        (string)$executableSource
    ),
    'CSRF and guest rate limiting remain outside executable request policy'
);

check_v310_request(
    !preg_match(
        '/UPDATE\s+users[\s\S]{0,300}\bpassword\b/i',
        $source
    ),
    'request service never mutates passwords'
);

check_v310_request(
    !str_contains(
        $source,
        'contact_email'
    )
    && !str_contains(
        $source,
        'farm_contact_email'
    ),
    'credential recovery uses users.email rather than commercial contact email'
);

check_v310_request(
    !str_contains(
        $source,
        'subscription_recovery'
    ),
    'credential recovery remains separate from subscription recovery'
);

if ($failures > 0) {
    echo "V310_ACCOUNT_CREDENTIAL_REQUEST_CONTRACT=FAIL\n";
    exit(1);
}

echo "V310_ACCOUNT_CREDENTIAL_REQUEST_CONTRACT=PASS\n";
