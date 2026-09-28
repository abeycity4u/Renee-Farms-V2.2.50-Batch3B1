<?php

declare(strict_types=1);

/**
 * V3.2 public trial request browser surface.
 *
 * Thin-route responsibilities only:
 * - render the visitor form;
 * - validate CSRF;
 * - enforce shared guest throttling;
 * - delegate request validation/persistence;
 * - use POST/Redirect/GET;
 * - render safe success/error feedback.
 *
 * This route deliberately does NOT:
 * - approve trial requests;
 * - choose commercial approval policy;
 * - create farms or users;
 * - provision tenant entitlements;
 * - issue credentials;
 * - start a trial;
 * - mutate billing/subscriptions.
 */

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/api/api_helpers.php';
require_once __DIR__
    . '/includes/trial_onboarding_intake.php';
require_once __DIR__
    . '/includes/platform_brand.php';

header('Cache-Control: no-store, max-age=0');

const TRIAL_PUBLIC_REQUEST_LIMIT = 6;
const TRIAL_PUBLIC_REQUEST_WINDOW_SECONDS = 600;

$flashType =
    $_SESSION['trial_request_flash_type']
    ?? null;

$flashMessage =
    $_SESSION['trial_request_flash_message']
    ?? null;

$form =
    $_SESSION['trial_request_form']
    ?? [];

unset(
    $_SESSION['trial_request_flash_type'],
    $_SESSION['trial_request_flash_message'],
    $_SESSION['trial_request_form']
);

if (!is_array($form)) {
    $form = [];
}

$selectedModules =
    $form['modules']
    ?? [];

if (!is_array($selectedModules)) {
    $selectedModules = [];
}

$allowedModules =
    farm_entitlement_known_modules();

$selectedModules =
    array_values(
        array_intersect(
            $allowedModules,
            $selectedModules
        )
    );

function trial_public_redirect(): never
{
    header(
        'Location: '
        . BASE_URL
        . '/trial.php',
        true,
        303
    );

    exit();
}

function trial_public_form_snapshot(
    array $input
): array {
    $modules =
        $input['modules']
        ?? [];

    if (!is_array($modules)) {
        $modules = [];
    }

    /*
     * Preserve only fields entered into this public onboarding form.
     * No passwords, credentials, tokens or payment data exist here.
     */
    return [
        'farm_name' =>
            trim(
                (string)($input['farm_name'] ?? '')
            ),

        'requested_workspace_id' =>
            trim(
                (string)(
                    $input['requested_workspace_id']
                    ?? ''
                )
            ),

        'admin_full_name' =>
            trim(
                (string)(
                    $input['admin_full_name']
                    ?? ''
                )
            ),

        'admin_username' =>
            trim(
                (string)(
                    $input['admin_username']
                    ?? ''
                )
            ),

        'admin_email' =>
            trim(
                (string)(
                    $input['admin_email']
                    ?? ''
                )
            ),

        'contact_name' =>
            trim(
                (string)(
                    $input['contact_name']
                    ?? ''
                )
            ),

        'contact_email' =>
            trim(
                (string)(
                    $input['contact_email']
                    ?? ''
                )
            ),

        'modules' =>
            array_values(
                array_filter(
                    array_map(
                        static fn ($value): string =>
                            trim((string)$value),
                        $modules
                    ),
                    static fn (string $value): bool =>
                        $value !== ''
                )
            ),
    ];
}

