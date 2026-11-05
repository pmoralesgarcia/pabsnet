<?php

namespace Selfauth;

/**
 * Fetches a client_id URL and extracts what the consent screen needs:
 * name, logo, site URL and published redirect URIs. Supports JSON client
 * metadata and h-app / <link rel="redirect_uri"> in HTML.
 */
class ClientDiscovery
{
    /** @return array{name:?string,logo:?string,url:?string,redirect_uris:string[],fetched:bool} */
    public static function discover(string $clientId): array
    {
        $empty = ['name' => null, 'logo' => null, 'url' => null, 'redirect_uris' => [], 'fetched' => false];
        if (ClientId::isLoopback($clientId)) {
            return $empty; // never fetch loopback: SSRF, and nothing useful there
        }
        $res = SafeHttp::get($clientId, 'application/json, text/html;q=0.9', 262144, 5);
        if (!$res || $res['status'] >= 400) {
            return $empty;
        }
        $out = $empty;
        $out['fetched'] = true;
        $type = strtolower($res['type']);
        $final = $res['url'];

        if (str_contains($type, 'json')) {
            $j = json_decode($res['body'], true, 5);
            if (is_array($j)) {
                // Spec: client_id in the document must match.
                if (isset($j['client_id']) && $j['client_id'] !== $clientId) {
                    return $empty;
                }
                $out['name'] = self::str($j['client_name'] ?? null, 100);
                $out['url'] = self::safeUrl($j['client_uri'] ?? null);
                $out['logo'] = self::safeUrl(isset($j['logo_uri']) ? SafeHttp::resolveUrl($final, (string) $j['logo_uri']) : null);
                foreach ((array) ($j['redirect_uris'] ?? []) as $r) {
                    if (is_string($r) && ClientId::validRedirectUri($r)) {
                        $out['redirect_uris'][] = $r;
                    }
                }
            }
            return $out;
        }

        if (!class_exists(\DOMDocument::class) || trim($res['body']) === '') {
            return $out;
        }
        $dom = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>' . $res['body'], LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $xp = new \DOMXPath($dom);

        foreach ($xp->query('//link[@rel][@href]') as $l) {
            $rels = preg_split('/\s+/', strtolower($l->getAttribute('rel')));
            if (in_array('redirect_uri', $rels, true)) {
                $u = SafeHttp::resolveUrl($final, $l->getAttribute('href'));
                if (ClientId::validRedirectUri($u)) {
                    $out['redirect_uris'][] = $u;
                }
            }
        }
        $happ = $xp->query('//*[contains(concat(" ",normalize-space(@class)," ")," h-app ") or contains(concat(" ",normalize-space(@class)," ")," h-x-app ")]')->item(0);
        if ($happ) {
            $n = $xp->query('.//*[contains(concat(" ",normalize-space(@class)," ")," p-name ")]', $happ)->item(0);
            $out['name'] = self::str($n ? $n->textContent : null, 100);
            $lg = $xp->query('.//img[contains(concat(" ",normalize-space(@class)," ")," u-logo ")]', $happ)->item(0);
            if ($lg) {
                $out['logo'] = self::safeUrl(SafeHttp::resolveUrl($final, $lg->getAttribute('src')));
            }
            $u = $xp->query('.//a[contains(concat(" ",normalize-space(@class)," ")," u-url ")]', $happ)->item(0);
            if ($u) {
                $out['url'] = self::safeUrl(SafeHttp::resolveUrl($final, $u->getAttribute('href')));
            }
        }
        if (!$out['name']) {
            $t = $xp->query('//title')->item(0);
            $out['name'] = self::str($t ? $t->textContent : null, 100);
        }
        return $out;
    }

    private static function str($v, int $max): ?string
    {
        if (!is_string($v)) {
            return null;
        }
        $v = trim(preg_replace('/\s+/u', ' ', strip_tags($v)));
        return $v === '' ? null : mb_substr($v, 0, $max);
    }

    private static function safeUrl($v): ?string
    {
        if (!is_string($v) || !preg_match('#^https?://#i', $v) || strlen($v) > 2048) {
            return null;
        }
        return $v;
    }
}
