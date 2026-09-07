@php
    $title = $sectionConfig['title'] ?? 'آراء العملاء';
    $items = is_array($sectionConfig['items'] ?? null) ? $sectionConfig['items'] : [];
@endphp
<section class="mx-auto max-w-6xl px-4 py-12">
    <h2 class="text-2xl font-bold text-slate-900">{{ $title }}</h2>
    <div class="mt-5 grid gap-4 md:grid-cols-2">
        @forelse($items as $item)
            @php
                $content = $item['content'] ?? ($item['text'] ?? '');
                $name = $item['name'] ?? ($item['author'] ?? '');
                $role = $item['role'] ?? null;
                $rating = isset($item['rating']) ? (int) $item['rating'] : null;
                $image = $item['image'] ?? null;
            @endphp
            <blockquote class="rounded-2xl border border-slate-200 bg-white p-5 text-sm leading-7 text-slate-600 shadow-sm">
                @if($image)
                    <img src="{{ $image }}" alt="{{ $name }}" class="mb-3 h-12 w-12 rounded-full object-cover">
                @endif
                <p>"{{ $content }}"</p>
                @if($rating)
                    <p class="mt-2 text-amber-500">{{ str_repeat('★', max(1, min(5, $rating))) }}</p>
                @endif
                <footer class="mt-3 text-xs font-semibold text-slate-500">
                    {{ $name }}
                    @if($role)
                        <span class="font-normal text-slate-400"> — {{ $role }}</span>
                    @endif
                </footer>
            </blockquote>
        @empty
            <p class="text-sm text-slate-500">يمكنك إضافة شهادات العملاء من إعدادات القسم.</p>
        @endforelse
    </div>
</section>
