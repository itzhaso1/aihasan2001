@php
    $social = is_array($settings['social_links'] ?? null) ? $settings['social_links'] : [];
    $labels = [
        'instagram' => 'Instagram',
        'facebook' => 'Facebook',
        'whatsapp' => 'WhatsApp',
        'x' => 'X',
        'twitter' => 'X',
        'linkedin' => 'LinkedIn',
        'youtube' => 'YouTube',
        'tiktok' => 'TikTok',
    ];
@endphp
<footer class="mt-12 border-t border-slate-200 bg-white py-6">
    <div class="mx-auto flex max-w-6xl flex-col gap-4 px-4 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between">
        <p>{{ $settings['footer_text'] ?? ($settings['business_name'] ?? $website->name) }}</p>
        <div class="flex flex-wrap items-center gap-3">
            @foreach($labels as $key => $label)
                @php $url = trim((string) ($social[$key] ?? '')); @endphp
                @if($url !== '')
                    <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-slate-600 hover:text-slate-900">{{ $label }}</a>
                @endif
            @endforeach
            <span>{{ now()->year }} ©</span>
        </div>
    </div>
</footer>
