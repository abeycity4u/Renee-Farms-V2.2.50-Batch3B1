<?php

/**
 * Canonical HTML text-node escaping.
 */
function app_html($value): string
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

/**
 * Canonical HTML attribute escaping.
 */
function app_attr($value): string
{
    return app_html($value);
}

/**
 * Encode PHP data for direct embedding inside a <script> block.
 *
 * Example:
 * const data = <?php echo app_json_script($data); ?>;
 */
function app_json_script($value): string
{
    $json = json_encode(
        $value,
        JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT
        | JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_INVALID_UTF8_SUBSTITUTE
    );

    if ($json === false) {
        throw new RuntimeException('Unable to safely encode script data.');
    }

    return $json;
}
