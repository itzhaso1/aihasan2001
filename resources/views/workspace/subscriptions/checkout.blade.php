<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-900">إتمام الدفع للاشتراك</h2>
    </x-slot>

    <div class="mx-auto max-w-4xl space-y-6">
        @include('workspace.partials.nav')
        @include('partials.flash')

        <div class="rounded-2xl border border-[#BDEFE5] bg-[#F3FCFA] p-5">
            <h3 class="text-base font-semibold text-[#067e6b]">Checkout Session #{{ $checkoutSession->id }}</h3>
            <p class="mt-2 text-sm text-gray-700">
                هذه الصفحة تمثل واجهة الدفع الجاهزة للربط مع HyperPay لاحقًا. عند اكتمال ربط API سيتم استبدال زر التأكيد بطلب دفع حقيقي.
            </p>
        </div>

        <div class="rounded-2xl border bg-white p-6">
            <h3 class="mb-4 font-semibold">تفاصيل الباقة المختارة</h3>
            <div class="grid gap-3 text-sm text-gray-700 sm:grid-cols-2">
                <p><span class="font-semibold">اسم الباقة:</span> {{ $checkoutSession->plan?->name }}</p>
                <p><span class="font-semibold">نوع المساحة:</span> {{ $checkoutSession->plan?->workspace_type }}</p>
                <p><span class="font-semibold">سعر الباقة:</span> {{ number_format((float) $checkoutSession->amount, 2) }} {{ $checkoutSession->currency }}</p>
                <p><span class="font-semibold">الفترة:</span> {{ $checkoutSession->plan?->billing_period }}</p>
                <p><span class="font-semibold">بوابة الدفع:</span> {{ $checkoutSession->payment_provider }}</p>
                <p><span class="font-semibold">رقم المرجع:</span> {{ $checkoutSession->provider_checkout_id }}</p>
            </div>
        </div>

        <div class="rounded-2xl border bg-white p-6">
            <h3 class="mb-4 font-semibold">حالة العملية</h3>
            <div class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl border border-gray-200 p-4 text-sm">
                    <p class="text-xs text-gray-500">Checkout Status</p>
                    <p class="mt-1 font-semibold text-gray-900">{{ $checkoutSession->checkout_status }}</p>
                </div>
                <div class="rounded-xl border border-gray-200 p-4 text-sm">
                    <p class="text-xs text-gray-500">Payment Status</p>
                    <p class="mt-1 font-semibold text-gray-900">{{ $checkoutSession->payment_status }}</p>
                </div>
                <div class="rounded-xl border border-gray-200 p-4 text-sm">
                    <p class="text-xs text-gray-500">Subscription Status</p>
                    <p class="mt-1 font-semibold text-gray-900">{{ $checkoutSession->subscription_status }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border bg-white p-6">
            <h3 class="mb-3 font-semibold">تأكيد الدفع</h3>

            @if($checkoutSession->payment_status === 'paid' && $checkoutSession->subscription_status === 'activated')
                <div class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-800">
                    تم الدفع بالفعل وتفعيل الاشتراك بنجاح.
                </div>
                <a href="{{ route('workspace.subscriptions.index') }}" class="mt-4 inline-flex rounded-lg bg-[#06C2A4] px-4 py-2 text-sm font-semibold text-white">
                    العودة إلى الاشتراكات
                </a>
            @else
                <form method="POST" action="{{ route('workspace.subscriptions.checkout.confirm-payment', $checkoutSession) }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="mb-1 block text-sm">مرجع عملية الدفع (اختياري)</label>
                        <input name="payment_reference" class="w-full rounded-lg border-gray-300" placeholder="مثال: hp_txn_20260820_001" />
                    </div>
                    <button class="rounded-lg bg-[#06C2A4] px-5 py-2 text-sm font-semibold text-white hover:bg-[#04a98e]">
                        تأكيد نجاح الدفع وتفعيل الباقة
                    </button>
                </form>
                <p class="mt-3 text-xs text-gray-500">
                    هذا تأكيد Frontend مؤقت. عند ربط HyperPay سيتم الاستبدال بتأكيد Webhook حقيقي قبل تفعيل الاشتراك.
                </p>
            @endif
        </div>
    </div>
</x-app-layout>
