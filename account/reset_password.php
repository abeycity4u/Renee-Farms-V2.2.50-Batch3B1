<?php

declare(strict_types=1);

/**
 * V3.1 public password-reset consumption route.
 *
 * Security flow:
 *
 * tokenized GET
 *   -> rotate session id
 *   -> store raw token server-side only
 *   -> 303 redirect to clean URL
 *
 * clean GET
 *   -> render password form without token material
 *
 * POST
 *   -> CSRF
 *   -> shared guest rate limit
 *   -> password confirmation
 *   -> shared lifecycle consumption
 *   -> PRG
 *
 * Token lookup, expiry, single-use behavior, account-state policy,
 * password hashing and token invalidation remain in the shared lifecycle.
 */

require_once dirname(__DIR__) . '/init.php';
require_once dirname(__DIR__) . '/api/api_helpers.php';
require_once dirname(__DIR__)
    . '/includes/account_credential_lifecycle.php';

header('Cache-Control: no-store, max-age=0');
header('Referrer-Policy: no-referrer');

const ACCOUNT_CREDENTIAL_RESET_SUBMIT_LIMIT = 8;
const ACCOUNT_CREDENTIAL_RESET_SUBMIT_WINDOW_SECONDS = 300;

const ACCOUNT_CREDENTIAL_RESET_TOKEN_SESSION_KEY =
    'account_credential_reset_token';

const ACCOUNT_CREDENTIAL_RESET_FLASH_MESSAGE_KEY =
    'account_credential_reset_flash_message';

const ACCOUNT_CREDENTIAL_RESET_FLASH_TYPE_KEY =
    'account_credential_reset_flash_type';

const ACCOUNT_CREDENTIAL_RESET_COMPLETED_KEY =
    'account_credential_reset_completed';

function account_reset_redirect_clean(): void
{
    header(
        'Location: '
        . BASE_URL
        . '/account/reset_password.php',
        true,
        303
    );

    exit();
}

/*
 * The delivery link carries the raw token once.
 *
 * Accept it into server-side session state, rotate the browser session id,
 * and immediately redirect to the clean route before rendering any HTML.
 */
if (
    strtoupper(
        (string)($_SERVER['REQUEST_METHOD'] ?? 'GET')
    ) === 'GET'
    && array_key_exists('token', $_GET)
) {
    $rawToken =
        trim(
            (string)($_GET['token'] ?? '')
        );

    /*
     * Never leave a previous reset capability active when a new tokenized
     * landing occurs, including an empty/malformed token.
     */
    unset(
        $_SESSION[
            ACCOUNT_CREDENTIAL_RESET_TOKEN_SESSION_KEY
        ]
    );

    if ($rawToken !== '') {
        session_regenerate_id(true);

        $_SESSION[
            ACCOUNT_CREDENTIAL_RESET_TOKEN_SESSION_KEY
        ] = $rawToken;
    }

    unset(
        $_SESSION[
            ACCOUNT_CREDENTIAL_RESET_COMPLETED_KEY
        ]
    );

    account_reset_redirect_clean();
}

$flashMessage =
    $_SESSION[
        ACCOUNT_CREDENTIAL_RESET_FLASH_MESSAGE_KEY
    ]
    ?? null;

$flashType =
    $_SESSION[
        ACCOUNT_CREDENTIAL_RESET_FLASH_TYPE_KEY
    ]
    ?? null;

$resetCompleted =
    ($_SESSION[
        ACCOUNT_CREDENTIAL_RESET_COMPLETED_KEY
    ] ?? false) === true;

unset(
    $_SESSION[
        ACCOUNT_CREDENTIAL_RESET_FLASH_MESSAGE_KEY
    ],
    $_SESSION[
        ACCOUNT_CREDENTIAL_RESET_FLASH_TYPE_KEY
    ],
    $_SESSION[
        ACCOUNT_CREDENTIAL_RESET_COMPLETED_KEY
    ]
);

