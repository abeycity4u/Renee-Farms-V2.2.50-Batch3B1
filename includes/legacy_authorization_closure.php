<?php
/**
 * V2.3 final legacy authorization closure.
 *
 * Keeps a few old browser routes aligned with the canonical permission/CSRF
 * model without reconstructing their large page implementations.
 */

if (!function_exists('legacy_authorization_closure_path')) {
    function legacy_authorization_closure_path(): string
    {
        $path = (string)($_SERVER['SCRIPT_NAME'] ?? '');
        return '/' . ltrim(str_replace('\\', '/', $path), '/');
    }
}

if (!function_exists('legacy_authorization_closure_is')) {
    function legacy_authorization_closure_is(string $path, string $suffix): bool
    {
        return $path === $suffix || str_ends_with($path, $suffix);
    }
}

if (!function_exists('legacy_authorization_closure_deny')) {
    function legacy_authorization_closure_deny(string $message = 'You do not have permission to perform this action.'): void
    {
        if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'GET') {
            header('Location: ' . BASE_URL . '/no_access.php');
            exit();
        }
        http_response_code(403);
        exit($message);
    }
}

if (!function_exists('legacy_authorization_closure_require')) {
    function legacy_authorization_closure_require(string $permission): void
    {
        if (function_exists('permission_runtime_has') && permission_runtime_has($permission)) return;
        legacy_authorization_closure_deny();
    }
}

if (!function_exists('legacy_authorization_closure_inject_sales_csrf')) {
    function legacy_authorization_closure_inject_sales_csrf(): void
    {
        if (!function_exists('csrf_field')) return;
        $field = csrf_field();
        ob_start(static function (string $html) use ($field): string {
            return (string)preg_replace_callback(
                '#<form\b([^>]*)\bmethod\s*=\s*(["\']?)post\2([^>]*)>#i',
                static fn(array $m): string => $m[0] . $field,
                $html
            );
        });
    }
}

$legacyAuthorizationPath = legacy_authorization_closure_path();
$legacyAuthorizationMethod = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$legacyAuthorizationPrivileged = function_exists('permission_runtime_privileged')
    ? permission_runtime_privileged()
    : (function_exists('isPlatformOwner') && isPlatformOwner())
        || (function_exists('hasRole') && hasRole('farm_admin'));

// Legacy Sales Records historically relied on role/action guards but did not
// validate CSRF for its browser forms. Keep every existing form working while
// enforcing one request boundary before any financial mutation can execute.
if (legacy_authorization_closure_is($legacyAuthorizationPath, '/management/sales_records.php')) {
    if ($legacyAuthorizationMethod === 'POST') {
        require_valid_csrf_post();
    } elseif ($legacyAuthorizationMethod === 'GET') {
        legacy_authorization_closure_inject_sales_csrf();
    }
}

// The Animal Profile is parent-gated by Ruminant Animal Registry — View in the
// central route map. Child-history writes (weights, health and cycle membership)
// are modifications and therefore require the existing Edit capability.
if (legacy_authorization_closure_is($legacyAuthorizationPath, '/ruminant/animal_view.php')) {
    if ($legacyAuthorizationMethod === 'POST') {
        legacy_authorization_closure_require('ruminant_animals_edit');
    } elseif ($legacyAuthorizationMethod === 'GET'
        && function_exists('permission_runtime_has')
        && !permission_runtime_has('ruminant_animals_edit')) {
        ob_start(static function (string $html): string {
            $style = '<style id="v230AnimalProfileReadOnly">'
                . 'a[href*="animal_registry.php?edit="],'
                . 'button[data-bs-target="#weightModal"],'
                . 'button[data-bs-target="#healthModal"],'
                . 'button[data-bs-target="#membershipModal"],'
                . 'button[onclick^="closeMembership"],'
                . '#cycle-membership form[method="post"],'
                . '#weightModal,#healthModal,#membershipModal,#closeMembershipModal'
                . '{display:none!important}'
                . '</style>';
            return str_contains($html, '</head>') ? str_replace('</head>', $style . '</head>', $html) : $html;
        });
    }
}

// Investigation pages are drill-downs from Farm Intelligence. Require the same
// parent View permission on direct URLs so another report-capable role cannot
// bypass the Farm Intelligence boundary. Existing follow-up CSRF and livestock
// context checks remain authoritative for the audited follow-through workflow.
if (legacy_authorization_closure_is($legacyAuthorizationPath, '/management/investigation.php')
    || legacy_authorization_closure_is($legacyAuthorizationPath, '/management/ruminant_investigation.php')) {
    legacy_authorization_closure_require('farm_intelligence');
}

// Membership-integrity repair changes a historical boundary. Keep the page
// visible to a delegated Farm Intelligence viewer, but reserve the corrective
// mutation for Farm Admin / Platform Owner until an explicit repair permission
// exists. This does not affect ordinary Animal Registry Edit operations.
if (legacy_authorization_closure_is($legacyAuthorizationPath, '/management/ruminant_membership_integrity.php')) {
    legacy_authorization_closure_require('farm_intelligence');
    if ($legacyAuthorizationMethod === 'POST' && !$legacyAuthorizationPrivileged) {
        legacy_authorization_closure_deny('Farm Admin access is required to repair historical membership boundaries.');
    }
    if ($legacyAuthorizationMethod === 'GET' && !$legacyAuthorizationPrivileged) {
        ob_start(static function (string $html): string {
            $style = '<style id="v230MembershipRepairReadOnly">form[method="post"]{display:none!important}</style>';
            return str_contains($html, '</head>') ? str_replace('</head>', $style . '</head>', $html) : $html;
        });
    }
}
