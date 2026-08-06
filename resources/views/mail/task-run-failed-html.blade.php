<!--
    Deliberate departure from §2's dark canvas (recorded for #99): email
    stays light. Client dark-mode handling is inconsistent (Outlook ignores
    prefers-color-scheme, many clients strip <style> blocks and invert
    colors unpredictably), so a dark canvas risks unreadable text in a
    client that partially "dark-modes" this markup. Table-based layout and
    inline styles throughout for the same reason: this has to render
    correctly in clients that don't support modern CSS. See #94.
-->
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>TaskConnect</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f5; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f5; padding:24px 0;">
<tr>
<td align="center">
<table role="presentation" width="480" cellpadding="0" cellspacing="0" style="background-color:#ffffff; border-radius:8px; border:1px solid #e5e5e5; max-width:480px; width:100%;">
<tr>
<td style="padding:24px 32px 0 32px;">
<span style="display:inline-block; font-size:18px; font-weight:700; color:#814dde;">TaskConnect</span>
</td>
</tr>
<tr>
<td style="padding:20px 32px 0 32px;">
<p style="margin:0; font-size:16px; font-weight:600; color:#1b1b18;">{{ $heading }}</p>
</td>
</tr>
<tr>
<td style="padding:12px 32px 0 32px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px; color:#3f3f46;">
<tr>
<td style="padding:4px 0; color:#71717a;">{{ $taskLabel }}</td>
<td style="padding:4px 0; text-align:right;">{{ $taskDisplay }}</td>
</tr>
<tr>
<td style="padding:4px 0; color:#71717a;">{{ $runLabel }}</td>
<td style="padding:4px 0; text-align:right; font-family:ui-monospace,SFMono-Regular,Consolas,monospace;">{{ $runId }}</td>
</tr>
<tr>
<td style="padding:4px 0; color:#71717a;">{{ $stateLabel }}</td>
<td style="padding:4px 0; text-align:right;">{{ $state }}</td>
</tr>
<tr>
<td style="padding:4px 0; color:#71717a;">{{ $errorCodeLabel }}</td>
<td style="padding:4px 0; text-align:right;">{{ $errorDisplay }}</td>
</tr>
</table>
</td>
</tr>
<tr>
<td style="padding:24px 32px 32px 32px;">
<table role="presentation" cellpadding="0" cellspacing="0">
<tr>
<td style="border-radius:6px; background-color:#814dde;">
<a href="{{ $runUrl }}" style="display:inline-block; padding:10px 20px; font-size:14px; font-weight:500; color:#ffffff; text-decoration:none;">{{ $viewRunButton }}</a>
</td>
</tr>
</table>
<p style="margin:16px 0 0 0; font-size:12px; color:#a1a1aa;">
{{ $diagnosticsNote }}
</p>
</td>
</tr>
</table>
</td>
</tr>
</table>
</body>
</html>
