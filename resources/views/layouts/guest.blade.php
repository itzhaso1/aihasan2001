<!DOCTYPE html>
<html lang="ar" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'HASEM') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-slate-100 font-sans text-gray-900 antialiased">
        <div class="min-h-screen flex flex-col items-center justify-center bg-gradient-to-b from-[#E8FAF6] via-slate-50 to-white px-4 py-8">
            <div class="text-center">
                <a href="/" class="inline-flex flex-col items-center">
                    <x-application-logo class="h-16 w-16 fill-current text-[#06C2A4]" />
                    <span class="mt-3 text-2xl font-extrabold tracking-tight text-[#06C2A4]">حاسم</span>
                </a>
            </div>

            <div class="mt-6 w-full max-w-lg overflow-hidden rounded-2xl bg-white/90 p-6 shadow-xl ring-1 ring-slate-200">
                {{ $slot }}
            </div>
        </div>
        @include('partials.ai-assistant-widget')
    </body>
</html>
