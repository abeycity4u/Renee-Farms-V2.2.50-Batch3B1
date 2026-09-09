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

$helperPath = $root . '/includes/expense_action_permissions.php';
$helper = is_file($helperPath) ? file_get_contents($helperPath) : '';

$check(
    $helper !== '',
    'Expense action permission helper exists and is readable'
);

$check(
    preg_match_all(
        '/<style\b[^>]*>.*?<\/style\s*>/is',
        $helper
    ) === 0,
    'Expense action permission helper contains zero inline style blocks'
);

$check(
    substr_count(
        $helper,
        '/assets/css/prepaint-expense-edit-readonly.css'
    ) === 2,
    'Expense edit prepaint keeps fallback and versioned references'
);

$check(
    substr_count(
        $helper,
        '/assets/css/prepaint-expense-delete-readonly.css'
    ) === 2,
    'Expense delete prepaint keeps fallback and versioned references'
);

$check(
    !str_contains($helper, '$rules[]'),
    'Expense helper no longer generates inline CSS rule arrays'
);

$check(
    str_contains(
        $helper,
        "if (!\$canEdit) {"
    ),
    'Expense edit stylesheet remains permission scoped'
);

$check(
    str_contains(
        $helper,
        "if (!\$canDelete) {"
    ),
    'Expense delete stylesheet remains permission scoped'
);

$check(
    str_contains(
        $helper,
        'if (!$styles) return;'
    ),
    'Expense helper avoids injecting styles when no restriction applies'
);

$check(
    str_contains(
        $helper,
        '$css = implode(\'\', $styles);'
    ),
    'Conditional stylesheet links are combined once'
);

foreach ([
    'assets/css/prepaint-expense-edit-readonly.css' => [
        'html body table .edit-expense-btn{display:none!important;}',
    ],
    'assets/css/prepaint-expense-delete-readonly.css' => [
        'html body table button[onclick^="deleteExpense("]',
        'html body table button[onclick*="deleteExpense("]',
        '{display:none!important;}',
    ],
] as $relative => $tokens) {
    $path = $root . '/' . $relative;
    $css = is_file($path) ? file_get_contents($path) : '';

    $check(
        $css !== '',
        "{$relative} exists and is readable"
    );

    $check(
        !str_contains($css, '<?php')
        && !str_contains($css, '<?='),
        "{$relative} contains no PHP"
    );

    foreach ($tokens as $token) {
        $check(
            str_contains($css, $token),
            "{$relative} preserves {$token}"
        );
    }
}

foreach ([
    'navbar_head.php' => 2,
    'includes/permission_prepaint.php' => 1,
] as $relative => $expected) {
    $text = file_get_contents($root . '/' . $relative);

    $check(
        preg_match_all(
            '/<style\b[^>]*>.*?<\/style\s*>/is',
            $text
        ) === $expected,
        "{$relative} retains expected remaining inline-style count"
    );
}

echo PHP_EOL
    . $checks
    . ' checks, '
    . $failures
    . ' failure(s).'
    . PHP_EOL;

exit($failures === 0 ? 0 : 1);
