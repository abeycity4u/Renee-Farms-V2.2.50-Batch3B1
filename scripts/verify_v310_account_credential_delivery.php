<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$service =
    $root . '/includes/account_credential_delivery.php';

$failures = 0;

function check_v310_delivery(
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

check_v310_delivery(
    is_file($service),
    'shared account credential delivery service exists'
);

if (!is_file($service)) {
    echo "V310_ACCOUNT_CREDENTIAL_DELIVERY_CONTRACT=FAIL\n";
    exit(1);
}

$source =
    (string)file_get_contents($service);

foreach ([
    'account_credential_activation_ttl_seconds',
    'account_credential_password_reset_ttl_seconds',
    'account_credential_delivery_ttl_seconds',
    'account_credential_delivery_text',
    'account_credential_delivery_context',
    'account_credential_delivery_link',
    'account_credential_delivery_expiry_text',
    'account_credential_delivery_message',
    'account_credential_send',
    'account_credential_send_activation',
    'account_credential_send_password_reset',
] as $function) {
    check_v310_delivery(
        str_contains(
            $source,
            'function ' . $function . '('
        ),
        $function . ' is centrally defined'
    );
}

check_v310_delivery(
    str_contains(
        $source,
        "require_once __DIR__ . '/account_credential_lifecycle.php';"
    )
    && str_contains(
        $source,
        "require_once __DIR__ . '/platform_public_url.php';"
    )
    && str_contains(
        $source,
        "require_once __DIR__ . '/platform_mailer.php';"
    ),
    'delivery composes lifecycle, public URL and central mail services'
);

check_v310_delivery(
    preg_match(
        '/function\s+account_credential_activation_ttl_seconds\s*\(\s*\)\s*:\s*int\s*\{\s*return\s+86400\s*;/s',
        $source
    ) === 1,
    'activation TTL policy is centrally fixed at 24 hours'
);

check_v310_delivery(
    preg_match(
        '/function\s+account_credential_password_reset_ttl_seconds\s*\(\s*\)\s*:\s*int\s*\{\s*return\s+3600\s*;/s',
        $source
    ) === 1,
    'password reset TTL policy is centrally fixed at one hour'
);

check_v310_delivery(
    str_contains(
        $source,
        "account_credential_issue_token("
    ),
    'delivery delegates secure token issuance to lifecycle service'
);

check_v310_delivery(
    str_contains(
        $source,
        "platform_public_url("
    )
    && str_contains(
        $source,
        "'/account/activate.php'"
    )
    && str_contains(
        $source,
        "'/account/reset_password.php'"
    ),
    'delivery links use canonical account routes through platform URL authority'
);

check_v310_delivery(
    str_contains(
        $source,
        "platform_mail_send("
    ),
    'outbound credential mail uses central platform transport'
);

check_v310_delivery(
    str_contains(
        $source,
        "'activation'"
    )
    && str_contains(
        $source,
        "'password_reset'"
    ),
    'delivery supports only lifecycle credential purposes'
);

check_v310_delivery(
    str_contains(
        $source,
        'LEFT JOIN farms f'
    )
    && str_contains(
        $source,
        'WHERE u.id = ?'
    ),
    'credential message context is derived centrally from canonical user identity'
);

check_v310_delivery(
    str_contains(
        $source,
        "'Farm Workspace ID: '"
    )
    && str_contains(
        $source,
        "'Username: '"
    ),
    'tenant credential messages carry workspace and username context'
);

check_v310_delivery(
    str_contains(
        $source,
        "\$workspaceId !== 'owner'"
    )
    && str_contains(
        $source,
        "'platform_owner'"
    ),
    'internal platform identity does not masquerade as tenant workspace'
);

check_v310_delivery(
    str_contains(
        $source,
        "'Activate your Renee Farms account'"
    )
    && str_contains(
        $source,
        "'Reset your Renee Farms password'"
    ),
    'activation and reset message subjects are purpose specific'
);

check_v310_delivery(
    str_contains(
        $source,
        'account_credential_delivery_context('
    )
    && strpos(
        $source,
        'account_credential_delivery_context(',
        strpos($source, 'function account_credential_send(')
    )
        <
    strpos(
        $source,
        'account_credential_issue_token(',
        strpos($source, 'function account_credential_send(')
    ),
    'message context is validated before a token is issued'
);

check_v310_delivery(
    strpos(
        $source,
        'account_credential_issue_token(',
        strpos($source, 'function account_credential_send(')
    )
        <
    strpos(
        $source,
        'platform_mail_send(',
        strpos($source, 'function account_credential_send(')
    ),
    'token issuance occurs before outbound delivery'
);

check_v310_delivery(
    !preg_match(
        '/return\s*\[[\s\S]{0,1200}[\'"]token[\'"]\s*=>/i',
        substr(
            $source,
            strpos(
                $source,
                'function account_credential_send('
            )
        )
    ),
    'delivery return contract does not expose raw credential token'
);

check_v310_delivery(
    !preg_match(
        '/return\s*\[[\s\S]{0,1200}[\'"]link[\'"]\s*=>/i',
        substr(
            $source,
            strpos(
                $source,
                'function account_credential_send('
            )
        )
    ),
    'delivery return contract does not expose credential link'
);

check_v310_delivery(
    !preg_match(
        '/\berror_log\s*\([^;]*(rawToken|token|link)/is',
        $source
    ),
    'credential delivery service never logs token or link material'
);

check_v310_delivery(
    !preg_match(
        '/\bheader\s*\(|\brequire_valid_csrf_post\s*\(|\brequire_rate_limit\s*\(/i',
        $source
    ),
    'HTTP routing, CSRF and rate limiting remain outside delivery service'
);

check_v310_delivery(
    !str_contains(
        $source,
        'account_credential_invalidate_tokens('
    ),
    'mail rejection does not broadly invalidate potentially newer concurrent tokens'
);

check_v310_delivery(
    str_contains(
        $source,
        "'sent' => (\$mail['sent'] ?? false) === true"
    )
    && str_contains(
        $source,
        "'reason' =>"
    ),
    'delivery propagates structured mail acceptance state without exposing secret'
);

check_v310_delivery(
    !str_contains(
        $source,
        'Initial password:'
    )
    && !preg_match(
        '/password_security_hash\s*\(|UPDATE\s+users\s+SET\s+password/i',
        $source
    ),
    'delivery service never sends or mutates plaintext passwords'
);

if ($failures > 0) {
    echo "V310_ACCOUNT_CREDENTIAL_DELIVERY_CONTRACT=FAIL\n";
    exit(1);
}

echo "V310_ACCOUNT_CREDENTIAL_DELIVERY_CONTRACT=PASS\n";
