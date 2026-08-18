<?php

namespace Selfauth;

/**
 * A tiny synchronous event bus. Call fire() from wherever something
 * noteworthy happens (a sign-in, a webmention, a block); it takes care
 * of both webhook delivery and email notification, so call sites don't
 * need to know about either.
 */
class EventBus
{
    public static function fire(\PDO $pdo, string $event, array $payload, string $summary): void
    {
        try {
            (new Webhook($pdo))->dispatch($event, $payload);
        } catch (\Throwable $e) {
            error_log('Selfauth webhook dispatch failed: ' . $e->getMessage());
        }

        Notifier::notify($event, $summary, $payload);
    }
}
