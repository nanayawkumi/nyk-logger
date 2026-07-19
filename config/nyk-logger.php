<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| nyk-logger configuration
|--------------------------------------------------------------------------
| Only the Brevo (mail) credentials are wired to your .env — those are the
| only keys you need to set there. Everything else uses the sensible defaults
| below; edit a value here if you want to override it.
*/

return [

    // Master switch for the whole package.
    'enabled' => true,

    // Environments where capture is active. Add 'local', 'staging', etc.
    'environments' => ['production'],

    // --- Capture sources (each can be toggled independently) ---

    // 1) The logging pipeline (Log::error(), Log::critical(), ...).
    'log' => [
        'enabled' => true,
        'levels' => ['error', 'critical', 'alert', 'emergency'],
    ],

    // 2) The framework's exception handler — catches uncaught exceptions that
    //    Laravel does NOT log by default (500 server errors, and selected 4xx
    //    such as 403 / 404).
    'exceptions' => [
        'enabled' => true,
        // Statuses to alert on. Listing 500 also matches ANY server error (>= 500).
        'http_statuses' => [500],
        // ...or alert on specific throwable types regardless of status, e.g.:
        //   \Illuminate\Auth\Access\AuthorizationException::class,           // 403
        //   \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class, // 404
        'types' => [],
    ],

    // Delivery channels: 'mail', 'slack', or both.
    'channels' => ['mail'],

    /*
    |--------------------------------------------------------------------------
    | Brevo (mail) — the ONLY settings you need in your .env
    |--------------------------------------------------------------------------
    | The sender must be a verified sender in your Brevo account.
    */
    'mail' => [
        'api_key' => env('NYK_LOGGER_API_KEY'),
        'to_email' => env('NYK_LOGGER_EMAIL'),
        'to_name' => env('NYK_LOGGER_NAME', 'System Administrator'),
        'from_email' => env('NYK_LOGGER_FROM_EMAIL', env('MAIL_FROM_ADDRESS')),
        'from_name' => env('NYK_LOGGER_FROM_NAME', env('APP_NAME', 'NYK Logger')),
    ],

    // Slack incoming webhook. Only needed if 'slack' is in channels. Keep the
    // secret out of source control, e.g. env('NYK_LOGGER_SLACK_WEBHOOK').
    'slack' => [
        'webhook_url' => null,
    ],

    // Push delivery onto the queue so failing requests never wait on the API.
    'queue' => [
        'enabled' => false,
        'connection' => null, // null = the app's default connection
        'queue' => 'default',
    ],

    // Minutes an identical error is suppressed after its first alert.
    'cooldown' => 30,

    // Global ceiling on alerts across ALL errors within the window.
    'rate_limit' => [
        'max' => 20,  // set to 0 to disable
        'decay' => 60, // window in minutes
    ],

    // Never alert on these exception classes (incl. subclasses) or on log
    // messages matching these regex patterns.
    'ignore_exceptions' => [
        // \Illuminate\Validation\ValidationException::class,
    ],
    'ignore_messages' => [
        // '/Route \[.*\] not defined/',
    ],

    // Replace these values with [REDACTED] before anything leaves your app.
    // Keys match case-insensitively; patterns scrub secrets inside strings.
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
