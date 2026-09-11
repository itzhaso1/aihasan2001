<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-900">{{ __('الاشتراك والباقات') }}</h2>
    </x-slot>

    @php
        $statusLabels = [
            'active' => __('نشط'),
            'trialing' => __('تجريبي'),
            'past_due' => __('متأخر الدفع'),
            'paused' => __('موقوف'),
            'cancelled' => __('ملغى'),
            'expired' => __('منتهي'),
        ];
        $tierLabels = [
            'starter' => 'Starter',
            'pro' => 'Pro',
            'business' => 'Business',
            'enterprise' => 'Enterprise',
        ];
        $currentTier = $entitlements['plan']['tier'] ?? null;
        $meters = $entitlements['meters'] ?? [];
        $upgradeRequired = session('upgrade_required');
    @endphp

    <div class="mx-auto max-w-7xl space-y-6" dir="rtl">
        @include('workspace.partials.nav')
        @include('partials.flash')

        @if($upgradeRequired)
            <div class="rounded-2xl border border-amber-300 bg-amber-50 p-5 text-amber-950">
                <h3 class="text-base font-semibold">{{ __('يلزم ترقية الباقة') }}</h3>
                <p class="mt-2 text-sm">
                    {{ $upgradeRequired['message'] ?? __('هذه الميزة غير متاحة في باقتك الحالية. قم بالترقية للمتابعة.') }}
                </p>
                @if(!empty($upgradeRequired['required_plan']))
                    <p class="mt-1 text-xs text-amber-800">
                        {{ __('الباقة المقترحة') }}: {{ $upgradeRequired['required_plan'] }}
                    </p>
                @endif
                <a href="#compare" class="mt-3 inline-block text-sm font-semibold text-[#067e6b] underline">
                    {{ __('قارن الباقات') }}
                </a>
            </div>
        @endif

        <div class="rounded-2xl border border-[#BDEFE5] bg-[#F3FCFA] p-5">
            <h3 class="text-base font-semibold text-[#067e6b]">{{ __('الباقة الحالية والاستخدام') }}</h3>
            @if($currentSubscription)
                <div class="mt-3 grid gap-3 text-sm text-gray-700 sm:grid-cols-2 lg:grid-cols-4">
                    <p>
                        <span class="font-semibold">{{ __('الخطة') }}:</span>
                        {{ $currentSubscription->plan?->display_name_ar ?: ($currentSubscription->plan?->name ?? ($entitlements['plan']['name'] ?? '-')) }}
                    </p>
                    <p>
                        <span class="font-semibold">{{ __('المستوى') }}:</span>
                        {{ $tierLabels[$currentTier] ?? ($currentTier ?: '-') }}
                    </p>
                    <p>
                        <span class="font-semibold">{{ __('الحالة') }}:</span>
                        {{ $statusLabels[$currentSubscription->status] ?? $currentSubscription->status }}
                    </p>
                    <p>
                        <span class="font-semibold">{{ __('ينتهي') }}:</span>
                        {{ $currentSubscription->current_period_end?->format('Y-m-d') ?? '-' }}
                    </p>
                </div>
                @if($currentSubscription->plan?->description)
                    <p class="mt-3 text-sm text-gray-600">{{ $currentSubscription->plan->description }}</p>
                @endif
            @else
                <p class="mt-2 text-sm text-gray-500">{{ __('لا يوجد اشتراك نشط حالياً.') }}</p>
            @endif

            @if(count($meters) > 0)
                <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($meters as $meterKey => $meter)
                        @php
                            $label = config("plans.meters.{$meterKey}.label", $meterKey);
                            $limit = $meter['limit'] ?? null;
                            $used = (float) ($meter['used'] ?? 0);
                            $pct = $limit && $limit > 0 ? min(100, round(($used / $limit) * 100)) : ($limit === 0.0 || $limit === 0 ? 100 : 0);
                            $barColor = $pct >= 90 ? 'bg-red-500' : ($pct >= 70 ? 'bg-amber-500' : 'bg-[#06C2A4]');
                        @endphp
                        @if($limit !== null)
                            <div class="rounded-xl border border-[#D8F5EF] bg-white p-3">
                                <div class="mb-1 flex items-center justify-between text-xs text-gray-600">
                                    <span class="font-semibold text-gray-800">{{ $label }}</span>
                                    <span>{{ number_format($used) }} / {{ number_format((float) $limit) }}</span>
                                </div>
                                <div class="h-2 overflow-hidden rounded-full bg-gray-100">
                                    <div class="h-full {{ $barColor }} transition-all" style="width: {{ $pct }}%"></div>
                                </div>
                                <p class="mt-1 text-[11px] text-gray-500">
                                    {{ __('المتبقي') }}:
                                    {{ $meter['remaining'] === null ? '∞' : number_format((float) $meter['remaining']) }}
                                </p>
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>

        <div id="compare" class="rounded-2xl border bg-white p-6 scroll-mt-6">
            <h3 class="mb-2 font-semibold text-gray-900">{{ __('مقارنة الباقات') }}</h3>
            <p class="mb-4 text-sm text-gray-600">{{ __('Starter / Pro / Business / Enterprise') }}</p>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-right font-semibold text-gray-700">{{ __('الميزة') }}</th>
                            @foreach($comparisonTiers as $tier)
                                <th class="px-4 py-3 text-center font-semibold text-gray-700">
                                    {{ $tierLabels[$tier] ?? $tier }}
                                    @if($currentTier === $tier)
                                        <span class="mt-1 block text-[10px] font-normal text-[#067e6b]">{{ __('باقتك') }}</span>
                                    @endif
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach($comparisonRows as $row)
                            <tr>
                                <td class="px-4 py-3 text-gray-800">{{ $row['label'] }}</td>
                                @foreach($comparisonTiers as $tier)
                                    @php
                                        $has = in_array($row['key'], $featureMatrix[$tier]['features'] ?? [], true);
                                    @endphp
                                    <td class="px-4 py-3 text-center {{ $has ? 'text-[#067e6b] font-semibold' : 'text-gray-300' }}">
                                        {{ $has ? '✓' : '—' }}
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @php
            // Per-tier visual identity for the plan cards (icon tint, badge, CTA colour).
            $tierThemes = [
                'starter' => [
                    'icon_bg' => 'bg-blue-50', 'icon_text' => 'text-blue-600',
                    'badge' => 'bg-blue-50 text-blue-700',
                    'button' => 'bg-blue-600 hover:bg-blue-700 focus-visible:ring-blue-500',
                    'icon' => 'rocket',
                ],
                'pro' => [
                    'icon_bg' => 'bg-orange-50', 'icon_text' => 'text-orange-500',
                    'badge' => 'bg-orange-50 text-orange-600',
                    'button' => 'bg-orange-500 hover:bg-orange-600 focus-visible:ring-orange-400',
                    'icon' => 'crown',
                ],
                'business' => [
                    'icon_bg' => 'bg-[#E8FAF6]', 'icon_text' => 'text-[#06C2A4]',
                    'badge' => 'bg-[#E8FAF6] text-[#067e6b]',
                    'button' => 'bg-[#06C2A4] hover:bg-[#04a98e] focus-visible:ring-[#06C2A4]',
                    'icon' => 'briefcase',
                ],
                'enterprise' => [
                    'icon_bg' => 'bg-violet-50', 'icon_text' => 'text-violet-600',
                    'badge' => 'bg-violet-50 text-violet-700',
                    'button' => 'bg-violet-600 hover:bg-violet-700 focus-visible:ring-violet-500',
                    'icon' => 'building',
                ],
            ];
            $defaultTheme = $tierThemes['business'];
            $periodLabels = [
                'monthly' => __('شهرياً'),
                'yearly' => __('سنوياً'),
                'lifetime' => __('مدى الحياة'),
            ];
            $popularTier = 'business';
        @endphp

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
            <div class="mb-6">
                <h3 class="text-lg font-bold text-gray-900">{{ __('اختر الباقة المناسبة لك') }}</h3>
                <p class="mt-1 text-sm text-gray-500">{{ __('ابدأ بمساحة تعمل لاحتياجاتك اليوم، ويمكنك الترقية في أي وقت') }}</p>
            </div>

            @if($availablePlans->count() === 0)
                <p class="text-sm text-gray-500">{{ __('لا توجد باقات متاحة حاليًا لنوع مساحة العمل هذه.') }}</p>
            @else
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    @foreach($availablePlans as $plan)
                        @php
                            $isCurrent = $currentSubscription && (int) $currentSubscription->plan_id === (int) $plan->id;
                            $theme = $tierThemes[$plan->tier] ?? $defaultTheme;
                            $isPopular = $plan->tier === $popularTier;
                        @endphp
                        <article class="relative flex flex-col rounded-2xl border-2 bg-white p-5 transition
                            {{ $isCurrent ? 'border-[#06C2A4] bg-[#F7FDFB] shadow-[0_0_0_4px_rgba(6,194,164,0.12)]' : 'border-gray-100 hover:border-gray-200 hover:shadow-md' }}">

                            @if($isPopular)
                                <span class="absolute -top-3 right-5 rounded-full bg-[#06C2A4] px-3 py-1 text-[11px] font-bold text-white shadow-sm">
                                    {{ __('الأكثر شيوعاً') }}
                                </span>
                            @endif

                            <div class="flex items-start justify-between gap-3">
                                <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl {{ $theme['icon_bg'] }} {{ $theme['icon_text'] }}">
                                    @switch($theme['icon'])
                                        @case('rocket')
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.59 14.37a6 6 0 0 1-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 0 0 6.16-12.12A14.98 14.98 0 0 0 9.63 8.41m5.96 5.96a14.926 14.926 0 0 1-5.841 2.58m-.119-8.54a6 6 0 0 0-7.381 5.84h4.8m2.581-5.84a14.927 14.927 0 0 0-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 0 1-2.448-2.448 14.9 14.9 0 0 1 .06-.312m-2.24 2.39a4.493 4.493 0 0 0-1.757 4.306 4.493 4.493 0 0 0 4.306-1.758M16.5 9a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z"/></svg>
                                            @break
                                        @case('crown')
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8.5l4.5 4 4.5-7 4.5 7 4.5-4L19.5 18h-15L3 8.5Z"/><path stroke-linecap="round" d="M5 21h14"/></svg>
                                            @break
                                        @case('building')
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/></svg>
                                            @break
                                        @default
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0M12 12.75h.008v.008H12v-.008Z"/></svg>
                                    @endswitch
                                </span>

                                @if($plan->tier)
                                    <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $theme['badge'] }}">
                                        {{ $tierLabels[$plan->tier] ?? $plan->tier }}
                                    </span>
                                @endif
                            </div>

                            <h4 class="mt-4 text-lg font-bold text-gray-900">{{ $plan->display_name_ar ?: $plan->name }}</h4>

                            <p class="mt-2 flex items-baseline gap-1.5">
                                <span class="text-2xl font-extrabold text-[#06C2A4]">{{ number_format((float) $plan->price, 2) }}</span>
                                <span class="text-sm font-semibold text-gray-500">{{ $plan->currency }}</span>
                            </p>
                            <p class="mt-0.5 text-xs text-gray-400">/ {{ $periodLabels[$plan->billing_period] ?? $plan->billing_period }}</p>

                            @if($plan->description)
                                <p class="mt-3 text-xs leading-relaxed text-gray-600">{{ $plan->description }}</p>
                            @endif

                            <div class="mt-auto pt-5">
                                @if($isCurrent)
                                    <p class="mb-2 text-xs font-semibold text-gray-500">{{ __('بوابة الدفع') }}</p>
                                    <div class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-[#E8FAF6] px-4 py-2.5 text-sm font-bold text-[#067e6b]">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm13.36-1.814a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z" clip-rule="evenodd"/></svg>
                                        {{ __('باقتك الحالية') }}
                                    </div>
                                @else
                                    <form method="POST" action="{{ route('workspace.subscriptions.store') }}" class="space-y-2">
                                        @csrf
                                        <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                                        <label for="payment_provider_{{ $plan->id }}" class="block text-xs font-semibold text-gray-500">{{ __('بوابة الدفع') }}</label>
                                        <select id="payment_provider_{{ $plan->id }}" name="payment_provider"
                                                class="w-full rounded-xl border-gray-200 bg-white text-sm font-semibold text-gray-700 focus:border-[#06C2A4] focus:ring-[#06C2A4]">
                                            <option value="hyperpay">HyperPay</option>
                                            <option value="local">{{ __('تجريبي محلي') }}</option>
                                        </select>
                                        <button type="submit"
                                                class="inline-flex w-full items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-bold text-white shadow-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 {{ $theme['button'] }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
                                            {{ __('متابعة إلى الدفع') }}
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>

        @php
            $pillStyles = [
                'completed' => 'bg-emerald-50 text-emerald-700',
                'paid' => 'bg-emerald-50 text-emerald-700',
                'activated' => 'bg-emerald-50 text-emerald-700',
                'active' => 'bg-emerald-50 text-emerald-700',
                'pending' => 'bg-amber-50 text-amber-700',
                'awaiting_payment' => 'bg-amber-50 text-amber-700',
                'pending_activation' => 'bg-amber-50 text-amber-700',
                'processing' => 'bg-amber-50 text-amber-700',
                'failed' => 'bg-red-50 text-red-700',
                'expired' => 'bg-gray-100 text-gray-600',
                'cancelled' => 'bg-gray-100 text-gray-600',
            ];
            $sessionStateLabel = fn ($s): array => match (true) {
                $s->subscription_status === 'activated' || $s->payment_status === 'paid' => [__('مفعل'), 'bg-emerald-500'],
                $s->payment_status === 'failed' => [__('فشل'), 'bg-red-500'],
                $s->checkout_status === 'expired' => [__('منتهي'), 'bg-gray-400'],
                default => [__('قيد الانتظار'), 'bg-amber-500'],
            };
        @endphp

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
            <div class="mb-4 flex items-center justify-between gap-3">
                <h3 class="flex items-center gap-2 text-base font-bold text-gray-900">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-[#06C2A4]" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z"/></svg>
                    {{ __('حالات الدفع للاشتراكات') }}
                </h3>
                <a href="{{ route('workspace.payments.index') }}"
                   class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-50">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
                    {{ __('عرض الكل') }}
                </a>
            </div>
            <div class="overflow-x-auto rounded-xl border border-gray-100">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-xs font-semibold text-gray-600">
                        <tr>
                            <th class="px-4 py-3 text-right">{{ __('الباقة') }}</th>
                            <th class="px-4 py-3 text-right">Checkout</th>
                            <th class="px-4 py-3 text-right">{{ __('الدفع') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('الاشتراك') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('المبلغ') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('الحالة') }}</th>
                            <th class="px-4 py-3 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($checkoutSessions as $session)
                            @php [$stateLabel, $stateDot] = $sessionStateLabel($session); @endphp
                            <tr class="hover:bg-gray-50/60">
                                <td class="px-4 py-3 font-semibold text-gray-800">{{ $session->plan?->name ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-md px-2 py-0.5 text-xs font-medium {{ $pillStyles[$session->checkout_status] ?? 'bg-gray-100 text-gray-600' }}">{{ $session->checkout_status }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded-md px-2 py-0.5 text-xs font-medium {{ $pillStyles[$session->payment_status] ?? 'bg-gray-100 text-gray-600' }}">{{ $session->payment_status }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded-md px-2 py-0.5 text-xs font-medium {{ $pillStyles[$session->subscription_status] ?? 'bg-gray-100 text-gray-600' }}">{{ $session->subscription_status }}</span>
                                </td>
                                <td class="px-4 py-3 font-semibold text-gray-800">
                                    <span class="text-xs text-gray-500">{{ $session->currency }}</span>
                                    {{ number_format((float) $session->amount, 2) }}
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-white px-2.5 py-1 text-xs font-semibold text-gray-700">
                                        <span class="h-2 w-2 rounded-full {{ $stateDot }}"></span>
                                        {{ $stateLabel }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-left">
                                    <a href="{{ route('workspace.subscriptions.checkout.show', $session) }}" class="text-xs font-semibold text-[#06C2A4] hover:underline">{{ __('فتح') }}</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-6 text-center text-gray-500">{{ __('لا توجد عمليات اشتراك بعد.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $checkoutSessions->links() }}</div>
        </div>

        <div class="rounded-2xl border bg-white p-6">
            <h3 class="mb-3 font-semibold">{{ __('سجل الاشتراكات') }}</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-right">{{ __('الخطة') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('الحالة') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('الفترة') }}</th>
                            <th class="px-4 py-3 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($subscriptions as $subscription)
                            <tr>
                                <td class="px-4 py-3">{{ $subscription->plan?->name ?? '-' }}</td>
                                <td class="px-4 py-3">{{ $statusLabels[$subscription->status] ?? $subscription->status }}</td>
                                <td class="px-4 py-3">{{ $subscription->current_period_start }} → {{ $subscription->current_period_end }}</td>
                                <td class="px-4 py-3 text-left">
                                    @if(in_array($subscription->status, ['active', 'trialing', 'past_due'], true))
                                        <form method="POST" action="{{ route('workspace.subscriptions.destroy', $subscription) }}" class="inline">
                                            @csrf @method('DELETE')
                                            <button class="text-red-600" onclick="return confirm('{{ __('إلغاء الاشتراك الحالي؟') }}')">{{ __('إلغاء') }}</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-6 text-center text-gray-500">{{ __('لا يوجد سجل.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $subscriptions->links() }}</div>
        </div>
    </div>
</x-app-layout>
