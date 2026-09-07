<?php
/**
 * Restricted subscription-recovery compatibility wrapper.
 *
 * The underlying product/price resolution is shared with the normal Farm Admin
 * billing workspace through billing_current_product.php. Recovery adds only its
 * suspended/cancelled/past_due status boundary and recovery-specific validation
 * message.
 */

require_once __DIR__ . '/billing_current_product.php';

if (!function_exists('billing_reactivation_modules')) {
    function billing_reactivation_modules(PDO $pdo, int $farmId, ?array $latest): array
    {
        return billing_current_product_modules($pdo, $farmId, $latest, true);
    }
}

if (!function_exists('billing_reactivation_quote')) {
    function billing_reactivation_quote(PDO $pdo, int $farmId): array
    {
        return billing_current_product($pdo, $farmId, ['suspended', 'cancelled', 'past_due']);
    }
}

if (!function_exists('billing_reactivation_assert_selection')) {
    function billing_reactivation_assert_selection(PDO $pdo, int $farmId, array $selection): array
    {
        return billing_current_product_assert_selection(
            $pdo,
            $farmId,
            $selection,
            ['suspended', 'cancelled', 'past_due'],
            'Subscription recovery can only renew the current commercial product.'
        );
    }
}
?>
