<?php

require_once __DIR__ . '/init.php';

http_response_code(403);

$accessDeniedTitle =
    isset($accessDeniedTitle)
    && trim((string)$accessDeniedTitle) !== ''
        ? trim((string)$accessDeniedTitle)
        : 'Oops! Access restricted';

$accessDeniedMessage =
    isset($accessDeniedMessage)
    && trim((string)$accessDeniedMessage) !== ''
        ? trim((string)$accessDeniedMessage)
        : 'You do not have permission to access this page.';

$accessDeniedHelp =
    isset($accessDeniedHelp)
    && trim((string)$accessDeniedHelp) !== ''
        ? trim((string)$accessDeniedHelp)
        : 'If you believe this is an error, contact your Farm Admin or Platform Owner.';

$accessDeniedDashboardUrl =
    BASE_URL . '/dashboard.php';

?>
<!doctype html>
<html lang="en">
<head>
    <?php include __DIR__ . '/navbar_head.php'; ?>
    <title><?php echo htmlspecialchars($accessDeniedTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <link
        rel="stylesheet"
        href="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/css/access-denied.css'); ?>"
    >
</head>

<body>
    <?php include __DIR__ . '/navbar.php'; ?>

    <main class="access-denied-shell">
        <section class="access-denied-card" role="alert" aria-labelledby="accessDeniedTitle">
            <div class="access-denied-card-body">
                <div class="access-denied-icon" aria-hidden="true">
                    <i class="bi bi-shield-lock-fill"></i>
                </div>

                <div class="access-denied-eyebrow">
                    Access restricted
                </div>

                <h1
                    id="accessDeniedTitle"
                    class="h3 access-denied-title"
                >
                    <?php
                    echo htmlspecialchars(
                        $accessDeniedTitle,
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>
                </h1>

                <p class="access-denied-message">
                    <?php
                    echo htmlspecialchars(
                        $accessDeniedMessage,
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>
                </p>

                <p class="access-denied-help">
                    <?php
                    echo htmlspecialchars(
                        $accessDeniedHelp,
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>
                </p>

                <div class="access-denied-actions">
                    <a
                        class="btn btn-primary px-4"
                        href="<?php
                        echo htmlspecialchars(
                            $accessDeniedDashboardUrl,
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>"
                    >
                        <i class="bi bi-arrow-left me-1"></i>
                        Back to Dashboard
                    </a>
                </div>
            </div>
        </section>
    </main>

    <script src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/vendor/bootstrap5/js/bootstrap.bundle.min.js'); ?>"></script>
    <script src="<?php echo BASE_URL; ?><?php echo versioned_asset('/assets/js/main.js'); ?>"></script>
</body>
</html>
