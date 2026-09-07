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
        if (defined($name)) return trim((string)constant($name));
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

if (!function_exists('platform_mail_reply_to')) {
    function platform_mail_reply_to(): string
    {
        $configured = strtolower(platform_mail_env('PLATFORM_MAIL_REPLY_TO'));
        return $configured !== '' && filter_var($configured, FILTER_VALIDATE_EMAIL) ? $configured : '';
    }
}

if (!function_exists('platform_mail_transport')) {
    function platform_mail_transport(): string
    {
        $transport = strtolower(platform_mail_env('PLATFORM_MAIL_TRANSPORT'));
        return $transport === 'smtp' ? 'smtp' : 'php_mail';
    }
}

if (!function_exists('platform_smtp_read_response')) {
    function platform_smtp_read_response($socket): array
    {
        $lines = [];
        $code = 0;
        while (($line = fgets($socket, 4096)) !== false) {
            $lines[] = rtrim($line, "\r\n");
            if (preg_match('/^(\d{3})([ -])/', $line, $m)) {
                $code = (int)$m[1];
                if ($m[2] === ' ') break;
            }
        }
        return ['code' => $code, 'lines' => $lines];
    }
}

if (!function_exists('platform_smtp_expect')) {
    function platform_smtp_expect($socket, array $expected): void
    {
        $response = platform_smtp_read_response($socket);
        if (!in_array((int)$response['code'], $expected, true)) {
            throw new RuntimeException('SMTP server rejected the request.');
        }
    }
}

if (!function_exists('platform_smtp_command')) {
    function platform_smtp_command($socket, string $command, array $expected): void
    {
        if (fwrite($socket, $command . "\r\n") === false) {
            throw new RuntimeException('SMTP connection write failed.');
        }
        platform_smtp_expect($socket, $expected);
    }
}

if (!function_exists('platform_smtp_send')) {
    function platform_smtp_send(string $to, string $subject, string $body): array
    {
        $host = platform_mail_env('PLATFORM_SMTP_HOST');
        $port = (int)platform_mail_env('PLATFORM_SMTP_PORT');
        $encryption = strtolower(platform_mail_env('PLATFORM_SMTP_ENCRYPTION'));
        $username = platform_mail_env('PLATFORM_SMTP_USERNAME');
        $password = platform_mail_env('PLATFORM_SMTP_PASSWORD');
        $fromAddress = platform_mail_from_address();
        $fromName = platform_mail_from_name();
        $replyTo = platform_mail_reply_to();

        if ($host === '' || $port < 1 || $port > 65535 || $username === '' || $password === '') {
            return ['sent' => false, 'transport' => 'smtp', 'reason' => 'smtp_not_configured'];
        }
        if (!in_array($encryption, ['ssl', 'tls'], true)) {
            return ['sent' => false, 'transport' => 'smtp', 'reason' => 'smtp_invalid_encryption'];
        }

        $remote = ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client($remote, $errno, $errstr, 20, STREAM_CLIENT_CONNECT);
        if (!is_resource($socket)) {
            return ['sent' => false, 'transport' => 'smtp', 'reason' => 'smtp_connect_failed'];
        }

        stream_set_timeout($socket, 20);
        try {
            platform_smtp_expect($socket, [220]);
            platform_smtp_command($socket, 'EHLO reneefarms.com', [250]);

            if ($encryption === 'tls') {
                platform_smtp_command($socket, 'STARTTLS', [220]);
                $crypto = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                if ($crypto !== true) throw new RuntimeException('SMTP TLS negotiation failed.');
                platform_smtp_command($socket, 'EHLO reneefarms.com', [250]);
            }

            platform_smtp_command($socket, 'AUTH LOGIN', [334]);
            platform_smtp_command($socket, base64_encode($username), [334]);
            platform_smtp_command($socket, base64_encode($password), [235]);
            platform_smtp_command($socket, 'MAIL FROM:<' . $fromAddress . '>', [250]);
            platform_smtp_command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
            platform_smtp_command($socket, 'DATA', [354]);

            $headers = [
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=UTF-8',
                'Content-Transfer-Encoding: 8bit',
                'From: ' . $fromName . ' <' . $fromAddress . '>',
                'To: ' . $to,
                'Subject: ' . $subject,
                'Date: ' . date(DATE_RFC2822),
                'Message-ID: <' . bin2hex(random_bytes(12)) . '@reneefarms.com>',
            ];
            if ($replyTo !== '') $headers[] = 'Reply-To: ' . $replyTo;

            $normalizedBody = str_replace(["\r\n", "\r"], "\n", $body);
            $normalizedBody = str_replace("\n", "\r\n", $normalizedBody);
            $normalizedBody = preg_replace('/(^|\r\n)\./', '$1..', $normalizedBody) ?? $normalizedBody;
            $message = implode("\r\n", $headers) . "\r\n\r\n" . $normalizedBody . "\r\n.\r\n";
            if (fwrite($socket, $message) === false) throw new RuntimeException('SMTP message write failed.');
            platform_smtp_expect($socket, [250]);
            @fwrite($socket, "QUIT\r\n");
            fclose($socket);

            return ['sent' => true, 'transport' => 'smtp', 'reason' => null];
        } catch (Throwable $e) {
            if (is_resource($socket)) fclose($socket);
            return ['sent' => false, 'transport' => 'smtp', 'reason' => 'smtp_rejected'];
        }
    }
}

if (!function_exists('platform_php_mail_send')) {
    function platform_php_mail_send(string $to, string $subject, string $body): array
    {
        $fromAddress = platform_mail_from_address();
        $fromName = platform_mail_from_name();
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'From: ' . $fromName . ' <' . $fromAddress . '>',
        ];
        $replyTo = platform_mail_reply_to();
        if ($replyTo !== '') $headers[] = 'Reply-To: ' . $replyTo;

        $sent = false;
        try {
            $sent = mail($to, $subject, $body, implode("\r\n", $headers));
        } catch (Throwable $e) {
            $sent = false;
        }
        return [
            'sent' => $sent,
            'transport' => 'php_mail',
            'reason' => $sent ? null : 'transport_rejected',
        ];
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
            return ['sent' => false, 'transport' => platform_mail_transport(), 'reason' => 'disabled'];
        }

        $result = platform_mail_transport() === 'smtp'
            ? platform_smtp_send($to, $subject, $body)
            : platform_php_mail_send($to, $subject, $body);

        if (($result['sent'] ?? false) !== true) {
            error_log('Platform mail transport rejected an outbound message for ' . $to . ' via ' . ($result['transport'] ?? 'unknown') . '.');
        }

        return $result;
    }
}
