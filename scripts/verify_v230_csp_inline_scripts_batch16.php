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

$index = file_get_contents($root . '/index.php');
$slideshow = file_get_contents($root . '/assets/js/homepage-slideshow.js');

$check(
    str_contains(
        $index,
        "versioned_asset('/assets/js/homepage-slideshow.js')"
    ),
    'Homepage loads external versioned slideshow asset'
);

$check(
    !str_contains($index, "document.getElementById('hero-slideshow-image-current')"),
    'Homepage no longer contains inline slideshow implementation'
);

$check(
    str_contains($slideshow, "document.getElementById('hero-slideshow-image-current')"),
    'External slideshow resolves current hero image'
);

$check(
    str_contains($slideshow, "document.getElementById('hero-slideshow-image-next')"),
    'External slideshow resolves next hero image'
);

$check(
    str_contains($slideshow, 'function preloadSlide(slide)'),
    'External slideshow retains image preload behavior'
);

$check(
    str_contains($slideshow, 'function showSlide(slideIndex)'),
    'External slideshow retains slide transition behavior'
);

$check(
    str_contains($slideshow, "'requestIdleCallback' in window"),
    'External slideshow retains idle preload optimization'
);

$check(
    str_contains($slideshow, 'window.setInterval(function ()'),
    'External slideshow retains automatic rotation'
);

$check(
    str_contains($slideshow, 'document.hidden'),
    'External slideshow avoids transitions while document is hidden'
);

/*
 * Count active inline script blocks in index.php.
 */
$active = preg_replace('/<!--.*?-->/s', '', $index);
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
    'Homepage contains zero active inline script blocks'
);

echo PHP_EOL . $checks . ' checks, ' . $failures . ' failure(s).' . PHP_EOL;
exit($failures === 0 ? 0 : 1);
