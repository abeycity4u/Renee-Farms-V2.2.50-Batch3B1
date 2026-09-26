<?php

declare(strict_types=1);

/**
 * V3.1 public account-activation route.
 *
 * Security flow:
 *
 * tokenized GET
 *   -> rotate anonymous session id
 *   -> store raw activation token server-side only
 *   -> 303 redirect to clean URL
 *
 * clean GET
 *   -> render password-selection form without token material
 *
 * POST
 *   -> CSRF
 *   -> shared guest rate limit
 *   -> password confirmation / policy
 *   -> shared lifecycle activation
 *   -> PRG
 *
 * Token validity, pending-account state, password hashing, credential-state
 * transition and token invalidation remain owned by the shared lifecycle.
 */

require_once dirname(__DIR__) . '/init.php';
require_once dirname(__DIR__) . '/api/api_helpers.php';
require_once dirname(__DIR__)
    . '/includes/account_credential_lifecycle.php';

header('Cache-Control: no-store, max-age=0');
header('Referrer-Policy: no-referrer');

const ACCOUNT_CREDENTIAL_ACTIVATION_SUBMIT_LIMIT = 8;
const ACCOUNT_CREDENTIAL_ACTIVATION_SUBMIT_WINDOW_SECONDS = 300;

const ACCOUNT_CREDENTIAL_ACTIVATION_TOKEN_SESSION_KEY =
    'account_credential_activation_token';

const ACCOUNT_CREDENTIAL_ACTIVATION_FLASH_MESSAGE_KEY =
    'account_credential_activation_flash_message';

const ACCOUNT_CREDENTIAL_ACTIVATION_FLASH_TYPE_KEY =
    'account_credential_activation_flash_type';

const ACCOUNT_CREDENTIAL_ACTIVATION_COMPLETED_KEY =
    'account_credential_activation_completed';

function account_activation_redirect_clean(): void
{
    header(
        'Location: '
        . BASE_URL
        . '/account/activate.php',
        true,
        303
    );

    exit();
}

