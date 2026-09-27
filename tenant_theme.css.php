<?php

require_once __DIR__ . '/init.php';

header('Content-Type: text/css; charset=UTF-8');
header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

/**
 * Central tenant accent theme.
 *
 * Product-shell and semantic colours remain independent:
 * - Renee AgriSuite navigation/product identity is platform-owned.
 * - success stays green;
 * - warning stays amber;
 * - danger stays red;
 * - info stays blue/cyan.
 *
 * Farm primary colour owns only neutral "primary/accent" UI semantics.
 */

$tenantPrimaryColor =
    currentFarm()['primary_color']
    ?? '#198754';

if (
    !is_string($tenantPrimaryColor)
    || !preg_match(
        '/^#[0-9a-fA-F]{6}$/',
        $tenantPrimaryColor
    )
) {
    $tenantPrimaryColor = '#198754';
}

$tenantPrimaryColor =
    strtoupper(
        $tenantPrimaryColor
    );

$hexToRgb =
    static function (
        string $hex
    ): array {
        $hex =
            ltrim(
                $hex,
                '#'
            );

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    };

$rgbToHex =
    static function (
        int $r,
        int $g,
        int $b
    ): string {
        return sprintf(
            '#%02X%02X%02X',
            max(0, min(255, $r)),
            max(0, min(255, $g)),
            max(0, min(255, $b))
        );
    };

$mix =
    static function (
        string $from,
        string $toward,
        float $amount
    ) use (
        $hexToRgb,
        $rgbToHex
    ): string {
        [$fr, $fg, $fb] =
            $hexToRgb($from);

        [$tr, $tg, $tb] =
            $hexToRgb($toward);

        $amount =
            max(
                0.0,
                min(
                    1.0,
                    $amount
                )
            );

        return $rgbToHex(
            (int)round(
                $fr
                + (($tr - $fr) * $amount)
            ),
            (int)round(
                $fg
                + (($tg - $fg) * $amount)
            ),
            (int)round(
                $fb
                + (($tb - $fb) * $amount)
            )
        );
    };

$relativeLuminance =
    static function (
        array $rgb
    ): float {
        $channels = [];

        foreach ($rgb as $channel) {
            $value =
                $channel / 255;

            $channels[] =
                $value <= 0.04045
                    ? $value / 12.92
                    : pow(
                        ($value + 0.055)
                        / 1.055,
                        2.4
                    );
        }

        return
            (0.2126 * $channels[0])
            + (0.7152 * $channels[1])
            + (0.0722 * $channels[2]);
    };

$contrastRatio =
    static function (
        float $a,
        float $b
    ): float {
        $lighter = max($a, $b);
        $darker = min($a, $b);

        return
            ($lighter + 0.05)
            / ($darker + 0.05);
    };

[$red, $green, $blue] =
    $hexToRgb(
        $tenantPrimaryColor
    );

$primaryLuminance =
    $relativeLuminance([
        $red,
        $green,
        $blue,
    ]);

$whiteLuminance = 1.0;

$darkText =
    '#111827';

$darkTextLuminance =
    $relativeLuminance(
        $hexToRgb(
            $darkText
        )
    );

$whiteContrast =
    $contrastRatio(
        $primaryLuminance,
        $whiteLuminance
    );

$darkContrast =
    $contrastRatio(
        $primaryLuminance,
        $darkTextLuminance
    );

$contrastText =
    $whiteContrast >= $darkContrast
        ? '#FFFFFF'
        : $darkText;

$hoverColor =
    $mix(
        $tenantPrimaryColor,
        '#000000',
        0.14
    );

$activeColor =
    $mix(
        $tenantPrimaryColor,
        '#000000',
        0.22
    );

$lightEmphasis =
    $mix(
        $tenantPrimaryColor,
        '#000000',
        0.35
    );

$darkEmphasis =
    $mix(
        $tenantPrimaryColor,
        '#FFFFFF',
        0.42
    );

$darkHover =
    $mix(
        $tenantPrimaryColor,
        '#FFFFFF',
        0.56
    );

$rgb =
    $red
    . ','
    . $green
    . ','
    . $blue;

