<?php

$root = dirname(__DIR__);
$failures = 0;
$checks = 0;

$check = function (bool $ok, string $label) use (&$failures, &$checks): void {
    $checks++;
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$ok) {
        $failures++;
    }
};

$page = file_get_contents($root . '/management/users.php');
$js = file_get_contents($root . '/assets/js/users.js');

$check(
    str_contains(
        $page,
        "versioned_asset('/assets/js/users.js')"
    ),
    'Users page loads external versioned behavior asset'
);

$check(
    substr_count($page, 'users.js') === 1,
    'Users page loads external behavior asset exactly once'
);

$check(
    str_contains($js, 'attachEditModal({'),
    'External Users asset retains edit modal setup'
);

$check(
    str_contains($js, "buttonSelector: '.edit-user-btn'"),
    'External Users asset retains edit-user button contract'
);

$check(
    str_contains($js, "modalSelector: '#editUserModal'"),
    'External Users asset retains edit-user modal contract'
);

$check(
    str_contains($js, 'function confirmUserDeletion(form, username)'),
    'External Users asset retains delete confirmation function'
);

$check(
    str_contains($js, 'AppConfirm.ask'),
    'External Users asset retains confirmation dialog'
);

$check(
    str_contains($js, "action.name='delete_user'") ||
    str_contains($js, "action.name = 'delete_user'"),
    'External Users asset retains delete-user submission contract'
);

$check(
    !str_contains($js, '<?php') &&
    !str_contains($js, '<?='),
    'External Users asset contains no PHP'
);

$active = preg_replace('/<!--.*?-->/s', '', $page);
$inlineCount = 0;

if (preg_match_all(
    '/<script\b([^>]*)>(.*?)<\/script\s*>/is',
    $active,
    $matches,
    PREG_SET_ORDER
)) {
    foreach ($matches as $match) {
        $attrs = $match[1] ?? '';
        $body = trim($match[2] ?? '');

        if (preg_match('/\bsrc\s*=/i', $attrs)) {
            continue;
        }

        if ($body !== '') {
            $inlineCount++;
        }
    }
}

$check(
    $inlineCount === 0,
    'Users page contains zero active inline script blocks'
);

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;

exit($failures === 0 ? 0 : 1);
