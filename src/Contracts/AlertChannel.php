<?php

declare(strict_types=1);

namespace Nanayawkumi\NykLogger\Contracts;

use Nanayawkumi\NykLogger\Support\AlertPayload;

interface AlertChannel
{
    /**
     * Deliver the alert. Implementations MUST NOT throw: a downed provider
     * can never be allowed to crash the host application. Return whether the
     * delivery succeeded.
     */
    public function send(AlertPayload $payload): bool;

    /**
     * The channel's config key / identifier (e.g. "mail", "slack").
     */
    public function name(): string;
}
