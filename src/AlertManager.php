<?php

declare(strict_types=1);

namespace Nanayawkumi\NykLogger;

use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Nanayawkumi\NykLogger\Events\AlertSending;
use Nanayawkumi\NykLogger\Events\AlertSuppressed;
use Nanayawkumi\NykLogger\Jobs\SendAlertJob;
use Nanayawkumi\NykLogger\Support\AlertPayload;
use Nanayawkumi\NykLogger\Support\Redactor;
use Throwable;

/**
 * Orchestrates a single log event: applies ignore rules, per-fingerprint
 * cooldown and a global rate cap, then dispatches the alert (queued or sync).
 * Also exposes a manual reporting API and lifecycle events.
 */
final class AlertManager
{
    private const COOLDOWN_PREFIX = 'nyk-logger:cooldown:';

    private const RATE_LIMIT_KEY = 'nyk-logger:ratelimit';

    /**
     * Optional user-supplied fingerprint resolver:
     * fn(string $message, ?Throwable $exception, string $level): ?string
     */
    private ?Closure $fingerprintResolver = null;

    public function __construct(
        private readonly Application $app,
        private readonly Config $config,
        private readonly Redactor $redactor,
    ) {
    }

    /**
     * Resolve the event dispatcher lazily so test fakes (Event::fake) that
     * rebind the container are respected.
     */
    private function events(): Dispatcher
    {
        return $this->app->make('events');
    }

    /**
     * Entry point invoked by the log listener. Honours the configured level
     * filter before processing.
     *
     * @param  array<string, mixed>  $context
     */
    public function capture(string $level, string $message, array $context = []): bool
    {
        if (! in_array($level, $this->levels(), true)) {
            return false;
        }

        return $this->process($level, $message, $context, enforceIgnore: true);
    }

    /**
     * Capture a throwable from an automatic source (e.g. the exception
     * handler). Skips the log-level filter but still honours ignore rules,
     * cooldown and the rate cap.
     *
     * @param  array<string, mixed>  $context
     */
    public function captureThrowable(Throwable $exception, string $level = 'error', array $context = []): bool
    {
        $context['exception'] = $exception;

        return $this->process($level, $exception->getMessage(), $context, enforceIgnore: true);
    }

    /**
     * Manually report a throwable, regardless of the configured level filter
     * and ignore rules (it's an explicit, intentional call).
     *
     * @param  array<string, mixed>  $context
     */
    public function report(Throwable $exception, string $level = 'error', array $context = []): bool
    {
        $context['exception'] = $exception;

        return $this->process($level, $exception->getMessage(), $context, enforceIgnore: false);
    }

    /**
     * Manually raise an alert from a plain message, regardless of the
     * configured level filter.
     *
     * @param  array<string, mixed>  $context
     */
    public function alert(string $message, string $level = 'error', array $context = []): bool
    {
        return $this->process($level, $message, $context, enforceIgnore: false);
    }

