# nyk-logger

[![PHP](https://img.shields.io/badge/PHP-%5E8.4-777bb4)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-%5E13.0-ff2d20)](https://laravel.com)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

Lightweight Laravel 13 package that globally catches application errors and
exceptions and dispatches structured alerts through pluggable channels —
**email via the Brevo Transactional API v3** and/or **Slack** — completely
bypassing your host app's SMTP configuration.

Built for production noise control and safety:

- **Multi-channel** delivery (Brevo email + Slack)
- **Queued dispatch** so a failing request never waits on outbound API calls
- **Per-fingerprint cooldown** + a **global rate cap** to prevent inbox floods
- **Ignore rules** for noisy, non-actionable errors (404s, validation, etc.)
- **Automatic secret redaction** before anything leaves your app
- **Manual reporting API**, **lifecycle events**, and **custom grouping**
- **`php artisan nyk-logger:test`** + boot-time config validation warnings
- **Crash-safe**: a downed provider can never take down the host app

## Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Configuration reference](#configuration-reference)
- [Verify your setup](#verify-your-setup)
- [How it works](#how-it-works)
- [Failure safety](#failure-safety)
- [Manual reporting API](#manual-reporting-api)
- [Lifecycle events](#lifecycle-events)
- [Custom grouping (fingerprint)](#custom-grouping-fingerprint)
- [Customising the email template](#customising-the-email-template)
- [Package structure](#package-structure)
- [Testing](#testing)
- [License](#license)

## Requirements

- PHP `^8.4`
- Laravel `^13.0`
- A Brevo account with a v3 API key and a verified sender (for the mail channel)

## Installation

```bash
composer require nanayawkumi/nyk-logger
```

The service provider is auto-discovered. Optionally publish the config and/or
the email view:

```bash
php artisan vendor:publish --tag=nyk-logger-config
php artisan vendor:publish --tag=nyk-logger-views
```

## Configuration

The only required setup is your `.env`. Add the keys for the channels you use:

```dotenv
NYK_LOGGER_ENABLED=true

# Channels: comma-separated. Options: mail, slack
NYK_LOGGER_CHANNELS=mail,slack

# --- Mail (Brevo) ---
NYK_LOGGER_API_KEY=your-brevo-v3-api-key
NYK_LOGGER_EMAIL=alerts@yourdomain.com
NYK_LOGGER_NAME="System Administrator"
NYK_LOGGER_FROM_EMAIL=noreply@yourdomain.com   # verified sender in Brevo
NYK_LOGGER_FROM_NAME="My App"

# --- Slack ---
NYK_LOGGER_SLACK_WEBHOOK=https://hooks.slack.com/services/XXX/YYY/ZZZ

# --- Queue (recommended in production) ---
NYK_LOGGER_QUEUE=true
NYK_LOGGER_QUEUE_CONNECTION=redis
NYK_LOGGER_QUEUE_NAME=notifications

# --- Throttling ---
NYK_LOGGER_COOLDOWN=30        # minutes an identical error is suppressed
NYK_LOGGER_RATE_MAX=20        # max alerts within the rate window (0 disables)
NYK_LOGGER_RATE_DECAY=60      # rate window in minutes
```

Array-based settings — the allowed **environments**, listened **levels**,
**ignore rules**, and **redaction** keys/patterns — live in the published
config file (`config/nyk-logger.php`).

### Configuration reference

| Key | Env var | Default | Description |
| --- | --- | --- | --- |
| `enabled` | `NYK_LOGGER_ENABLED` | `true` | Master switch. |
| `environments` | — | `['production']` | Environments where the listener is active. |
| `levels` | — | `error, critical, alert, emergency` | Log levels that trigger an alert. |
| `channels` | `NYK_LOGGER_CHANNELS` | `mail` | Active channels (`mail`, `slack`). |
| `mail.api_key` | `NYK_LOGGER_API_KEY` | `null` | Brevo v3 API key. |
| `mail.to_email` | `NYK_LOGGER_EMAIL` | `null` | Recipient address. |
| `mail.to_name` | `NYK_LOGGER_NAME` | `System Administrator` | Recipient name. |
| `mail.from_email` | `NYK_LOGGER_FROM_EMAIL` | `MAIL_FROM_ADDRESS` | Verified Brevo sender. |
| `mail.from_name` | `NYK_LOGGER_FROM_NAME` | `APP_NAME` | Sender name. |
| `slack.webhook_url` | `NYK_LOGGER_SLACK_WEBHOOK` | `null` | Slack incoming webhook URL. |
| `queue.enabled` | `NYK_LOGGER_QUEUE` | `false` | Push delivery onto the queue. |
| `queue.connection` | `NYK_LOGGER_QUEUE_CONNECTION` | `null` | Queue connection. |
| `queue.queue` | `NYK_LOGGER_QUEUE_NAME` | `null` | Queue name. |
| `cooldown` | `NYK_LOGGER_COOLDOWN` | `30` | Minutes an identical error is suppressed. |
| `rate_limit.max` | `NYK_LOGGER_RATE_MAX` | `20` | Max alerts per window (`0` disables). |
| `rate_limit.decay` | `NYK_LOGGER_RATE_DECAY` | `60` | Rate window in minutes. |
| `ignore_exceptions` | — | `[]` | Exception classes (incl. subclasses) to skip. |
| `ignore_messages` | — | `[]` | Regex patterns; matching messages are skipped. |
| `redact.keys` | — | common secrets | Context/request keys to replace with `[REDACTED]`. |
| `redact.patterns` | — | bearer tokens | Value regexes to scrub. |

## Verify your setup

```bash
php artisan nyk-logger:test
```

Fires a sample alert through every enabled channel and reports per-channel
success/failure — no need to trigger a real error. If required keys are missing
for an active channel, a warning is also logged automatically at console/boot
time so misconfiguration surfaces during deploys.

## How it works

1. On boot the package checks it is `enabled` and the current environment is in
   `environments`. If not, it registers nothing (zero overhead).
2. A global `Log::listen()` handler intercepts entries at the configured
   `levels` (default `error`, `critical`, `alert`, `emergency`).
3. **Ignore rules** drop matching exception classes / message patterns.
4. A **fingerprint** is derived from the exception (`file + line + code`) or the
   message string, checked against a **cooldown** cache entry.
5. A **global rate cap** limits total alerts per window across all errors.
6. Request context (URL, method, IP, user, input) is captured and **redacted**,
   then a `SendAlertJob` fans it out to every enabled channel — **queued** by
   default so the failing request is never slowed down.

## Failure safety

- Each channel's API call is wrapped in `try/catch` with a timeout and **never
  rethrows** — a downed Brevo/Slack endpoint cannot crash your app.
- Cache and rate-limiter access is guarded too; if your backend is down the
  package fails "open" and still attempts delivery.

## Manual reporting API

Trigger alerts intentionally from anywhere in your app via the facade. Manual
calls bypass the log-level filter (they're explicit) but still respect the
cooldown and rate cap:

```php
use Nanayawkumi\NykLogger\Facades\NykLogger;

try {
    $this->chargeCard();
} catch (\Throwable $e) {
    NykLogger::report($e);                       // report a throwable
    NykLogger::report($e, 'critical', ['order' => $id]);
}

NykLogger::alert('Nightly reconciliation mismatch', 'critical', [
    'expected' => $expected,
    'actual'   => $actual,
]);
```

## Lifecycle events

Hook into delivery with standard Laravel events:

| Event | When | Payload |
| --- | --- | --- |
| `Events\AlertSending` | Just before dispatch (synchronous, **cancellable**) | `AlertPayload $payload` |
| `Events\AlertSent` | After fan-out to channels | `AlertPayload $payload`, `array<string,bool> $results` |
| `Events\AlertSuppressed` | When filtered out | `string $reason` (`ignored`/`cooldown`/`rate_limit`/`cancelled`), plus level/message/fingerprint |

Return `false` from an `AlertSending` listener to cancel an alert:

```php
use Nanayawkumi\NykLogger\Events\AlertSending;

Event::listen(AlertSending::class, function (AlertSending $e) {
    if (str_contains($e->payload->message, 'expected noise')) {
        return false; // cancel delivery
    }
});
```

Prefer a destination that isn't built in (PagerDuty, Sentry, a database row)?
Listen for `AlertSent` and forward the immutable `AlertPayload` yourself — no
need to modify the package:

```php
use Nanayawkumi\NykLogger\Events\AlertSent;

Event::listen(AlertSent::class, function (AlertSent $e) {
    MyIncidentTracker::open($e->payload->toArray());
});
```

## Custom grouping (fingerprint)

By default alerts are grouped by `file + line + code` (or the message for text
logs). Override grouping with your own resolver, e.g. in a service provider's
`boot()`:

```php
use Nanayawkumi\NykLogger\Facades\NykLogger;

NykLogger::fingerprintUsing(function (string $message, ?\Throwable $e, string $level) {
    // Return a string to group on, or null to fall back to the default.
    return $e instanceof \App\Exceptions\PaymentException ? 'payments' : null;
});
```

## Customising the email template

Publish and edit the Blade view — it receives the full `AlertPayload` data:

```bash
php artisan vendor:publish --tag=nyk-logger-views
# edit resources/views/vendor/nyk-logger/error-email.blade.php
```

Available variables: `level`, `title`, `message`, `environment`, `appName`,
`exceptionClass`, `file`, `line`, `code`, `trace`, `url`, `method`, `ip`,
`userAgent`, `userId`, `context` (redacted), `timestamp`, `fingerprint`.

## Package structure

```
src/
├── NykLoggerServiceProvider.php   # bindings, log listener, config validation
├── AlertManager.php               # ignore -> cooldown -> rate cap -> dispatch
├── Contracts/AlertChannel.php     # channel interface (send + name)
├── Channels/
│   ├── ChannelManager.php         # resolves enabled channels
│   ├── BrevoMailChannel.php       # Brevo Transactional API v3
│   └── SlackChannel.php           # Slack incoming webhook (Block Kit)
├── Jobs/SendAlertJob.php          # queued fan-out + AlertSent event
├── Console/TestCommand.php        # php artisan nyk-logger:test
├── Events/                        # AlertSending / AlertSent / AlertSuppressed
├── Facades/NykLogger.php          # report() / alert() / fingerprintUsing()
├── Support/
│   ├── AlertPayload.php           # immutable, serializable DTO
│   └── Redactor.php               # secret scrubbing
└── Views/error-email.blade.php    # responsive HTML template
```

## Testing

```bash
composer test
```

## License

MIT
