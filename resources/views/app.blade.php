<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover, interactive-widget=resizes-content">
        <meta name="theme-color" media="(prefers-color-scheme: light)" content="#f5f6f6">
        <meta name="theme-color" media="(prefers-color-scheme: dark)" content="#151616">
        <meta name="color-scheme" content="light dark">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @if (\App\Services\NativeRuntime::available())
            <meta name="buff-native-runtime" content="true">
        @endif
        <title inertia>{{ config('app.name', 'Buff') }}</title>
        <link rel="icon" type="image/png" href="/icon.png">
        @if (request()->is('settings/account'))
            <link rel="modulepreload" href="{{ Vite::asset('resources/js/Pages/Settings/Account.vue') }}" crossorigin>
        @endif
        @vite(['resources/css/app.css', 'resources/js/app.ts'])
        @inertiaHead
    </head>
    <body>
        @inertia
    </body>
</html>
