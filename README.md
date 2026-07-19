# nyk-logger

[PHP](https://php.net)
[Laravel](https://laravel.com)
[License](LICENSE)

Lightweight Laravel package (10 → 13) that globally catches application errors
and exceptions and dispatches structured alerts through pluggable channels —
**email via the Brevo Transactional API v3** and/or **Slack** — completely
bypassing your host app's SMTP configuration.

Built for production noise control and safety:

- **Two capture sources**: the log pipeline **and** the exception handler — so
  you can catch uncaught 500s and selected 4xx (403, 404, ...) Laravel never logs
- **Multi-channel** delivery (Brevo email + Slack)
- **Queued dispatch** so a failing request never waits on outbound API calls
- **Per-fingerprint cooldown** + a **global rate cap** to prevent inbox floods
- **Ignore rules** for noisy, non-actionable errors (404s, validation, etc.)
- **Automatic secret redaction** before anything leaves your app
- **Manual reporting API**, **lifecycle events**, and **custom grouping**
- `php artisan nyk-logger:test` + boot-time config validation warnings
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

- PHP `^8.1`
- Laravel `^10.0`, `^11.0`, `^12.0`, or `^13.0`
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

The **only** keys you need in your `.env` are the Brevo credentials:

```dotenv
NYK_LOGGER_API_KEY=your-brevo-v3-api-key
NYK_LOGGER_EMAIL=alerts@yourdomain.com
NYK_LOGGER_NAME="System Administrator"
NYK_LOGGER_FROM_EMAIL=noreply@yourdomain.com   # verified sender in Brevo
NYK_LOGGER_FROM_NAME="My App"
```

Everything else ships with sensible defaults baked into the config file. To
change any of them (channels, capture sources, queue, cooldown, rate limit,
ignore/redaction rules, the Slack webhook, ...), publish the config and edit
the value directly:

```bash
php artisan vendor:publish --tag=nyk-logger-config
```

### Capture sources

The package captures problems from two independent, individually toggleable
sources:

**1. The log pipeline** — reacts to log entries at the configured levels:

```php
'log' => [
    'enabled' => true,
    'levels'  => ['error', 'critical', 'alert', 'emergency'],
],
```

**2. The exception handler** — decorates Laravel's handler to catch **uncaught
exceptions the framework doesn't log by default**, such as 500 server errors
and selected 4xx (403, 404). Choose exactly what to alert on by HTTP status
and/or exception class:

```php
'exceptions' => [
    'enabled' => true,
    // Listing 500 also matches ANY server error (status >= 500).
    'http_statuses' => [500, 403],
    // ...or match specific throwable types regardless of status.
    'types' => [
        \Illuminate\Auth\Access\AuthorizationException::class, // 403
    ],
],
```

Both sources feed the same cooldown and rate cap, so an error caught by both
(e.g. a logged 500) is de-duplicated into a single alert.

### Configuration reference


Only the Brevo `mail.*` keys read from the environment; everything else is a
literal default you edit in the published config file.

| Key                 | Env var (mail only)     | Default                             | Description                                        |
| ------------------- | ----------------------- | ----------------------------------- | -------------------------------------------------- |
| `enabled`           | —                       | `true`                              | Master switch.                                     |
| `environments`      | —                       | `['production']`                    | Environments where capture is active.              |
| `log.enabled`       | —                       | `true`                              | Capture from the log pipeline.                     |
| `log.levels`        | —                       | `error, critical, alert, emergency` | Log levels that trigger an alert.                  |
| `exceptions.enabled`| —                       | `true`                              | Capture uncaught exceptions from the handler.      |
| `exceptions.http_statuses` | —                | `[500]`                             | Statuses to alert on (`500` also matches `>=500`). |
| `exceptions.types`  | —                       | `[]`                                | Throwable classes to alert on regardless of status.|
| `channels`          | —                       | `['mail']`                          | Active channels (`mail`, `slack`).                 |
| `mail.api_key`      | `NYK_LOGGER_API_KEY`    | `null`                              | Brevo v3 API key.                                  |
| `mail.to_email`     | `NYK_LOGGER_EMAIL`      | `null`                              | Recipient address.                                 |
| `mail.to_name`      | `NYK_LOGGER_NAME`       | `System Administrator`              | Recipient name.                                    |
| `mail.from_email`   | `NYK_LOGGER_FROM_EMAIL` | `MAIL_FROM_ADDRESS`                 | Verified Brevo sender.                             |
| `mail.from_name`    | `NYK_LOGGER_FROM_NAME`  | `APP_NAME`                          | Sender name.                                       |
| `slack.webhook_url` | —                       | `null`                              | Slack incoming webhook URL.                        |
| `queue.enabled`     | —                       | `false`                             | Push delivery onto the queue.                      |
| `queue.connection`  | —                       | `null`                              | Queue connection (`null` = default).               |
| `queue.queue`       | —                       | `default`                           | Queue name.                                        |
| `cooldown`          | —                       | `30`                                | Minutes an identical error is suppressed.          |
| `rate_limit.max`    | —                       | `20`                                | Max alerts per window (`0` disables).              |
| `rate_limit.decay`  | —                       | `60`                                | Rate window in minutes.                            |
| `ignore_exceptions` | —                       | `[]`                                | Exception classes (incl. subclasses) to skip.      |
| `ignore_messages`   | —                       | `[]`                                | Regex patterns; matching messages are skipped.     |
| `redact.keys`       | —                       | common secrets                      | Context/request keys to replace with `[REDACTED]`. |
| `redact.patterns`   | —                       | bearer tokens                       | Value regexes to scrub.                            |




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
2. Two capture sources feed the pipeline (each independently toggleable):
   a global `Log::listen()` handler for the configured `log.levels`, and a
   decorator around the framework's exception handler that catches uncaught
   exceptions matching your `exceptions.http_statuses` / `exceptions.types`.
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


| Event                    | When                                                | Payload                                                                                          |
| ------------------------ | --------------------------------------------------- | ------------------------------------------------------------------------------------------------ |
| `Events\AlertSending`    | Just before dispatch (synchronous, **cancellable**) | `AlertPayload $payload`                                                                          |
| `Events\AlertSent`       | After fan-out to channels                           | `AlertPayload $payload`, `array<string,bool> $results`                                           |
| `Events\AlertSuppressed` | When filtered out                                   | `string $reason` (`ignored`/`cooldown`/`rate_limit`/`cancelled`), plus level/message/fingerprint |


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
├── Exceptions/
│   └── ReportingExceptionHandler.php  # decorates the framework handler
├── Events/                        # AlertSending / AlertSent / AlertSuppressed
├── Facades/NykLogger.php          # report() / alert() / fingerprintUsing()
├── Support/
│   ├── AlertPayload.php           # immutable, serializable DTO
│   ├── HttpStatusResolver.php     # throwable -> HTTP status/level
│   └── Redactor.php               # secret scrubbing
└── Views/error-email.blade.php    # responsive HTML template
```



## Testing

```bash
composer test
```



## License

MIT