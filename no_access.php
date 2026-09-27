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

    <style>
        .access-denied-shell {
            display: flex;
            justify-content: center;
            padding: clamp(4.5rem, 11vh, 8rem) 1rem 3rem;
        }

        .access-denied-card {
            width: min(100%, 720px);
            text-align: center;
            border: 1px solid var(--bs-border-color);
            border-radius: 1.25rem;
            background: var(--bs-body-bg);
            box-shadow: 0 1rem 2.5rem rgba(0, 0, 0, .12);
            overflow: hidden;
        }

        .access-denied-card-body {
            padding: clamp(2rem, 5vw, 3.25rem);
        }

        .access-denied-icon {
            width: 4rem;
            height: 4rem;
            margin: 0 auto 1.25rem;
            display: grid;
            place-items: center;
            border-radius: 50%;
            font-size: 1.8rem;
            background: var(--bs-danger-bg-subtle);
            color: var(--bs-danger-text-emphasis);
        }

        .access-denied-eyebrow {
            margin-bottom: .65rem;
            font-size: .78rem;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: var(--bs-secondary-color);
        }

        .access-denied-title {
            margin-bottom: 1rem;
            font-weight: 750;
        }

        .access-denied-message {
            margin: 0 auto .7rem;
            max-width: 560px;
            font-size: 1.08rem;
            color: var(--bs-body-color);
        }

        .access-denied-help {
            margin: 0 auto 1.75rem;
            max-width: 560px;
            color: var(--bs-secondary-color);
        }

        .access-denied-actions {
            display: flex;
            justify-content: center;
        }
    </style>
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
