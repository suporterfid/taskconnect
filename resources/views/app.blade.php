@php
    $manifestPaths = [
        public_path('build/.vite/manifest.json'),
        public_path('build/manifest.json'),
    ];

    $manifest = null;
    foreach ($manifestPaths as $path) {
        if (is_readable($path)) {
            $manifest = json_decode(file_get_contents($path), true);
            break;
        }
    }

    $entry = $manifest['src/main.ts']
        ?? $manifest['index.html']
        ?? null;
    $cssFiles = $entry['css'] ?? [];
    $jsFile = $entry['file'] ?? null;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>TaskConnect</title>
    <link rel="icon" type="image/svg+xml" href="/build/favicon.svg">
    @foreach ($cssFiles as $css)
        <link rel="stylesheet" href="{{ asset('build/'.$css) }}">
    @endforeach
</head>
<body>
    <div id="app"></div>
    @if ($jsFile)
        <script type="module" src="{{ asset('build/'.$jsFile) }}"></script>
    @endif
</body>
</html>
