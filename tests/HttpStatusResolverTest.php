<?php

declare(strict_types=1);

namespace Nanayawkumi\NykLogger\Tests;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Nanayawkumi\NykLogger\Support\HttpStatusResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class HttpStatusResolverTest extends TestCase
{
    private HttpStatusResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new HttpStatusResolver();
    }

    #[Test]
    public function it_reads_the_status_from_http_exceptions(): void
    {
        $this->assertSame(404, $this->resolver->resolve(new NotFoundHttpException()));
    }

    #[Test]
    public function it_maps_authorization_to_403(): void
    {
        $this->assertSame(403, $this->resolver->resolve(new AuthorizationException()));
    }

    #[Test]
    public function it_maps_authentication_to_401(): void
    {
        $this->assertSame(401, $this->resolver->resolve(new AuthenticationException()));
    }

    #[Test]
    public function it_defaults_unknown_throwables_to_500(): void
    {
        $this->assertSame(500, $this->resolver->resolve(new RuntimeException('boom')));
    }

    #[Test]
    public function it_maps_server_errors_to_critical(): void
    {
        $this->assertSame('critical', $this->resolver->levelFor(500));
        $this->assertSame('error', $this->resolver->levelFor(403));
    }
}
