<?php
/**
 * Platform Owner tenant-view discoverability bridge.
 *
 * Adds a direct navigation entry to the existing read-only Platform Tenant View
 * without changing tenant identity, session farm context, or authorization.
 */

if (!function_exists('isPlatformOwner') || !isPlatformOwner()) return;

ob_start(static function (string $html): string {
    if (stripos($html, '/management/platform_tenant_view.php') !== false) return $html;

    $needle = '<li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/management/farms.php"><i class="bi bi-buildings menu-icon me-2"></i> Platform Farms</a></li>';
    if (strpos($html, $needle) === false) return $html;

    $replacement = $needle
        . '<li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/management/platform_tenant_view.php"><i class="bi bi-eye menu-icon me-2"></i> Tenant View</a></li>';

    return str_replace($needle, $replacement, $html);
});
