<?php

namespace Selfauth;

/** Plain HTTP for the storage clients, where the transport is injected in tests. */
class HttpClient
{
    /** @return array{0:int, 1:string} Status and body. Throws if the request never completed. */
    public static function send(string $method, string $url, array $headers, ?string $body): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_FOLLOWLOCATION => false, // Never carry credentials to another host.
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $response = curl_exec($ch);
        if ($response === false) {
            throw new \RuntimeException('Request failed: ' . curl_error($ch));
        }
        return [(int) curl_getinfo($ch, CURLINFO_HTTP_CODE), (string) $response];
    }
}
