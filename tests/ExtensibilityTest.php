<?php

declare(strict_types=1);

namespace Nanayawkumi\NykLogger\Tests;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Nanayawkumi\NykLogger\AlertManager;
use Nanayawkumi\NykLogger\Events\AlertSending;
use Nanayawkumi\NykLogger\Events\AlertSent;
use Nanayawkumi\NykLogger\Events\AlertSuppressed;
use Nanayawkumi\NykLogger\Facades\NykLogger;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

final class ExtensibilityTest extends TestCase
{
    #[Test]
    public function the_facade_reports_a_throwable(): void
    {
        Http::fake(['api.brevo.com/*' => Http::response([], 201)]);

        NykLogger::report(new RuntimeException('manual boom', 9));

        Http::assertSent(fn (Request $r): bool => str_contains((string) $r['htmlContent'], 'manual boom'));
    }

    #[Test]
    public function the_facade_raises_a_plain_alert(): void
    {
        Http::fake(['api.brevo.com/*' => Http::response([], 201)]);

        NykLogger::alert('custom message', 'critical');

        Http::assertSent(fn (Request $r): bool => str_contains((string) $r['subject'], 'CRITICAL'));
    }

    #[Test]
    public function manual_reports_bypass_the_level_filter(): void
    {
        config()->set('nyk-logger.levels', ['emergency']);
        Http::fake(['api.brevo.com/*' => Http::response([], 201)]);

        // "error" is not in the level filter, but a manual report still fires.
        NykLogger::alert('forced', 'error');

        Http::assertSentCount(1);
    }

    #[Test]
    public function it_fires_the_alert_sending_and_sent_events(): void
    {
        Event::fake([AlertSending::class, AlertSent::class]);
        Http::fake(['api.brevo.com/*' => Http::response([], 201)]);

        Log::error('watch me');

        Event::assertDispatched(AlertSending::class);
        Event::assertDispatched(AlertSent::class, function (AlertSent $e): bool {
            return ($e->results['mail'] ?? null) === true;
        });
    }

    #[Test]
    public function a_listener_can_cancel_delivery_via_alert_sending(): void
    {
        Http::fake();
        Event::listen(AlertSending::class, static fn (): bool => false);

        Log::error('should be cancelled');

        Http::assertNothingSent();
    }

    #[Test]
    public function it_fires_alert_suppressed_with_a_reason(): void
    {
        Event::fake([AlertSuppressed::class]);
        config()->set('nyk-logger.ignore_messages', ['/nope/']);

        Log::error('nope not this one');

        Event::assertDispatched(AlertSuppressed::class, fn (AlertSuppressed $e): bool => $e->reason === 'ignored');
    }

    #[Test]
    public function a_custom_fingerprint_resolver_controls_grouping(): void
    {
        Http::fake(['api.brevo.com/*' => Http::response([], 201)]);

        // Group two distinct messages under one fingerprint -> second suppressed.
        app(AlertManager::class)->fingerprintUsing(static fn (): string => 'grouped');

        Log::error('first distinct');
        Log::error('second distinct');

        Http::assertSentCount(1);
    }
}
