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

$behaviors = file_get_contents($root . '/assets/js/app-behaviors.js');
$animalView = file_get_contents($root . '/ruminant/animal_view.php');

$check(
    str_contains($behaviors, "[data-membership-close-id]"),
    'Shared behavior recognizes membership close contract'
);

$check(
    str_contains($behaviors, "document.getElementById('close_membership_id')"),
    'Shared behavior resolves membership id input'
);

$check(
    str_contains($behaviors, "document.getElementById('close_membership_cycle')"),
    'Shared behavior resolves membership cycle input'
);

$check(
    str_contains($behaviors, "document.getElementById('closeMembershipModal')"),
    'Shared behavior resolves membership modal'
);

$check(
    str_contains($behaviors, 'idInput.value = membershipId;'),
    'Shared behavior populates membership id'
);

$check(
    str_contains($behaviors, "cycleInput.value = membershipCloseTarget.dataset.cycleCode || '';"),
    'Shared behavior populates cycle code'
);

$check(
    str_contains($behaviors, 'bootstrap.Modal.getOrCreateInstance(modalElement).show();'),
    'Shared behavior opens membership modal'
);

$check(
    !str_contains($animalView, 'function closeMembership'),
    'Animal View no longer contains inline closeMembership function'
);

$check(
    str_contains($animalView, 'data-membership-close-id='),
    'Animal View retains declarative membership close contract'
);

$check(
    str_contains($animalView, 'data-cycle-code='),
    'Animal View retains cycle code data contract'
);

/*
 * Count active inline script blocks in Animal View.
 */
$active = preg_replace('/<!--.*?-->/s', '', $animalView);
$inlineCount = 0;

if (preg_match_all('/<script\b([^>]*)>(.*?)<\/script\s*>/is', $active, $matches, PREG_SET_ORDER)) {
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
    'Animal View contains zero active inline script blocks'
);

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;
exit($failures === 0 ? 0 : 1);
