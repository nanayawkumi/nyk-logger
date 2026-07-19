<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $level }} · {{ $appName }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f5f7; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f5f7; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px; max-width:100%; background-color:#ffffff; border-radius:10px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,0.08);">

                    {{-- Header --}}
                    <tr>
                        <td style="background-color:#b91c1c; padding:20px 28px;">
                            <p style="margin:0; color:#fecaca; font-size:12px; letter-spacing:1px; text-transform:uppercase;">{{ $appName }} · {{ $environment }}</p>
                            <h1 style="margin:6px 0 0; color:#ffffff; font-size:20px; font-weight:600;">
                                {{ $level }} Alert
                            </h1>
                        </td>
                    </tr>

                    {{-- Error title / message --}}
                    <tr>
                        <td style="padding:24px 28px 8px;">
                            <p style="margin:0 0 6px; color:#6b7280; font-size:12px; text-transform:uppercase; letter-spacing:0.5px;">Message</p>
                            <p style="margin:0; color:#111827; font-size:16px; line-height:1.5; font-weight:600; word-break:break-word;">
                                {{ $title }}
                            </p>
                            @if(!empty($exceptionClass))
                                <p style="margin:8px 0 0; color:#9ca3af; font-size:13px; font-family:monospace;">{{ $exceptionClass }}</p>
                            @endif
                        </td>
                    </tr>

                    {{-- Meta table --}}
                    <tr>
                        <td style="padding:12px 28px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb; border-radius:8px; border-collapse:separate; overflow:hidden;">
                                @php
                                    $rows = array_filter([
                                        'Environment' => $environment,
                                        'Level'       => $level,
                                        'File'        => $file ? $file.':'.$line : 'N/A',
                                        'Code'        => $code ?? 'N/A',
                                        'URL'         => $url,
                                        'Method'      => $method,
                                        'IP'          => $ip ?? null,
                                        'User ID'     => isset($userId) ? (string) $userId : null,
                                        'User Agent'  => $userAgent ?? null,
                                        'Time'        => $timestamp,
                                    ], static fn ($v) => $v !== null && $v !== '');
                                @endphp
                                @foreach($rows as $label => $value)
                                    <tr>
                                        <td style="padding:10px 14px; background-color:#f9fafb; color:#6b7280; font-size:13px; width:120px; border-bottom:1px solid #e5e7eb; vertical-align:top;">{{ $label }}</td>
                                        <td style="padding:10px 14px; color:#111827; font-size:13px; border-bottom:1px solid #e5e7eb; word-break:break-word; font-family:monospace;">{{ $value }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>

                    {{-- Context (redacted) --}}
                    @if(!empty($context))
                        <tr>
                            <td style="padding:12px 28px 0;">
                                <p style="margin:0 0 8px; color:#6b7280; font-size:12px; text-transform:uppercase; letter-spacing:0.5px;">Context <span style="color:#9ca3af; text-transform:none;">(sensitive values redacted)</span></p>
                                <pre style="margin:0; background-color:#f3f4f6; color:#111827; padding:16px; border-radius:8px; font-size:12px; line-height:1.5; overflow-x:auto; white-space:pre-wrap; word-break:break-word;"><code style="font-family:'SF Mono',Menlo,Consolas,monospace;">{{ json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</code></pre>
                            </td>
                        </tr>
                    @endif

                    {{-- Stack trace --}}
                    @if(!empty($trace))
                        <tr>
                            <td style="padding:12px 28px 24px;">
                                <p style="margin:0 0 8px; color:#6b7280; font-size:12px; text-transform:uppercase; letter-spacing:0.5px;">Stack Trace</p>
                                <pre style="margin:0; background-color:#0d1117; color:#c9d1d9; padding:16px; border-radius:8px; font-size:12px; line-height:1.5; overflow-x:auto; white-space:pre-wrap; word-break:break-word;"><code style="font-family:'SF Mono',Menlo,Consolas,monospace;">{{ $trace }}</code></pre>
                            </td>
                        </tr>
                    @endif

                    {{-- Footer --}}
                    <tr>
                        <td style="padding:16px 28px; background-color:#f9fafb; border-top:1px solid #e5e7eb;">
                            <p style="margin:0; color:#9ca3af; font-size:12px; line-height:1.5;">
                                Sent by <strong>nyk-logger</strong> · Further identical alerts are suppressed during the cooldown window.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
