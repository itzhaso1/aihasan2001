@php
    $title = $sectionConfig['title'] ?? 'عن النشاط';
    $text = $settings['about_text'] ?? ($sectionConfig['text'] ?? '');
@endphp
<section class="mx-auto max-w-6xl px-4 py-12">
    <h2 class="text-2xl font-bold text-slate-900">{{ $title }}</h2>
    <p class="mt-3 max-w-3xl text-sm leading-7 text-slate-600">{{ $text !== '' ? $text : 'يمكنك تعديل هذا القسم من صفحة تخصيص الموقع.' }}</p>
</section>
