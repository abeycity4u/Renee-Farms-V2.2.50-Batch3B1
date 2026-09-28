<?php

declare(strict_types=1);

/**
 * Shared Platform Owner Farms & Tenants workspace navigation.
 *
 * This helper owns discoverability only:
 * - Tenants remain managed by management/farms.php.
 * - Trial requests remain managed by management/trial_onboarding_reviews.php.
 * - Route authorization remains authoritative on each destination page.
 */

if (!function_exists(
    'platform_owner_tenant_workspace_attention_count'
)) {
    function platform_owner_tenant_workspace_attention_count(
        PDO $pdo
    ): int {
        $stmt = $pdo->query(
            "SELECT COUNT(*)
             FROM trial_onboarding_requests
             WHERE status IN (
                 'pending_review',
                 'approved'
             )"
        );

        return (int)$stmt->fetchColumn();
    }
}

if (!function_exists(
    'platform_owner_tenant_workspace_render'
)) {
    function platform_owner_tenant_workspace_render(
        PDO $pdo,
        string $activeTab
    ): void {
        if (!in_array(
            $activeTab,
            ['tenants', 'trial_requests'],
            true
        )) {
            throw new InvalidArgumentException(
                'Unknown Farms & Tenants workspace tab.'
            );
        }

        $attentionCount =
            platform_owner_tenant_workspace_attention_count(
                $pdo
            );

        $base =
            htmlspecialchars(
                BASE_URL,
                ENT_QUOTES,
                'UTF-8'
            );

        $tenantsActive =
            $activeTab === 'tenants';

        $trialActive =
            $activeTab === 'trial_requests';

        ?>
        <div
            class="card border-0 shadow-sm my-3"
            aria-label="Farms and tenants workspace navigation"
        >
            <div class="card-body py-2">
                <div
                    class="nav nav-pills gap-2"
                    role="navigation"
                    aria-label="Farms and tenants sections"
                >
                    <a
                        class="nav-link<?php
                            echo $tenantsActive
                                ? ' active'
                                : '';
                        ?>"
                        href="<?php
                            echo $base;
                        ?>/management/farms.php"
                        <?php
                            echo $tenantsActive
                                ? 'aria-current="page"'
                                : '';
                        ?>
                    >
                        <i class="bi bi-buildings me-1"></i>
                        Tenants
                    </a>

                    <a
                        class="nav-link<?php
                            echo $trialActive
                                ? ' active'
                                : '';
                        ?>"
                        href="<?php
                            echo $base;
                        ?>/management/trial_onboarding_reviews.php"
                        <?php
                            echo $trialActive
                                ? 'aria-current="page"'
                                : '';
                        ?>
                    >
                        <i class="bi bi-person-check me-1"></i>
                        Trial Requests

                        <?php if ($attentionCount > 0): ?>
                            <span
                                class="badge rounded-pill bg-warning text-dark ms-1"
                                title="Trial requests needing review or provisioning"
                            >
                                <?php
                                    echo $attentionCount;
                                ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }
}
