<?php

declare(strict_types=1);

namespace Nanayawkumi\NykLogger\Exceptions;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Nanayawkumi\NykLogger\AlertManager;
use Nanayawkumi\NykLogger\Support\HttpStatusResolver;
use Throwable;

/**
 * Decorates the framework's exception handler so uncaught exceptions can be
 * captured directly — including ones Laravel never logs (500s, 403, 404, ...).
 * All original handler behaviour is preserved via delegation.
 */
final class ReportingExceptionHandler implements ExceptionHandler
{
    /**
     * @param  array<string, mixed>  $config  The "exceptions" config section.
     */
    public function __construct(
        private readonly ExceptionHandler $inner,
        private readonly AlertManager $alerts,
        private readonly HttpStatusResolver $status,
        private readonly array $config,
    ) {
    }

    public function report(Throwable $e)
    {
        try {
            if ($this->shouldCapture($e)) {
                $status = $this->status->resolve($e);
                $this->alerts->captureThrowable($e, $this->status->levelFor($status), [
                    'http_status' => $status,
                    'source' => 'exception-handler',
                ]);
            }
        } catch (Throwable $ignored) {
            // Capturing must never interfere with the real handler.
            error_log('[nyk-logger] exception-handler capture failed: '.$ignored->getMessage());
        }

        $this->inner->report($e);
    }

    public function shouldReport(Throwable $e)
    {
        return $this->inner->shouldReport($e);
    }

    public function render($request, Throwable $e)
    {
        return $this->inner->render($request, $e);
    }

    public function renderForConsole($output, Throwable $e)
    {
        $this->inner->renderForConsole($output, $e);
    }

    private function shouldCapture(Throwable $e): bool
    {
        if (! ($this->config['enabled'] ?? false)) {
            return false;
        }

        foreach ((array) ($this->config['types'] ?? []) as $type) {
            if (is_string($type) && $type !== '' && $e instanceof $type) {
                return true;
            }
        }

        $statuses = array_map('intval', (array) ($this->config['http_statuses'] ?? []));

        if ($statuses === []) {
            return false;
        }

        $status = $this->status->resolve($e);

        if (in_array($status, $statuses, true)) {
            return true;
        }

        // Listing 500 also matches any server error (>= 500).
        return $status >= 500 && in_array(500, $statuses, true);
    }
}
