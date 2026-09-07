<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'HASEM') }} | منصة حاسم</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#F7FCFB] text-gray-900 antialiased">
    <div class="relative overflow-hidden">
        <div class="absolute inset-x-0 -top-24 -z-10 transform-gpu overflow-hidden blur-3xl">
            <div class="relative right-1/2 aspect-[1200/520] w-[72rem] bg-gradient-to-r from-[#c8f5ec] via-[#dff9f3] to-[#eafbf7] opacity-90"></div>
        </div>

        <header class="sticky top-0 z-30 border-b border-gray-200 bg-white/95 backdrop-blur">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                <a href="/" class="flex items-center gap-3">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-[#06C2A4] text-sm font-extrabold text-white">HA</span>
                    <span class="text-xl font-extrabold tracking-tight text-[#06C2A4]">حاسم</span>
                </a>
                <nav class="hidden items-center gap-6 text-sm text-gray-600 md:flex">
                    <a href="#features" class="hover:text-[#06C2A4]">الميزات</a>
                    <a href="#pricing" class="hover:text-[#06C2A4]">الاشتراكات</a>
                    <a href="#support" class="hover:text-[#06C2A4]">الدعم</a>
                </nav>
                <div class="flex items-center gap-2">
                    @auth
                        <a href="{{ route('workspace.subscriptions.index') }}" class="rounded-lg bg-[#06C2A4] px-4 py-2 text-sm font-semibold text-white hover:bg-[#04a98e]">اشتراكي</a>
                    @else
                        <a href="{{ route('login') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">تسجيل الدخول</a>
                        <a href="{{ route('register') }}" class="rounded-lg bg-[#06C2A4] px-4 py-2 text-sm font-semibold text-white hover:bg-[#04a98e]">إنشاء حساب</a>
                    @endauth
                </div>
            </div>
        </header>

        <main>
            <section class="mx-auto grid max-w-7xl items-center gap-10 px-4 pb-16 pt-16 sm:px-6 lg:grid-cols-2 lg:px-8 lg:pb-24 lg:pt-24">
                <div>
                    <span class="inline-flex items-center rounded-full border border-[#B4EFE3] bg-[#E8FAF6] px-3 py-1 text-xs font-semibold text-[#069c83]">
                        منصة حاسم لإدارة الأعمال والمحادثات الذكية
                    </span>
                    <h1 class="mt-5 text-4xl font-extrabold leading-tight text-gray-900 sm:text-5xl">
                        كل ما تحتاجه لتشغيل
                        <span class="text-[#06C2A4]">المحادثات، الطلبات، والمبيعات</span>
                        من مكان واحد
                    </h1>
                    <p class="mt-6 text-lg leading-8 text-gray-600">
                        منصة SaaS عربية بهوية احترافية موحدة، تدعم إدارة فرق العمل، المنتجات، المخزون، المدفوعات، وتكامل الذكاء الاصطناعي.
                    </p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        @auth
                            <a href="{{ route('workspace.subscriptions.index') }}" class="rounded-xl bg-[#06C2A4] px-6 py-3 text-sm font-semibold text-white shadow-sm hover:bg-[#04a98e]">إدارة الاشتراك</a>
                        @else
                            <a href="{{ route('register') }}" class="rounded-xl bg-[#06C2A4] px-6 py-3 text-sm font-semibold text-white shadow-sm hover:bg-[#04a98e]">ابدأ مجانًا</a>
                            <a href="{{ route('login') }}" class="rounded-xl border border-gray-300 px-6 py-3 text-sm font-semibold text-gray-700 hover:bg-gray-50">لدي حساب بالفعل</a>
                        @endauth
                    </div>
                </div>

                <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-xl shadow-[#dff9f3]/60">
                    <h2 class="text-lg font-semibold text-gray-900">مؤشرات تشغيلية سريعة</h2>
                    <div class="mt-5 grid grid-cols-2 gap-3 text-sm">
                        <div class="rounded-xl border border-gray-100 bg-[#F8FDFC] p-4"><p class="font-semibold text-gray-800">Workspaces</p><p class="mt-1 text-gray-500">عزل كامل لكل عميل</p></div>
                        <div class="rounded-xl border border-gray-100 bg-[#F8FDFC] p-4"><p class="font-semibold text-gray-800">WhatsApp</p><p class="mt-1 text-gray-500">Webhook + Automation</p></div>
                        <div class="rounded-xl border border-gray-100 bg-[#F8FDFC] p-4"><p class="font-semibold text-gray-800">AI</p><p class="mt-1 text-gray-500">ردود ذكية دقيقة</p></div>
                        <div class="rounded-xl border border-gray-100 bg-[#F8FDFC] p-4"><p class="font-semibold text-gray-800">Orders</p><p class="mt-1 text-gray-500">دورة طلب كاملة</p></div>
                    </div>
                </div>
            </section>

            <section id="features" class="border-y border-gray-100 bg-white py-16">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="mx-auto max-w-3xl text-center">
                        <h2 class="text-3xl font-bold text-gray-900">الميزات الأساسية في منصة حاسم</h2>
                        <p class="mt-4 text-gray-600">واجهة موحدة بنفس الهوية البصرية داخل وخارج النظام.</p>
                    </div>
                    <div class="mt-10 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <article class="rounded-2xl border border-gray-200 bg-white p-6">
                            <h3 class="text-lg font-semibold">مساحات عمل معزولة</h3>
                            <p class="mt-2 text-sm text-gray-600">عزل كامل للبيانات بين الأفراد والشركات والمتاجر.</p>
                        </article>
                        <article class="rounded-2xl border border-gray-200 bg-white p-6">
                            <h3 class="text-lg font-semibold">تكامل Google AI Studio</h3>
                            <p class="mt-2 text-sm text-gray-600">ردود ذكية معززة ببيانات المنتجات الفعلية من قاعدة البيانات.</p>
                        </article>
                        <article class="rounded-2xl border border-gray-200 bg-white p-6">
                            <h3 class="text-lg font-semibold">إدارة تجارية متكاملة</h3>
                            <p class="mt-2 text-sm text-gray-600">منتجات، مخزون، عملاء، طلبات، ومدفوعات من لوحة واحدة.</p>
                        </article>
                    </div>
                </div>
            </section>

            <section id="pricing" class="py-16">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="mx-auto max-w-3xl text-center">
                        <h2 class="text-3xl font-bold text-gray-900">الباقات الرسمية</h2>
                        <p class="mt-4 text-gray-600">أربع باقات فقط: المبتدئة · الاحترافية · الأعمال · المؤسسات</p>
                    </div>
                    @php
                        $officialPlans = $officialPlans ?? collect();
                    @endphp
                    @if($officialPlans->count() > 0)
                        <div class="mt-10 grid gap-5 md:grid-cols-2 lg:grid-cols-4">
                            @foreach($officialPlans as $plan)
                                <article class="rounded-2xl border {{ $plan->code === 'pro' ? 'border-2 border-[#06C2A4] bg-[#E8FAF6]' : 'border-gray-200 bg-white' }} p-6">
                                    <p class="text-sm font-semibold {{ $plan->code === 'pro' ? 'text-[#069c83]' : 'text-gray-500' }}">{{ strtoupper($plan->code) }}</p>
                                    <h3 class="mt-2 text-xl font-bold text-gray-900">{{ $plan->display_name_ar ?: $plan->name }}</h3>
                                    <p class="mt-2 text-2xl font-extrabold text-[#06C2A4]">
                                        {{ number_format((float) $plan->price, 2) }} <span class="text-sm text-gray-600">{{ $plan->currency }}</span>
                                    </p>
                                    <p class="mt-1 text-xs text-gray-500">الفترة: {{ $plan->billing_period === 'yearly' ? 'سنوي' : ($plan->billing_period === 'monthly' ? 'شهري' : $plan->billing_period) }}</p>
                                    <div class="mt-3 flex flex-wrap gap-1">
                                        @foreach(array_slice($plan->features ?? [], 0, 4) as $feature)
                                            <span class="rounded-md bg-white/80 px-2 py-1 text-[11px] text-gray-700">{{ $feature }}</span>
                                        @endforeach
                                    </div>
                                    <div class="mt-5">
                                        @auth
                                            <a href="{{ route('workspace.subscriptions.index') }}" class="inline-flex w-full justify-center rounded-lg bg-[#06C2A4] px-4 py-2 text-sm font-semibold text-white hover:bg-[#04a98e]">
                                                اشترك الآن
                                            </a>
                                        @else
                                            <a href="{{ route('register') }}" class="inline-flex w-full justify-center rounded-lg bg-[#06C2A4] px-4 py-2 text-sm font-semibold text-white hover:bg-[#04a98e]">
                                                سجّل وابدأ الاشتراك
                                            </a>
                                        @endauth
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @else
                        <div class="mt-8 rounded-xl border border-dashed border-gray-300 bg-white p-6 text-center text-sm text-gray-500">
                            لا توجد باقات مفعلة حاليًا. يمكن لمدير المنصة إعدادها من لوحة الأدمن.
                        </div>
                    @endif
                </div>
            </section>
        </main>

        <footer id="support" class="border-t border-gray-200 bg-white">
            @include('partials.company-footer-content', ['showLinks' => true])
        </footer>
    </div>
    @include('partials.ai-assistant-widget')
</body>
</html>