if (
    strtoupper(
        (string)(
            $_SERVER['REQUEST_METHOD']
            ?? 'GET'
        )
    ) === 'POST'
) {
    $_SESSION['trial_request_form'] =
        trial_public_form_snapshot(
            $_POST
        );

    if (!csrf_request_is_valid()) {
        $_SESSION['trial_request_flash_type'] =
            'error';

        $_SESSION['trial_request_flash_message'] =
            'Your request could not be verified. Please try again.';

        trial_public_redirect();
    }

    $rate =
        rate_limit_attempt(
            'public_trial_request',
            TRIAL_PUBLIC_REQUEST_LIMIT,
            TRIAL_PUBLIC_REQUEST_WINDOW_SECONDS
        );

    if (($rate['allowed'] ?? false) !== true) {
        $_SESSION['trial_request_flash_type'] =
            'error';

        $_SESSION['trial_request_flash_message'] =
            'Too many trial requests were submitted from this connection. Please try again later.';

        trial_public_redirect();
    }

    try {
        trial_onboarding_intake_create(
            $pdo,
            $_POST,
            (string)(
                $_SERVER['REMOTE_ADDR']
                ?? ''
            )
        );

        unset(
            $_SESSION['trial_request_form']
        );

        $_SESSION['trial_request_flash_type'] =
            'success';

        $_SESSION['trial_request_flash_message'] =
            'Your trial request has been received. We will review your farm details and send account activation instructions when your workspace is ready. Your 14-day trial begins only after successful account activation.';

    } catch (
        InvalidArgumentException
        | DomainException $exception
    ) {
        $_SESSION['trial_request_flash_type'] =
            'error';

        $_SESSION['trial_request_flash_message'] =
            $exception->getMessage();

    } catch (Throwable $exception) {
        if (function_exists('log_app_error')) {
            log_app_error(
                'public_trial_request_failed',
                [
                    'exception' =>
                        get_class($exception),
                ]
            );
        }

        $_SESSION['trial_request_flash_type'] =
            'error';

        $_SESSION['trial_request_flash_message'] =
            'Your trial request could not be submitted right now. Please try again.';
    }

    trial_public_redirect();
}

