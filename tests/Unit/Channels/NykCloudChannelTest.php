<?php

declare(strict_types=1);

namespace Nanayawkumi\NykLogger\Tests\Unit\Channels;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Nanayawkumi\NykLogger\Channels\NykCloudChannel;
use Nanayawkumi\NykLogger\Support\AlertPayload;
use Nanayawkumi\NykLogger\Tests\TestCase;

final class NykCloudChannelTest extends TestCase
{
    public function test_posts_payload_with_bearer_token(): void
    {
        Http::fake([
            'https://nykdesk.test/api/v1/ingest/alerts' => Http::response(['data' => ['id' => 1]], 201),
        ]);

        $channel = new NykCloudChannel(
            http: $this->app->make(HttpFactory::class),
            apiKey: 'nyk_test_key_1234567890abcdefghijklmnop',
            endpoint: 'https://nykdesk.test/api/v1/ingest/alerts',
        );

        $ok = $channel->send($this->payload());

        $this->assertTrue($ok);
        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://nykdesk.test/api/v1/ingest/alerts'
                && $request->hasHeader('Authorization', 'Bearer nyk_test_key_1234567890abcdefghijklmnop')
                && $request['type'] === 'alert'
                && $request['fingerprint'] === 'fp-1';
        });
    }

    public function test_skips_when_misconfigured(): void
    {
        $channel = new NykCloudChannel(
            http: $this->app->make(HttpFactory::class),
            apiKey: null,
            endpoint: null,
        );

        $this->assertFalse($channel->send($this->payload()));
    }

    private function payload(): AlertPayload
    {
        return new AlertPayload(
            level: 'error',
            title: 'Test',
            message: 'Hello',
            environment: 'testing',
            appName: 'Demo',
            exceptionClass: null,
            file: null,
            line: null,
            code: null,
            trace: null,
            url: '/',
            method: 'GET',
            ip: null,
            userAgent: null,
            userId: null,
            context: [],
            timestamp: now()->toIso8601String(),
            fingerprint: 'fp-1',
        );
    }
}
