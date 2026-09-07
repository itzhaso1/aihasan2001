@php
    $title = $settings['hero_title'] ?? ($sectionConfig['title'] ?? ($settings['business_name'] ?? $website->name));
    $subtitle = $settings['hero_description'] ?? ($sectionConfig['subtitle'] ?? '');
    $buttonText = $settings['cta_text'] ?? ($sectionConfig['button_text'] ?? 'احجز الآن');
    $image = $settings['hero_image_url'] ?? ($sectionConfig['image'] ?? null);
@endphp

<section class="ws-bg-soft">
    <div class="mx-auto grid max-w-6xl gap-8 px-4 py-16 md:grid-cols-2 md:items-center">
        <div>
            <h1 class="text-3xl font-black tracking-tight text-slate-900 sm:text-4xl">{{ $title }}</h1>
            @if($subtitle)
                <p class="mt-4 text-sm leading-7 text-slate-600 sm:text-base">{{ $subtitle }}</p>
            @endif
            <a href="{{ $bookingUrl }}" class="ws-btn mt-6 inline-flex rounded-xl px-5 py-3 text-sm font-semibold">
                {{ $buttonText }}
            </a>
        </div>
        @if($image)
            <div>
                <img src="{{ $image }}" alt="hero" loading="lazy" class="h-72 w-full rounded-2xl object-cover shadow-md sm:h-80">
            </div>
        @endif
    </div>
</section>
