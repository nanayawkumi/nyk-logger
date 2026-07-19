<?php

declare(strict_types=1);

namespace Nanayawkumi\NykLogger\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\InteractsWithQueue;
use Nanayawkumi\NykLogger\Channels\ChannelManager;
use Nanayawkumi\NykLogger\Events\AlertSent;
use Nanayawkumi\NykLogger\Support\AlertPayload;
use Throwable;

/**
 * Fans a captured alert out to every enabled channel. Queued by default so
 * the failing request never waits on outbound API calls.
 */
final class SendAlertJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public readonly AlertPayload $payload,
    ) {
    }

    public function handle(ChannelManager $channels, Dispatcher $events): void
    {
        $results = [];

        foreach ($channels->enabled() as $channel) {
            try {
                $results[$channel->name()] = $channel->send($this->payload);
            } catch (Throwable $e) {
                // A single channel failing must not abort the others.
                $results[$channel->name()] = false;
                error_log('[nyk-logger] channel "'.$channel->name().'" threw: '.$e->getMessage());
            }
        }

        $events->dispatch(new AlertSent($this->payload, $results));
    }
}
