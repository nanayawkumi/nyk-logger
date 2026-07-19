<?php

declare(strict_types=1);

namespace Nanayawkumi\NykLogger\Events;

use Nanayawkumi\NykLogger\Support\AlertPayload;

/**
 * Fired synchronously just before an alert is dispatched. A listener may
 * return false to cancel delivery.
 */
final readonly class AlertSending
{
    public function __construct(
        public AlertPayload $payload,
    ) {
    }
}
