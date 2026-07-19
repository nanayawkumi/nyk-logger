<?php

declare(strict_types=1);

namespace Nanayawkumi\NykLogger\Tests;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ExceptionCaptureTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Enable handler capture (before boot so the decorator is installed)
        // and disable the log source to isolate this capture path.
        $app['config']->set('nyk-logger.exceptions.enabled', true);
        $app['config']->set('nyk-logger.exceptions.http_statuses', [500, 403]);
        $app['config']->set('nyk-logger.log.enabled', false);
    }

    #[Test]
    public function it_captures_500_server_errors_from_the_handler(): void
    {
        Http::fake(['api.brevo.com/*' => Http::response([], 201)]);

        app(ExceptionHandler::class)->report(new RuntimeException('server exploded'));

        Http::assertSent(fn (Request $r): bool => str_contains((string) $r['htmlContent'], 'server exploded'));
    }

    #[Test]
    public function it_captures_403_from_an_authorization_exception(): void
    {
        Http::fake(['api.brevo.com/*' => Http::response([], 201)]);

        app(ExceptionHandler::class)->report(new AuthorizationException('forbidden'));

        Http::assertSent(fn (Request $r): bool => str_contains((string) $r['htmlContent'], 'forbidden'));
    }

    #[Test]
    public function it_ignores_statuses_that_are_not_configured(): void
    {
        Http::fake();

        // 404 is not in [500, 403] and is < 500 -> not captured.
        app(ExceptionHandler::class)->report(new NotFoundHttpException('missing'));

        Http::assertNothingSent();
    }

    #[Test]
    public function it_still_delegates_to_the_original_handler(): void
    {
        // The decorator must not break normal reporting; shouldReport should
        // continue to reflect the underlying handler's decision.
        $handler = app(ExceptionHandler::class);

        $this->assertTrue($handler->shouldReport(new RuntimeException('x')));
    }
}
