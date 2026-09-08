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

$targets = [
    'management/investigation.php',
    'management/ruminant_investigation.php',
];

$check(
    str_contains($behaviors, "[data-investigation-followup]"),
    'Shared behavior recognizes data-investigation-followup contract'
);

$check(
    str_contains($behaviors, 'dataset.investigationFollowup'),
    'Shared behavior reads investigation follow-up mode'
);

$check(
    str_contains($behaviors, "form.removeAttribute('data-confirm')"),
    'Shared behavior clears confirmation for non-resolve follow-up'
);

$check(
    str_contains(
        $behaviors,
        'Resolve this investigation with the recorded management finding? Source records will not be changed.'
    ),
    'Shared behavior retains resolve confirmation message'
);

$check(
    str_contains($behaviors, "form.setAttribute('data-confirm-title', 'Resolve investigation?')"),
    'Shared behavior retains resolve confirmation title'
);

$check(
    str_contains($behaviors, "form.setAttribute('data-confirm-button', 'Resolve Investigation')"),
    'Shared behavior retains resolve confirmation button text'
);

$check(
    str_contains($behaviors, "form.setAttribute('data-confirm-tone', 'primary')"),
    'Shared behavior retains resolve confirmation tone'
);

foreach ($targets as $relative) {
    $content = file_get_contents($root . '/' . $relative);

    $check(
        !str_contains($content, 'onclick='),
        $relative . ' no longer uses inline onclick handlers'
    );

    $check(
        substr_count($content, 'data-investigation-followup="save"') === 1,
        $relative . ' has exactly one centralized save follow-up control'
    );

    $check(
        substr_count($content, 'data-investigation-followup="resolve"') === 1,
        $relative . ' has exactly one centralized resolve follow-up control'
    );

    $check(
        str_contains($content, 'name="followup_mode" value="save"'),
        $relative . ' preserves backend save follow-up value'
    );

    $check(
        str_contains($content, 'name="followup_mode" value="resolve"'),
        $relative . ' preserves backend resolve follow-up value'
    );
}

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;
exit($failures === 0 ? 0 : 1);
