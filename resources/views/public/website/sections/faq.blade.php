@php
    $title = $sectionConfig['title'] ?? 'الأسئلة الشائعة';
    $items = is_array($sectionConfig['items'] ?? null) ? $sectionConfig['items'] : [];
@endphp
<section class="mx-auto max-w-6xl px-4 py-12">
    <h2 class="text-2xl font-bold text-slate-900">{{ $title }}</h2>
    <div class="mt-5 space-y-3">
        @forelse($items as $item)
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="font-semibold text-slate-900">{{ $item['question'] ?? '' }}</h3>
                <p class="mt-2 text-sm text-slate-600">{{ $item['answer'] ?? '' }}</p>
            </div>
        @empty
            <p class="text-sm text-slate-500">أضف الأسئلة الشائعة من إعدادات القسم.</p>
        @endforelse
    </div>
</section>
