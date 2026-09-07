@extends('layouts.appointments', ['pageTitle' => 'الإجازات والعطل'])

@section('content')
    <div class="space-y-6" dir="rtl">
        <div>
            <h2 class="text-xl font-bold text-slate-900">الإجازات والعطل</h2>
            <p class="mt-1 text-sm text-slate-500">إدارة أيام الإغلاق والعطل الجزئية لفريق المواعيد.</p>
        </div>

        @include('partials.flash')

        <form method="POST" action="{{ route('workspace.appointments.holidays.store') }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            @csrf
            <h3 class="text-sm font-semibold text-slate-900">إضافة إجازة / عطلة</h3>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">التاريخ</label>
                    <input type="date" name="holiday_date" value="{{ old('holiday_date') }}" required class="w-full rounded-xl border-slate-300 text-sm">
                    @error('holiday_date') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">الموظف (اختياري)</label>
                    <select name="staff_id" class="w-full rounded-xl border-slate-300 text-sm">
                        <option value="">كل الفريق</option>
                        @foreach($staff as $member)
                            <option value="{{ $member->id }}" @selected((string) old('staff_id') === (string) $member->id)>{{ $member->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">من الساعة</label>
                    <input type="time" name="start_time" value="{{ old('start_time') }}" class="w-full rounded-xl border-slate-300 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-600">إلى الساعة</label>
                    <input type="time" name="end_time" value="{{ old('end_time') }}" class="w-full rounded-xl border-slate-300 text-sm">
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">السبب</label>
                    <input type="text" name="reason" value="{{ old('reason') }}" maxlength="255" class="w-full rounded-xl border-slate-300 text-sm" placeholder="مثال: عطلة رسمية">
                </div>
                <div class="md:col-span-2">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="is_recurring" value="1" @checked(old('is_recurring')) class="rounded border-slate-300">
                        تكرار سنوي
                    </label>
                </div>
            </div>
            <div class="mt-4">
                <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">حفظ</button>
            </div>
        </form>

        <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-4 py-3 text-right font-semibold">التاريخ</th>
                        <th class="px-4 py-3 text-right font-semibold">الموظف</th>
                        <th class="px-4 py-3 text-right font-semibold">الوقت</th>
                        <th class="px-4 py-3 text-right font-semibold">السبب</th>
                        <th class="px-4 py-3 text-right font-semibold">تكرار</th>
                        <th class="px-4 py-3 text-left font-semibold"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($holidays as $holiday)
                        <tr>
                            <td class="px-4 py-3">{{ $holiday->holiday_date?->format('Y-m-d') }}</td>
                            <td class="px-4 py-3">{{ $holiday->staff?->name ?? 'كل الفريق' }}</td>
                            <td class="px-4 py-3">
                                @if($holiday->start_time || $holiday->end_time)
                                    {{ $holiday->start_time ? \Illuminate\Support\Str::of($holiday->start_time)->substr(0, 5) : '—' }}
                                    –
                                    {{ $holiday->end_time ? \Illuminate\Support\Str::of($holiday->end_time)->substr(0, 5) : '—' }}
                                @else
                                    يوم كامل
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $holiday->reason ?: '—' }}</td>
                            <td class="px-4 py-3">{{ $holiday->is_recurring ? 'نعم' : 'لا' }}</td>
                            <td class="px-4 py-3 text-left">
                                <form method="POST" action="{{ route('workspace.appointments.holidays.destroy', $holiday) }}" onsubmit="return confirm('تأكيد حذف هذه الإجازة؟')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-rose-600 hover:underline">حذف</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-slate-500">لا توجد إجازات مسجّلة بعد.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $holidays->links() }}</div>
    </div>
@endsection
