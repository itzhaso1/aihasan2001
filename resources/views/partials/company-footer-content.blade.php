@php
    $showLinks = $showLinks ?? false;
@endphp

<div class="mx-auto grid max-w-7xl gap-8 px-4 py-10 sm:px-6 lg:grid-cols-4 lg:px-8">
    <div>
        <h4 class="text-lg font-bold text-[#06C2A4]">شركة حاسم الذكي</h4>
        <ul class="mt-4 space-y-2 text-sm text-gray-600">
            <li><span class="font-semibold text-gray-800">السجل التجاري:</span> 7054911743</li>
            <li><span class="font-semibold text-gray-800">رقم التواصل:</span> 0595397059</li>
        </ul>
    </div>

    <div class="lg:col-span-2">
        <h5 class="text-sm font-semibold text-gray-900">وسائل الدفع المعتمدة</h5>
        <div class="mt-3 flex flex-wrap gap-2">
            <span class="inline-flex items-center gap-2 rounded-xl border border-[#d7e2ff] bg-white px-3 py-2 text-xs font-semibold text-[#1a1f71]">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M3 6h18M3 12h18M3 18h18" stroke-width="1.7" stroke-linecap="round" />
                </svg>
                VISA
            </span>
            <span class="inline-flex items-center gap-2 rounded-xl border border-[#d7e2ff] bg-white px-3 py-2 text-xs font-semibold text-[#003087]">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <circle cx="9" cy="12" r="5" stroke-width="1.7" />
                    <circle cx="15" cy="12" r="5" stroke-width="1.7" />
                </svg>
                PayPal
            </span>
            <span class="inline-flex items-center gap-2 rounded-xl border border-[#d5f4ee] bg-white px-3 py-2 text-xs font-semibold text-[#00A88E]">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M7 4v16M17 4v16M7 12h10" stroke-width="1.7" stroke-linecap="round" />
                </svg>
                HyperPay
            </span>
        </div>
        <p class="mt-2 text-[11px] text-gray-500">يمكن استبدال الشعارات الحالية بملفات الهوية الرسمية التي تعتمدها شركتكم.</p>
    </div>

    <div>
        <h5 class="text-sm font-semibold text-gray-900">الاعتمادات والربط الرسمي</h5>
        <div class="mt-3 space-y-2">
            <span class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700">
                <svg class="h-4 w-4 text-[#06C2A4]" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M12 3 5 6v5c0 5 3.4 8.5 7 10 3.6-1.5 7-5 7-10V6l-7-3Z" stroke-width="1.7" />
                    <path d="m9 12 2 2 4-4" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                ZATCA
            </span>
            <span class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700">
                <svg class="h-4 w-4 text-[#06C2A4]" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M4 10h16M6 6h12M8 14h8M7 18h10" stroke-width="1.7" stroke-linecap="round" />
                </svg>
                البنك المركزي السعودي (ساما)
            </span>
        </div>
    </div>
</div>

@if($showLinks)
    <div class="border-t border-gray-100">
        <div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-4 text-xs text-gray-500 sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:px-8">
            <div class="flex flex-wrap items-center gap-4">
                <a href="#features" class="hover:text-[#06C2A4]">الميزات</a>
                <a href="#pricing" class="hover:text-[#06C2A4]">الاشتراكات</a>
                <a href="{{ route('platform.login') }}" class="hover:text-[#06C2A4]">منصة الإدارة</a>
            </div>
            <p>© {{ now()->year }} شركة حاسم الذكي — جميع الحقوق محفوظة.</p>
        </div>
    </div>
@else
    <div class="border-t border-gray-100">
        <div class="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-4 text-xs text-gray-500 sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:px-8">
            <p>© {{ now()->year }} شركة حاسم الذكي — جميع الحقوق محفوظة.</p>
            <p class="text-[#06C2A4]">حلول SaaS ذكية متوافقة مع متطلبات السوق السعودي</p>
        </div>
    </div>
@endif
