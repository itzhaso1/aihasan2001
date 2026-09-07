<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle ?? 'حاسم المالية' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 font-sans text-slate-900 antialiased">
    <div x-data="{ mobileSidebar: false }" class="min-h-screen">
        <aside class="fixed inset-y-0 right-0 z-40 hidden w-80 border-l border-slate-200 bg-white xl:flex xl:flex-col">
            @include('workspace.finance.partials.sidebar')
        </aside>

        <div class="xl:pr-80">
            <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur">
                <div class="flex h-16 items-center justify-between px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center gap-3">
                        <button @click="mobileSidebar = true" type="button" class="rounded-lg border border-slate-200 p-2 text-slate-600 xl:hidden">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                        <div>
                            <p class="text-sm text-slate-500">{{ request()->attributes->get('workspace')?->name }}</p>
                            <h1 class="text-lg font-bold text-slate-900">{{ $pageTitle ?? 'حاسم المالية' }}</h1>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <form method="GET" action="{{ route('workspace.finance.search') }}" class="hidden sm:block">
                            <input type="search" name="q" value="{{ request('q') }}" placeholder="بحث..." class="w-44 rounded-lg border-slate-300 px-3 py-2 text-xs">
                        </form>
                        <a href="{{ route('workspace.dashboard') }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-100">
                            ← العودة إلى حاسم
                        </a>
                        <span class="hidden rounded-lg bg-[#E8FAF6] px-3 py-2 text-xs font-semibold text-[#0f7668] sm:inline-block">
                            {{ auth()->user()?->name }}
                        </span>
                    </div>
                </div>
            </header>

            <main class="p-4 sm:p-6 lg:p-8">
                @include('partials.flash')
                @yield('content')
            </main>
        </div>

        <div x-cloak x-show="mobileSidebar" class="fixed inset-0 z-50 xl:hidden">
            <button @click="mobileSidebar = false" type="button" class="absolute inset-0 bg-slate-900/50"></button>
            <aside x-transition class="absolute inset-y-0 right-0 w-80 border-l border-slate-200 bg-white">
                <div class="flex items-center justify-between border-b border-slate-200 px-4 py-4">
                    <h2 class="text-base font-bold text-slate-900">حاسم المالية</h2>
                    <button @click="mobileSidebar = false" type="button" class="rounded-md p-2 text-slate-600 hover:bg-slate-100">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="h-[calc(100%-64px)] overflow-y-auto">
                    @include('workspace.finance.partials.sidebar')
                </div>
            </aside>
        </div>
    </div>
</body>
</html>
