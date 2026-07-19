<?php

declare(strict_types=1);

namespace Nanayawkumi\NykLogger\Tests;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

final class TestCommandTest extends TestCase
{
    #[Test]
    public function it_sends_a_sample_alert_through_enabled_channels(): void
    {
        Http::fake(['api.brevo.com/*' => Http::response([], 201)]);

        $this->artisan('nyk-logger:test')->assertSuccessful();

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.brevo.com/v3/smtp/email');
    }

    #[Test]
    public function it_reports_failure_when_a_channel_cannot_deliver(): void
    {
        Http::fake(['api.brevo.com/*' => Http::response('bad key', 401)]);

        $this->artisan('nyk-logger:test')->assertFailed();
    }
}
