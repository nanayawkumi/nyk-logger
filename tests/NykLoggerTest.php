<?php

declare(strict_types=1);

namespace Nanayawkumi\NykLogger\Tests;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Nanayawkumi\NykLogger\Jobs\SendAlertJob;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

final class NykLoggerTest extends TestCase
{
    #[Test]
    public function it_sends_a_brevo_email_when_an_error_is_logged(): void
    {
        Http::fake(['api.brevo.com/*' => Http::response(['messageId' => 'abc'], 201)]);

        Log::error('Something broke badly');

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://api.brevo.com/v3/smtp/email'
                && $request->method() === 'POST'
                && $request->hasHeader('api-key', 'test-brevo-key')
                && $request['to'][0]['email'] === 'alerts@example.com'
                && $request['sender']['email'] === 'noreply@example.com'
                && str_contains((string) $request['subject'], 'ERROR');
        });
    }

    #[Test]
    public function it_includes_exception_details_in_the_payload(): void
    {
        Http::fake(['api.brevo.com/*' => Http::response([], 201)]);

        Log::error('Boom', ['exception' => new RuntimeException('kaboom', 42)]);

        Http::assertSent(function (Request $request): bool {
            $html = (string) $request['htmlContent'];

            return str_contains($html, 'kaboom') && str_contains($html, 'RuntimeException');
        });
    }

    #[Test]
    public function it_suppresses_duplicate_errors_within_the_cooldown_window(): void
    {
        Http::fake(['api.brevo.com/*' => Http::response([], 201)]);

        $exception = new RuntimeException('repeated', 7);

        Log::error('first', ['exception' => $exception]);
        Log::error('second', ['exception' => $exception]);

        Http::assertSentCount(1);
    }

    #[Test]
    public function it_ignores_non_alert_log_levels(): void
    {
        Http::fake();

        Log::info('just fyi');
        Log::debug('noisy');
        Log::warning('careful');

        Http::assertNothingSent();
    }

    #[Test]
    public function it_does_not_crash_the_host_when_a_channel_is_down(): void
    {
        Http::fake(fn () => throw new ConnectionException('network down'));

        Log::critical('database exploded');

        $this->assertTrue(true);
    }

    #[Test]
    public function it_enforces_the_global_rate_cap_across_distinct_errors(): void
    {
        config()->set('nyk-logger.rate_limit.max', 1);
        Http::fake(['api.brevo.com/*' => Http::response([], 201)]);

        Log::error('distinct error one');
        Log::error('distinct error two');

        Http::assertSentCount(1);
    }

    #[Test]
    public function it_skips_ignored_exception_classes(): void
    {
        config()->set('nyk-logger.ignore_exceptions', [RuntimeException::class]);
        Http::fake();

        Log::error('ignored', ['exception' => new RuntimeException('nope')]);

        Http::assertNothingSent();
    }

    #[Test]
    public function it_skips_messages_matching_ignore_patterns(): void
    {
        config()->set('nyk-logger.ignore_messages', ['/Route \[.*\] not defined/']);
        Http::fake();

        Log::error('Route [login] not defined');

        Http::assertNothingSent();
    }

    #[Test]
    public function it_redacts_sensitive_context_before_sending(): void
    {
        Http::fake(['api.brevo.com/*' => Http::response([], 201)]);

        Log::error('leaky', [
            'password' => 'super-secret-value',
            'note' => 'Authorization: Bearer abcdef123456',
        ]);

        Http::assertSent(function (Request $request): bool {
            $html = (string) $request['htmlContent'];

            return str_contains($html, '[REDACTED]')
                && ! str_contains($html, 'super-secret-value')
                && ! str_contains($html, 'abcdef123456');
        });
    }

    #[Test]
    public function it_delivers_to_the_slack_channel(): void
    {
        config()->set('nyk-logger.channels', ['slack']);
        Http::fake(['hooks.slack.com/*' => Http::response('ok', 200)]);

        Log::error('slack me', ['exception' => new RuntimeException('slacked', 1)]);

        Http::assertSent(function (Request $request): bool {
            return str_contains($request->url(), 'hooks.slack.com')
                && isset($request['blocks'])
                && str_contains((string) $request['text'], 'slacked');
        });
    }

    #[Test]
    public function it_queues_the_alert_when_the_queue_is_enabled(): void
    {
        config()->set('nyk-logger.queue.enabled', true);
        Queue::fake();

        Log::error('queue me');

        Queue::assertPushed(SendAlertJob::class);
    }
}
