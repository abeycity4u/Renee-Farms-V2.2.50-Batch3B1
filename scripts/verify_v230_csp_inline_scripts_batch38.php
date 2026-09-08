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

$php = file_get_contents(
    $root . '/navbar_head.php'
);

$calendar = file_get_contents(
    $root . '/assets/js/calendar-runtime.js'
);

$theme = file_get_contents(
    $root . '/assets/js/theme-bootstrap.js'
);

$notify = file_get_contents(
    $root . '/assets/js/app-notify.js'
);

$check(
    !preg_match(
        '/<script\b(?![^>]*\bsrc\s*=)[^>]*>.*?<\/script\s*>/is',
        $php
    ),
    'navbar_head has zero literal inline script blocks'
);

$check(
    str_contains(
        $php,
        "versioned_asset('/assets/js/calendar-runtime.js')"
    ),
    'navbar_head loads versioned calendar runtime'
);

$check(
    str_contains(
        $php,
        "versioned_asset('/assets/js/theme-bootstrap.js')"
    ),
    'navbar_head loads versioned early theme bootstrap'
);

$check(
    str_contains(
        $php,
        "versioned_asset('/assets/js/app-notify.js')"
    ),
    'navbar_head loads versioned notification runtime'
);

$check(
    str_contains($php, 'data-today="')
    && str_contains($php, 'data-timezone="')
    && str_contains($php, 'data-current-month="'),
    'navbar_head exposes centralized calendar config'
);

$check(
    str_contains($php, 'ENT_QUOTES | ENT_SUBSTITUTE'),
    'navbar_head safely escapes calendar config attributes'
);

$check(
    str_contains($calendar, 'document.currentScript'),
    'Calendar runtime reads its own script config'
);

$check(
    str_contains($calendar, 'dataset.today')
    && str_contains($calendar, 'dataset.timezone')
    && str_contains($calendar, 'dataset.currentMonth'),
    'Calendar runtime reads all three centralized values'
);

$check(
    str_contains($calendar, 'window.ReneeCalendar'),
    'Calendar runtime preserves public ReneeCalendar contract'
);

$check(
    !str_contains($calendar, '<?php')
    && !str_contains($calendar, '<?='),
    'Calendar runtime contains no PHP'
);

$check(
    str_contains($theme, "localStorage.getItem('farm-theme')"),
    'Theme bootstrap retains persisted theme lookup'
);

$check(
    str_contains(
        $theme,
        "document.documentElement.setAttribute('data-theme'"
    )
    && str_contains(
        $theme,
        "document.documentElement.setAttribute('data-bs-theme'"
    ),
    'Theme bootstrap preserves document theme attributes'
);

$check(
    !str_contains($theme, '<?php')
    && !str_contains($theme, '<?='),
    'Theme bootstrap contains no PHP'
);

$check(
    str_contains($notify, 'window.AppNotify'),
    'Notification runtime preserves public AppNotify contract'
);

$check(
    str_contains($notify, "document.getElementById('appNotifications')"),
    'Notification runtime retains shared notification container binding'
);

$check(
    str_contains($notify, 'data-notification-close'),
    'Notification runtime retains delegated close behavior'
);

$check(
    str_contains($notify, 'DOMContentLoaded'),
    'Notification runtime retains initial notification lifecycle'
);

$check(
    !str_contains($notify, '<?php')
    && !str_contains($notify, '<?='),
    'Notification runtime contains no PHP'
);

$check(
    substr_count($php, '<style>') === 3,
    'Existing three inline style blocks remain untouched for CSS phase'
);

$calendarPos = strpos($php, '/assets/js/calendar-runtime.js');
$themePos = strpos($php, '/assets/js/theme-bootstrap.js');
$notifyPos = strpos($php, '/assets/js/app-notify.js');
$navigationPos = strpos($php, '/assets/js/navigation.js');

$check(
    $calendarPos !== false
    && $themePos !== false
    && $calendarPos < $themePos,
    'Calendar runtime remains before theme bootstrap'
);

$check(
    $themePos !== false
    && $navigationPos !== false
    && $themePos < $navigationPos,
    'Theme bootstrap remains early in head before deferred navigation'
);

$check(
    $notifyPos !== false,
    'Notification runtime remains present in shared head'
);

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;

exit($failures === 0 ? 0 : 1);
