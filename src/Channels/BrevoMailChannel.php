<?php

declare(strict_types=1);

namespace Nanayawkumi\NykLogger\Channels;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Http\Client\Factory as HttpFactory;
use Nanayawkumi\NykLogger\Contracts\AlertChannel;
use Nanayawkumi\NykLogger\Support\AlertPayload;
use Throwable;

/**
 * Delivers alerts as HTML email through Brevo's Transactional Email API (v3),
 * using Laravel's HTTP client and bypassing the host app's SMTP stack.
 */
final readonly class BrevoMailChannel implements AlertChannel
{
    private const string ENDPOINT = 'https://api.brevo.com/v3/smtp/email';

    private const int TIMEOUT_SECONDS = 10;

    public function __construct(
        private HttpFactory $http,
        private ViewFactory $view,
        private ?string $apiKey,
        private ?string $toEmail,
        private string $toName,
        private ?string $fromEmail,
        private string $fromName,
    ) {
    }

    public function name(): string
    {
        return 'mail';
    }

    public function send(AlertPayload $payload): bool
    {
        if (! $this->isConfigured()) {
            error_log('[nyk-logger] mail channel skipped: missing api_key, to_email or from_email.');

            return false;
        }

        try {
            $html = $this->view->make('nyk-logger::error-email', $payload->toArray())->render();

            $response = $this->http
                ->withHeaders([
                    'api-key' => $this->apiKey,
                    'accept' => 'application/json',
                    'content-type' => 'application/json',
                ])
                ->timeout(self::TIMEOUT_SECONDS)
                ->post(self::ENDPOINT, [
                    'sender' => [
                        'name' => $this->fromName,
                        'email' => $this->fromEmail,
                    ],
                    'to' => [
                        [
                            'email' => $this->toEmail,
                            'name' => $this->toName,
                        ],
                    ],
                    'subject' => $payload->subject(),
                    'htmlContent' => $html,
                ]);

            if ($response->failed()) {
                error_log(sprintf(
                    '[nyk-logger] Brevo API responded with HTTP %d: %s',
                    $response->status(),
                    $response->body(),
                ));

                return false;
            }

            return true;
        } catch (Throwable $e) {
            error_log('[nyk-logger] mail channel failed: '.$e->getMessage());

            return false;
        }
    }

    public function isConfigured(): bool
    {
        return ! empty($this->apiKey)
            && ! empty($this->toEmail)
            && ! empty($this->fromEmail);
    }
}
