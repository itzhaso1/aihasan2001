@php
    $title = $sectionConfig['title'] ?? 'المعرض';
    $images = is_array($sectionConfig['images'] ?? null) ? $sectionConfig['images'] : [];
@endphp
<section class="mx-auto max-w-6xl px-4 py-12">
    <h2 class="text-2xl font-bold text-slate-900">{{ $title }}</h2>
    <div class="mt-5 grid grid-cols-2 gap-3 md:grid-cols-4">
        @forelse($images as $image)
            @php
                $url = is_array($image) ? ($image['image'] ?? ($image['url'] ?? '')) : (string) $image;
                $caption = is_array($image) ? ($image['caption'] ?? '') : '';
            @endphp
            @if($url !== '')
                <figure class="overflow-hidden rounded-xl">
                    <img src="{{ $url }}" alt="{{ $caption !== '' ? $caption : 'gallery image' }}" loading="lazy" class="h-28 w-full object-cover sm:h-36">
                    @if($caption !== '')
                        <figcaption class="mt-1 text-center text-[11px] text-slate-500">{{ $caption }}</figcaption>
                    @endif
                </figure>
            @endif
        @empty
            <div class="col-span-full rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">
                أضف صور المعرض من إعدادات القالب.
            </div>
        @endforelse
    </div>
</section>
