<?php
/**
 * V2.3 Gap D Phase 3A Platform Owner refund-review surface verifier.
 *
 * Static/database-free/provider-network-free.
 *
 * Proves:
 * - dedicated Platform Owner-only review surface;
 * - canonical POST + CSRF + transaction boundary;
 * - preserve action delegates to shared service;
 * - no direct commercial/provider mutation in the page;
 * - reverse entitlement is not exposed;
 * - navigation remains centralized and Platform Owner-only;
 * - Refund Reviews actually anchors after Tenant View using a regex
 *   that functionally matches the rendered Tenant View markup.
 */

$root = dirname(__DIR__);

$pagePath =
    $root . '/management/billing_refund_reviews.php';

$navPath =
    $root . '/includes/platform_owner_nav_discoverability.php';

foreach ([$pagePath, $navPath] as $requiredPath) {
    if (!is_file($requiredPath)) {
        fwrite(
            STDERR,
            'FAIL: required file missing: '
            . $requiredPath
            . PHP_EOL
        );
        exit(1);
    }
}

$page =
    (string)file_get_contents($pagePath);

$nav =
    (string)file_get_contents($navPath);

$checks = 0;
$failures = 0;

$check = static function (
    bool $ok,
    string $message
) use (&$checks, &$failures): void {
    $checks++;

    echo ($ok ? 'PASS: ' : 'FAIL: ')
        . $message
        . PHP_EOL;

    if (!$ok) {
        $failures++;
    }
};

$check(
    strpos($page, 'requireLogin();') !== false
    && strpos($page, 'requirePlatformOwner();') !== false,
    'refund review route requires login and Platform Owner'
);

$check(
    strpos(
        $page,
        "if (\$requestMethod === 'POST')"
    ) !== false
    && strpos(
        $page,
        'require_valid_csrf_post();'
    ) !== false,
    'mutation path is POST-only and uses canonical CSRF enforcement'
);

$check(
    strpos(
        $page,
        '$pdo->beginTransaction();'
    ) !== false
    && strpos(
        $page,
        '$pdo->commit();'
    ) !== false
    && strpos(
        $page,
        '$pdo->rollBack();'
    ) !== false,
    'preserve action executes inside explicit transaction boundary'
);

$check(
    substr_count(
        $page,
        'billing_refund_resolution_resolve_preserve'
    ) === 1,
    'page delegates preserve resolution exactly once to shared service'
);

$check(
    strpos(
        $page,
        'billing_refund_resolution_resolve_reverse'
    ) === false
    && strpos(
        $page,
        'name="reverse_entitlement"'
    ) === false,
    'Phase 3A surface exposes no reverse-entitlement action'
);

$check(
    strpos(
        $page,
        'name="preserve_entitlement"'
    ) !== false
    && strpos(
        $page,
        'name="resolution_reason"'
    ) !== false
    && strpos(
        $page,
        'maxlength="160"'
    ) !== false
    && preg_match(
        '/name="resolution_reason"[^>]*\brequired\b/s',
        $page
    ) === 1,
    'preserve action is explicit and requires bounded review reason'
);

$check(
    strpos(
        $page,
        'redirectBillingRefundReviews();'
    ) !== false
    && strpos(
        $page,
        "\$_SESSION['success']"
    ) !== false
    && strpos(
        $page,
        "\$_SESSION['error']"
    ) !== false,
    'surface uses PRG and shared session notification flow'
);

$check(
    strpos(
        $page,
        'billing_refund_resolution_ready($pdo)'
    ) !== false,
    'surface fails closed when refund-resolution storage is not ready'
);

$check(
    strpos(
        $page,
        'pa.farm_id = rr.farm_id'
    ) !== false
    && strpos(
        $page,
        'f.id = rr.farm_id'
    ) !== false,
    'review read model keeps payment and tenant lineage bound'
);

$protectedDirectDml =
    '/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+'
    . '(?:farms|farm_modules|farm_role_limits|'
    . 'farm_subscription_seat_addons|subscriptions|'
    . 'billing_payment_attempts|billing_seat_change_requests|'
    . 'billing_refund_resolutions)\b/i';

