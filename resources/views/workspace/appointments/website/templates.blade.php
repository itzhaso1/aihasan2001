@extends('layouts.appointments')

@section('content')
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-bold text-slate-900">Template Gallery</h2>
                <p class="text-sm text-slate-500">اختر القالب المناسب لعملك ثم انتقل للتخصيص.</p>
            </div>
            <a href="{{ route('workspace.appointments.website.overview') }}" class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Back</a>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach($templates as $template)
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <span class="inline-flex rounded-full bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-700">{{ strtoupper($template->category ?? 'general') }}</span>
                    <h3 class="mt-3 text-base font-bold text-slate-900">{{ $template->name }}</h3>
                    <p class="mt-2 text-xs leading-6 text-slate-500">{{ $template->description }}</p>

                    <div class="mt-4 text-xs text-slate-600">
                        <p>Layout: {{ data_get($template->layout, 'style', '-') }}</p>
                        <p>Sections: {{ count((array) $template->default_sections) }}</p>
                    </div>

                    <form method="POST" action="{{ route('workspace.appointments.website.templates.select', $website) }}" class="mt-4">
                        @csrf
                        <input type="hidden" name="template_id" value="{{ $template->id }}">
                        <button type="submit" class="w-full rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">
                            Choose Template
                        </button>
                    </form>
                </article>
            @endforeach
        </div>
    </div>
@endsection
