<?php

declare(strict_types=1);

namespace Nanayawkumi\NykLogger\Events;

use Nanayawkumi\NykLogger\Support\AlertPayload;

/**
 * Fired after an alert has been fanned out to every enabled channel, carrying
 * the per-channel delivery result (channel name => success).
 */
final class AlertSent
{
    /**
     * @param  array<string, bool>  $results
     */
    public function __construct(
        public readonly AlertPayload $payload,
        public readonly array $results,
    ) {
    }
}
