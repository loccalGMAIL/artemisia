<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ __('landing.title') }}</title>

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="min-h-screen flex flex-col items-center justify-center gap-6 bg-white text-gray-900">
        <h1 class="text-4xl font-semibold">{{ __('landing.title') }}</h1>
        <p class="text-lg text-gray-600">{{ __('landing.tagline') }}</p>

        <nav class="flex gap-4">
            <a class="underline" href="{{ route('filament.staff.auth.login') }}">{{ __('landing.staff_access') }}</a>
            <a class="underline" href="{{ route('filament.client.auth.login') }}">{{ __('landing.client_access') }}</a>
        </nav>
    </body>
</html>
