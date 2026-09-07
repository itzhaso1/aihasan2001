@php
    $title = $sectionConfig['title'] ?? 'الخدمات';
    $showPrice = array_key_exists('show_price', $sectionConfig) ? (bool) $sectionConfig['show_price'] : true;
    $showDuration = array_key_exists('show_duration', $sectionConfig) ? (bool) $sectionConfig['show_duration'] : true;
@endphp
<section class="mx-auto max-w-6xl px-4 py-12">
    <div class="mb-6 flex items-end justify-between">
        <h2 class="text-2xl font-bold text-slate-900">{{ $title }}</h2>
        <a href="{{ $bookingUrl }}" class="text-sm font-semibold ws-accent">احجز الآن</a>
    </div>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse($services as $service)
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="font-semibold text-slate-900">{{ $service->name }}</h3>
                @if($service->description)
                    <p class="mt-2 text-xs leading-6 text-slate-500">{{ $service->description }}</p>
                @endif
                <div class="mt-3 flex flex-wrap gap-3 text-xs text-slate-600">
                    @if($showDuration)
                        <span>{{ $service->duration_minutes }} دقيقة</span>
                    @endif
                    @if($showPrice)
                        <span>{{ number_format((float) $service->price, 2) }}</span>
                    @endif
                </div>
            </article>
        @empty
            <p class="text-sm text-slate-500">لا توجد خدمات منشورة حاليًا.</p>
        @endforelse
    </div>
</section>