$check(
    preg_match(
        $protectedDirectDml,
        $page
    ) !== 1,
    'surface performs no direct commercial-state DML'
);

$providerOps =
    '/\bcurl_(?:init|exec)|'
    . 'billing_provider_(?:initialize|verify|charge)/i';

$check(
    preg_match(
        $providerOps,
        $page
    ) !== 1,
    'surface performs no provider or network operation'
);

$route =
    '/management/billing_refund_reviews.php';

$check(
    substr_count(
        $nav,
        $route
    ) === 2,
    'navigation contains one duplicate guard plus one refund-review link'
);

$check(
    substr_count(
        $nav,
        "stripos(\$html, '/management/billing_refund_reviews.php')"
    ) === 1,
    'navigation duplicate-prevention guard exists exactly once'
);

$check(
    substr_count(
        $nav,
        'Refund Reviews'
    ) === 1,
    'navigation label exists exactly once'
);

$refundGuardPosition =
    strpos(
        $nav,
        "stripos(\$html, '/management/billing_refund_reviews.php')"
    );

$farmAdminBillingPosition =
    strpos(
        $nav,
        'if ($showBillingAccount'
    );

$ownerGatePosition =
    $refundGuardPosition === false
        ? false
        : strrpos(
            substr(
                $nav,
                0,
                $refundGuardPosition
            ),
            'if ($showPlatformTenantView'
        );

$check(
    $refundGuardPosition !== false
    && $ownerGatePosition !== false
    && $ownerGatePosition < $refundGuardPosition
    && $farmAdminBillingPosition !== false
    && $refundGuardPosition < $farmAdminBillingPosition,
    'Refund Reviews navigation is Platform Owner-gated before Farm Admin billing navigation'
);

$check(
    strpos(
        $nav,
        '/management/platform_tenant_view.php'
    ) !== false
    && strpos(
        $nav,
        'Tenant View'
    ) !== false
    && strpos(
        $nav,
        '/billing/account.php'
    ) !== false,
    'existing Tenant View and Farm Admin billing navigation remain present'
);

$navDml =
    '/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\b/i';

$check(
    preg_match(
        $navDml,
        $nav
    ) !== 1,
    'navigation bridge remains navigation-only with no database mutation'
);

$check(
    preg_match(
        $providerOps,
        $nav
    ) !== 1,
    'navigation bridge performs no provider or network operation'
);

/*
 * Extract the exact preg_replace pattern that anchors Refund Reviews after
 * Tenant View and prove it against representative rendered navbar HTML.
 */
$pattern = null;

if (preg_match(
    "/preg_replace\\(\\s*'([^']*platform_tenant_view[^']*)'/s",
    $nav,
    $patternMatch
) === 1) {
    $pattern = $patternMatch[1];
}

$check(
    is_string($pattern)
    && $pattern !== '',
    'Tenant View navigation anchor regex is discoverable'
);

$sampleTenantView =
    '<li><a class="dropdown-item" href="/management/platform_tenant_view.php">'
    . '<i class="bi bi-eye menu-icon me-2"></i> Tenant View</a></li>';

$sampleMatched =
    is_string($pattern)
    && $pattern !== ''
        ? @preg_match(
            $pattern,
            $sampleTenantView
        )
        : false;

$check(
    $sampleMatched === 1,
    'Refund Reviews navigation regex functionally matches rendered Tenant View link'
);

$correctSpacing =
    'bi bi-eye menu-icon me-2"></i> Tenant View';

$brokenSpacing =
    'bi bi-eye menu-iconme-2"></i> Tenant View';

$check(
    substr_count(
        $nav,
        $correctSpacing
    ) >= 2
    && substr_count(
        $nav,
        $brokenSpacing
    ) === 0,
    'Tenant View link and anchor regex preserve exact icon spacing'
);

echo PHP_EOL;
echo "Checks: {$checks}" . PHP_EOL;
echo "Failures: {$failures}" . PHP_EOL;

if ($failures > 0) {
    echo "V2.3 REFUND REVIEW SURFACE: FAILED" . PHP_EOL;
    exit(1);
}

echo "V2.3 REFUND REVIEW SURFACE: PASSED" . PHP_EOL;
