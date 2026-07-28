{{--
    Shared skeleton for the themed error pages. Values below are the design
    tokens' actual hex/rem values, hardcoded rather than referencing
    frontend/src/style.css's @theme block: these pages must render even when
    public/build is missing (§10, #94), so they cannot depend on the Vite
    build output. Keep in sync with style.css's --color-canvas/--color-action/
    --color-action-hover/--color-text/--color-text-muted/--radius-md by hand.
--}}
<!doctype html>
<html lang="en" style="background-color: #000; color-scheme: dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $code }} &mdash; TaskConnect</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #000000;
            color: #ebebeb;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
        }
        .card { max-width: 28rem; padding: 2rem; text-align: center; }
        .code { font-size: 3rem; font-weight: 700; color: #814dde; margin: 0 0 0.5rem; }
        .title { font-size: 1.25rem; font-weight: 600; margin: 0 0 0.5rem; }
        .message { color: #b0b0b0; margin: 0 0 1.5rem; }
        a.action {
            display: inline-block;
            background: #814dde;
            color: #ffffff;
            text-decoration: none;
            padding: 0.5rem 1.25rem;
            border-radius: 0.5rem;
            font-weight: 500;
        }
        a.action:hover { background: #1f0d69; }
        a.action:focus-visible { outline: 2px solid #814dde; outline-offset: 2px; }
    </style>
</head>
<body>
    <div class="card">
        <p class="code">{{ $code }}</p>
        <p class="title">{{ $title }}</p>
        <p class="message">{{ $message }}</p>
        <a class="action" href="{{ url('/') }}">Back to TaskConnect</a>
    </div>
</body>
</html>