function trial_public_value(
    array $form,
    string $key
): string {
    return htmlspecialchars(
        (string)($form[$key] ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
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
        Start Your Trial | <?php
            echo htmlspecialchars(
                platform_brand_page_name(),
                ENT_QUOTES,
                'UTF-8'
            );
        ?>
    </title>

    <meta
        name="description"
        content="Request a 14-day Renee AgriSuite trial workspace for your farm."
    >

    <meta
        name="theme-color"
        content="#1b4332"
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
                . versioned_asset(
                    '/assets/css/platform-brand.css'
                ),
                ENT_QUOTES,
                'UTF-8'
            );
        ?>"
    >

    <style nonce="<?php echo app_csp_nonce(); ?>">
        :root {
            color-scheme: light;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family:
                Inter,
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;
            background:
                linear-gradient(
                    180deg,
                    #f3f8f5 0%,
                    #ffffff 100%
                );
            color: #17352b;
        }

        .trial-shell {
            width: min(940px, calc(100% - 32px));
            margin: 0 auto;
            padding: 42px 0 64px;
        }

        .trial-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 28px;
        }

        .trial-back {
            color: #245642;
            text-decoration: none;
            font-weight: 650;
        }

        .trial-card {
            background: #ffffff;
            border: 1px solid #dbe8e1;
            border-radius: 24px;
            box-shadow:
                0 20px 60px rgba(16, 54, 39, .08);
            overflow: hidden;
        }

        .trial-heading {
            padding: 32px 34px 24px;
            border-bottom: 1px solid #e7efea;
        }

        .trial-heading h1 {
            margin: 0 0 10px;
            font-size: clamp(1.8rem, 4vw, 2.6rem);
            line-height: 1.08;
        }

        .trial-heading p {
            margin: 0;
            max-width: 720px;
            color: #587064;
            line-height: 1.65;
        }

        .trial-body {
            padding: 30px 34px 34px;
        }

        .trial-grid {
            display: grid;
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
            gap: 20px;
        }

        .trial-field-full {
            grid-column: 1 / -1;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-size: .92rem;
            font-weight: 700;
        }

        input[type="text"],
        input[type="email"] {
            width: 100%;
            min-height: 46px;
            border: 1px solid #b9ccc1;
            border-radius: 10px;
            padding: 10px 12px;
            font: inherit;
            color: inherit;
            background: #fff;
        }

        input:focus {
            outline: 3px solid rgba(30, 111, 76, .14);
            border-color: #2b7a57;
        }

        .hint {
            margin-top: 6px;
            color: #6c8176;
            font-size: .82rem;
            line-height: 1.45;
        }

        .module-box {
            display: grid;
            grid-template-columns:
                repeat(3, minmax(0, 1fr));
            gap: 10px;
        }

        .module-option {
            display: flex;
            align-items: center;
            gap: 9px;
            min-height: 48px;
            margin: 0;
            padding: 10px 12px;
            border: 1px solid #c9d8d0;
            border-radius: 10px;
            cursor: pointer;
        }

        .trial-alert {
            margin-bottom: 22px;
            padding: 13px 15px;
            border-radius: 10px;
            line-height: 1.5;
        }

        .trial-alert-success {
            background: #e9f7ef;
            border: 1px solid #a8d7b8;
            color: #205b38;
        }

        .trial-alert-error {
            background: #fff2f2;
            border: 1px solid #e8baba;
            color: #8a2d2d;
        }

        .trial-submit-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            margin-top: 28px;
        }

        .trial-submit {
            border: 0;
            border-radius: 10px;
            min-height: 48px;
            padding: 11px 22px;
            background: #1b6747;
            color: #fff;
            font: inherit;
            font-weight: 750;
            cursor: pointer;
        }

        .trial-note {
            margin: 0;
            max-width: 520px;
            color: #647a6f;
            font-size: .86rem;
            line-height: 1.5;
        }

        @media (max-width: 720px) {
            .trial-grid,
            .module-box {
                grid-template-columns: 1fr;
            }

            .trial-heading,
            .trial-body {
                padding-left: 22px;
                padding-right: 22px;
            }

            .trial-submit-row,
            .trial-top {
                align-items: stretch;
                flex-direction: column;
            }

            .trial-submit {
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <main class="trial-shell">
        <div class="trial-top">
            <a
                class="trial-back"
                href="<?php
                    echo htmlspecialchars(
                        BASE_URL . '/index.php',
                        ENT_QUOTES,
                        'UTF-8'
                    );
                ?>"
            >
                ← Back to website
            </a>

            <a
                class="trial-back"
                href="<?php
                    echo htmlspecialchars(
                        BASE_URL . '/sign.php',
                        ENT_QUOTES,
                        'UTF-8'
                    );
                ?>"
            >
                Already have an account? Sign in
            </a>
        </div>

        <section class="trial-card">
            <div class="trial-heading">
                <div>
                    <?php
                        echo platform_brand_html(
                            'platform-brand-auth'
                        );
                    ?>
                </div>

                <h1>Request your 14-day farm trial</h1>

                <p>
                    Tell us about your farm and the parts of Renee AgriSuite
                    you want to use. Submitting this form creates a trial
                    request only. Your workspace is created after approval,
                    and your 14-day trial starts when the Farm Admin
                    successfully activates the account.
                </p>
            </div>

            <div class="trial-body">
                <?php if (
                    is_string($flashMessage)
                    && trim($flashMessage) !== ''
                ): ?>
                    <div
                        class="trial-alert <?php
                            echo $flashType === 'success'
                                ? 'trial-alert-success'
                                : 'trial-alert-error';
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
                    autocomplete="on"
                    novalidate
                >
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?php
                            echo htmlspecialchars(
                                csrf_token(),
                                ENT_QUOTES,
                                'UTF-8'
                            );
                        ?>"
                    >

                    <div class="trial-grid">
                        <div>
                            <label for="farm_name">
                                Farm name
                            </label>

                            <input
                                id="farm_name"
                                name="farm_name"
                                type="text"
                                maxlength="150"
                                required
                                value="<?php
                                    echo trial_public_value(
                                        $form,
                                        'farm_name'
                                    );
                                ?>"
                            >
                        </div>

                        <div>
                            <label for="requested_workspace_id">
                                Preferred Farm Workspace ID
                            </label>

                            <input
                                id="requested_workspace_id"
                                name="requested_workspace_id"
                                type="text"
                                maxlength="100"
                                pattern="[a-z0-9]+(-[a-z0-9]+)*"
                                autocapitalize="none"
                                required
                                value="<?php
                                    echo trial_public_value(
                                        $form,
                                        'requested_workspace_id'
                                    );
                                ?>"
                            >

                            <div class="hint">
                                Example: green-valley-farm.
                                Lowercase letters, numbers and hyphens only.
                            </div>
                        </div>

                        <div>
                            <label for="admin_full_name">
                                Farm Admin full name
                            </label>

                            <input
                                id="admin_full_name"
                                name="admin_full_name"
                                type="text"
                                maxlength="<?php
                                    echo ACCOUNT_IDENTITY_FULL_NAME_MAX;
                                ?>"
                                required
                                value="<?php
                                    echo trial_public_value(
                                        $form,
                                        'admin_full_name'
                                    );
                                ?>"
                            >
                        </div>

                        <div>
                            <label for="admin_username">
                                Farm Admin username
                            </label>

                            <input
                                id="admin_username"
                                name="admin_username"
                                type="text"
                                maxlength="<?php
                                    echo ACCOUNT_IDENTITY_USERNAME_MAX;
                                ?>"
                                autocapitalize="none"
                                required
                                value="<?php
                                    echo trial_public_value(
                                        $form,
                                        'admin_username'
                                    );
                                ?>"
                            >
                        </div>

                        <div>
                            <label for="admin_email">
                                Farm Admin email
                            </label>

                            <input
                                id="admin_email"
                                name="admin_email"
                                type="email"
                                maxlength="254"
                                autocomplete="email"
                                required
                                value="<?php
                                    echo trial_public_value(
                                        $form,
                                        'admin_email'
                                    );
                                ?>"
                            >

                            <div class="hint">
                                Activation instructions will be sent to this
                                email after the workspace is provisioned.
                            </div>
                        </div>

                        <div>
                            <label for="contact_name">
                                Business/contact name
                                <span class="hint">(optional)</span>
                            </label>

                            <input
                                id="contact_name"
                                name="contact_name"
                                type="text"
                                maxlength="150"
                                value="<?php
                                    echo trial_public_value(
                                        $form,
                                        'contact_name'
                                    );
                                ?>"
                            >
                        </div>

                        <div class="trial-field-full">
                            <label for="contact_email">
                                Business/contact email
                                <span class="hint">(optional)</span>
                            </label>

                            <input
                                id="contact_email"
                                name="contact_email"
                                type="email"
                                maxlength="254"
                                value="<?php
                                    echo trial_public_value(
                                        $form,
                                        'contact_email'
                                    );
                                ?>"
                            >

                            <div class="hint">
                                This may be different from the Farm Admin
                                credential email.
                            </div>
                        </div>

                        <fieldset class="trial-field-full">
                            <legend>
                                What do you want to manage?
                            </legend>

                            <div class="module-box">
                                <?php foreach (
                                    farm_entitlement_module_labels()
                                    as $code => $label
                                ): ?>
                                    <label class="module-option">
                                        <input
                                            type="checkbox"
                                            name="modules[]"
                                            value="<?php
                                                echo htmlspecialchars(
                                                    $code,
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                );
                                            ?>"
                                            <?php
                                                echo in_array(
                                                    $code,
                                                    $selectedModules,
                                                    true
                                                )
                                                    ? 'checked'
                                                    : '';
                                            ?>
                                        >

                                        <span>
                                            <?php
                                                echo htmlspecialchars(
                                                    $label,
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                );
                                            ?>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>
                    </div>

                    <div class="trial-submit-row">
                        <p class="trial-note">
                            No payment is taken on this form.
                            Submitting a request does not start your trial.
                        </p>

                        <button
                            class="trial-submit"
                            type="submit"
                        >
                            Request 14-day trial
                        </button>
                    </div>
                </form>
            </div>
        </section>
    </main>
</body>
</html>
