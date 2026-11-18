<?php

declare(strict_types=1);

namespace Nanayawkumi\NykLogger\Channels;

use Illuminate\Http\Client\Factory as HttpFactory;
use Nanayawkumi\NykLogger\Contracts\AlertChannel;
use Nanayawkumi\NykLogger\Support\AlertPayload;
use Throwable;

/**
 * Delivers alerts to NykDesk Cloud ingest API using a project API key.
 */
final class NykCloudChannel implements AlertChannel
{
    private const DEFAULT_TIMEOUT_SECONDS = 10;

    public function __construct(
        private readonly HttpFactory $http,
        private readonly ?string $apiKey,
        private readonly ?string $endpoint,
        private readonly int $timeoutSeconds = self::DEFAULT_TIMEOUT_SECONDS,
    ) {
    }

    public function name(): string
    {
        return 'nyk';
    }

    public function send(AlertPayload $payload): bool
    {
        if (empty($this->apiKey) || empty($this->endpoint)) {
            error_log('[nyk-logger] nyk channel skipped: missing api_key or endpoint.');

            return false;
        }

        try {
            $body = array_merge($payload->toArray(), [
                'type' => 'alert',
                'version' => 1,
            ]);

            $response = $this->http
                ->timeout($this->timeoutSeconds)
                ->withToken($this->apiKey)
                ->acceptJson()
                ->asJson()
                ->post($this->endpoint, $body);

            if ($response->failed()) {
                error_log(sprintf(
                    '[nyk-logger] NykDesk Cloud responded with HTTP %d: %s',
                    $response->status(),
                    $response->body(),
                ));

                return false;
            }

            return true;
        } catch (Throwable $e) {
            error_log('[nyk-logger] nyk channel failed: '.$e->getMessage());

            return false;
        }
    }
}
