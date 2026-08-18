<?php

namespace Selfauth;

/**
 * A tiny, dependency-free JWT verifier supporting the two algorithms
 * OIDC providers actually use for ID tokens: RS256 (RSA) and ES256
 * (ECDSA P-256) -- Kanidm signs with ES256 by default and only offers
 * RS256 in an explicit "legacy crypto" mode. There is no PHP extension
 * that verifies a JWT directly, so this builds a PEM public key from the
 * JWK's raw key material and calls openssl_verify() by hand.
 */
class Jwt
{
    /**
     * @param string $jwt   The compact JWS (header.payload.signature)
     * @param array  $jwks  A JWK Set, e.g. ['keys' => [...]]
     * @return array Decoded payload claims
     * @throws \RuntimeException on any verification failure
     */
    public static function decodeAndVerify(string $jwt, array $jwks): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new \RuntimeException('Malformed JWT');
        }
        [$headerB64, $payloadB64, $sigB64] = $parts;

        $header = json_decode(self::b64urlDecode($headerB64), true);
        $payload = json_decode(self::b64urlDecode($payloadB64), true);
        $signature = self::b64urlDecode($sigB64);

        if (!is_array($header) || !is_array($payload)) {
            throw new \RuntimeException('Malformed JWT contents');
        }

        $alg = $header['alg'] ?? null;
        if (!in_array($alg, ['RS256', 'ES256'], true)) {
            throw new \RuntimeException('Unsupported JWT algorithm: ' . ($alg ?? 'none'));
        }

        $jwk = self::findKey($jwks, $header['kid'] ?? null, $alg);
        if ($jwk === null) {
            throw new \RuntimeException('No matching signing key found in JWKS for kid=' . ($header['kid'] ?? '(none)'));
        }

        $signingInput = $headerB64 . '.' . $payloadB64;
        $pem = $alg === 'RS256' ? self::rsaPemFromJwk($jwk) : self::ecPemFromJwk($jwk);

        $derSignature = $alg === 'ES256' ? self::es256SignatureToDer($signature) : $signature;

        $ok = openssl_verify($signingInput, $derSignature, $pem, OPENSSL_ALGO_SHA256);
        if ($ok !== 1) {
            throw new \RuntimeException('JWT signature verification failed');
        }

        return $payload;
    }

    private static function findKey(array $jwks, ?string $kid, string $alg): ?array
    {
        $keys = $jwks['keys'] ?? (isset($jwks['kty']) ? [$jwks] : []);
        $wantKty = $alg === 'RS256' ? 'RSA' : 'EC';

        // Prefer an exact kid match.
        if ($kid !== null) {
            foreach ($keys as $key) {
                if (($key['kid'] ?? null) === $kid) {
                    return $key;
                }
            }
        }
        // Fall back to the first key of the right type (some providers
        // publish a single signing key and omit "kid" entirely).
        foreach ($keys as $key) {
            if (($key['kty'] ?? null) === $wantKty) {
                return $key;
            }
        }
        return null;
    }

    public static function b64urlDecode(string $data): string
    {
        $data = strtr($data, '-_', '+/');
        $pad = strlen($data) % 4;
        if ($pad !== 0) {
            $data .= str_repeat('=', 4 - $pad);
        }
        $decoded = base64_decode($data, true);
        if ($decoded === false) {
            throw new \RuntimeException('Invalid base64url data');
        }
        return $decoded;
    }

    // ------------------------------------------------------------------
    // ASN.1 DER helpers (no bcmath/gmp dependency -- pure byte-string
    // manipulation, since JWK moduli/coordinates are already big-endian
    // byte strings once base64url-decoded).
    // ------------------------------------------------------------------

    private static function derLength(int $len): string
    {
        if ($len < 128) {
            return chr($len);
        }
        $bytes = ltrim(pack('N', $len), "\x00");
        return chr(0x80 | strlen($bytes)) . $bytes;
    }

    private static function derInteger(string $bytes): string
    {
        // Strip leading zero bytes, then re-add exactly one if the high
        // bit is set (DER INTEGER is signed; a leading 0x00 keeps it
        // interpreted as positive).
        $bytes = ltrim($bytes, "\x00");
        if ($bytes === '') {
            $bytes = "\x00";
        }
        if (ord($bytes[0]) & 0x80) {
            $bytes = "\x00" . $bytes;
        }
        return "\x02" . self::derLength(strlen($bytes)) . $bytes;
    }

    private static function derSequence(string $contents): string
    {
        return "\x30" . self::derLength(strlen($contents)) . $contents;
    }

    private static function derBitString(string $contents): string
    {
        // Leading 0x00 = zero unused bits.
        return "\x03" . self::derLength(strlen($contents) + 1) . "\x00" . $contents;
    }

    private static function pem(string $der, string $label): string
    {
        return "-----BEGIN {$label}-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END {$label}-----\n";
    }

    /**
     * Build a PEM SubjectPublicKeyInfo for an RSA key from a JWK's
     * base64url-encoded modulus (n) and exponent (e).
     */
    public static function rsaPemFromJwk(array $jwk): string
    {
        if (($jwk['kty'] ?? null) !== 'RSA' || empty($jwk['n']) || empty($jwk['e'])) {
            throw new \RuntimeException('Not a usable RSA JWK');
        }
        $n = self::b64urlDecode($jwk['n']);
        $e = self::b64urlDecode($jwk['e']);

        $rsaPublicKey = self::derSequence(self::derInteger($n) . self::derInteger($e));

        // rsaEncryption OID: 1.2.840.113549.1.1.1
        $algId = self::derSequence("\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01" . "\x05\x00");
        $spki = self::derSequence($algId . self::derBitString($rsaPublicKey));

        return self::pem($spki, 'PUBLIC KEY');
    }

    /**
     * Build a PEM SubjectPublicKeyInfo for an EC (P-256 / secp256r1) key
     * from a JWK's base64url-encoded x/y coordinates.
     */
    public static function ecPemFromJwk(array $jwk): string
    {
        if (($jwk['kty'] ?? null) !== 'EC' || empty($jwk['x']) || empty($jwk['y'])) {
            throw new \RuntimeException('Not a usable EC JWK');
        }
        $crv = $jwk['crv'] ?? 'P-256';
        if ($crv !== 'P-256') {
            throw new \RuntimeException('Unsupported EC curve: ' . $crv);
        }
        $x = str_pad(self::b64urlDecode($jwk['x']), 32, "\x00", STR_PAD_LEFT);
        $y = str_pad(self::b64urlDecode($jwk['y']), 32, "\x00", STR_PAD_LEFT);
        $point = "\x04" . $x . $y; // uncompressed point

        // id-ecPublicKey OID: 1.2.840.10045.2.1, prime256v1 OID: 1.2.840.10045.3.1.7
        $algId = self::derSequence(
            "\x06\x07\x2a\x86\x48\xce\x3d\x02\x01" .
            "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07"
        );
        $spki = self::derSequence($algId . self::derBitString($point));

        return self::pem($spki, 'PUBLIC KEY');
    }

    /**
     * JOSE ES256 signatures are raw R||S (32 bytes each). openssl_verify()
     * for EC keys expects a DER-encoded ECDSA-Sig-Value SEQUENCE instead.
     */
    public static function es256SignatureToDer(string $rawSignature): string
    {
        if (strlen($rawSignature) !== 64) {
            throw new \RuntimeException('Unexpected ES256 signature length');
        }
        $r = substr($rawSignature, 0, 32);
        $s = substr($rawSignature, 32, 32);
        return self::derSequence(self::derInteger($r) . self::derInteger($s));
    }
}
