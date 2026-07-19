<?php

declare(strict_types=1);

namespace Nanayawkumi\NykLogger\Events;

/**
 * Fired when an alert is filtered out before dispatch. The $reason is one of:
 * "ignored", "cooldown", "rate_limit", or "cancelled".
 */
final readonly class AlertSuppressed
{
    public function __construct(
        public string $reason,
        public string $level,
        public string $message,
        public ?string $fingerprint = null,
        public ?string $exceptionClass = null,
    ) {
    }
}
