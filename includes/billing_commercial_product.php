<?php
/**
 * Canonical commercial-product module identity.
 *
 * Product contract:
 * - standalone Sales is a real commercial product;
 * - shared Sales attached to Poultry/Ruminant is a capability of that
 *   livestock product and is never another priced bundle dimension;
 * - user roles, including sales_rep, never decide the farm product;
 * - callers that need a required product may explicitly reject [].
 */

if (!function_exists('billing_commercial_product_modules')) {
    function billing_commercial_product_modules(
        array $modules
    ): array {
        $known = [];

        foreach ($modules as $module) {
            $module =
                strtolower(
                    trim(
                        (string)$module
                    )
                );

            if (
                in_array(
                    $module,
                    [
                        'poultry',
                        'ruminant',
                        'sales',
                    ],
                    true
                )
            ) {
                $known[$module] =
                    true;
            }
        }

        /*
         * Livestock product identity takes precedence over shared Sales.
         *
         * A Poultry/Ruminant tenant may have Sales capability or even a
         * legacy explicit sales entitlement row. That must never classify
         * the tenant as standalone Sales-only for commercial pricing.
         */
        $livestock = [];

        foreach (
            [
                'poultry',
                'ruminant',
            ]
            as $module
        ) {
            if (isset($known[$module])) {
                $livestock[] =
                    $module;
            }
        }

        if ($livestock) {
            sort(
                $livestock,
                SORT_STRING
            );

            return $livestock;
        }

        if (isset($known['sales'])) {
            return ['sales'];
        }

        return [];
    }
}

if (!function_exists('billing_commercial_product_require_modules')) {
    function billing_commercial_product_require_modules(
        array $modules
    ): array {
        $normalized =
            billing_commercial_product_modules(
                $modules
            );

        if (!$normalized) {
            throw new InvalidArgumentException(
                'A billing quote requires a supported commercial product.'
            );
        }

        return $normalized;
    }
}

if (!function_exists('billing_commercial_product_bundle_key')) {
    function billing_commercial_product_bundle_key(
        array $modules
    ): string {
        $modules =
            billing_commercial_product_require_modules(
                $modules
            );

        if ($modules === ['sales']) {
            return 'sales';
        }

        if ($modules === ['poultry']) {
            return 'poultry';
        }

        if ($modules === ['ruminant']) {
            return 'ruminant';
        }

        if (
            $modules
            === [
                'poultry',
                'ruminant',
            ]
        ) {
            return 'poultry+ruminant';
        }

        throw new InvalidArgumentException(
            'Unsupported commercial product bundle.'
        );
    }
}

if (!function_exists('billing_commercial_product_is_sales_only')) {
    function billing_commercial_product_is_sales_only(
        array $modules
    ): bool {
        return
            billing_commercial_product_modules(
                $modules
            )
            === ['sales'];
    }
}
