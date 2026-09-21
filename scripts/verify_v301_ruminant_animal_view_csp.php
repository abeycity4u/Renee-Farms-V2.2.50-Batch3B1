<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$pagePath =
    $root
    . '/ruminant/animal_view.php';

$assetPath =
    $root
    . '/assets/js/ruminant-animal-view.js';

$page =
    is_file($pagePath)
        ? (string)file_get_contents($pagePath)
        : '';

$asset =
    is_file($assetPath)
        ? (string)file_get_contents($assetPath)
        : '';

$checks = 0;
$failures = 0;

$check =
    static function (
        string $label,
        bool $ok
    ) use (
        &$checks,
        &$failures
    ): void {
        $checks++;

        echo
            (
                $ok
                    ? 'PASS: '
                    : 'FAIL: '
            )
            . $label
            . PHP_EOL;

        if (!$ok) {
            $failures++;
        }
    };


$check(
    'Animal Profile is readable',
    $page !== ''
);

$check(
    'external Animal Profile asset is readable',
    $asset !== ''
);


$check(
    'Animal Profile loads versioned external behavior exactly once',
    substr_count(
        $page,
        "versioned_asset('/assets/js/ruminant-animal-view.js')"
    ) === 1
);


$check(
    'Bootstrap bundle loads before Animal Profile behavior',
    strpos(
        $page,
        "bootstrap.bundle.min.js"
    ) !== false
    &&
    strpos(
        $page,
        "ruminant-animal-view.js"
    ) !== false
    &&
    strpos(
        $page,
        "bootstrap.bundle.min.js"
    )
    <
    strpos(
        $page,
        "ruminant-animal-view.js"
    )
);


$check(
    'Animal Profile exposes CSP-safe modal reopen configuration',
    str_contains(
        $page,
        'id="ruminantAnimalViewConfig"'
    )
    &&
    str_contains(
        $page,
        'data-reopen-transfer-reverse='
    )
    &&
    str_contains(
        $page,
        'data-reopen-transfer='
    )
    &&
    str_contains(
        $page,
        '$reopenTransferReverseModal && $canTransfer'
    )
    &&
    str_contains(
        $page,
        "\$reopenTransferModal && \$canTransfer && \$animal['status']==='active'"
    )
);


$activePage =
    preg_replace(
        '/<!--.*?-->/s',
        '',
        $page
    );

$inlineCount = 0;

if (
    preg_match_all(
        '/<script\b([^>]*)>(.*?)<\/script\s*>/is',
        (string)$activePage,
        $matches,
        PREG_SET_ORDER
    )
) {
    foreach ($matches as $match) {
        $attrs =
            (string)(
                $match[1]
                ?? ''
            );

        $body =
            trim(
                (string)(
                    $match[2]
                    ?? ''
                )
            );

        if (
            preg_match(
                '/\bsrc\s*=/i',
                $attrs
            )
        ) {
            continue;
        }

        if ($body !== '') {
            $inlineCount++;
        }
    }
}


$check(
    'Animal Profile contains zero active inline script blocks',
    $inlineCount === 0
);


$check(
    'external asset wires transfer reversal modal event',
    str_contains(
        $asset,
        "'show.bs.modal'"
    )
    &&
    str_contains(
        $asset,
        "'cycleTransferReverseModal'"
    )
);


$check(
    'external asset preserves reversal trigger data mapping',
    str_contains(
        $asset,
        "'data-transfer-reverse-id'"
    )
    &&
    str_contains(
        $asset,
        "'data-transfer-from'"
    )
    &&
    str_contains(
        $asset,
        "'data-transfer-to'"
    )
    &&
    str_contains(
        $asset,
        "'data-transfer-date'"
    )
);


$check(
    'external asset clears reversal reason for fresh modal action',
    str_contains(
        $asset,
        "'[name=\"transfer_reversal_reason\"]'"
    )
    &&
    str_contains(
        $asset,
        'reasonInput.value = \'\';'
    )
);


$check(
    'external asset restores both failed modal states',
    str_contains(
        $asset,
        'config.dataset.reopenTransferReverse'
    )
    &&
    str_contains(
        $asset,
        'config.dataset.reopenTransfer'
    )
    &&
    str_contains(
        $asset,
        "'cycleTransferReverseModal'"
    )
    &&
    str_contains(
        $asset,
        "'cycleTransferModal'"
    )
);


$check(
    'external asset reuses Bootstrap modal API',
    str_contains(
        $asset,
        'window.bootstrap.Modal'
    )
    &&
    str_contains(
        $asset,
        '.getOrCreateInstance(element)'
    )
);


$check(
    'external asset contains no PHP',
    !str_contains(
        $asset,
        '<?php'
    )
    &&
    !str_contains(
        $asset,
        '<?='
    )
);


echo
    PHP_EOL
    . 'CHECK_COUNT='
    . $checks
    . PHP_EOL;

echo
    'FAILED_COUNT='
    . $failures
    . PHP_EOL;

echo
    'RESULT='
    . (
        $failures === 0
            ? 'PASS'
            : 'FAIL'
    )
    . PHP_EOL;

exit(
    $failures === 0
        ? 0
        : 1
);
