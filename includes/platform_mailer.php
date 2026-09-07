<?php
/**
 * Central Renee Farms outbound-mail transport.
 *
 * The transport intentionally has no business logic. Message builders pass a
 * validated recipient, subject and body here. Credentials or message bodies are
 * never written to application logs.
 */

if (!function_exists('platform_mail_env')) {
    function platform_mail_env(string $name): string
    {
        $value = getenv($name);
        if ($value === false && array_key_exists($name, $_ENV)) $value = $_ENV[$name];
        if ($value === false && array_key_exists($name, $_SERVER)) $value = $_SERVER[$name];
        return trim((string)($value === false ? '' : $value));
    }
}

if (!function_exists('platform_mail_clean_header_text')) {
    function platform_mail_clean_header_text(string $value, int $maxLength = 160): string
    {
        $value = trim(preg_replace('/[\r\n\x00-\x1F\x7F]+/', ' ', $value) ?? '');
        return substr($value, 0, $maxLength);
    }
}

if (!function_exists('platform_mail_from_address')) {
    function platform_mail_from_address(): string
    {
        $configured = strtolower(platform_mail_env('PLATFORM_MAIL_FROM'));
        if ($configured !== '' && filter_var($configured, FILTER_VALIDATE_EMAIL)) {
            return $configured;
        }
        return 'no-reply@reneefarms.com';
    }
}

if (!function_exists('platform_mail_from_name')) {
    function platform_mail_from_name(): string
    {
        $configured = platform_mail_clean_header_text(platform_mail_env('PLATFORM_MAIL_FROM_NAME'));
        return $configured !== '' ? $configured : 'Renee Farms Platform';
    }
}

if (!function_exists('platform_mail_send')) {
    function platform_mail_send(
        string $to,
        string $subject,
        string $body,
        array $options = []
    ): array {
        $to = strtolower(trim($to));
        if ($to === '' || strlen($to) > 254 || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('A valid email recipient is required.');
        }

        $subject = platform_mail_clean_header_text($subject, 180);
        if ($subject === '') throw new InvalidArgumentException('An email subject is required.');

        $enabled = strtolower(platform_mail_env('PLATFORM_MAIL_ENABLED'));
        if (in_array($enabled, ['0', 'false', 'off', 'disabled'], true)) {
            return ['sent' => false, 'transport' => 'php_mail', 'reason' => 'disabled'];
        }

        $fromAddress = platform_mail_from_address();
        $fromName = platform_mail_from_name();
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'From: ' . $fromName . ' <' . $fromAddress . '>',
        ];

        $replyTo = strtolower(platform_mail_env('PLATFORM_MAIL_REPLY_TO'));
        if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $headers[] = 'Reply-To: ' . $replyTo;
        }

        $sent = false;
        try {
            $sent = mail($to, $subject, $body, implode("\r\n", $headers));
        } catch (Throwable $e) {
            $sent = false;
        }

        if (!$sent) {
            error_log('Platform mail transport rejected an outbound message for ' . $to . '.');
        }

        return [
            'sent' => $sent,
            'transport' => 'php_mail',
            'reason' => $sent ? null : 'transport_rejected',
        ];
    }
}
