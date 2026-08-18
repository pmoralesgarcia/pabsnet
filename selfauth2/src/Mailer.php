<?php

namespace Selfauth;

/**
 * A minimal SMTP client, written from scratch since this environment has
 * no Composer/PHPMailer dependency available. Supports plaintext, implicit
 * TLS (smtps, port 465 style), and STARTTLS, plus AUTH LOGIN/PLAIN.
 * Good enough for "send a short notification email", not a general
 * mail library.
 */
class Mailer
{
    public static function isConfigured(): bool
    {
        return (bool) getenv('SELFAUTH_SMTP_HOST') && (bool) getenv('SELFAUTH_NOTIFY_EMAIL');
    }

    /**
     * @throws \RuntimeException on any SMTP-level failure
     */
    public static function send(string $subject, string $body): void
    {
        $host = (string) getenv('SELFAUTH_SMTP_HOST');
        $port = (int) (getenv('SELFAUTH_SMTP_PORT') ?: 587);
        $user = getenv('SELFAUTH_SMTP_USER') ?: null;
        $pass = getenv('SELFAUTH_SMTP_PASS') ?: null;
        $encryption = strtolower(getenv('SELFAUTH_SMTP_ENCRYPTION') ?: 'tls'); // tls | ssl | none
        $from = getenv('SELFAUTH_SMTP_FROM') ?: ($user ?: 'selfauth@localhost');
        $to = (string) getenv('SELFAUTH_NOTIFY_EMAIL');
        $timeout = 10;

        $transport = $encryption === 'ssl' ? 'ssl://' : '';
        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client(
            $transport . $host . ':' . $port,
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT
        );
        if ($socket === false) {
            throw new \RuntimeException("Could not connect to SMTP server: $errstr ($errno)");
        }
        stream_set_timeout($socket, $timeout);

        try {
            self::expect($socket, 220);
            self::ehlo($socket, $host);

            if ($encryption === 'tls') {
                self::command($socket, 'STARTTLS', 220);
                if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \RuntimeException('STARTTLS negotiation failed');
                }
                self::ehlo($socket, $host);
            }

            if ($user !== null && $pass !== null) {
                self::command($socket, 'AUTH LOGIN', 334);
                self::command($socket, base64_encode($user), 334);
                self::command($socket, base64_encode($pass), 235);
            }

            self::command($socket, 'MAIL FROM:<' . self::sanitizeAddress($from) . '>', 250);
            self::command($socket, 'RCPT TO:<' . self::sanitizeAddress($to) . '>', 250);
            self::command($socket, 'DATA', 354);

            $headers = [
                'From: ' . $from,
                'To: ' . $to,
                'Subject: ' . self::encodeHeader($subject),
                'Date: ' . date('r'),
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=UTF-8',
                'Content-Transfer-Encoding: 8bit',
            ];
            // Dot-stuff any line that starts with a lone "." per RFC 5321.
            $escapedBody = preg_replace('/^\./m', '..', $body);
            $message = implode("\r\n", $headers) . "\r\n\r\n" . $escapedBody . "\r\n.";
            self::command($socket, $message, 250);

            self::command($socket, 'QUIT', 221);
        } finally {
            fclose($socket);
        }
    }

    private static function ehlo($socket, string $host): void
    {
        self::command($socket, 'EHLO ' . (parse_url($host, PHP_URL_HOST) ?: 'selfauth.local'), 250, true);
    }

    private static function sanitizeAddress(string $address): string
    {
        // Defend against SMTP header/command injection via CR/LF.
        return str_replace(["\r", "\n"], '', $address);
    }

    private static function encodeHeader(string $subject): string
    {
        $subject = str_replace(["\r", "\n"], ' ', $subject);
        if (preg_match('/[^\x20-\x7E]/', $subject)) {
            return '=?UTF-8?B?' . base64_encode($subject) . '?=';
        }
        return $subject;
    }

    private static function command($socket, string $line, int $expectCode, bool $multiline = false): string
    {
        fwrite($socket, $line . "\r\n");
        return self::expect($socket, $expectCode, $multiline);
    }

    private static function expect($socket, int $expectCode, bool $multiline = false): string
    {
        $response = '';
        do {
            $line = fgets($socket, 1024);
            if ($line === false) {
                throw new \RuntimeException('Unexpected end of stream talking to SMTP server');
            }
            $response .= $line;
            $continues = isset($line[3]) && $line[3] === '-';
        } while ($continues);

        $code = (int) substr($response, 0, 3);
        if ($code !== $expectCode) {
            throw new \RuntimeException("SMTP error: expected $expectCode, got: " . trim($response));
        }
        return $response;
    }
}
