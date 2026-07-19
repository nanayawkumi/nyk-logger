<?php

declare(strict_types=1);

namespace Nanayawkumi\NykLogger\Events;

use Nanayawkumi\NykLogger\Support\AlertPayload;

/**
 * Fired synchronously just before an alert is dispatched. A listener may
 * return false to cancel delivery.
 */
final class AlertSending
{
    public function __construct(
        public readonly AlertPayload $payload,
    ) {
    }
}
