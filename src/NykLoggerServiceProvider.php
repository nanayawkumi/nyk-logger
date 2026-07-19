<?php

declare(strict_types=1);

namespace Nanayawkumi\NykLogger;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Nanayawkumi\NykLogger\Channels\BrevoMailChannel;
use Nanayawkumi\NykLogger\Channels\ChannelManager;
use Nanayawkumi\NykLogger\Channels\SlackChannel;
use Nanayawkumi\NykLogger\Console\TestCommand;
use Nanayawkumi\NykLogger\Support\Redactor;

final class NykLoggerServiceProvider extends ServiceProvider
{
    private const string CONFIG_KEY = 'nyk-logger';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/nyk-logger.php', self::CONFIG_KEY);

        $this->app->singleton(Redactor::class, static function ($app): Redactor {
            $redact = $app['config']->get(self::CONFIG_KEY . '.redact', []);

            return new Redactor(
                keys: (array) ($redact['keys'] ?? []),
                patterns: (array) ($redact['patterns'] ?? []),
            );
        });

        $this->app->singleton(ChannelManager::class, static fn($app): ChannelManager => new ChannelManager(
            container: $app,
            config: $app['config'],
        ));

        $this->app->singleton(AlertManager::class, static fn($app): AlertManager => new AlertManager(
            app: $app,
            config: $app['config'],
            redactor: $app->make(Redactor::class),
        ));

        $this->app->alias(AlertManager::class, 'nyk-logger');

        $this->registerChannels();
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/Views', 'nyk-logger');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/nyk-logger.php' => $this->app->configPath('nyk-logger.php'),
            ], 'nyk-logger-config');

            $this->publishes([
                __DIR__ . '/Views' => $this->app->resourcePath('views/vendor/nyk-logger'),
            ], 'nyk-logger-views');

            $this->commands([TestCommand::class]);

            $this->validateConfig();
        }

        if (! $this->shouldListen()) {
            return;
        }

        $manager = $this->app->make(AlertManager::class);

        Log::listen(static function (MessageLogged $log) use ($manager): void {
            $manager->capture($log->level, $log->message, $log->context);
        });
    }

    private function registerChannels(): void
    {
        $this->app->bind(BrevoMailChannel::class, static function ($app): BrevoMailChannel {
            $mail = $app['config']->get(self::CONFIG_KEY . '.mail', []);

            return new BrevoMailChannel(
                http: $app->make(HttpFactory::class),
                view: $app->make(ViewFactory::class),
                apiKey: $mail['api_key'] ?? null,
                toEmail: $mail['to_email'] ?? null,
                toName: (string) ($mail['to_name'] ?? 'System Administrator'),
                fromEmail: $mail['from_email'] ?? null,
                fromName: (string) ($mail['from_name'] ?? 'NYK Logger'),
            );
        });

        $this->app->bind(SlackChannel::class, static function ($app): SlackChannel {
            $slack = $app['config']->get(self::CONFIG_KEY . '.slack', []);

            return new SlackChannel(
                http: $app->make(HttpFactory::class),
                webhookUrl: $slack['webhook_url'] ?? null,
            );
        });
    }

    /**
     * Warn (once, at console/boot time) about missing configuration for any
     * enabled channel so misconfiguration surfaces during deploys/commands
     * instead of silently failing at send time.
     */
    private function validateConfig(): void
    {
        $config = $this->app->make('config')->get(self::CONFIG_KEY);

        if (! ($config['enabled'] ?? false)) {
            return;
        }

        $channels = (array) ($config['channels'] ?? []);
        $problems = [];

        if (in_array('mail', $channels, true)) {
            $mail = $config['mail'] ?? [];

            foreach (
                [
                    'api_key' => 'NYK_LOGGER_API_KEY',
                    'to_email' => 'NYK_LOGGER_EMAIL',
                    'from_email' => 'NYK_LOGGER_FROM_EMAIL',
                ] as $key => $env
            ) {
                if (empty($mail[$key])) {
                    $problems[] = "mail.{$key} ({$env})";
                }
            }
        }

        if (in_array('slack', $channels, true) && empty($config['slack']['webhook_url'])) {
            $problems[] = 'slack.webhook_url (NYK_LOGGER_SLACK_WEBHOOK)';
        }

        if ($problems !== []) {
            $this->app->make('log')->warning(
                '[nyk-logger] Missing configuration: ' . implode(', ', $problems)
                    . '. Alerts on the affected channel(s) will be skipped.'
            );
        }
    }

    /**
     * Determine whether the global listener should be registered at all.
     */
    private function shouldListen(): bool
    {
        $config = $this->app->make('config')->get(self::CONFIG_KEY);

        if (! ($config['enabled'] ?? false)) {
            return false;
        }

        $environments = $config['environments'] ?? [];

        return $environments !== [] && App::environment($environments);
    }
}