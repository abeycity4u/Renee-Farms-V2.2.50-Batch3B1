<?php
/**
 * Friendly access-denied presentation for the Platform Owner Tenant View.
 *
 * This runs only on the dedicated tenant-view route. It does not broaden access;
 * non-owner users still receive HTTP 403 and are returned to their own workspace.
 */

$platformTenantAccessPath = '/' . ltrim(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if (!($platformTenantAccessPath === '/management/platform_tenant_view.php'
    || str_ends_with($platformTenantAccessPath, '/management/platform_tenant_view.php'))) return;
if (!isset($_SESSION['user_id'])) return;
if (function_exists('isPlatformOwner') && isPlatformOwner()) return;

http_response_code(403);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Access Restricted - Renee Farms Platform</title>
    <link href="<?php echo BASE_URL; ?>/assets/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>/assets/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4 p-md-5 text-center">
                    <div class="display-5 text-secondary mb-3"><i class="bi bi-shield-lock"></i></div>
                    <h3 class="mb-2">Platform Owner access required</h3>
                    <p class="text-muted mb-4">
                        This read-only tenant support view is available only to the Platform Owner.
                        Your farm account and workspace remain unchanged.
                    </p>
                    <a class="btn btn-success" href="<?php echo BASE_URL; ?>/dashboard.php">
                        <i class="bi bi-arrow-left"></i> Return to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
<?php exit();
