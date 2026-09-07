@php
    $title = $sectionConfig['title'] ?? 'ساعات العمل';
    $dayLabels = ['sun' => 'الأحد', 'mon' => 'الاثنين', 'tue' => 'الثلاثاء', 'wed' => 'الأربعاء', 'thu' => 'الخميس', 'fri' => 'الجمعة', 'sat' => 'السبت'];
@endphp
<section class="mx-auto max-w-6xl px-4 py-12">
    <h2 class="text-2xl font-bold text-slate-900">{{ $title }}</h2>
    <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        @foreach($dayLabels as $day => $label)
            @php
                $entry = $business_hours[$day] ?? ['closed' => true, 'ranges' => []];
                $ranges = is_array($entry['ranges'] ?? null) ? $entry['ranges'] : [];
            @endphp
            <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3 text-sm last:border-0">
                <span class="font-medium text-slate-800">{{ $label }}</span>
                @if(($entry['closed'] ?? false) || $ranges === [])
                    <span class="text-slate-500">مغلق</span>
                @else
                    <span class="text-slate-600">
                        {{ collect($ranges)->map(fn ($range) => ($range['start'] ?? '').' - '.($range['end'] ?? ''))->implode(' | ') }}
                    </span>
                @endif
            </div>
        @endforeach
    </div>
</section>
