<?php

declare(strict_types=1);

namespace Nanayawkumi\NykLogger\Channels;

use Illuminate\Http\Client\Factory as HttpFactory;
use Nanayawkumi\NykLogger\Contracts\AlertChannel;
use Nanayawkumi\NykLogger\Support\AlertPayload;
use Throwable;

/**
 * Delivers alerts to a Slack Incoming Webhook using Block Kit formatting.
 */
final class SlackChannel implements AlertChannel
{
    private const TIMEOUT_SECONDS = 10;

    public function __construct(
        private readonly HttpFactory $http,
        private readonly ?string $webhookUrl,
    ) {
    }

    public function name(): string
    {
        return 'slack';
    }

    public function send(AlertPayload $payload): bool
    {
        if (empty($this->webhookUrl)) {
            error_log('[nyk-logger] slack channel skipped: missing webhook_url.');

            return false;
        }

        try {
            $response = $this->http
                ->timeout(self::TIMEOUT_SECONDS)
                ->post($this->webhookUrl, $this->buildMessage($payload));

            if ($response->failed()) {
                error_log(sprintf(
                    '[nyk-logger] Slack webhook responded with HTTP %d: %s',
                    $response->status(),
                    $response->body(),
                ));

                return false;
            }

            return true;
        } catch (Throwable $e) {
            error_log('[nyk-logger] slack channel failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMessage(AlertPayload $payload): array
    {
        $location = $payload->file !== null
            ? $payload->file.':'.$payload->line
            : 'N/A';

        $fields = [
            $this->field('Environment', $payload->environment),
            $this->field('Level', strtoupper($payload->level)),
            $this->field('URL', $payload->method.' '.$payload->url),
            $this->field('Location', $location),
        ];

        $blocks = [
            [
                'type' => 'header',
                'text' => [
                    'type' => 'plain_text',
                    'text' => mb_strimwidth(sprintf('🚨 %s · %s', strtoupper($payload->level), $payload->appName), 0, 150, '…'),
                    'emoji' => true,
                ],
            ],
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => '*'.$this->escape(mb_strimwidth($payload->title, 0, 500, '…')).'*',
                ],
            ],
            [
                'type' => 'section',
                'fields' => $fields,
            ],
        ];

        if ($payload->trace !== null && $payload->trace !== '') {
            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "```".$this->escape(mb_strimwidth($payload->trace, 0, 2500, "\n…"))."```",
                ],
            ];
        }

        return [
            'text' => sprintf('[%s] %s: %s', $payload->appName, strtoupper($payload->level), $payload->title),
            'blocks' => $blocks,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function field(string $label, string $value): array
    {
        return [
            'type' => 'mrkdwn',
            'text' => '*'.$label.":*\n".$this->escape($value),
        ];
    }

    private function escape(string $value): string
    {
        return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $value);
    }
}
