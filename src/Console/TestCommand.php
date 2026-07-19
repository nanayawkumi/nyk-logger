<?php

declare(strict_types=1);

namespace Nanayawkumi\NykLogger\Console;

use Illuminate\Console\Command;
use Nanayawkumi\NykLogger\Channels\ChannelManager;
use Nanayawkumi\NykLogger\Support\AlertPayload;
use Throwable;

/**
 * Fires a sample alert through every enabled channel so operators can verify
 * their credentials and routing end-to-end. Bypasses cooldown/rate/ignore
 * gating since it is an explicit, intentional test.
 */
final class TestCommand extends Command
{
    protected $signature = 'nyk-logger:test';

    protected $description = 'Send a sample nyk-logger alert through every enabled channel';

    public function handle(ChannelManager $channels): int
    {
        $enabled = $channels->enabled();

        if ($enabled === []) {
            $this->components->error('No channels are enabled. Set NYK_LOGGER_CHANNELS or the "channels" config.');

            return self::FAILURE;
        }

        $payload = $this->samplePayload();
        $failures = 0;

        foreach ($enabled as $channel) {
            try {
                $ok = $channel->send($payload);
            } catch (Throwable $e) {
                $ok = false;
                $this->components->error(sprintf('[%s] threw: %s', $channel->name(), $e->getMessage()));
            }

            if ($ok) {
                $this->components->info(sprintf('[%s] test alert sent successfully.', $channel->name()));
            } else {
                $failures++;
                $this->components->warn(sprintf('[%s] failed to send (check config / provider status).', $channel->name()));
            }
        }

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function samplePayload(): AlertPayload
    {
        $appName = (string) config('app.name', 'Application');

        return new AlertPayload(
            level: 'error',
            title: 'nyk-logger test alert',
            message: 'This is a test alert triggered via `php artisan nyk-logger:test`.',
            environment: (string) app()->environment(),
            appName: $appName,
            exceptionClass: \RuntimeException::class,
            file: __FILE__,
            line: __LINE__,
            code: '0',
            trace: "#0 {main}\n(sample stack trace)",
            url: 'N/A (console/CLI)',
            method: 'N/A',
            ip: null,
            userAgent: null,
            userId: null,
            context: ['log' => ['note' => 'sample context']],
            timestamp: now()->toDateTimeString(),
            fingerprint: md5('nyk-logger:test'),
        );
    }
}
