<?php

namespace Selfauth;

/**
 * Catalogue of IndieAuth scopes with plain-language descriptions and a risk
 * level. Identity scopes (profile/email) only reveal data to the client;
 * access scopes let a client act on the owner's site through a token endpoint.
 */
class Scopes
{
    public const LOW = 'low';
    public const MEDIUM = 'medium';
    public const HIGH = 'high';

    public static function catalog(): array
    {
        return [
            'profile' => ['group' => 'identity', 'risk' => self::LOW, 'title' => 'Your public profile', 'desc' => 'Your name and photo, so the app can greet you.'],
            'email' => ['group' => 'identity', 'risk' => self::MEDIUM, 'title' => 'Your email address', 'desc' => 'Shares your email address with this app. Needs the profile scope too.'],
            'read' => ['group' => 'access', 'risk' => self::MEDIUM, 'title' => 'Read your content', 'desc' => 'Read your channels and private feeds.'],
            'follow' => ['group' => 'access', 'risk' => self::MEDIUM, 'title' => 'Manage who you follow', 'desc' => 'Follow and unfollow sites for you.'],
            'mute' => ['group' => 'access', 'risk' => self::MEDIUM, 'title' => 'Mute people', 'desc' => 'Mute and unmute accounts for you.'],
            'block' => ['group' => 'access', 'risk' => self::MEDIUM, 'title' => 'Block people', 'desc' => 'Block and unblock accounts for you.'],
            'channels' => ['group' => 'access', 'risk' => self::MEDIUM, 'title' => 'Manage channels', 'desc' => 'Create and organise your reading channels.'],
            'create' => ['group' => 'access', 'risk' => self::HIGH, 'title' => 'Publish posts', 'desc' => 'Create new posts on your website.'],
            'draft' => ['group' => 'access', 'risk' => self::MEDIUM, 'title' => 'Save drafts', 'desc' => 'Create drafts that are not public yet.'],
            'media' => ['group' => 'access', 'risk' => self::HIGH, 'title' => 'Upload files', 'desc' => 'Upload images and other files to your website.'],
            'update' => ['group' => 'access', 'risk' => self::HIGH, 'title' => 'Edit your posts', 'desc' => 'Change posts that already exist.'],
            'delete' => ['group' => 'access', 'risk' => self::HIGH, 'title' => 'Delete your posts', 'desc' => 'Permanently remove posts from your website.'],
        ];
    }

    /** Space-separated scope string -> unique, ordered list. */
    public static function parse(?string $scope): array
    {
        if ($scope === null || trim($scope) === '') {
            return [];
        }
        return array_values(array_unique(preg_split('/ +/', trim($scope))));
    }

    public static function info(string $scope): array
    {
        return self::catalog()[$scope] ?? [
            'group' => 'unknown', 'risk' => self::HIGH,
            'title' => 'Custom permission', 'desc' => 'This app made up its own permission. Selfauth does not know what it allows.',
        ];
    }

    /** Identity scopes first, then access scopes by risk, unknown last. */
    public static function sorted(array $scopes): array
    {
        $rank = ['identity' => 0, 'access' => 1, 'unknown' => 2];
        $risk = [self::LOW => 0, self::MEDIUM => 1, self::HIGH => 2];
        usort($scopes, function ($a, $b) use ($rank, $risk) {
            $ia = self::info($a);
            $ib = self::info($b);
            return [$rank[$ia['group']], $risk[$ia['risk']], $a] <=> [$rank[$ib['group']], $risk[$ib['risk']], $b];
        });
        return $scopes;
    }

    /** Whether a scope is pre-ticked on the consent screen (only low risk). */
    public static function defaultOn(string $scope): bool
    {
        return self::info($scope)['risk'] === self::LOW;
    }

    /** Owner-configurable allow-list. Empty/unset = known catalogue only. */
    public static function allowed(string $scope): bool
    {
        $env = trim((string) getenv('SELFAUTH_ALLOWED_SCOPES'));
        if ($env === '' ) {
            return isset(self::catalog()[$scope]);
        }
        if ($env === '*') {
            return true;
        }
        return in_array($scope, preg_split('/[\s,]+/', $env), true);
    }

    /**
     * Enforce: only allowed scopes, and email requires profile.
     * @return string[]
     */
    public static function normalizeGranted(array $granted): array
    {
        $granted = array_values(array_filter(array_unique($granted), [self::class, 'allowed']));
        if (in_array('email', $granted, true) && !in_array('profile', $granted, true)) {
            $granted = array_values(array_diff($granted, ['email']));
        }
        return $granted;
    }

    public static function riskSummary(array $scopes): string
    {
        $worst = self::LOW;
        foreach ($scopes as $s) {
            $r = self::info($s)['risk'];
            if ($r === self::HIGH) {
                return self::HIGH;
            }
            if ($r === self::MEDIUM) {
                $worst = self::MEDIUM;
            }
        }
        return $worst;
    }
}
