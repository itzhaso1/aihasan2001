@php
    $direction = $theme['direction'] ?? 'rtl';
    $isRtl = $direction === 'rtl';
    $primaryColor = $theme['primary_color'] ?? '#0f766e';
    $secondaryColor = $theme['secondary_color'] ?? '#14b8a6';
    $fontFamily = $theme['font'] ?? 'Cairo';
    $pageSlug = $current_page?->slug ?? 'home';
    $isPreviewMode = ! empty($isPreview);
    $requestPath = trim((string) request()->path(), '/');
    $isSlugPublicMode = str_starts_with($requestPath, 'public/');
    $isPreviewRoute = str_starts_with($requestPath, 'public-preview/');
    // Only custom-domain / platform-subdomain hosting uses root paths like /booking.
    // Slug mode (/public/{slug}) and preview must keep prefixed URLs.
    $isHostedMode = ! $isSlugPublicMode
        && ! $isPreviewRoute
        && ! $isPreviewMode
        && request()->attributes->has('website')
        && ! str_contains((string) request()->getHost(), '127.0.0.1')
        && ! str_contains((string) request()->getHost(), 'localhost');

    if ($isPreviewMode || $isPreviewRoute) {
        $previewToken = (string) $website->preview_token;
        $homeUrl = route('public.website.preview', [$previewToken]);
        $bookingUrl = route('public.website.preview', [$previewToken, 'booking']);
        $contactUrl = route('public.website.preview', [$previewToken, 'contact']);
        $servicesApi = route('public.api.services', $website->slug);
        $serviceStaffApiTemplate = route('public.api.services.staff', [$website->slug, '__SERVICE__']);
        $availabilityApi = route('public.api.availability', $website->slug);
        $validateApi = route('public.api.booking.validate', $website->slug);
        $storeApi = route('public.api.booking.store', $website->slug);
    } elseif ($isHostedMode) {
        $homeUrl = url('/');
        $bookingUrl = url('/booking');
        $contactUrl = url('/contact');
        $servicesApi = url('/api/public/services');
        $serviceStaffApiTemplate = url('/api/public/services/__SERVICE__/staff');
        $availabilityApi = url('/api/public/availability');
        $validateApi = url('/api/public/booking/validate');
        $storeApi = url('/api/public/booking');
    } else {
        $homeUrl = route('public.website.show', $website->slug);
        $bookingUrl = route('public.website.page', [$website->slug, 'booking']);
        $contactUrl = route('public.website.page', [$website->slug, 'contact']);
        $servicesApi = route('public.api.services', $website->slug);
        $serviceStaffApiTemplate = route('public.api.services.staff', [$website->slug, '__SERVICE__']);
        $availabilityApi = route('public.api.availability', $website->slug);
        $validateApi = route('public.api.booking.validate', $website->slug);
        $storeApi = route('public.api.booking.store', $website->slug);
    }
@endphp
<!DOCTYPE html>
<html lang="{{ $isRtl ? 'ar' : 'en' }}" dir="{{ $direction }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $seo['title'] ?? $website->name }}</title>
    @if(!empty($seo['description']))
        <meta name="description" content="{{ $seo['description'] }}">
    @endif
    @if(!empty($seo['canonical']))
        <link rel="canonical" href="{{ $seo['canonical'] }}">
    @endif
    @if(!empty($seo['robots']))
        <meta name="robots" content="{{ $seo['robots'] }}">
    @endif
    @if(!empty($seo['og_title']))
        <meta property="og:title" content="{{ $seo['og_title'] }}">
    @endif
    @if(!empty($seo['og_description']))
        <meta property="og:description" content="{{ $seo['og_description'] }}">
    @endif
    @if(!empty($seo['favicon']))
        <link rel="icon" href="{{ $seo['favicon'] }}">
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root {
            --ws-primary: {{ $primaryColor }};
            --ws-secondary: {{ $secondaryColor }};
            --ws-font: "{{ $fontFamily }}", system-ui, -apple-system, sans-serif;
        }
        body { font-family: var(--ws-font); }
        .ws-btn { background-color: var(--ws-primary); color: #fff; }
        .ws-btn:hover { background-color: color-mix(in srgb, var(--ws-primary), #000 10%); }
        .ws-accent { color: var(--ws-primary); }
        .ws-bg-soft { background-color: color-mix(in srgb, var(--ws-primary), #fff 92%); }
        .ws-border { border-color: color-mix(in srgb, var(--ws-primary), #fff 75%); }
    </style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
    <header class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-3">
            <a href="{{ $homeUrl }}" class="flex min-w-0 items-center gap-3 text-base font-bold ws-accent">
                @if(!empty($settings['logo_url']))
                    <img
                        src="{{ $settings['logo_url'] }}"
                        alt="{{ $settings['business_name'] ?? $website->name }}"
                        class="h-9 w-auto max-w-[140px] object-contain sm:h-10 sm:max-w-[180px]"
                    >
                @endif
                <span class="truncate {{ !empty($settings['logo_url']) ? 'hidden sm:inline' : '' }}">
                    {{ $settings['business_name'] ?? $website->name }}
                </span>
            </a>
            <nav class="flex shrink-0 items-center gap-1 text-sm sm:gap-2">
                <a href="{{ $homeUrl }}" class="rounded-lg px-2 py-1.5 sm:px-3 {{ $pageSlug === 'home' ? 'ws-bg-soft ws-accent font-semibold' : 'text-slate-600 hover:bg-slate-100' }}">Home</a>
                <a href="{{ $bookingUrl }}" class="rounded-lg px-2 py-1.5 sm:px-3 {{ $pageSlug === 'booking' ? 'ws-bg-soft ws-accent font-semibold' : 'text-slate-600 hover:bg-slate-100' }}">الحجز</a>
                <a href="{{ $contactUrl }}" class="rounded-lg px-2 py-1.5 sm:px-3 {{ $pageSlug === 'contact' ? 'ws-bg-soft ws-accent font-semibold' : 'text-slate-600 hover:bg-slate-100' }}">Contact</a>
            </nav>
        </div>
    </header>

    <main>
        @if(!empty($isPreview))
            <div class="border-b border-amber-200 bg-amber-50 py-2 text-center text-xs font-semibold text-amber-700">
                Preview Mode — Draft content is visible only through this secure preview link.
            </div>
        @endif

        @if($pageSlug === 'booking')
            @include('public.website.sections.booking_funnel')
        @elseif($pageSlug === 'contact')
            @include('public.website.sections.contact_page')
        @else
            @foreach($sections as $section)
                @continue(($section->component_key ?? '') === 'footer')
                @php($sectionConfig = is_array($section->config) ? $section->config : [])
                @includeIf('public.website.sections.'.$section->component_key, ['sectionConfig' => $sectionConfig])
            @endforeach
        @endif
    </main>

    @include('public.website.sections.footer')

    <script>
        window.PublicBookingConfig = {
            websiteSlug: @json($website->slug),
            routes: {
                services: @json($servicesApi),
                serviceStaff: @json($serviceStaffApiTemplate),
                availability: @json($availabilityApi),
                validate: @json($validateApi),
                store: @json($storeApi),
            },
            timezone: @json($timezone ?? config('app.timezone')),
            requiresPaymentLabel: @json(__('Requires payment')),
        };
    </script>
</body>
</html>
