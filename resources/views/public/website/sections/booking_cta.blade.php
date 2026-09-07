@php
    $title = $sectionConfig['title'] ?? 'احجز موعدك الآن';
    $buttonText = $sectionConfig['button_text'] ?? ($settings['cta_text'] ?? 'احجز الآن');
@endphp
<section class="mx-auto max-w-6xl px-4 py-12">
    <div class="ws-bg-soft ws-border rounded-2xl border p-6 text-center">
        <h2 class="text-2xl font-bold text-slate-900">{{ $title }}</h2>
        <a href="{{ $bookingUrl }}" class="ws-btn mt-4 inline-flex rounded-xl px-6 py-3 text-sm font-semibold">
            {{ $buttonText }}
        </a>
    </div>
</section>
