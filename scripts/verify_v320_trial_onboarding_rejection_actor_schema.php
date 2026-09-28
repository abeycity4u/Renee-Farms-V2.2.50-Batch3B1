<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$path =
    $root
    . '/migrations/088_trial_onboarding_rejection_actor.sql';

$source =
    is_file($path)
        ? (string)file_get_contents($path)
        : '';

$failures = 0;

function rejection_actor_check(
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

rejection_actor_check(
    $source !== '',
    'rejection actor migration exists'
);

rejection_actor_check(
    preg_match(
        '/ALTER\s+TABLE\s+trial_onboarding_requests/i',
        $source
    ) === 1,
    'migration alters only onboarding request schema target'
);

rejection_actor_check(
    preg_match(
        '/ADD\s+COLUMN\s+rejected_by_user_id\s+INT\s+NULL/i',
        $source
    ) === 1,
    'nullable rejected_by_user_id column is added'
);

rejection_actor_check(
    str_contains(
        $source,
        'idx_trial_onboarding_rejected_by'
    ),
    'rejection actor lookup index is declared'
);

rejection_actor_check(
    str_contains(
        $source,
        'fk_trial_onboarding_rejected_by'
    ),
    'rejection actor foreign key is declared'
);

rejection_actor_check(
    preg_match(
        '/FOREIGN\s+KEY\s*\(\s*rejected_by_user_id\s*\)\s*REFERENCES\s+users\s*\(\s*id\s*\)\s*ON\s+DELETE\s+SET\s+NULL/is',
        $source
    ) === 1,
    'rejection actor references users with ON DELETE SET NULL'
);

rejection_actor_check(
    str_contains(
        $source,
        "'088_trial_onboarding_rejection_actor.sql'"
    ),
    'migration marker is recorded'
);

rejection_actor_check(
    !preg_match(
        '/\bUPDATE\s+trial_onboarding_requests\b/i',
        $source
    )
    && !preg_match(
        '/\bDELETE\s+FROM\s+trial_onboarding_requests\b/i',
        $source
    ),
    'migration does not mutate request lifecycle rows'
);

rejection_actor_check(
    !preg_match(
        '/\bALTER\s+TABLE\s+(?!trial_onboarding_requests\b)/i',
        $source
    ),
    'migration alters no unrelated table'
);

rejection_actor_check(
    !preg_match(
        '/\b(INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+(farms|users)\b/i',
        $source
    ),
    'migration does not mutate farms or users'
);

exit(
    $failures === 0
        ? 0
        : 1
);
