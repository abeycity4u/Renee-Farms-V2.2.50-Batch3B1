<?php
/**
 * GA security contract: authenticated sessions must be invalidated after any
 * password hash change, regardless of which password-writing flow performed it.
 *
 * Non-destructive source verifier. No database connection is required.
 */

$root = dirname(__DIR__);
$failures = 0;

function ga_session_contract_check(bool $condition, string $label): void
{
    global $failures;
    if ($condition) {
        echo "PASS: {$label}\n";
        return;
    }

    $failures++;
    echo "FAIL: {$label}\n";
}

$passwordSource = file_get_contents($root . '/includes/password_security.php');
$signSource = file_get_contents($root . '/sign.php');
$initSource = file_get_contents($root . '/init.php');
$lifecycleSource = file_get_contents($root . '/includes/account_credential_lifecycle.php');
$usersSource = file_get_contents($root . '/management/users.php');

foreach ([
    'password security' => $passwordSource,
    'sign in' => $signSource,
    'bootstrap' => $initSource,
    'credential lifecycle' => $lifecycleSource,
    'team users' => $usersSource,
] as $label => $source) {
    ga_session_contract_check($source !== false, $label . ' source is readable');
}

if (
    $passwordSource === false
    || $signSource === false
    || $initSource === false
    || $lifecycleSource === false
    || $usersSource === false
) {
    echo "FAILURES={$failures}\n";
    exit($failures === 0 ? 0 : 1);
}

ga_session_contract_check(
    str_contains($initSource, "require_once __DIR__ . '/includes/password_security.php';"),
    'shared bootstrap loads canonical password security'
);

ga_session_contract_check(
    str_contains($passwordSource, 'password_security_session_fingerprint'),
    'canonical password security owns session fingerprinting'
);

ga_session_contract_check(
    str_contains($passwordSource, 'password_security_bind_authenticated_session'),
    'canonical password security owns login session binding'
);

ga_session_contract_check(
    str_contains($passwordSource, 'password_security_enforce_authenticated_session'),
    'canonical password security owns authenticated session enforcement'
);

ga_session_contract_check(
    str_contains($passwordSource, "SELECT password FROM users WHERE id = ? AND farm_id = ? LIMIT 1"),
    'session enforcement revalidates user inside the current tenant'
);

ga_session_contract_check(
    str_contains($passwordSource, 'hash_equals($currentFingerprint, $sessionFingerprint)'),
    'session fingerprint comparison is timing-safe'
);

ga_session_contract_check(
    str_contains($passwordSource, "$_SESSION['credential_session_fingerprint']")
        || str_contains($passwordSource, "\$_SESSION['credential_session_fingerprint']"),
    'authenticated session stores a credential fingerprint rather than the password hash'
);

ga_session_contract_check(
    str_contains($signSource, 'password_security_bind_authenticated_session($user);'),
    'successful sign in binds the session to current credential state'
);

ga_session_contract_check(
    str_contains($lifecycleSource, 'password_security_hash($newPassword)'),
    'public credential lifecycle writes passwords through canonical hashing'
);

ga_session_contract_check(
    str_contains($usersSource, 'password_security_hash('),
    'Team User password changes use canonical hashing'
);

ga_session_contract_check(
    str_contains($passwordSource, "if ($farmId < 1 || $sessionFingerprint === '')")
        || str_contains($passwordSource, "if (\$farmId < 1 || \$sessionFingerprint === '')"),
    'legacy authenticated sessions without credential fingerprint fail closed'
);

require_once $root . '/includes/password_security.php';

$one = password_security_session_fingerprint('$2y$example-one');
$two = password_security_session_fingerprint('$2y$example-two');

ga_session_contract_check(
    strlen($one) === 64 && ctype_xdigit($one),
    'credential session fingerprint is a fixed one-way digest'
);

ga_session_contract_check(
    !hash_equals($one, $two),
    'different stored password hashes produce different session fingerprints'
);

echo "FAILURES={$failures}\n";
exit($failures === 0 ? 0 : 1);
