<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$lifecyclePath =
    $root . '/includes/account_credential_lifecycle.php';

$activatePath =
    $root . '/account/activate.php';

$fail = false;

function check_contract(
    bool $condition,
    string $label
): void {
    global $fail;

    echo ($condition ? 'PASS: ' : 'FAIL: ')
        . $label
        . PHP_EOL;

    if (!$condition) {
        $fail = true;
    }
}

$lifecycle =
    file_get_contents($lifecyclePath);

$activate =
    file_get_contents($activatePath);

check_contract(
    is_string($lifecycle),
    'credential lifecycle is readable'
);

check_contract(
    is_string($activate),
    'activation route is readable'
);

if (!is_string($lifecycle) || !is_string($activate)) {
    exit(1);
}

check_contract(
    str_contains(
        $lifecycle,
        "function account_credential_lookup_token("
    ),
    'shared read-only token lookup exists'
);

check_contract(
    str_contains(
        $lifecycle,
        "bool \$forUpdate = false"
    ),
    'shared lookup defaults to read-only mode'
);

check_contract(
    str_contains(
        $lifecycle,
        "\$sql .= ' FOR UPDATE';"
    ),
    'shared lookup supports transaction locking for mutation paths'
);

check_contract(
    str_contains(
        $lifecycle,
        "!empty(\$token['consumed_at'])"
    ),
    'shared lookup rejects consumed tokens'
);

check_contract(
    str_contains(
        $lifecycle,
        "t.expires_at > NOW()"
    ),
    'shared lookup rejects expired tokens'
);

check_contract(
    str_contains(
        $lifecycle,
        "\$purpose === 'activation'"
    )
    && str_contains(
        $lifecycle,
        "\$credentialState !== 'pending_activation'"
    ),
    'shared lookup enforces activation credential state'
);

check_contract(
    str_contains(
        $lifecycle,
        "return account_credential_lookup_token("
    )
    && str_contains(
        $lifecycle,
        "true\n        );"
    ),
    'locking token resolver delegates to shared lookup'
);

$getGuardNeedle =
    "account_credential_lookup_token(\n"
    . "            \$pdo,\n"
    . "            \$rawActivationToken,\n"
    . "            'activation'\n"
    . "        );";

check_contract(
    str_contains(
        $activate,
        $getGuardNeedle
    ),
    'activation GET delegates token validity to shared lifecycle'
);

check_contract(
    str_contains(
        $activate,
        "\$usableActivationToken === null"
    ),
    'activation GET detects stale token'
);

check_contract(
    str_contains(
        $activate,
        "unset(\n"
        . "            \$_SESSION[\n"
        . "                ACCOUNT_CREDENTIAL_ACTIVATION_TOKEN_SESSION_KEY"
    ),
    'activation GET clears stale session capability'
);

check_contract(
    str_contains(
        $activate,
        "\$hasActivationToken = false;"
    ),
    'stale token disables password-form render branch'
);

check_contract(
    str_contains(
        $activate,
        "This activation link is invalid or has expired."
    ),
    'stale token gets explicit invalid-link message'
);

$lookupPos =
    strpos(
        $activate,
        'account_credential_lookup_token('
    );

$postPos =
    strpos(
        $activate,
        ") === 'POST'\n) {"
    );

check_contract(
    $lookupPos !== false
    && $postPos !== false
    && $lookupPos < $postPos,
    'stale-token validation happens before POST branch/render'
);

check_contract(
    str_contains(
        $activate,
        'account_credential_consume_activation('
    ),
    'POST activation still uses central consume lifecycle'
);

if ($fail) {
    echo "A6I_STALE_LINK_GET_GUARD=FAIL\n";
    exit(1);
}

echo "A6I_STALE_LINK_GET_GUARD=PASS\n";
exit(0);
