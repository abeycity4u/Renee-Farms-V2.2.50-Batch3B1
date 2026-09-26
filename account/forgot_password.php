<?php

declare(strict_types=1);

/**
 * V3.1 public password-reset request surface.
 *
 * Thin browser responsibilities only:
 * - render farm/platform recovery identity form;
 * - validate POST CSRF;
 * - enforce shared guest rate-limit decision;
 * - delegate account lookup + neutral durable enqueue;
 * - use POST/Redirect/GET;
 * - expose one generic account-safe result.
 *
 * Account lookup, eligibility, tokens, mail transport and password mutation
 * remain owned by the shared credential services.
 */

require_once dirname(__DIR__) . '/init.php';
require_once dirname(__DIR__) . '/api/api_helpers.php';
require_once dirname(__DIR__)
    . '/includes/account_credential_request.php';

header('Cache-Control: no-store, max-age=0');

const ACCOUNT_CREDENTIAL_RESET_REQUEST_LIMIT = 8;
const ACCOUNT_CREDENTIAL_RESET_REQUEST_WINDOW_SECONDS = 300;

$flashMessage =
    $_SESSION['credential_request_message']
    ?? null;

$flashType =
    $_SESSION['credential_request_type']
    ?? null;

$selectedAccountType =
    $_SESSION['credential_request_account_type']
    ?? 'farm';

unset(
    $_SESSION['credential_request_message'],
    $_SESSION['credential_request_type'],
    $_SESSION['credential_request_account_type']
);

if (!in_array(
    $selectedAccountType,
    [
        'farm',
        'platform',
    ],
    true
)) {
    $selectedAccountType = 'farm';
}

