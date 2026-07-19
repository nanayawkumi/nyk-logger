<?php

declare(strict_types=1);

namespace Nanayawkumi\NykLogger\Support;

/**
 * Immutable, fully-serializable representation of a single alert. All request
 * context is captured at build time so the payload can be safely queued and
 * processed by a worker where the original request no longer exists.
 */
final readonly class AlertPayload
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public string $level,
        public string $title,
        public string $message,
        public string $environment,
        public string $appName,
        public ?string $exceptionClass,
        public ?string $file,
        public ?int $line,
        public ?string $code,
        public ?string $trace,
        public string $url,
        public string $method,
        public ?string $ip,
        public ?string $userAgent,
        public int|string|null $userId,
        public array $context,
        public string $timestamp,
        public string $fingerprint,
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
