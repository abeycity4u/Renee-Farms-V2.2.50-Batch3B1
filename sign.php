<?php require_once(__DIR__ . '/init.php'); ?>
<?php
require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/includes/functions.php');
require_once(__DIR__ . '/api/api_helpers.php');

function verifyLoginPassword(PDO $pdo, array $user, string $password): bool {
    return password_security_verify($password, (string)($user['password'] ?? ''));
}

function ensurePlatformOwnerWorkspace(PDO $pdo, array $user): array {
    $pdo->exec("INSERT INTO farms (name, slug, subscription_plan, subscription_status)
                SELECT 'Renee Farms Platform', 'owner', 'platform', 'active'
                WHERE NOT EXISTS (SELECT 1 FROM farms WHERE slug = 'owner')");
    $farmStmt = $pdo->query("SELECT id, name, subscription_status FROM farms WHERE slug = 'owner' LIMIT 1");
    $farm = $farmStmt->fetch(PDO::FETCH_ASSOC);
    if (!$farm) return $user;

    if ((int) ($user['farm_id'] ?? 0) !== (int) $farm['id']) {
        $pdo->prepare('UPDATE users SET farm_id = ? WHERE id = ?')->execute([(int) $farm['id'], (int) $user['id']]);
    }
    $user['farm_id'] = (int) $farm['id'];
    $user['farm_name'] = $farm['name'];
    $user['subscription_status'] = $farm['subscription_status'];
    return $user;
}

$selectedAccountType = 'farm';

// Consume failed-login flash state only on GET. This keeps the browser on a
// refresh-safe GET after a failed POST and never places credentials in the URL.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    $flashError = $_SESSION['login_error'] ?? null;
    if (is_string($flashError) && $flashError !== '') {
        $error = $flashError;
    }
    unset($_SESSION['login_error']);

    $flashAccountType = $_SESSION['login_account_type'] ?? null;
    if (in_array($flashAccountType, ['farm', 'platform'], true)) {
        $selectedAccountType = $flashAccountType;
    }
    unset($_SESSION['login_account_type']);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    require_rate_limit('login_attempt', 12, 300);
    $accountType = ($_POST['account_type'] ?? 'farm') === 'platform' ? 'platform' : 'farm';
    $selectedAccountType = $accountType;
    $farmSlug = strtolower(trim($_POST['farm_slug'] ?? ''));
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($accountType === 'platform') {
        $stmt = $pdo->prepare("SELECT u.*, COALESCE(f.name, 'Renee Farms Platform') AS farm_name, COALESCE(f.subscription_status, 'active') AS subscription_status
                               FROM users u
                               LEFT JOIN farms f ON f.id = u.farm_id
                               LEFT JOIN user_roles ur ON ur.user_id = u.id
                               LEFT JOIN roles r ON r.id = ur.role_id
                               WHERE u.username = ?
                                 AND (u.user_type IN ('platform_owner', 'platform_admin') OR r.code IN ('platform_owner', 'platform_admin'))
                               GROUP BY u.id
                               LIMIT 1");
        $stmt->execute([$username]);
    } else {
        $stmt = $pdo->prepare("SELECT u.*, f.name AS farm_name, f.subscription_status
                               FROM users u INNER JOIN farms f ON f.id = u.farm_id
                               WHERE u.username = ? AND f.slug = ? LIMIT 1");
        $stmt->execute([$username, $farmSlug]);
    }
    $user = $stmt->fetch();

    if ($user && ($accountType === 'platform' || !in_array($user['subscription_status'], ['suspended', 'cancelled'], true)) && verifyLoginPassword($pdo, $user, $password)) {
        if ($accountType === 'platform') $user = ensurePlatformOwnerWorkspace($pdo, $user);
        $previousLogin = $user['last_login_at'] ?? null;

        $updateLoginStmt = $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
        $updateLoginStmt->execute([$user['id']]);

        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['farm_id'] = (int) $user['farm_id'];
        $_SESSION['farm_name'] = $user['farm_name'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_type'] = $user['user_type'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['last_login_at'] = $previousLogin;

        header('Location: dashboard.php');
        exit();
    }

    log_app_error('login_failed', ['account_type' => $accountType, 'farm_slug' => $farmSlug, 'username' => $username]);
    $_SESSION['login_error'] = 'Invalid workspace, username, or password.';
    $_SESSION['login_account_type'] = $accountType;
    header('Location: ' . (defined('BASE_URL') ? BASE_URL : '') . '/sign.php', true, 303);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Sign In | Renee Farms Workspace</title>
    <meta name="theme-color" content="#1b4332">
    <meta name="description" content="Secure access to Renee Farms operational workspace.">

    <link rel="icon" href="assets/images/favicon.ico?v=2024.06.01" type="image/x-icon" sizes="any">
    <link rel="apple-touch-icon" href="assets/images/favicon.ico?v=2024.06.01">

    <link rel="stylesheet" href="assets/css/sign-page.css">
</head>
<body>
    <main class="auth-shell" aria-label="Renee Farms secure sign in layout">
        <section class="auth-hero">
            <div>
                <div class="brand">
                    <img src="assets/images/logo.jpg?v=2024.06.01" alt="Renee Farms logo" width="46" height="46" decoding="async">
                    <div>
                        <strong>RENEE FARMS LTD</strong>
                        <span>Farm Operations Workspace</span>
                    </div>
                </div>
                <h1>Welcome back to your agricultural command center.</h1>
                <p>
                    Securely access production insights, stock flows, and performance data from one professional workspace.
                </p>
                <div class="points" aria-hidden="true">
                    <span>✅ Unified poultry and ruminant records</span>
                    <span>✅ Real-time inventory and expense intelligence</span>
                    <span>✅ Role-based access with secure authentication</span>
                </div>
            </div>
            <a class="back-link" href="index.php">← Back to website</a>
        </section>

        <section class="auth-card">
            <span class="chip">Protected Access</span>
            <h2>Sign in to continue</h2>
            <p>Use your official credentials to open the Renee Farms dashboard.</p>

            <?php if (isset($error)): ?>
                <div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <form method="POST" autocomplete="on">
                <div class="login-type-toggle" role="radiogroup" aria-label="Account type">
                    <label><input type="radio" name="account_type" value="farm" <?php echo $selectedAccountType === 'farm' ? 'checked' : ''; ?> data-login-type> Farm login</label>
                    <span class="toggle-divider" aria-hidden="true"></span>
                    <label><input type="radio" name="account_type" value="platform" <?php echo $selectedAccountType === 'platform' ? 'checked' : ''; ?> data-login-type> Platform owner</label>
                </div>
                <div id="farmWorkspaceField">
                    <label for="farm_slug">Farm Workspace ID</label>
                    <input id="farm_slug" name="farm_slug" type="text" required autofocus autocapitalize="none" placeholder="e.g. green-valley-farm">
                    <small class="form-hint">Use the workspace ID created by the platform owner when your farm was added.</small>
                </div>
                <div>
                    <label for="username">Username</label>
                    <input id="username" name="username" type="text" required>
                </div>
                <div>
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" required>
                </div>
                <button class="button" type="submit">Enter Workspace</button>
            </form>

            <p class="helper">Need access? Contact your administrator for account setup.</p>
        </section>
    </main>
<script src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/js/sign.js'); ?>"></script>
</body>
</html>