    /**
     * Register a custom fingerprint resolver used to group alerts. Return null
     * from the callback to fall back to the default fingerprint.
     */
    public function fingerprintUsing(?callable $resolver): void
    {
        $this->fingerprintResolver = $resolver === null ? null : Closure::fromCallable($resolver);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function process(string $level, string $message, array $context, bool $enforceIgnore): bool
    {
        $exception = ($context['exception'] ?? null) instanceof Throwable
            ? $context['exception']
            : null;

        $exceptionClass = $exception !== null ? $exception::class : null;

        if ($enforceIgnore && $this->shouldIgnore($message, $exception)) {
            $this->events()->dispatch(new AlertSuppressed('ignored', $level, $message, null, $exceptionClass));

            return false;
        }

        $fingerprint = $this->fingerprint($message, $exception, $level);

        if ($this->onCooldown($fingerprint)) {
            $this->events()->dispatch(new AlertSuppressed('cooldown', $level, $message, $fingerprint, $exceptionClass));

            return false;
        }

        if ($this->rateLimited()) {
            $this->events()->dispatch(new AlertSuppressed('rate_limit', $level, $message, $fingerprint, $exceptionClass));

            return false;
        }

        $payload = $this->buildPayload($level, $message, $context, $exception, $fingerprint);

        if ($this->cancelled($payload)) {
            $this->events()->dispatch(new AlertSuppressed('cancelled', $level, $message, $fingerprint, $exceptionClass));

            return false;
        }

        $this->dispatch($payload);

        return true;
    }

    private function cancelled(AlertPayload $payload): bool
    {
        // Halting dispatch: the first listener to return a non-null value wins.
        // A returned false cancels delivery.
        return $this->events()->dispatch(new AlertSending($payload), [], true) === false;
    }

    public function dispatch(AlertPayload $payload): void
    {
        $queue = $this->config->get('nyk-logger.queue', []);

        if (empty($queue['enabled'])) {
            SendAlertJob::dispatchSync($payload);

            return;
        }

        $pending = SendAlertJob::dispatch($payload);

        if (! empty($queue['connection'])) {
            $pending->onConnection((string) $queue['connection']);
        }

        if (! empty($queue['queue'])) {
            $pending->onQueue((string) $queue['queue']);
        }
    }

    /**
     * @return list<string>
     */
    private function levels(): array
    {
        return (array) $this->config->get('nyk-logger.log.levels', ['error', 'critical', 'alert', 'emergency']);
    }

    private function shouldIgnore(string $message, ?Throwable $exception): bool
    {
        if ($exception !== null) {
            foreach ((array) $this->config->get('nyk-logger.ignore_exceptions', []) as $class) {
                if (is_string($class) && $class !== '' && $exception instanceof $class) {
                    return true;
                }
            }
        }

        $haystacks = array_filter([$message, $exception?->getMessage()]);

        foreach ((array) $this->config->get('nyk-logger.ignore_messages', []) as $pattern) {
            if (! is_string($pattern) || $pattern === '') {
                continue;
            }

            foreach ($haystacks as $haystack) {
                if (@preg_match($pattern, $haystack) === 1) {
                    return true;
                }
            }
        }

        return false;
    }

    private function fingerprint(string $message, ?Throwable $exception, string $level): string
    {
        if ($this->fingerprintResolver !== null) {
            try {
                $custom = ($this->fingerprintResolver)($message, $exception, $level);

                if (is_string($custom) && $custom !== '') {
                    return md5($custom);
                }
            } catch (Throwable $e) {
                error_log('[nyk-logger] custom fingerprint resolver threw: '.$e->getMessage());
            }
        }

        if ($exception !== null) {
            return md5($exception->getFile().$exception->getLine().$exception->getCode());
        }

        return md5($message);
    }

    /**
     * Guarded so a downed cache/DB backend cannot crash the host app; in that
     * scenario we fail "open" and allow the alert through.
     */
    private function onCooldown(string $fingerprint): bool
    {
        $minutes = (int) $this->config->get('nyk-logger.cooldown', 30);
        $key = self::COOLDOWN_PREFIX.$fingerprint;

        try {
            if (Cache::has($key)) {
                return true;
            }

            Cache::put($key, true, $minutes * 60);
        } catch (Throwable $e) {
            error_log('[nyk-logger] cache unavailable, skipping cooldown: '.$e->getMessage());
        }

        return false;
    }

    private function rateLimited(): bool
    {
        $rate = $this->config->get('nyk-logger.rate_limit', []);
        $max = (int) ($rate['max'] ?? 0);

        if ($max <= 0) {
            return false;
        }

        $decaySeconds = (int) ($rate['decay'] ?? 60) * 60;

        try {
            if (RateLimiter::tooManyAttempts(self::RATE_LIMIT_KEY, $max)) {
                return true;
            }

            RateLimiter::hit(self::RATE_LIMIT_KEY, $decaySeconds);
        } catch (Throwable $e) {
            error_log('[nyk-logger] rate limiter unavailable, skipping cap: '.$e->getMessage());
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function buildPayload(
        string $level,
        string $message,
        array $context,
        ?Throwable $exception,
        string $fingerprint,
    ): AlertPayload {
        $requestAvailable = $this->app->bound('request') && ! $this->app->runningInConsole();
        $request = $requestAvailable ? $this->app->make('request') : null;

        return new AlertPayload(
            level: $level,
            title: $exception?->getMessage() ?: $message,
            message: $message,
            environment: (string) $this->app->environment(),
            appName: (string) $this->config->get('app.name', 'Application'),
            exceptionClass: $exception !== null ? $exception::class : null,
            file: $exception?->getFile(),
            line: $exception?->getLine(),
            code: $exception !== null ? (string) $exception->getCode() : null,
            trace: $exception?->getTraceAsString(),
            url: $request !== null ? $request->fullUrl() : 'N/A (console/CLI)',
            method: $request !== null ? $request->method() : 'N/A',
            ip: $request?->ip(),
            userAgent: $request?->userAgent(),
            userId: $this->resolveUserId(),
            context: $this->buildContext($context, $request),
            timestamp: now()->toDateTimeString(),
            fingerprint: $fingerprint,
        );
    }

    /**
     * Merge and redact log context + request input.
     *
     * @param  array<string, mixed>  $logContext
     * @return array<string, mixed>
     */
    private function buildContext(array $logContext, mixed $request): array
    {
        $context = [];

        $logContext = Arr::except($logContext, ['exception']);

        if ($logContext !== []) {
            $context['log'] = $this->redactor->scrub($logContext);
        }

        if ($request !== null) {
            $input = $request->all();

            if ($input !== []) {
                $context['request'] = $this->redactor->scrub($input);
            }
        }

        return $context;
    }

    private function resolveUserId(): int|string|null
    {
        try {
            if (! $this->app->bound('auth')) {
                return null;
            }

            $id = $this->app->make('auth')->id();

            return is_int($id) || is_string($id) ? $id : null;
        } catch (Throwable) {
            return null;
        }
    }
}
