<?php

declare(strict_types=1);

namespace Nanayawkumi\NykLogger\Channels;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Nanayawkumi\NykLogger\Contracts\AlertChannel;

/**
 * Resolves the set of alert channels enabled in configuration.
 */
final class ChannelManager
{
    /**
     * Map of channel name => container binding.
     *
     * @var array<string, class-string<AlertChannel>>
     */
    private const DRIVERS = [
        'mail' => BrevoMailChannel::class,
        'slack' => SlackChannel::class,
    ];

    public function __construct(
        private readonly Container $container,
        private readonly Config $config,
    ) {
    }

    /**
     * @return list<AlertChannel>
     */
    public function enabled(): array
    {
        $names = $this->config->get('nyk-logger.channels', ['mail']);

        $channels = [];

        foreach ((array) $names as $name) {
            $name = trim((string) $name);
            $driver = self::DRIVERS[$name] ?? null;

            if ($driver === null) {
                error_log('[nyk-logger] unknown channel "'.$name.'" ignored.');

                continue;
            }

            $channels[] = $this->container->make($driver);
        }

        return $channels;
    }
}