/*
 * The email link carries the raw activation token once.
 *
 * Replace any previous session-held activation capability, rotate the
 * anonymous session id, then strip the token from the browser URL before
 * rendering HTML.
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

    unset(
        $_SESSION[
            ACCOUNT_CREDENTIAL_ACTIVATION_TOKEN_SESSION_KEY
        ]
    );

    if ($rawToken !== '') {
        session_regenerate_id(true);

        $_SESSION[
            ACCOUNT_CREDENTIAL_ACTIVATION_TOKEN_SESSION_KEY
        ] = $rawToken;
    }

    unset(
        $_SESSION[
            ACCOUNT_CREDENTIAL_ACTIVATION_COMPLETED_KEY
        ]
    );

    account_activation_redirect_clean();
}

$flashMessage =
    $_SESSION[
        ACCOUNT_CREDENTIAL_ACTIVATION_FLASH_MESSAGE_KEY
    ]
    ?? null;

$flashType =
    $_SESSION[
        ACCOUNT_CREDENTIAL_ACTIVATION_FLASH_TYPE_KEY
    ]
    ?? null;

$activationCompleted =
    ($_SESSION[
        ACCOUNT_CREDENTIAL_ACTIVATION_COMPLETED_KEY
    ] ?? false) === true;

unset(
    $_SESSION[
        ACCOUNT_CREDENTIAL_ACTIVATION_FLASH_MESSAGE_KEY
    ],
    $_SESSION[
        ACCOUNT_CREDENTIAL_ACTIVATION_FLASH_TYPE_KEY
    ],
    $_SESSION[
        ACCOUNT_CREDENTIAL_ACTIVATION_COMPLETED_KEY
    ]
);

$hasActivationToken =
    isset(
        $_SESSION[
            ACCOUNT_CREDENTIAL_ACTIVATION_TOKEN_SESSION_KEY
        ]
    )
    && is_string(
        $_SESSION[
            ACCOUNT_CREDENTIAL_ACTIVATION_TOKEN_SESSION_KEY
        ]
    )
    && $_SESSION[
        ACCOUNT_CREDENTIAL_ACTIVATION_TOKEN_SESSION_KEY
    ] !== '';

if (
    strtoupper(
        (string)($_SERVER['REQUEST_METHOD'] ?? 'GET')
    ) === 'POST'
) {
    if (!csrf_request_is_valid()) {
        $_SESSION[
            ACCOUNT_CREDENTIAL_ACTIVATION_FLASH_TYPE_KEY
        ] = 'error';

        $_SESSION[
            ACCOUNT_CREDENTIAL_ACTIVATION_FLASH_MESSAGE_KEY
        ] =
            'Your request could not be verified. Please try again.';

        account_activation_redirect_clean();
    }

    $rate =
        rate_limit_attempt(
            'account_credential_activation_submit',
            ACCOUNT_CREDENTIAL_ACTIVATION_SUBMIT_LIMIT,
            ACCOUNT_CREDENTIAL_ACTIVATION_SUBMIT_WINDOW_SECONDS
        );

    if (($rate['allowed'] ?? false) !== true) {
        $_SESSION[
            ACCOUNT_CREDENTIAL_ACTIVATION_FLASH_TYPE_KEY
        ] = 'error';

        $_SESSION[
            ACCOUNT_CREDENTIAL_ACTIVATION_FLASH_MESSAGE_KEY
        ] =
            'Too many requests. Please try again shortly.';

        account_activation_redirect_clean();
    }

    $rawToken =
        $_SESSION[
            ACCOUNT_CREDENTIAL_ACTIVATION_TOKEN_SESSION_KEY
        ]
        ?? '';

    if (!is_string($rawToken) || $rawToken === '') {
        $_SESSION[
            ACCOUNT_CREDENTIAL_ACTIVATION_FLASH_TYPE_KEY
        ] = 'error';

        $_SESSION[
            ACCOUNT_CREDENTIAL_ACTIVATION_FLASH_MESSAGE_KEY
        ] =
            'This activation link is unavailable or has expired.';

        account_activation_redirect_clean();
    }

    $newPassword =
        (string)($_POST['new_password'] ?? '');

    $confirmPassword =
        (string)($_POST['confirm_password'] ?? '');

    if (!hash_equals($newPassword, $confirmPassword)) {
        $_SESSION[
            ACCOUNT_CREDENTIAL_ACTIVATION_FLASH_TYPE_KEY
        ] = 'error';

        $_SESSION[
            ACCOUNT_CREDENTIAL_ACTIVATION_FLASH_MESSAGE_KEY
        ] =
            'The passwords do not match.';

        account_activation_redirect_clean();
    }

    $passwordError =
        password_security_validate(
            $newPassword
        );

    if ($passwordError !== null) {
        $_SESSION[
            ACCOUNT_CREDENTIAL_ACTIVATION_FLASH_TYPE_KEY
        ] = 'error';

        $_SESSION[
            ACCOUNT_CREDENTIAL_ACTIVATION_FLASH_MESSAGE_KEY
        ] = $passwordError;

        account_activation_redirect_clean();
    }

    try {
        account_credential_consume_activation(
            $pdo,
            $rawToken,
            $newPassword
        );

        /*
         * Activation is complete and the lifecycle has consumed/invalidated
         * the credential capability. Remove its raw session copy too.
         */
        unset(
            $_SESSION[
                ACCOUNT_CREDENTIAL_ACTIVATION_TOKEN_SESSION_KEY
            ]
        );

        session_regenerate_id(true);

        $_SESSION[
            ACCOUNT_CREDENTIAL_ACTIVATION_FLASH_TYPE_KEY
        ] = 'success';

        $_SESSION[
            ACCOUNT_CREDENTIAL_ACTIVATION_FLASH_MESSAGE_KEY
        ] =
            'Your account has been activated successfully. You can now sign in.';

        $_SESSION[
            ACCOUNT_CREDENTIAL_ACTIVATION_COMPLETED_KEY
        ] = true;

        account_activation_redirect_clean();
    } catch (InvalidArgumentException $e) {
        /*
         * Password-policy validation is retryable while the activation
         * capability remains valid.
         */
        $_SESSION[
            ACCOUNT_CREDENTIAL_ACTIVATION_FLASH_TYPE_KEY
        ] = 'error';

        $_SESSION[
            ACCOUNT_CREDENTIAL_ACTIVATION_FLASH_MESSAGE_KEY
        ] =
            password_security_validate(
                $newPassword
            )
            ?? 'The password could not be accepted.';

        account_activation_redirect_clean();
    } catch (Throwable $e) {
        /*
         * Invalid, expired, consumed, or wrong-state activation capabilities
         * are terminal for this browser handoff.
         */
        unset(
            $_SESSION[
                ACCOUNT_CREDENTIAL_ACTIVATION_TOKEN_SESSION_KEY
            ]
        );

        error_log(
            'Account credential activation consume failed: '
            . get_class($e)
        );

        $_SESSION[
            ACCOUNT_CREDENTIAL_ACTIVATION_FLASH_TYPE_KEY
        ] = 'error';

        $_SESSION[
            ACCOUNT_CREDENTIAL_ACTIVATION_FLASH_MESSAGE_KEY
        ] =
            'This activation link is invalid or has expired. Please contact your administrator for a new invitation.';

        account_activation_redirect_clean();
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
        Activate Account | Renee Farms Workspace
    </title>

    <meta
        name="theme-color"
        content="#1b4332"
    >

    <meta
        name="description"
        content="Activate your Renee Farms account and choose your password."
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
    aria-label="Renee Farms account activation"
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
                Activate your workspace account.
            </h1>

            <p>
                Choose your password to complete account activation
                and prepare your secure workspace access.
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
            Account Activation
        </span>

        <h2>
            Set your password
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

        <?php if ($activationCompleted): ?>

            <p class="helper">
                Your account activation is complete.
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

        <?php elseif ($hasActivationToken): ?>

            <p>
                Create the password you will use to sign in.
            </p>

            <form
                method="POST"
                action="<?php
                    echo htmlspecialchars(
                        BASE_URL
                        . '/account/activate.php',
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
                    Activate account
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
                This activation link is unavailable or has expired.
            </div>

            <p class="helper">
                Contact your administrator if you need a new
                activation invitation.
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
                Back to sign in
            </a>

        <?php endif; ?>
    </section>
</main>
</body>
</html>
