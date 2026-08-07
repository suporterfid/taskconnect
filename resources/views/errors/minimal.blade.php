{{--
    Independent error shell: it deliberately repeats the canonical semantic
    values because an error response must remain readable when the Vite bundle
    is unavailable. Keep both modes synchronized with frontend/src/style.css.
--}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>{{ $code }} &mdash; TaskConnect</title>
    <style>
        :root {
            color-scheme: light;
            --color-bg-canvas: #FFFFFF;
            --color-bg-elevated: #FFFFFF;
            --color-text-primary: #252525;
            --color-text-secondary: #5F5F5F;
            --color-border-default: #D9D7D3;
            --color-action-primary: #1A6DC1;
            --color-action-primary-hover: #14599E;
            --color-action-primary-active: #104B86;
            --color-action-primary-content: #FFFFFF;
            --color-focus-ring: #1A6DC1;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                color-scheme: dark;
                --color-bg-canvas: #191919;
                --color-bg-elevated: #252525;
                --color-text-primary: #F1F1EF;
                --color-text-secondary: #C6C6C2;
                --color-border-default: #4A4A4A;
                --color-action-primary: #529CCA;
                --color-action-primary-hover: #70B4DE;
                --color-action-primary-active: #3E83B5;
                --color-action-primary-content: #111111;
                --color-focus-ring: #79B8E8;
            }
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-block-size: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: max(16px, env(safe-area-inset-top)) max(16px, env(safe-area-inset-right)) max(16px, env(safe-area-inset-bottom)) max(16px, env(safe-area-inset-left));
            background: var(--color-bg-canvas);
            color: var(--color-text-primary);
            font-family: Inter, "Noto Sans", "Noto Sans Arabic", "Noto Sans Hebrew", "Noto Sans SC", "Noto Sans Thai", "Noto Sans Devanagari", Arial, sans-serif;
            font-size: 16px;
            line-height: 1.5;
        }
        .card {
            inline-size: min(100%, 32rem);
            padding: clamp(1rem, 6vw, 2rem);
            border: 1px solid var(--color-border-default);
            border-radius: 8px;
            background: var(--color-bg-elevated);
            text-align: center;
            overflow-wrap: anywhere;
        }
        .code { font-size: clamp(2rem, 12vw, 3rem); line-height: 1.1; font-weight: 700; margin: 0 0 0.5rem; }
        .title { font-size: 1.25rem; line-height: 1.4; font-weight: 600; margin: 0 0 0.5rem; }
        .message { color: var(--color-text-secondary); margin: 0 0 1.5rem; }
        a.action {
            display: inline-flex;
            min-inline-size: 44px;
            min-block-size: 44px;
            max-inline-size: 100%;
            align-items: center;
            justify-content: center;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            background: var(--color-action-primary);
            color: var(--color-action-primary-content);
            font-weight: 500;
            text-align: center;
            text-decoration: none;
            overflow-wrap: anywhere;
        }
        a.action:hover { background: var(--color-action-primary-hover); }
        a.action:active { background: var(--color-action-primary-active); }
        a.action:focus-visible { outline: 2px solid var(--color-focus-ring); outline-offset: 2px; }

        @media (forced-colors: active) {
            .card, a.action { border: 1px solid CanvasText; forced-color-adjust: auto; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation-duration: 1ms !important; transition-duration: 1ms !important; }
        }
    </style>
</head>
<body>
    <main class="card">
        <p class="code">{{ $code }}</p>
        <h1 class="title">{{ $title }}</h1>
        <p class="message">{{ $message }}</p>
        <a class="action" href="{{ url('/') }}">{{ __('errors.back') }}</a>
    </main>
</body>
</html>
