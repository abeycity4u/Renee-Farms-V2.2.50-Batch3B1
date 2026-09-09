<?php
/**
 * CSP-safe visual percentage helpers.
 *
 * Dynamic inline style attributes are incompatible with strict style-src 'self'.
 * Convert visual percentages to one of the finite 0..100 CSS utility classes.
 */

if (!function_exists('app_percent_bucket')) {
    function app_percent_bucket($value): int
    {
        if (!is_numeric($value)) {
            return 0;
        }

        $percent = (float) $value;

        if (!is_finite($percent)) {
            return 0;
        }

        return max(0, min(100, (int) round($percent)));
    }
}

if (!function_exists('app_percent_class')) {
    function app_percent_class($value): string
    {
        return 'app-pct-' . app_percent_bucket($value);
    }
}

if (!function_exists('app_score_class')) {
    function app_score_class($value): string
    {
        return 'app-score-' . app_percent_bucket($value);
    }
}
?>
