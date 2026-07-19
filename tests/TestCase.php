<?php

declare(strict_types=1);

namespace Nanayawkumi\NykLogger\Tests;

use Nanayawkumi\NykLogger\NykLoggerServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            NykLoggerServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('app.name', 'Test App');

        $app['config']->set('nyk-logger', [
            'enabled' => true,
            // Testbench boots in the "testing" environment, so allow it here.
            'environments' => ['testing'],
            'levels' => ['error', 'critical', 'alert', 'emergency'],
            'channels' => ['mail'],
            'mail' => [
                'api_key' => 'test-brevo-key',
                'to_email' => 'alerts@example.com',
                'to_name' => 'System Administrator',
                'from_email' => 'noreply@example.com',
                'from_name' => 'Test App',
            ],
            'slack' => [
                'webhook_url' => 'https://hooks.slack.com/services/T000/B000/XXXX',
            ],
            'queue' => [
                'enabled' => false,
                'connection' => null,
                'queue' => null,
            ],
            'cooldown' => 30,
            'rate_limit' => [
                'max' => 20,
                'decay' => 60,
            ],
            'ignore_exceptions' => [],
            'ignore_messages' => [],
            'redact' => [
                'keys' => ['password', 'token', 'authorization'],
                'patterns' => ['/(?i)(bearer\s+)[a-z0-9._\-]+/'],
            ],
        ]);
    }
}
