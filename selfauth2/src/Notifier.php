<?php

namespace Selfauth;

class Notifier
{
    /** @return string[] */
    private static function subscribedEvents(): array
    {
        $csv = getenv('SELFAUTH_NOTIFY_EVENTS') ?: '';
        return array_values(array_filter(array_map('trim', explode(',', $csv))));
    }

    public static function isEnabled(): bool
    {
        return Mailer::isConfigured() && self::subscribedEvents() !== [];
    }

    public static function notify(string $event, string $summary, array $context = []): void
    {
        if (!self::isEnabled() || !in_array($event, self::subscribedEvents(), true)) {
            return;
        }

        $lines = ["Event: $event", "When: " . gmdate('c'), '', $summary, ''];
        foreach ($context as $key => $value) {
            $lines[] = ucfirst(str_replace('_', ' ', $key)) . ': ' . (is_scalar($value) ? (string) $value : json_encode($value));
        }
        $lines[] = '';
        $lines[] = '-- Selfauth';

        try {
            Mailer::send('[Selfauth] ' . $summary, implode("\n", $lines));
        } catch (\Throwable $e) {
            // Email delivery failures must never break the request that
            // triggered them (a login, a webmention, etc). Best effort only.
            error_log('Selfauth notifier: failed to send email: ' . $e->getMessage());
        }
    }
}
