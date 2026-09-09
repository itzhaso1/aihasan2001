@props([
    'class' => 'h-10 w-auto',
])

@php
    $branding = app(\App\Services\Platform\PlatformBranding::class);
    $url = $branding->logoUrl();
    $name = $branding->siteName();
@endphp

@if($url)
    <img src="{{ $url }}" alt="{{ $name }}" {{ $attributes->merge(['class' => $class, 'style' => 'object-contain']) }} />
@else
    <x-application-logo {{ $attributes->merge(['class' => $class.' fill-current']) }} />
@endif
