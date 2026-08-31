<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    <style>
        :root {
            color-scheme: light dark;
        }

        body {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            min-height: 100vh;
            margin: 0;
            font-family: system-ui, sans-serif;
            text-align: center;
        }

        a {
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            background: #2563eb;
            color: #fff;
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <p>{{ $label }}</p>
    <a href="{{ $appLink }}">{{ __('messages.open_in_app') }}</a>

    <script>
        window.location.href = @json($appLink);
    </script>
</body>
</html>