if (
    strtoupper(
        (string)($_SERVER['REQUEST_METHOD'] ?? 'GET')
    ) === 'POST'
) {
    $accountType =
        (($_POST['account_type'] ?? 'farm') === 'platform')
            ? 'platform'
            : 'farm';

    /*
     * Preserve only the non-sensitive UI choice across PRG.
     * Never flash workspace id or username.
     */
    $_SESSION['credential_request_account_type'] =
        $accountType;

    if (!csrf_request_is_valid()) {
        $_SESSION['credential_request_type'] =
            'error';

        $_SESSION['credential_request_message'] =
            'Your request could not be verified. Please try again.';

        header(
            'Location: '
            . BASE_URL
            . '/account/forgot_password.php',
            true,
            303
        );

        exit();
    }

    $rate =
        rate_limit_attempt(
            'account_credential_reset_request',
            ACCOUNT_CREDENTIAL_RESET_REQUEST_LIMIT,
            ACCOUNT_CREDENTIAL_RESET_REQUEST_WINDOW_SECONDS
        );

    if (($rate['allowed'] ?? false) !== true) {
        $_SESSION['credential_request_type'] =
            'error';

        $_SESSION['credential_request_message'] =
            'Too many requests. Please try again shortly.';

        header(
            'Location: '
            . BASE_URL
            . '/account/forgot_password.php',
            true,
            303
        );

        exit();
    }

    if ($accountType === 'platform') {
        $result =
            account_credential_request_platform_password_reset(
                $pdo,
                (string)($_POST['username'] ?? '')
            );
    } else {
        $result =
            account_credential_request_farm_password_reset(
                $pdo,
                (string)($_POST['workspace_id'] ?? ''),
                (string)($_POST['username'] ?? '')
            );
    }

    $_SESSION['credential_request_type'] =
        'success';

    $_SESSION['credential_request_message'] =
        (string)($result['message'] ?? '');

    header(
        'Location: '
        . BASE_URL
        . '/account/forgot_password.php',
        true,
        303
    );

    exit();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Reset Password | Renee Farms Workspace
    </title>

    <meta
        name="theme-color"
        content="#1b4332"
    >

    <meta
        name="description"
        content="Request a secure password reset link for your Renee Farms account."
    >

    <link
        rel="icon"
        href="<?php
            echo htmlspecialchars(
                BASE_URL
                . '/assets/images/favicon.ico?v=2024.06.01',
                ENT_QUOTES,
                'UTF-8'
            );
        ?>"
        type="image/x-icon"
        sizes="any"
    >

    <link
        rel="stylesheet"
        href="<?php
            echo htmlspecialchars(
                BASE_URL
                . versioned_asset('/assets/css/sign-page.css'),
                ENT_QUOTES,
                'UTF-8'
            );
        ?>"
    >
</head>

<body>
<main
    class="auth-shell"
    aria-label="Renee Farms password reset request"
>
    <section class="auth-hero">
        <div>
            <div class="brand">
                <img
                    src="<?php
                        echo htmlspecialchars(
                            BASE_URL
                            . '/assets/images/logo.jpg?v=2024.06.01',
                            ENT_QUOTES,
                            'UTF-8'
                        );
                    ?>"
                    alt="Renee Farms logo"
                    width="46"
                    height="46"
                    decoding="async"
                >

                <div>
                    <strong>RENEE FARMS LTD</strong>
                    <span>Farm Operations Workspace</span>
                </div>
            </div>

            <h1>
                Recover access to your workspace securely.
            </h1>

            <p>
                Enter the account details you normally use to sign in.
                If the account is eligible, a password reset link will
                be sent to the email address stored on the account.
            </p>
        </div>

        <a
            class="back-link"
            href="<?php
                echo htmlspecialchars(
                    BASE_URL . '/sign.php',
                    ENT_QUOTES,
                    'UTF-8'
                );
            ?>"
        >
            ← Back to sign in
        </a>
    </section>

    <section class="auth-card">
        <span class="chip">
            Account Recovery
        </span>

        <h2>
            Request a password reset
        </h2>

        <p>
            Choose the account type and provide your normal sign-in
            identity.
        </p>

        <?php if (
            is_string($flashMessage)
            && $flashMessage !== ''
        ): ?>
            <div
                class="<?php
                    echo $flashType === 'success'
                        ? 'success'
                        : 'error';
                ?>"
                role="status"
            >
                <?php
                    echo htmlspecialchars(
                        $flashMessage,
                        ENT_QUOTES,
                        'UTF-8'
                    );
                ?>
            </div>
        <?php endif; ?>

        <form
            method="POST"
            action="<?php
                echo htmlspecialchars(
                    BASE_URL
                    . '/account/forgot_password.php',
                    ENT_QUOTES,
                    'UTF-8'
                );
            ?>"
            autocomplete="off"
        >
            <?php echo csrf_field(); ?>

            <div
                class="login-type-toggle"
                role="radiogroup"
                aria-label="Account type"
            >
                <label>
                    <input
                        type="radio"
                        name="account_type"
                        value="farm"
                        <?php
                            echo $selectedAccountType === 'farm'
                                ? 'checked'
                                : '';
                        ?>
                    >
                    Farm account
                </label>

                <span
                    class="toggle-divider"
                    aria-hidden="true"
                ></span>

                <label>
                    <input
                        type="radio"
                        name="account_type"
                        value="platform"
                        <?php
                            echo $selectedAccountType === 'platform'
                                ? 'checked'
                                : '';
                        ?>
                    >
                    Platform account
                </label>
            </div>

            <div>
                <label for="workspace_id">
                    Farm Workspace ID
                </label>

                <input
                    id="workspace_id"
                    name="workspace_id"
                    type="text"
                    autocapitalize="none"
                    autocomplete="off"
                    placeholder="Required for farm accounts"
                >

                <small class="form-hint">
                    Platform accounts can leave this field blank.
                </small>
            </div>

            <div>
                <label for="username">
                    Username
                </label>

                <input
                    id="username"
                    name="username"
                    type="text"
                    required
                    autocomplete="username"
                    autocapitalize="none"
                >
            </div>

            <button
                class="button"
                type="submit"
            >
                Send reset link
            </button>
        </form>

        <p class="helper">
            For privacy, the same confirmation is shown whether or not
            the account details match an eligible account.
        </p>
    </section>
</main>
</body>
</html>
