@php
    $title = $sectionConfig['title'] ?? 'تواصل معنا';
@endphp
<section class="mx-auto max-w-6xl px-4 py-12">
    <h2 class="text-2xl font-bold text-slate-900">{{ $title }}</h2>
    <div class="mt-4 grid gap-4 md:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs text-slate-500">الهاتف</p>
            <p class="mt-1 font-semibold text-slate-900">{{ $settings['contact_phone'] ?? '-' }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs text-slate-500">البريد الإلكتروني</p>
            <p class="mt-1 font-semibold text-slate-900">{{ $settings['contact_email'] ?? '-' }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs text-slate-500">العنوان</p>
            <p class="mt-1 font-semibold text-slate-900">{{ $settings['contact_address'] ?? '-' }}</p>
        </div>
    </div>
</section>