$hasResetToken =
    isset(
        $_SESSION[
            ACCOUNT_CREDENTIAL_RESET_TOKEN_SESSION_KEY
        ]
    )
    && is_string(
        $_SESSION[
            ACCOUNT_CREDENTIAL_RESET_TOKEN_SESSION_KEY
        ]
    )
    && $_SESSION[
        ACCOUNT_CREDENTIAL_RESET_TOKEN_SESSION_KEY
    ] !== '';

if (
    strtoupper(
        (string)($_SERVER['REQUEST_METHOD'] ?? 'GET')
    ) === 'POST'
) {
    if (!csrf_request_is_valid()) {
        $_SESSION[
            ACCOUNT_CREDENTIAL_RESET_FLASH_TYPE_KEY
        ] = 'error';

        $_SESSION[
            ACCOUNT_CREDENTIAL_RESET_FLASH_MESSAGE_KEY
        ] =
            'Your request could not be verified. Please try again.';

        account_reset_redirect_clean();
    }

    $rate =
        rate_limit_attempt(
            'account_credential_password_reset_submit',
            ACCOUNT_CREDENTIAL_RESET_SUBMIT_LIMIT,
            ACCOUNT_CREDENTIAL_RESET_SUBMIT_WINDOW_SECONDS
        );

    if (($rate['allowed'] ?? false) !== true) {
        $_SESSION[
            ACCOUNT_CREDENTIAL_RESET_FLASH_TYPE_KEY
        ] = 'error';

        $_SESSION[
            ACCOUNT_CREDENTIAL_RESET_FLASH_MESSAGE_KEY
        ] =
            'Too many requests. Please try again shortly.';

        account_reset_redirect_clean();
    }

    $rawToken =
        $_SESSION[
            ACCOUNT_CREDENTIAL_RESET_TOKEN_SESSION_KEY
        ]
        ?? '';

    if (!is_string($rawToken) || $rawToken === '') {
        $_SESSION[
            ACCOUNT_CREDENTIAL_RESET_FLASH_TYPE_KEY
        ] = 'error';

        $_SESSION[
            ACCOUNT_CREDENTIAL_RESET_FLASH_MESSAGE_KEY
        ] =
            'This password reset link is unavailable or has expired.';

        account_reset_redirect_clean();
    }

    $newPassword =
        (string)($_POST['new_password'] ?? '');

    $confirmPassword =
        (string)($_POST['confirm_password'] ?? '');

    if (!hash_equals($newPassword, $confirmPassword)) {
        $_SESSION[
            ACCOUNT_CREDENTIAL_RESET_FLASH_TYPE_KEY
        ] = 'error';

        $_SESSION[
            ACCOUNT_CREDENTIAL_RESET_FLASH_MESSAGE_KEY
        ] =
            'The passwords do not match.';

        account_reset_redirect_clean();
    }

    $passwordError =
        password_security_validate(
            $newPassword
        );

    if ($passwordError !== null) {
        $_SESSION[
            ACCOUNT_CREDENTIAL_RESET_FLASH_TYPE_KEY
        ] = 'error';

        $_SESSION[
            ACCOUNT_CREDENTIAL_RESET_FLASH_MESSAGE_KEY
        ] = $passwordError;

        account_reset_redirect_clean();
    }

    try {
        account_credential_consume_password_reset(
            $pdo,
            $rawToken,
            $newPassword
        );

        /*
         * The capability is one-time. Remove the raw token from session
         * immediately after successful lifecycle consumption.
         */
        unset(
            $_SESSION[
                ACCOUNT_CREDENTIAL_RESET_TOKEN_SESSION_KEY
            ]
        );

        session_regenerate_id(true);

        $_SESSION[
            ACCOUNT_CREDENTIAL_RESET_FLASH_TYPE_KEY
        ] = 'success';

        $_SESSION[
            ACCOUNT_CREDENTIAL_RESET_FLASH_MESSAGE_KEY
        ] =
            'Your password has been reset successfully. You can now sign in.';

        $_SESSION[
            ACCOUNT_CREDENTIAL_RESET_COMPLETED_KEY
        ] = true;

        account_reset_redirect_clean();
    } catch (InvalidArgumentException $e) {
        /*
         * Password-policy errors remain retryable with the same valid
         * reset capability.
         */
        $_SESSION[
            ACCOUNT_CREDENTIAL_RESET_FLASH_TYPE_KEY
        ] = 'error';

        $_SESSION[
            ACCOUNT_CREDENTIAL_RESET_FLASH_MESSAGE_KEY
        ] =
            password_security_validate(
                $newPassword
            )
            ?? 'The password could not be accepted.';

        account_reset_redirect_clean();
    } catch (Throwable $e) {
        /*
         * Token/account failures are intentionally collapsed to one public
         * message. Do not expose lifecycle exception detail.
         */
        unset(
            $_SESSION[
                ACCOUNT_CREDENTIAL_RESET_TOKEN_SESSION_KEY
            ]
        );

        error_log(
            'Account credential reset consume failed: '
            . get_class($e)
        );

        $_SESSION[
            ACCOUNT_CREDENTIAL_RESET_FLASH_TYPE_KEY
        ] = 'error';

        $_SESSION[
            ACCOUNT_CREDENTIAL_RESET_FLASH_MESSAGE_KEY
        ] =
            'This password reset link is invalid or has expired. Please request a new one.';

        account_reset_redirect_clean();
    }
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
        Set New Password | Renee Farms Workspace
    </title>

    <meta
        name="theme-color"
        content="#1b4332"
    >

    <meta
        name="description"
        content="Choose a new password for your Renee Farms account."
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
    aria-label="Renee Farms password reset"
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
                Choose a new secure password.
            </h1>

            <p>
                Complete the reset using the secure link sent to your
                account email.
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
            Password Reset
        </span>

        <h2>
            Set a new password
        </h2>

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

        <?php if ($resetCompleted): ?>
            <p class="helper">
                Your reset is complete.
            </p>

            <a
                class="button"
                href="<?php
                    echo htmlspecialchars(
                        BASE_URL . '/sign.php',
                        ENT_QUOTES,
                        'UTF-8'
                    );
                ?>"
            >
                Continue to sign in
            </a>

        <?php elseif ($hasResetToken): ?>

            <p>
                Enter your new password below.
            </p>

            <form
                method="POST"
                action="<?php
                    echo htmlspecialchars(
                        BASE_URL
                        . '/account/reset_password.php',
                        ENT_QUOTES,
                        'UTF-8'
                    );
                ?>"
                autocomplete="off"
            >
                <?php echo csrf_field(); ?>

                <div>
                    <label for="new_password">
                        New password
                    </label>

                    <input
                        id="new_password"
                        name="new_password"
                        type="password"
                        required
                        minlength="<?php
                            echo (int)password_security_min_length();
                        ?>"
                        autocomplete="new-password"
                    >
                </div>

                <div>
                    <label for="confirm_password">
                        Confirm new password
                    </label>

                    <input
                        id="confirm_password"
                        name="confirm_password"
                        type="password"
                        required
                        minlength="<?php
                            echo (int)password_security_min_length();
                        ?>"
                        autocomplete="new-password"
                    >
                </div>

                <button
                    class="button"
                    type="submit"
                >
                    Reset password
                </button>
            </form>

            <p class="helper">
                Passwords must contain at least
                <?php
                    echo (int)password_security_min_length();
                ?>
                characters.
            </p>

        <?php else: ?>

            <div
                class="error"
                role="status"
            >
                This password reset link is unavailable or has expired.
            </div>

            <p class="helper">
                Request a new reset link to continue.
            </p>

            <a
                class="button"
                href="<?php
                    echo htmlspecialchars(
                        BASE_URL
                        . '/account/forgot_password.php',
                        ENT_QUOTES,
                        'UTF-8'
                    );
                ?>"
            >
                Request a new link
            </a>

        <?php endif; ?>
    </section>
</main>
</body>
</html>
