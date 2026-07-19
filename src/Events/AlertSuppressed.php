<?php

declare(strict_types=1);

namespace Nanayawkumi\NykLogger\Events;

/**
 * Fired when an alert is filtered out before dispatch. The $reason is one of:
 * "ignored", "cooldown", "rate_limit", or "cancelled".
 */
final class AlertSuppressed
{
    public function __construct(
        public readonly string $reason,
        public readonly string $level,
        public readonly string $message,
        public readonly ?string $fingerprint = null,
        public readonly ?string $exceptionClass = null,
    ) {
    }
}
