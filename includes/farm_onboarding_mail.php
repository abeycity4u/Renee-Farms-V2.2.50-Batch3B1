<?php
/**
 * Farm-creation onboarding email.
 *
 * This service sends the one-time credential message only from the plaintext
 * password present in the successful create request. The password is never
 * persisted here and never logged. Farm creation remains committed even if the
 * external mail transport rejects the message.
 */

require_once __DIR__ . '/farm_contact_email.php';
require_once __DIR__ . '/platform_mailer.php';
require_once __DIR__ . '/billing_route_request.php';

if (!function_exists('farm_onboarding_text')) {
    function farm_onboarding_text($value, int $maxLength = 180): string
    {
        $value = trim(preg_replace('/[\x00-\x1F\x7F]+/', ' ', (string)$value) ?? '');
        return substr($value, 0, $maxLength);
    }
}

if (!function_exists('farm_onboarding_send_credentials')) {
    function farm_onboarding_send_credentials(array $details): array
    {
        $email = farm_contact_email_normalize($details['contact_email'] ?? '');
        $farmName = farm_onboarding_text($details['farm_name'] ?? 'Your farm');
        $workspaceId = farm_onboarding_text($details['workspace_id'] ?? '', 120);
        $username = farm_onboarding_text($details['username'] ?? '', 120);
        $password = (string)($details['password'] ?? '');
        $recipientName = farm_onboarding_text(
            $details['recipient_name'] ?? ($details['owner_name'] ?? ''),
            160
        );

        if ($workspaceId === '' || $username === '' || $password === '') {
            throw new InvalidArgumentException('Farm onboarding credentials are incomplete.');
        }

        $loginUrl = billing_route_public_url('/login.php');
        $greeting = $recipientName !== '' ? 'Hello ' . $recipientName . ',' : 'Hello,';

        $body = $greeting . "\n\n"
            . 'Your ' . $farmName . " workspace has been created on Renee Farms Platform.\n\n"
            . "Sign-in details\n"
            . "---------------\n"
            . 'Farm Workspace ID: ' . $workspaceId . "\n"
            . 'Username: ' . $username . "\n"
            . 'Initial password: ' . $password . "\n"
            . 'Sign in: ' . $loginUrl . "\n\n"
            . "Please keep this message private. The password is included only in this initial setup email. "
            . "If the credential must be changed later, contact the platform owner for a secure reset.\n\n"
            . "Renee Farms Platform\n";

        return platform_mail_send(
            $email,
            'Your Renee Farms workspace is ready',
            $body
        );
    }
}
