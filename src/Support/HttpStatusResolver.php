<?php

declare(strict_types=1);

namespace Nanayawkumi\NykLogger\Support;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Maps a throwable to the HTTP status code Laravel would render for it. This
 * mirrors the framework's own conversion (which happens at render time, not
 * report time) so we can decide on capture before the response is built.
 */
final class HttpStatusResolver
{
    public function resolve(Throwable $e): int
    {
        if ($e instanceof HttpExceptionInterface) {
            return $e->getStatusCode();
        }

        return match (true) {
            $e instanceof AuthenticationException => 401,
            $e instanceof AuthorizationException => 403,
            $e instanceof ValidationException => 422,
            $e instanceof ModelNotFoundException => 404,
            $e instanceof TokenMismatchException => 419,
            default => 500,
        };
    }

    /**
     * A status >= 500 is a server error and maps to the "critical" level;
     * everything else is treated as an "error".
     */
    public function levelFor(int $status): string
    {
        return $status >= 500 ? 'critical' : 'error';
    }
}
