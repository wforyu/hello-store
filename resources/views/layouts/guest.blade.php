<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Hello Store') }}</title>

        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0"
             style="background: linear-gradient(135deg, #0f0c29 0%, #302b63 50%, #24243e 100%);">
            <div class="absolute inset-0 overflow-hidden pointer-events-none">
                <div class="absolute -top-40 -right-40 w-80 h-80 bg-amber-500/10 rounded-full blur-3xl"></div>
                <div class="absolute -bottom-40 -left-40 w-96 h-96 bg-purple-500/10 rounded-full blur-3xl"></div>
            </div>

            <div class="relative mb-6">
                <a href="/" class="flex flex-col items-center">
                    <div class="w-20 h-20 flex items-center justify-center bg-gradient-to-br from-amber-400 to-amber-600 rounded-2xl shadow-2xl shadow-amber-500/20">
                        <x-application-logo class="w-12 h-12 fill-current text-white" />
                    </div>
                    <span class="mt-3 text-2xl font-extrabold text-white tracking-tight">{{ config('app.name', 'HELLO STORE') }}</span>
                </a>
            </div>

            <div class="relative w-full sm:max-w-md px-4">
                <div class="bg-white rounded-2xl shadow-2xl shadow-black/30 px-8 py-8">
                    {{ $slot }}
                </div>
                <p class="mt-6 text-center text-sm text-white/40">&copy; {{ date('Y') }} {{ config('app.name', 'Hello Store') }}. All rights reserved.</p>
            </div>
        </div>
    </body>
</html>
