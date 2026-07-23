<?php

namespace Selfauth;

class Support
{
    public static function errorPage(string $header, string $body, string $http = '400 Bad Request'): void
    {
        $protocol = $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0';
        header($protocol . ' ' . $http);
        $header = htmlspecialchars($header, ENT_QUOTES);
        $body = htmlspecialchars($body, ENT_QUOTES);
        $html = <<<HTML
<!doctype html>
<html>
    <head>
        <meta charset="utf-8">
        <style>
            .error{width:100%;text-align:center;margin-top:10%;font-family:sans-serif;}
        </style>
        <title>Error: $header</title>
    </head>
    <body>
        <div class="error">
            <h1>Error: $header</h1>
            <p>$body</p>
        </div>
    </body>
</html>
HTML;
        die($html);
    }

    public static function filterInputRegexp(int $type, string $variable, string $regexp, ?int $flags = null)
    {
        $options = ['options' => ['regexp' => $regexp]];
        if ($flags !== null) {
            $options['flags'] = $flags;
        }
        return filter_input($type, $variable, FILTER_VALIDATE_REGEXP, $options);
    }

    public static function getQValue(string $mime, string $accept): float
    {
        $fulltype = preg_replace('@^([^/]+\/).+$@', '$1*', $mime);
        $regex = implode('', [
            '/(?<=^|,)\s*(\*\/\*|',
            preg_quote($fulltype, '/'),
            '|',
            preg_quote($mime, '/'),
            ')\s*(?:[^,]*?;\s*q\s*=\s*([0-9.]+))?\s*(?:,|$)/',
        ]);
        preg_match_all($regex, $accept, $matches);
        $types = array_combine($matches[1], $matches[2]);
        if (array_key_exists($mime, $types)) {
            $q = $types[$mime];
        } elseif (array_key_exists($fulltype, $types)) {
            $q = $types[$fulltype];
        } elseif (array_key_exists('*/*', $types)) {
            $q = $types['*/*'];
        } else {
            return 0.0;
        }
        return $q === '' ? 1.0 : (float) $q;
    }

    /**
     * Determine the client IP. Only trusts X-Forwarded-For when
     * SELFAUTH_TRUST_PROXY=true (set this when running behind a reverse
     * proxy / the bundled Docker setup terminates TLS itself, e.g. Caddy
     * or Traefik in front of the container).
     */
    public static function clientIp(): string
    {
        $trustProxy = filter_var(getenv('SELFAUTH_TRUST_PROXY') ?: 'false', FILTER_VALIDATE_BOOLEAN);
        if ($trustProxy && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($parts[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public static function clientInfo(string $clientId): ?array
    {
        if (!function_exists('curl_init')) {
            return null;
        }
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $clientId);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($curl, CURLOPT_TIMEOUT_MS, 4000);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT_MS, 2000);
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Accept: application/json']);
        $body = curl_exec($curl);
        curl_close($curl);
        if ($body === false) {
            return null;
        }
        $info = json_decode($body, true, 3);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        return $info;
    }

    public static function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}
