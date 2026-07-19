<?php

declare(strict_types=1);

namespace Nanayawkumi\NykLogger\Support;

/**
 * Immutable, fully-serializable representation of a single alert. All request
 * context is captured at build time so the payload can be safely queued and
 * processed by a worker where the original request no longer exists.
 */
final class AlertPayload
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $level,
        public readonly string $title,
        public readonly string $message,
        public readonly string $environment,
        public readonly string $appName,
        public readonly ?string $exceptionClass,
        public readonly ?string $file,
        public readonly ?int $line,
        public readonly ?string $code,
        public readonly ?string $trace,
        public readonly string $url,
        public readonly string $method,
        public readonly ?string $ip,
        public readonly ?string $userAgent,
        public readonly int|string|null $userId,
        public readonly array $context,
        public readonly string $timestamp,
        public readonly string $fingerprint,
    ) {
    }

    public function subject(): string
    {
        $short = mb_strimwidth($this->title, 0, 100, '…');

        return sprintf('[%s] %s: %s', $this->appName, strtoupper($this->level), $short);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'level' => strtoupper($this->level),
            'title' => $this->title,
            'message' => $this->message,
            'environment' => $this->environment,
            'appName' => $this->appName,
            'exceptionClass' => $this->exceptionClass,
            'file' => $this->file,
            'line' => $this->line,
            'code' => $this->code,
            'trace' => $this->trace,
            'url' => $this->url,
            'method' => $this->method,
            'ip' => $this->ip,
            'userAgent' => $this->userAgent,
            'userId' => $this->userId,
            'context' => $this->context,
            'timestamp' => $this->timestamp,
            'fingerprint' => $this->fingerprint,
        ];
    }
}
