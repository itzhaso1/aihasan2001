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

        <div class="rounded-2xl border bg-white p-6">
            <h3 class="mb-4 font-semibold">{{ __('اختر الباقة') }}</h3>

            @if($availablePlans->count() === 0)
                <p class="text-sm text-gray-500">{{ __('لا توجد باقات متاحة حاليًا لنوع مساحة العمل هذه.') }}</p>
            @else
                <div class="grid gap-4 lg:grid-cols-2 xl:grid-cols-4">
                    @foreach($availablePlans as $plan)
                        @php
                            $isCurrent = $currentSubscription && (int) $currentSubscription->plan_id === (int) $plan->id;
                        @endphp
                        <article class="rounded-2xl border p-4 {{ $isCurrent ? 'border-[#06C2A4] bg-[#F3FCFA]' : 'border-gray-200' }}">
                            <div class="flex items-start justify-between gap-3">
                                <h4 class="text-lg font-bold text-gray-900">{{ $plan->display_name_ar ?: $plan->name }}</h4>
                                @if($plan->tier)
                                    <span class="rounded-md bg-[#E8FAF6] px-2 py-1 text-xs font-semibold text-[#067e6b]">
                                        {{ $tierLabels[$plan->tier] ?? $plan->tier }}
                                    </span>
                                @endif
                            </div>
                            <p class="mt-2 text-2xl font-extrabold text-[#06C2A4]">
                                {{ number_format((float) $plan->price, 2) }}
                                <span class="text-sm text-gray-600">{{ $plan->currency }}</span>
                            </p>
                            <p class="mt-1 text-xs text-gray-500">{{ __('الفترة') }}: {{ __($plan->billing_period === 'yearly' ? 'سنوي' : 'شهري') }}</p>
                            @if($plan->description)
                                <p class="mt-3 text-xs leading-relaxed text-gray-600">{{ $plan->description }}</p>
                            @endif

                            @if($isCurrent)
                                <p class="mt-5 rounded-lg bg-white px-3 py-2 text-center text-sm font-semibold text-[#067e6b]">
                                    {{ __('باقتك الحالية') }}
                                </p>
                            @else
                                <form method="POST" action="{{ route('workspace.subscriptions.store') }}" class="mt-5 space-y-2">
                                    @csrf
                                    <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                                    <label class="block text-xs font-semibold text-gray-700">{{ __('بوابة الدفع') }}</label>
                                    <select name="payment_provider" class="w-full rounded-lg border-gray-300 text-sm">
                                        <option value="hyperpay">HyperPay</option>
                                        <option value="local">{{ __('تجريبي محلي') }}</option>
                                    </select>
                                    <button class="w-full rounded-lg bg-[#06C2A4] px-4 py-2 text-sm font-semibold text-white hover:bg-[#04a98e]">
                                        {{ __('متابعة إلى الدفع') }}
                                    </button>
                                </form>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="rounded-2xl border bg-white p-6">
            <h3 class="mb-3 font-semibold">{{ __('حالات الدفع للاشتراكات') }}</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-right">{{ __('الباقة') }}</th>
                            <th class="px-4 py-3 text-right">Checkout</th>
                            <th class="px-4 py-3 text-right">{{ __('الدفع') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('الاشتراك') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('المبلغ') }}</th>
                            <th class="px-4 py-3 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($checkoutSessions as $session)
                            <tr>
                                <td class="px-4 py-3">{{ $session->plan?->name ?? '-' }}</td>
                                <td class="px-4 py-3">{{ $session->checkout_status }}</td>
                                <td class="px-4 py-3">{{ $session->payment_status }}</td>
                                <td class="px-4 py-3">{{ $session->subscription_status }}</td>
                                <td class="px-4 py-3">{{ number_format((float) $session->amount, 2) }} {{ $session->currency }}</td>
                                <td class="px-4 py-3 text-left">
                                    <a href="{{ route('workspace.subscriptions.checkout.show', $session) }}" class="text-[#06C2A4]">{{ __('فتح') }}</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">{{ __('لا توجد عمليات اشتراك بعد.') }}</td></tr>
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
