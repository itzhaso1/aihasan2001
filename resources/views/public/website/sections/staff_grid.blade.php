@php
    $title = $sectionConfig['title'] ?? 'الفريق';
@endphp
<section class="mx-auto max-w-6xl px-4 py-12">
    <h2 class="text-2xl font-bold text-slate-900">{{ $title }}</h2>
    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse($staff as $member)
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-base font-semibold text-slate-900">{{ $member->name }}</p>
                <p class="mt-1 text-sm text-slate-500">{{ $member->role ?: 'Staff' }}</p>
            </article>
        @empty
            <p class="text-sm text-slate-500">لا يوجد طاقم نشط حاليًا.</p>
        @endforelse
    </div>
</section>
