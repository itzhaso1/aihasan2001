@extends('layouts.appointments', ['pageTitle' => 'Overview'])

@section('content')
    <div class="space-y-6">
        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs text-slate-500">Today - Total</p>
                <p class="mt-2 text-2xl font-bold text-slate-900">{{ number_format((int) $todayCards['today']) }}</p>
            </article>
            <article class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm">
                <p class="text-xs text-emerald-700">Confirmed</p>
                <p class="mt-2 text-2xl font-bold text-emerald-800">{{ number_format((int) $todayCards['confirmed']) }}</p>
            </article>
            <article class="rounded-2xl border border-amber-200 bg-amber-50 p-4 shadow-sm">
                <p class="text-xs text-amber-700">Waiting confirmation</p>
                <p class="mt-2 text-2xl font-bold text-amber-800">{{ number_format((int) $todayCards['needs_confirmation']) }}</p>
            </article>
            <article class="rounded-2xl border border-blue-200 bg-blue-50 p-4 shadow-sm">
                <p class="text-xs text-blue-700">In progress</p>
                <p class="mt-2 text-2xl font-bold text-blue-800">{{ number_format((int) $todayCards['in_progress']) }}</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs text-slate-500">Upcoming</p>
                <p class="mt-2 text-2xl font-bold text-slate-900">{{ number_format((int) $todayCards['upcoming']) }}</p>
            </article>
            <article class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm">
                <p class="text-xs text-emerald-700">Completed</p>
                <p class="mt-2 text-2xl font-bold text-emerald-800">{{ number_format((int) $todayCards['completed']) }}</p>
            </article>
            <article class="rounded-2xl border border-rose-200 bg-rose-50 p-4 shadow-sm">
                <p class="text-xs text-rose-700">Cancelled</p>
                <p class="mt-2 text-2xl font-bold text-rose-800">{{ number_format((int) $todayCards['cancelled']) }}</p>
            </article>
            <article class="rounded-2xl border border-violet-200 bg-violet-50 p-4 shadow-sm">
                <p class="text-xs text-violet-700">No-show</p>
                <p class="mt-2 text-2xl font-bold text-violet-800">{{ number_format((int) $todayCards['no_show']) }}</p>
            </article>
        </section>

        <section class="grid gap-4 xl:grid-cols-3">
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="mb-3 text-sm font-bold text-slate-900">Payments Today</h2>
                <div class="grid gap-2 sm:grid-cols-2">
                    <div class="rounded-xl bg-emerald-50 p-3">
                        <p class="text-xs text-emerald-700">Paid</p>
                        <p class="text-lg font-bold text-emerald-800">{{ number_format((int) $paymentCards['paid']) }}</p>
                    </div>
                    <div class="rounded-xl bg-amber-50 p-3">
                        <p class="text-xs text-amber-700">Pending</p>
                        <p class="text-lg font-bold text-amber-800">{{ number_format((int) $paymentCards['pending']) }}</p>
                    </div>
                    <div class="rounded-xl bg-slate-100 p-3">
                        <p class="text-xs text-slate-700">Unpaid</p>
                        <p class="text-lg font-bold text-slate-800">{{ number_format((int) $paymentCards['unpaid']) }}</p>
                    </div>
                    <div class="rounded-xl bg-blue-50 p-3">
                        <p class="text-xs text-blue-700">Revenue</p>
                        <p class="text-lg font-bold text-blue-800">{{ number_format((float) $paymentCards['revenue'], 2) }}</p>
                    </div>
                </div>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="mb-3 text-sm font-bold text-slate-900">Attention Needed</h2>
                <div class="grid gap-2 sm:grid-cols-3">
                    <div class="rounded-xl bg-amber-50 p-3">
                        <p class="text-xs text-amber-700">طلبات بانتظار المراجعة</p>
                        <p class="text-lg font-bold text-amber-800">{{ number_format((int) $attentionCards['waiting_requests']) }}</p>
                    </div>
                    <div class="rounded-xl bg-rose-50 p-3">
                        <p class="text-xs text-rose-700">حجوزات غير مدفوعة / قيد الانتظار</p>
                        <p class="text-lg font-bold text-rose-800">{{ number_format((int) $attentionCards['unpaid_bookings']) }}</p>
                    </div>
                    <div class="rounded-xl bg-slate-100 p-3">
                        <p class="text-xs text-slate-700">مواعيد تحتاج تأكيد</p>
                        <p class="text-lg font-bold text-slate-800">{{ number_format((int) $attentionCards['needs_confirmation']) }}</p>
                    </div>
                </div>
                <h3 class="mb-2 mt-4 text-xs font-bold text-slate-600">Quick Actions</h3>
                <div class="grid gap-2 sm:grid-cols-2">
                    <a href="{{ route('workspace.appointments.bookings.index') }}" class="rounded-lg border border-slate-300 px-3 py-2 text-center text-xs font-semibold text-slate-700 hover:bg-slate-100">New booking</a>
                    <a href="{{ route('workspace.appointments.requests.index') }}" class="rounded-lg border border-slate-300 px-3 py-2 text-center text-xs font-semibold text-slate-700 hover:bg-slate-100">New request</a>
                    <a href="{{ route('workspace.appointments.calendar.index') }}" class="rounded-lg border border-slate-300 px-3 py-2 text-center text-xs font-semibold text-slate-700 hover:bg-slate-100">Calendar</a>
                    <a href="{{ route('workspace.appointments.customers.index') }}" class="rounded-lg border border-slate-300 px-3 py-2 text-center text-xs font-semibold text-slate-700 hover:bg-slate-100">Customers</a>
                    @if(\Illuminate\Support\Facades\Route::has('workspace.appointments.website.overview'))
                        <a href="{{ route('workspace.appointments.website.overview') }}" class="rounded-lg border border-slate-300 px-3 py-2 text-center text-xs font-semibold text-slate-700 hover:bg-slate-100">Website Builder</a>
                    @endif
                </div>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="mb-3 text-sm font-bold text-slate-900">Requests Snapshot</h2>
                <div class="space-y-2 text-xs">
                    <div class="flex items-center justify-between rounded-lg bg-slate-50 p-2">
                        <span>New</span>
                        <span class="font-bold">{{ number_format((int) $requestCards['new']) }}</span>
                    </div>
                    <div class="flex items-center justify-between rounded-lg bg-slate-50 p-2">
                        <span>Awaiting customer</span>
                        <span class="font-bold">{{ number_format((int) $requestCards['awaiting_customer']) }}</span>
                    </div>
                    <div class="flex items-center justify-between rounded-lg bg-slate-50 p-2">
                        <span>Needs attention</span>
                        <span class="font-bold">{{ number_format((int) $requestCards['needs_attention']) }}</span>
                    </div>
                </div>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-bold text-slate-900">Upcoming appointments</h2>
                    <a href="{{ route('workspace.appointments.bookings.index', ['from_date' => now($timezone)->toDateString()]) }}" class="text-xs font-semibold text-slate-600 hover:text-slate-900">فتح صفحة الحجوزات</a>
                </div>
                <div class="space-y-2">
                    @forelse($latestBookings as $booking)
                        <a href="{{ route('workspace.appointments.bookings.show', $booking) }}" class="block rounded-xl border border-slate-200 p-3 transition hover:border-slate-300 hover:bg-slate-50">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-sm font-semibold text-slate-900">{{ $booking->customer_name }}</p>
                                @php($statusKey = (string) $booking->appointment_status)
                                @include('workspace.appointments.partials.status-badge', [
                                    'label' => $statusLabels[$statusKey] ?? $statusKey,
                                    'tone' => match ($statusKey) {
                                        'confirmed', 'completed' => 'emerald',
                                        'cancelled', 'no_show' => 'rose',
                                        'checked_in', 'in_progress' => 'blue',
                                        default => 'amber',
                                    }
                                ])
                            </div>
                            <p class="mt-1 text-xs text-slate-600">{{ $booking->service?->name ?: 'خدمة غير محددة' }} • {{ $booking->staff?->name ?: 'بدون موظف' }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $booking->starts_at?->timezone($timezone)->locale('ar')->translatedFormat('l، j F - g:i A') }}</p>
                        </a>
                    @empty
                        <div class="rounded-xl border border-dashed border-slate-300 p-4 text-center">
                            <p class="text-sm font-semibold text-slate-700">لا توجد حجوزات اليوم</p>
                            <p class="mt-1 text-xs text-slate-500">عندما يصل أول حجز سيظهر هنا مباشرة.</p>
                            <a href="{{ route('workspace.appointments.bookings.index') }}" class="mt-3 inline-flex rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white">إنشاء أو مراجعة الحجوزات</a>
                        </div>
                    @endforelse
                </div>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-bold text-slate-900">طلبات المواعيد النشطة</h2>
                    <a href="{{ route('workspace.appointments.requests.index') }}" class="text-xs font-semibold text-slate-600 hover:text-slate-900">فتح الطلبات</a>
                </div>
                <div class="space-y-2">
                    @forelse($latestRequests as $request)
                        <a href="{{ route('workspace.appointments.requests.show', $request) }}" class="block rounded-xl border border-slate-200 p-3 transition hover:border-slate-300 hover:bg-slate-50">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-sm font-semibold text-slate-900">{{ $request->customer_name }}</p>
                                @php($requestStatus = (string) $request->status)
                                @include('workspace.appointments.partials.status-badge', [
                                    'label' => $requestStatusLabels[$requestStatus] ?? $requestStatus,
                                    'tone' => match ($requestStatus) {
                                        'approved' => 'emerald',
                                        'rejected', 'cancelled', 'expired' => 'rose',
                                        'awaiting_customer' => 'blue',
                                        default => 'amber',
                                    }
                                ])
                            </div>
                            <p class="mt-1 text-xs text-slate-600">{{ $request->service?->name ?: 'خدمة غير محددة' }} • {{ $request->staff?->name ?: 'بدون موظف' }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $request->created_at?->timezone($timezone)->locale('ar')->translatedFormat('l، j F - g:i A') }}</p>
                        </a>
                    @empty
                        <div class="rounded-xl border border-dashed border-slate-300 p-4 text-center">
                            <p class="text-sm font-semibold text-slate-700">لا توجد طلبات حالية</p>
                            <p class="mt-1 text-xs text-slate-500">أي طلب جديد من AI أو واتساب أو الموقع سيظهر هنا.</p>
                            <a href="{{ route('workspace.appointments.requests.index') }}" class="mt-3 inline-flex rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white">إدارة الطلبات</a>
                        </div>
                    @endforelse
                </div>
            </article>
        </section>
    </div>
@endsection
