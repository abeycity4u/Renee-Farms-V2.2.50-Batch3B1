<?php
/**
 * Shared navigation discoverability bridge.
 *
 * Keeps the existing Platform Owner tenant-view shortcut and exposes the
 * tenant-facing Billing & Subscription workspace to normal Farm Admin sessions.
 * This changes navigation only; route authorization remains authoritative.
 */

$showPlatformTenantView = function_exists('isPlatformOwner') && isPlatformOwner();
$showBillingAccount = !$showPlatformTenantView
    && function_exists('hasRole')
    && hasRole('farm_admin');

if (!$showPlatformTenantView && !$showBillingAccount) return;

ob_start(static function (string $html) use ($showPlatformTenantView, $showBillingAccount): string {
    if ($showPlatformTenantView
        && stripos($html, '/management/platform_tenant_view.php') === false) {
        $html = preg_replace(
            '~(<li><a class="dropdown-item" href="[^"]*/management/farms\.php"><i class="bi bi-buildings menu-icon me-2"></i> Farms &amp; Tenants</a></li>)~i',
            '$1<li><a class="dropdown-item" href="' . htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') . '/management/platform_tenant_view.php"><i class="bi bi-eye menu-icon me-2"></i> Tenant View</a></li>',
            $html,
            1
        ) ?? $html;
    }

    if ($showBillingAccount
        && stripos($html, '/billing/account.php') === false) {
        $billingLink = '<li><a class="dropdown-item" href="'
            . htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8')
            . '/billing/account.php"><i class="bi bi-credit-card menu-icon me-2"></i> Billing &amp; Subscription</a></li>';

        $html = preg_replace(
            '~(<li><button type="button" class="dropdown-item" id="themeToggle">.*?</button></li>)~i',
            '$1' . $billingLink,
            $html,
            1
        ) ?? $html;
    }

    return $html;
});
