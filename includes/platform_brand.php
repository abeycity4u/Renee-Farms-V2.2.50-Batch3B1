<?php

declare(strict_types=1);

/**
 * Renee AgriSuite central platform-brand authority.
 *
 * This file owns product-facing display identity only.
 *
 * It MUST NOT replace or mutate:
 * - tenant/farm names;
 * - farmBrandName() tenant identity;
 * - historical tenant slugs;
 * - reneefarms.com transport/domain authority;
 * - repository names;
 * - legal issuer/company records.
 */

if (!function_exists('platform_brand_product_name')) {
    function platform_brand_product_name(): string
    {
        return 'RENEE AGRISUITE';
    }
}

if (!function_exists('platform_brand_product_text')) {
    function platform_brand_product_text(): string
    {
        return 'Renee AgriSuite';
    }
}

if (!function_exists('platform_brand_parent_name')) {
    function platform_brand_parent_name(): string
    {
        return 'Renee Farms';
    }
}

if (!function_exists('platform_brand_parent_byline')) {
    function platform_brand_parent_byline(): string
    {
        return 'by ' . platform_brand_parent_name();
    }
}

if (!function_exists('platform_brand_plain_lockup')) {
    function platform_brand_plain_lockup(): string
    {
        return platform_brand_product_name()
            . ' '
            . platform_brand_parent_byline();
    }
}

if (!function_exists('platform_brand_page_name')) {
    function platform_brand_page_name(): string
    {
        return platform_brand_product_name();
    }
}

if (!function_exists('platform_brand_mail_sender_name')) {
    function platform_brand_mail_sender_name(): string
    {
        return platform_brand_plain_lockup();
    }
}

if (!function_exists('platform_brand_html')) {
    function platform_brand_html(
        string $extraClass = ''
    ): string {
        $extraClass = trim(
            preg_replace(
                '/[^A-Za-z0-9 _-]+/',
                '',
                $extraClass
            ) ?? ''
        );

        $class =
            'platform-brand-lockup'
            . ($extraClass !== ''
                ? ' ' . $extraClass
                : '');

        return '<span class="'
            . htmlspecialchars(
                $class,
                ENT_QUOTES,
                'UTF-8'
            )
            . '">'
            . '<span class="platform-brand-product">'
            . htmlspecialchars(
                platform_brand_product_name(),
                ENT_QUOTES,
                'UTF-8'
            )
            . '</span>'
            . '<span class="platform-brand-parent">'
            . htmlspecialchars(
                platform_brand_parent_byline(),
                ENT_QUOTES,
                'UTF-8'
            )
            . '</span>'
            . '</span>';
    }
}
