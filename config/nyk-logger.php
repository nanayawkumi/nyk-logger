<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Global Toggle
    |--------------------------------------------------------------------------
    | Master switch for the package. When false, no listeners fire and no
    | alerts are dispatched regardless of environment.
    */
    'enabled' => env('NYK_LOGGER_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Allowed Environments
    |--------------------------------------------------------------------------
    | Alerts are only dispatched when App::environment() matches one of these.
    */
    'environments' => [
        'production',
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Levels
    |--------------------------------------------------------------------------
    | Which PSR-3 levels trigger an alert.
    */
    'levels' => ['error', 'critical', 'alert', 'emergency'],

    /*
    |--------------------------------------------------------------------------
    | Active Channels
    |--------------------------------------------------------------------------
    | Any combination of: 'mail', 'slack'. Alerts fan out to every enabled
    | channel.
    */
    'channels' => explode(',', (string) env('NYK_LOGGER_CHANNELS', 'mail')),

    /*
    |--------------------------------------------------------------------------
    | Mail Channel (Brevo Transactional API v3)
    |--------------------------------------------------------------------------
    */
    'mail' => [
        'api_key' => env('NYK_LOGGER_API_KEY'),
        'to_email' => env('NYK_LOGGER_EMAIL'),
        'to_name' => env('NYK_LOGGER_NAME', 'System Administrator'),
        // Sender must be a verified sender in your Brevo account.
        'from_email' => env('NYK_LOGGER_FROM_EMAIL', env('MAIL_FROM_ADDRESS')),
        'from_name' => env('NYK_LOGGER_FROM_NAME', env('APP_NAME', 'NYK Logger')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Slack Channel (Incoming Webhook)
    |--------------------------------------------------------------------------
    */
    'slack' => [
        'webhook_url' => env('NYK_LOGGER_SLACK_WEBHOOK'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    | When enabled, alerts are pushed onto the queue so the failing request is
    | never slowed down by the outbound API call. Falls back to synchronous
    | dispatch when disabled.
    */
    'queue' => [
        'enabled' => (bool) env('NYK_LOGGER_QUEUE', false),
        'connection' => env('NYK_LOGGER_QUEUE_CONNECTION'),
        'queue' => env('NYK_LOGGER_QUEUE_NAME'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Per-Fingerprint Cooldown
    |--------------------------------------------------------------------------
    | Minutes an identical error fingerprint is suppressed after its first
    | alert, preventing inbox flooding from a single looping error.
    */
    'cooldown' => (int) env('NYK_LOGGER_COOLDOWN', 30),

    /*
    |--------------------------------------------------------------------------
    | Global Rate Limit
    |--------------------------------------------------------------------------
    | A hard ceiling on the total number of alerts (across all distinct
    | errors) within the decay window. Protects against bursts of many
    | *different* errors. Set 'max' to 0 to disable.
    */
    'rate_limit' => [
        'max' => (int) env('NYK_LOGGER_RATE_MAX', 20),
        'decay' => (int) env('NYK_LOGGER_RATE_DECAY', 60), // minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Ignore Rules
    |--------------------------------------------------------------------------
    | Exceptions of these classes (or subclasses) and log messages matching
    | these regex patterns are never alerted on. Great for silencing noisy,
    | non-actionable errors like 404s and validation failures.
    */
    'ignore_exceptions' => [
        // \Illuminate\Auth\AuthenticationException::class,
        // \Illuminate\Validation\ValidationException::class,
        // \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
    ],

    'ignore_messages' => [
        // '/Route \[.*\] not defined/',
    ],

    /*
    |--------------------------------------------------------------------------
    | Redaction
    |--------------------------------------------------------------------------
    | Context/request keys whose values are replaced with [REDACTED] before
    | anything leaves your app. Matching is case-insensitive. Value patterns
    | (regex) scrub secrets found anywhere in string values.
    */
    'redact' => [
        'keys' => [
            'password',
            'password_confirmation',
            'token',
            'access_token',
            'refresh_token',
            'secret',
            'authorization',
            'api_key',
            'api-key',
            'apikey',
            'credit_card',
            'card_number',
            'cvv',
        ],
        'patterns' => [
            '/(?i)(bearer\s+)[a-z0-9._\-]+/',
        ],
    ],
];
