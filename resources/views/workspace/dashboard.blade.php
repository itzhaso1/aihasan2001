<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">لوحة {{ $workspace->name }}</h2>
                <p class="mt-1 text-xs text-slate-500">واجهة عمل موحدة بتقسيمات واضحة حسب الوحدات.</p>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @include('workspace.partials.nav')
            @include('partials.flash')

            @php
                $moduleCards = [
                    [
                        'title' => 'Dashboard',
                        'description' => 'ملخص حالة مساحة العمل ومؤشرات الأداء.',
                        'icon' => 'home',
                        'links' => [
                            ['label' => 'الرئيسية', 'route' => 'workspace.dashboard', 'active' => 'workspace.dashboard'],
                        ],
                    ],
                    [
                        'title' => 'Products & Inventory',
                        'description' => 'التصنيفات والمنتجات وإدارة المخزون.',
                        'icon' => 'box',
                        'links' => [
                            ['label' => 'التصنيفات', 'route' => 'workspace.categories.index', 'active' => 'workspace.categories.*'],
                            ['label' => 'المنتجات', 'route' => 'workspace.products.index', 'active' => 'workspace.products.*'],
                            ['label' => 'المخزون', 'route' => 'workspace.inventory.index', 'active' => 'workspace.inventory.*'],
                        ],
                    ],
                    [
                        'title' => 'POS / Cashier',
                        'description' => 'واجهة الكاشير، إدارة الطاولات، وطلبات QR Menu.',
                        'icon' => 'wallet',
                        'links' => [
                            ['label' => 'POS / Cashier', 'route' => 'workspace.pos.cashier.index', 'active' => 'workspace.pos.*'],
                        ],
                    ],
                    [
                        'title' => 'Communication',
                        'description' => 'المحادثات والبريد الإلكتروني وواتساب.',
                        'icon' => 'chat',
                        'links' => [
                            ['label' => 'المحادثات', 'route' => 'workspace.conversations.index', 'active' => 'workspace.conversations.*'],
                            ['label' => 'Channels', 'route' => 'workspace.channels.index', 'active' => 'workspace.channels.*'],
                            ['label' => 'البريد الإلكتروني', 'route' => 'workspace.emails.index', 'active' => 'workspace.emails.*'],
                            ['label' => 'واتساب', 'route' => 'workspace.whatsapp-accounts.index', 'active' => 'workspace.whatsapp-accounts.*'],
                        ],
                    ],
                    [
                        'title' => 'Payments & Subscriptions',
                        'description' => 'المدفوعات، بوابات الدفع، والاشتراكات.',
                        'icon' => 'wallet',
                        'links' => [
                            ['label' => 'المدفوعات', 'route' => 'workspace.payments.index', 'active' => 'workspace.payments.*'],
                            ['label' => 'بوابات الدفع', 'route' => 'workspace.payment-gateways.index', 'active' => 'workspace.payment-gateways.*'],
                            ['label' => 'الاشتراكات', 'route' => 'workspace.subscriptions.index', 'active' => 'workspace.subscriptions.*'],
                        ],
                    ],
                    [
                        'title' => 'Employees',
                        'description' => 'إدارة موظفي مساحة العمل.',
                        'icon' => 'id-card',
                        'links' => [
                            ['label' => 'Workspace Employees', 'route' => 'workspace.employees.index', 'active' => 'workspace.employees.*'],
                        ],
                    ],
                    [
                        'title' => 'Finance',
                        'description' => 'وحدة الفوترة والحسابات بشكل مستقل.',
                        'icon' => 'bank',
                        'links' => [
                            ['label' => 'Finance Dashboard', 'route' => 'workspace.finance.dashboard', 'active' => 'workspace.finance.dashboard'],
                            ['label' => 'Invoices', 'route' => 'workspace.finance.invoices.index', 'active' => 'workspace.finance.invoices.*'],
                            ['label' => 'Accounting', 'route' => 'workspace.finance.accounting.dashboard', 'active' => 'workspace.finance.accounting.*'],
                            ['label' => 'Expenses', 'route' => 'workspace.finance.expenses.index', 'active' => 'workspace.finance.expenses.*'],
                            ['label' => 'Payroll', 'route' => 'workspace.finance.payroll.index', 'active' => 'workspace.finance.payroll.*'],
                            ['label' => 'Banks', 'route' => 'workspace.finance.banks.index', 'active' => 'workspace.finance.banks.*'],
                            ['label' => 'VAT', 'route' => 'workspace.finance.vat.index', 'active' => 'workspace.finance.vat.*'],
                            ['label' => 'Reports', 'route' => 'workspace.finance.reports.index', 'active' => 'workspace.finance.reports.*'],
                            ['label' => 'Finance Employees', 'route' => 'workspace.finance.employees.index', 'active' => 'workspace.finance.employees.*'],
                            ['label' => 'العقود', 'route' => 'workspace.finance.contracts.index', 'active' => 'workspace.finance.contracts.*'],
                            ['label' => 'Finance Settings', 'route' => 'workspace.finance.settings.index', 'active' => 'workspace.finance.settings.*'],
                        ],
                    ],
                    [
                        'title' => 'Appointments',
                        'description' => 'إدارة الحجز والمواعيد.',
                        'icon' => 'calendar',
                        'links' => [
                            ['label' => 'Appointments', 'route' => 'workspace.appointments.dashboard', 'active' => 'workspace.appointments.*'],
                            ['label' => 'Website Builder', 'route' => 'workspace.appointments.website.overview', 'active' => 'workspace.appointments.website.*'],
                        ],
                    ],
                    [
                        'title' => 'AI',
                        'description' => 'إعدادات الذكاء الاصطناعي.',
                        'icon' => 'spark',
                        'links' => [
                            ['label' => 'AI Settings', 'route' => 'workspace.ai-settings.edit', 'active' => 'workspace.ai-settings.*'],
                        ],
                    ],
                ];

                $icons = [
                    'home' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M3 10.75 12 3l9 7.75V21a1 1 0 0 1-1 1h-5.5v-7h-5v7H4a1 1 0 0 1-1-1V10.75Z" />',
                    'box' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="m3.75 7.5 8.25-4.5 8.25 4.5m-16.5 0 8.25 4.5m8.25-4.5v9L12 21.75m0-9.75v9.75" />',
                    'users' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M16 19.5v-1.125A3.375 3.375 0 0 0 12.625 15h-4.25A3.375 3.375 0 0 0 5 18.375V19.5m11-9.375a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Zm-8.75 0a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />',
                    'chat' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M7.5 17.25H4.875A1.875 1.875 0 0 1 3 15.375V5.625A1.875 1.875 0 0 1 4.875 3.75h14.25A1.875 1.875 0 0 1 21 5.625v9.75a1.875 1.875 0 0 1-1.875 1.875H12l-4.5 3v-3Z" />',
                    'wallet' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M2.25 7.5A2.25 2.25 0 0 1 4.5 5.25h15A2.25 2.25 0 0 1 21.75 7.5v9A2.25 2.25 0 0 1 19.5 18.75h-15A2.25 2.25 0 0 1 2.25 16.5v-9Zm15.75 4.5h3.75v3H18a1.5 1.5 0 0 1 0-3Z" />',
                    'id-card' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h12A2.25 2.25 0 0 1 20.25 6v12A2.25 2.25 0 0 1 18 20.25H6A2.25 2.25 0 0 1 3.75 18V6Zm4.5 3.75a2.25 2.25 0 1 0 4.5 0 2.25 2.25 0 0 0-4.5 0Zm-.75 6h6m1.5-6h2.25m-2.25 3h2.25m-2.25 3h2.25" />',
                    'bank' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M3 9.75 12 4.5l9 5.25M4.5 10.5V18m5.25-7.5V18m5.25-7.5V18m5.25-7.5V18M3 21h18" />',
                    'calendar' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M7.5 2.25v3m9-3v3m-12 3h15m-15 0V19.5a1.5 1.5 0 0 0 1.5 1.5h12a1.5 1.5 0 0 0 1.5-1.5V8.25m-15 0A1.5 1.5 0 0 1 6 6.75h12a1.5 1.5 0 0 1 1.5 1.5" />',
                    'spark' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M12 3.75 13.9 8.1 18.25 10l-4.35 1.9L12 16.25l-1.9-4.35L5.75 10l4.35-1.9L12 3.75Zm6.75 12.75 1.125 2.625L22.5 20.25l-2.625 1.125L18.75 24l-1.125-2.625L15 20.25l2.625-1.125L18.75 16.5Z" />',
                ];

                foreach ($moduleCards as &$moduleCard) {
                    $moduleCard['links'] = collect($moduleCard['links'])
                        ->filter(fn (array $link): bool => \Illuminate\Support\Facades\Route::has($link['route']))
                        ->values()
                        ->all();
                    $moduleCard['is_active'] = collect($moduleCard['links'])
                        ->contains(fn (array $link): bool => request()->routeIs($link['active'] ?? $link['route']));
                }
                unset($moduleCard);
            @endphp

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                    <p class="text-sm text-gray-500">المحادثات</p>
                    <p class="mt-2 text-2xl font-bold">{{ $stats['conversations'] }}</p>
                </div>
                <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                    <p class="text-sm text-gray-500">حالة الاشتراك</p>
                    <p class="mt-2 text-2xl font-bold">{{ $stats['subscription_status'] }}</p>
                </div>
                <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                    <p class="text-sm text-gray-500">استهلاك AI (30 يوم)</p>
                    <p class="mt-2 text-2xl font-bold">{{ number_format($stats['ai_tokens_30d']) }}</p>
                </div>
                @if($isCommercial)
                    <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                        <p class="text-sm text-gray-500">طلبات اليوم</p>
                        <p class="mt-2 text-2xl font-bold">{{ $stats['orders_today'] }}</p>
                    </div>
                    <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                        <p class="text-sm text-gray-500">طلبات مدفوعة</p>
                        <p class="mt-2 text-2xl font-bold">{{ $stats['paid_orders'] }}</p>
                    </div>
                    <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                        <p class="text-sm text-gray-500">إجمالي المبيعات</p>
                        <p class="mt-2 text-2xl font-bold">{{ number_format($stats['sales_total'], 2) }}</p>
                    </div>
                    <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                        <p class="text-sm text-gray-500">العملاء</p>
                        <p class="mt-2 text-2xl font-bold">{{ $stats['customers'] }}</p>
                    </div>
                    <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                        <p class="text-sm text-gray-500">المنتجات</p>
                        <p class="mt-2 text-2xl font-bold">{{ $stats['products'] }}</p>
                    </div>
                    <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                        <p class="text-sm text-gray-500">مدفوعات ناجحة</p>
                        <p class="mt-2 text-2xl font-bold">{{ $stats['paid_payments'] }}</p>
                    </div>
                @endif
            </div>

            @if(!empty($entitlements['comparison']))
                <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" dir="rtl">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="text-base font-bold text-slate-900">مزايا الباقة الحالية</h3>
                            <p class="text-xs text-slate-500">
                                الباقة:
                                {{ $entitlements['plan']['name'] ?? '—' }}
                                @if(!empty($entitlements['plan']['tier']))
                                    ({{ strtoupper($entitlements['plan']['tier']) }})
                                @endif
                            </p>
                        </div>
                        <a href="{{ route('workspace.subscriptions.index') }}" class="text-sm font-semibold text-[#067e6b] underline">إدارة الاشتراك</a>
                    </div>
                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach($entitlements['comparison'] as $row)
                            <div class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50 px-3 py-2 text-sm">
                                <span class="text-slate-700">{{ $row['label'] }}</span>
                                <span class="{{ !empty($row['enabled']) ? 'text-emerald-600' : 'text-slate-300' }} font-bold">
                                    {{ !empty($row['enabled']) ? '✅' : '❌' }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">الاشتراك والاستخدام</h3>
                        <p class="text-xs text-slate-500">بيانات مباشرة من الاشتراك الحالي واستهلاك الرسائل في نفس فترة الاشتراك.</p>
                    </div>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                        Status: {{ $subscriptionUsage['status'] }}
                    </span>
                </div>

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                        <p class="text-xs text-slate-500">Current Plan</p>
                        <p class="mt-2 text-sm font-bold text-slate-900">{{ $subscriptionUsage['plan_name'] ?? 'No active plan' }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                        <p class="text-xs text-slate-500">Expiry Date</p>
                        <p class="mt-2 text-sm font-bold text-slate-900">{{ $subscriptionUsage['expires_at']?->format('Y-m-d H:i') ?? '-' }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                        <p class="text-xs text-slate-500">Messages Used</p>
                        <p class="mt-2 text-sm font-bold text-slate-900">{{ number_format((int) $subscriptionUsage['messages_used']) }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                        <p class="text-xs text-slate-500">Messages Limit</p>
                        <p class="mt-2 text-sm font-bold text-slate-900">
                            {{ $subscriptionUsage['messages_limit'] !== null ? number_format((int) $subscriptionUsage['messages_limit']) : 'Unlimited / Not set' }}
                        </p>
                    </div>
                </div>

                @if($subscriptionUsage['messages_limit'] !== null)
                    @php
                        $progress = max(0, min(100, (float) ($subscriptionUsage['usage_percent'] ?? 0)));
                    @endphp
                    <div class="mt-5">
                        <div class="mb-2 flex items-center justify-between text-xs text-slate-600">
                            <span>Usage Percentage</span>
                            <span>{{ number_format($progress, 1) }}%</span>
                        </div>
                        <div class="h-3 overflow-hidden rounded-full bg-slate-100">
                            <div
                                class="h-full rounded-full {{ $subscriptionUsage['is_over_limit'] ? 'bg-rose-500' : ($subscriptionUsage['is_near_limit'] ? 'bg-amber-500' : 'bg-emerald-500') }}"
                                style="width: {{ $progress }}%;"
                            ></div>
                        </div>

                        @if($subscriptionUsage['is_over_limit'])
                            <p class="mt-3 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700">
                                تجاوزت حد الرسائل المحدد في خطتك الحالية.
                            </p>
                        @elseif($subscriptionUsage['is_near_limit'])
                            <p class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-700">
                                تنبيه: وصلت لاستهلاك مرتفع ويُنصح بمتابعة الاستخدام قبل بلوغ الحد.
                            </p>
                        @endif
                    </div>
                @endif
            </section>

            <section class="mt-6">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-slate-900">Modules</h3>
                    <p class="text-xs text-slate-500">تنظيم سريع للوحدات مع نفس الروابط الحالية.</p>
                </div>
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @foreach($moduleCards as $card)
                        @continue(empty($card['links']))
                        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                            <div class="mb-3 flex items-start gap-3">
                                <span class="flex h-10 w-10 items-center justify-center rounded-xl {{ $card['is_active'] ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700' }}">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">{!! $icons[$card['icon']] ?? '' !!}</svg>
                                </span>
                                <div>
                                    <h4 class="text-sm font-bold text-slate-900">{{ $card['title'] }}</h4>
                                    <p class="mt-1 text-xs text-slate-500">{{ $card['description'] }}</p>
                                </div>
                            </div>
                            <div class="space-y-1.5">
                                @foreach($card['links'] as $link)
                                    @php
                                        $isActive = request()->routeIs($link['active'] ?? $link['route']);
                                    @endphp
                                    <a
                                        href="{{ route($link['route']) }}"
                                        class="{{ $isActive ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 text-slate-700 hover:border-slate-300 hover:bg-slate-50' }} flex items-center justify-between rounded-xl border px-3 py-2 text-sm font-medium transition"
                                    >
                                        <span>{{ $link['label'] }}</span>
                                        <svg class="h-4 w-4 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="m14.25 6.75-6 5.25 6 5.25" />
                                        </svg>
                                    </a>
                                @endforeach
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
