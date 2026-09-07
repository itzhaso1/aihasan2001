@php
    $tones = [
        'slate' => 'bg-slate-100 text-slate-700 border-slate-200',
        'blue' => 'bg-blue-100 text-blue-700 border-blue-200',
        'amber' => 'bg-amber-100 text-amber-700 border-amber-200',
        'emerald' => 'bg-emerald-100 text-emerald-700 border-emerald-200',
        'rose' => 'bg-rose-100 text-rose-700 border-rose-200',
        'violet' => 'bg-violet-100 text-violet-700 border-violet-200',
    ];
    $tone = $tone ?? 'slate';
@endphp
<span class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-semibold {{ $tones[$tone] ?? $tones['slate'] }}">
    {{ $label ?? '—' }}
</span>
