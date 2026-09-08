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

$page = file_get_contents($root . '/ruminant/animal_registry.php');
$js = file_get_contents($root . '/assets/js/ruminant-animal-registry.js');

$check(
    str_contains(
        $page,
        "versioned_asset('/assets/js/ruminant-animal-registry.js')"
    ),
    'Animal Registry loads external versioned behavior asset'
);

$check(
    !str_contains($page, 'function newAnimal()'),
    'Animal Registry no longer contains inline newAnimal implementation'
);

$check(
    !str_contains($page, 'function editAnimal(a)'),
    'Animal Registry no longer contains inline editAnimal implementation'
);

$check(
    !str_contains($page, 'function exitAnimal(a)'),
    'Animal Registry no longer contains inline exitAnimal implementation'
);

$check(
    str_contains($page, 'data-ruminant-registry-edit-animal='),
    'Animal Registry exposes declarative initial-edit data contract'
);

$check(
    str_contains(
        $page,
        'app_attr(json_encode($editAnimal, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE))'
    ),
    'Initial-edit JSON is HTML-attribute escaped'
);

$check(
    str_contains($js, 'function newAnimal()'),
    'External registry asset retains new-animal behavior'
);

$check(
    str_contains($js, 'function editAnimal(a)'),
    'External registry asset retains edit-animal behavior'
);

$check(
    str_contains($js, 'function exitAnimal(a)'),
    'External registry asset retains exit-animal behavior'
);

$check(
    str_contains(
        $js,
        "[data-ruminant-registry-edit-animal]"
    ),
    'External registry asset reads initial-edit data contract'
);

$check(
    str_contains(
        $js,
        'config.dataset.ruminantRegistryEditAnimal'
    ),
    'External registry asset reads encoded animal payload'
);

$check(
    str_contains(
        $js,
        'editAnimal(JSON.parse(encoded));'
    ),
    'External registry asset parses and opens initial edit state'
);

$check(
    !str_contains($js, '<?php') && !str_contains($js, '<?='),
    'External registry asset contains no PHP'
);

$check(
    str_contains($page, 'data-ruminant-animal-new'),
    'Animal Registry retains shared new-animal click contract'
);

$check(
    str_contains($page, 'data-ruminant-animal-edit'),
    'Animal Registry retains shared edit-animal click contract'
);

$check(
    str_contains($page, 'data-ruminant-animal-exit'),
    'Animal Registry retains shared exit-animal click contract'
);

$check(
    !str_contains($page, 'btnbtn-light'),
    'Animal Registry contains no malformed btnbtn-light token'
);

$check(
    !str_contains($page, '<divclass='),
    'Animal Registry contains no malformed divclass token'
);

/*
 * Count active inline script blocks in Animal Registry.
 */
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
    'Animal Registry contains zero active inline script blocks'
);

$check(
    substr_count($page, 'id="animalModal"') === 1,
    'Animal Registry contains exactly one animal modal'
);

$check(
    substr_count($page, 'id="exitModal"') === 1,
    'Animal Registry contains exactly one exit modal'
);

$check(
    substr_count($page, 'ruminant-animal-registry.js') === 1,
    'Animal Registry loads registry behavior asset exactly once'
);

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;

exit($failures === 0 ? 0 : 1);
