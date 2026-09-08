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

$php = file_get_contents($root . '/includes/permission_runtime.php');
$js = file_get_contents($root . '/assets/js/permission-runtime.js');

$check(
    str_contains($php, 'id="permissionRuntimeConfig"'),
    'Permission runtime emits centralized config element'
);

$check(
    str_contains($php, 'data-config="'),
    'Permission runtime exposes serialized capability data'
);

$check(
    str_contains($php, "versioned_asset('/assets/js/permission-runtime.js')"),
    'Permission runtime uses versioned external asset when available'
);

$check(
    str_contains($php, "BASE_URL . '/assets/js/permission-runtime.js'"),
    'Permission runtime retains safe non-versioned asset fallback'
);

$check(
    str_contains($php, 'JSON_HEX_TAG')
    && str_contains($php, 'JSON_HEX_AMP')
    && str_contains($php, 'JSON_HEX_APOS')
    && str_contains($php, 'JSON_HEX_QUOT'),
    'Permission capability JSON is HTML/script-context hardened'
);

$check(
    str_contains($php, 'ENT_QUOTES | ENT_SUBSTITUTE'),
    'Permission runtime config and asset output use safe HTML escaping'
);

$check(
    !str_contains($php, "'<script>(function()")
    && !str_contains($php, '"<script>(function()')
    && !str_contains($php, 'DOMContentLoaded'),
    'Permission runtime PHP contains no embedded browser behavior'
);

$check(
    str_contains($js, "document.getElementById('permissionRuntimeConfig')"),
    'External asset reads permission runtime config element'
);

$check(
    str_contains($js, "JSON.parse(configElement.dataset.config || '{}')"),
    'External asset parses serialized permission capability config'
);

$check(
    str_contains($js, 'function blockCalendar('),
    'External asset retains calendar action blocking'
);

$check(
    str_contains($js, 'function stripActionColumn('),
    'External asset retains action-column removal'
);

$check(
    str_contains($js, 'cfg.daily'),
    'External asset retains daily capability enforcement'
);

$check(
    str_contains($js, 'cfg.extra'),
    'External asset retains extra capability enforcement'
);

$check(
    str_contains($js, 'cfg.nav'),
    'External asset retains navigation capability enforcement'
);

$check(
    str_contains($js, 'openRecordModal'),
    'External asset retains add-record restriction selector'
);

$check(
    str_contains($js, 'deleteLayerDailyRecord')
    && str_contains($js, 'deleteBroilerDailyRecord'),
    'External asset retains daily delete restriction selectors'
);

$check(
    str_contains($js, '#addExpenseModal')
    && str_contains($js, '#addTransactionModal'),
    'External asset retains expense/feed add restrictions'
);

$check(
    str_contains($js, '#addSaleModal')
    && str_contains($js, 'record_payment')
    && str_contains($js, '.edit-sale-btn')
    && str_contains($js, 'deleteSale'),
    'External asset retains sales capability restrictions'
);

$check(
    str_contains($js, 'newAnimal')
    && str_contains($js, 'editAnimal')
    && str_contains($js, 'exitAnimal'),
    'External asset retains animal capability restrictions'
);

$check(
    str_contains($js, '#appNavbar a[href]')
    && str_contains($js, 'path.endsWith(suffix)'),
    'External asset retains permission-based navigation filtering'
);

$check(
    str_contains($js, '#manageMenu')
    && str_contains($js, '.dropdown-item[href]'),
    'External asset retains empty Management menu cleanup'
);

$check(
    str_contains($js, "document.addEventListener('DOMContentLoaded'"),
    'External permission behavior waits for DOM readiness'
);

$check(
    !str_contains($js, '<?php')
    && !str_contains($js, '<?='),
    'External permission asset contains no PHP'
);

$check(
    !preg_match('/<script\b(?![^>]*\bsrc\s*=)[^>]*>.*?<\/script\s*>/is', $php),
    'Permission runtime PHP contains zero literal active inline script blocks'
);

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;

exit($failures === 0 ? 0 : 1);
