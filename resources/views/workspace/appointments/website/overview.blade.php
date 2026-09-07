@extends('layouts.appointments')

@section('content')
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-bold text-slate-900">Website Overview</h2>
                <p class="text-sm text-slate-500">إدارة موقع الحجز العام وربطه بالقالب والدومين.</p>
            </div>
        </div>

        @if(!$website)
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-slate-900">Create Website</h3>
                <p class="mt-1 text-sm text-slate-500">ابدأ بإنشاء موقع الحجز لمساحة العمل الحالية.</p>

                <form method="POST" action="{{ route('workspace.appointments.website.store') }}" class="mt-5 grid gap-4 md:grid-cols-2">
                    @csrf
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Website Name</label>
                        <input type="text" name="name" value="{{ old('name') }}" required class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Slug (optional)</label>
                        <input type="text" name="slug" value="{{ old('slug') }}" class="w-full rounded-xl border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500" />
                    </div>
                    <div class="md:col-span-2">
                        <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">
                            Create Website
                        </button>
                    </div>
                </form>
            </div>
        @else
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs text-slate-500">Status</p>
                    <p class="mt-2 text-lg font-bold text-slate-900">{{ strtoupper($website->status) }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs text-slate-500">Template</p>
                    <p class="mt-2 text-lg font-bold text-slate-900">{{ $website->template?->name ?? 'Not selected' }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs text-slate-500">Primary Domain</p>
                    <p class="mt-2 text-sm font-bold text-slate-900 break-all">{{ $website->primaryDomain?->domain ?? 'Not connected' }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs text-slate-500">Published At</p>
                    <p class="mt-2 text-sm font-bold text-slate-900">{{ $website->published_at?->format('Y-m-d H:i') ?? '-' }}</p>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-slate-900">Quick Actions</h3>
                <div class="mt-4 flex flex-wrap gap-2">
                    <a href="{{ route('workspace.appointments.website.templates', $website) }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Templates</a>
                    <a href="{{ route('workspace.appointments.website.customize', $website) }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Customize</a>
                    <a href="{{ route('workspace.appointments.website.domains', $website) }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Domains</a>
                    <a href="{{ route('workspace.appointments.website.preview', $website) }}" target="_blank" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Preview</a>

                    @if($website->status !== 'published')
                        <form method="POST" action="{{ route('workspace.appointments.website.publish', $website) }}">
                            @csrf
                            <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Publish</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('workspace.appointments.website.unpublish', $website) }}">
                            @csrf
                            <button type="submit" class="rounded-xl bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-500">Unpublish</button>
                        </form>
                    @endif
                </div>
            </div>
        @endif
    </div>
@endsection