echo <<<CSS
:root{
    --farm-primary:{$tenantPrimaryColor};
    --farm-primary-rgb:{$rgb};
    --farm-primary-hover:{$hoverColor};
    --farm-primary-active:{$activeColor};
    --farm-primary-emphasis:{$lightEmphasis};
    --farm-primary-emphasis-dark:{$darkEmphasis};
    --farm-primary-hover-dark:{$darkHover};
    --farm-primary-contrast:{$contrastText};

    --farm-primary-soft:rgba({$rgb},0.12);
    --farm-primary-soft-strong:rgba({$rgb},0.20);
    --farm-primary-border:rgba({$rgb},0.42);
    --farm-primary-focus:rgba({$rgb},0.25);

    --bs-primary:var(--farm-primary);
    --bs-primary-rgb:var(--farm-primary-rgb);
    --bs-link-color:var(--farm-primary-emphasis);
    --bs-link-hover-color:var(--farm-primary-hover);
    --bs-focus-ring-color:var(--farm-primary-focus);
}

/* Primary actions: tenant-branded but contrast-safe. */
.btn-primary{
    --bs-btn-color:var(--farm-primary-contrast);
    --bs-btn-bg:var(--farm-primary);
    --bs-btn-border-color:var(--farm-primary);
    --bs-btn-hover-color:var(--farm-primary-contrast);
    --bs-btn-hover-bg:var(--farm-primary-hover);
    --bs-btn-hover-border-color:var(--farm-primary-hover);
    --bs-btn-focus-shadow-rgb:var(--farm-primary-rgb);
    --bs-btn-active-color:var(--farm-primary-contrast);
    --bs-btn-active-bg:var(--farm-primary-active);
    --bs-btn-active-border-color:var(--farm-primary-active);
}

.btn-outline-primary{
    --bs-btn-color:var(--farm-primary-emphasis);
    --bs-btn-border-color:var(--farm-primary);
    --bs-btn-hover-color:var(--farm-primary-contrast);
    --bs-btn-hover-bg:var(--farm-primary);
    --bs-btn-hover-border-color:var(--farm-primary);
    --bs-btn-focus-shadow-rgb:var(--farm-primary-rgb);
    --bs-btn-active-color:var(--farm-primary-contrast);
    --bs-btn-active-bg:var(--farm-primary-active);
    --bs-btn-active-border-color:var(--farm-primary-active);
}

/* Neutral primary/accent semantics. */
.text-primary{
    color:var(--farm-primary-emphasis)!important;
}

.text-primary-emphasis{
    color:var(--farm-primary-emphasis)!important;
}

.bg-primary,
.text-bg-primary{
    background-color:var(--farm-primary)!important;
    color:var(--farm-primary-contrast)!important;
}

.bg-primary-subtle{
    background-color:var(--farm-primary-soft)!important;
}

.border-primary{
    border-color:var(--farm-primary)!important;
}

/* Selection surfaces. */
.nav-pills .nav-link.active,
.nav-pills .show>.nav-link{
    background-color:var(--farm-primary);
    color:var(--farm-primary-contrast);
}

.page-item.active .page-link{
    background-color:var(--farm-primary);
    border-color:var(--farm-primary);
    color:var(--farm-primary-contrast);
}

.form-check-input:checked{
    background-color:var(--farm-primary);
    border-color:var(--farm-primary);
}

.form-check-input:focus{
    border-color:var(--farm-primary);
    box-shadow:0 0 0 .25rem var(--farm-primary-focus);
}

/* Keyboard focus stays visible without changing semantic state colours. */
.btn-primary:focus-visible,
.btn-outline-primary:focus-visible,
.page-link:focus-visible,
.nav-link:focus-visible{
    box-shadow:0 0 0 .25rem var(--farm-primary-focus);
}

/*
 * Dark mode needs a lighter text accent and a slightly stronger
 * translucent accent surface. The actual primary button colour remains
 * the customer's selected farm colour.
 */
html[data-theme="dark"]{
    --bs-link-color:var(--farm-primary-emphasis-dark);
    --bs-link-hover-color:var(--farm-primary-hover-dark);
}

html[data-theme="dark"] .text-primary,
html[data-theme="dark"] .text-primary-emphasis{
    color:var(--farm-primary-emphasis-dark)!important;
}

html[data-theme="dark"] .bg-primary-subtle{
    background-color:var(--farm-primary-soft-strong)!important;
}

html[data-theme="dark"] .btn-outline-primary{
    --bs-btn-color:var(--farm-primary-emphasis-dark);
}

/*
 * Intentionally NOT overridden here:
 * .bg-success / .text-success
 * .bg-warning / .text-warning
 * .bg-danger  / .text-danger
 * .bg-info    / .text-info
 * navbar.bg-success
 *
 * Those are platform or semantic status colours, not tenant branding.
 */
CSS;
