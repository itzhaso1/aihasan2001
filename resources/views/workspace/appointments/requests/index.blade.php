@extends('layouts.appointments', ['pageTitle' => 'Appointment Requests'])

@section('content')
    <div class="space-y-4">
        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="text-base font-bold text-slate-900">Appointment Requests Inbox</h2>
            <p class="mt-1 text-xs text-slate-500"><strong>Request:</strong> طلب موعد لم يتحول بعد إلى حجز نهائي. <strong>Booking:</strong> موعد تم إنشاؤه فعليًا وأصبح جزءًا من جدول النشاط.</p>
            <div class="mt-3 grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-xl bg-slate-100 p-3"><p class="text-xs text-slate-600">New</p><p class="text-xl font-bold text-slate-900">{{ number_format((int) ($requestSummary['new'] ?? 0)) }}</p></div>
                <div class="rounded-xl bg-amber-50 p-3"><p class="text-xs text-amber-700">Reviewing</p><p class="text-xl font-bold text-amber-800">{{ number_format((int) ($requestSummary['reviewing'] ?? 0)) }}</p></div>
                <div class="rounded-xl bg-blue-50 p-3"><p class="text-xs text-blue-700">Awaiting customer</p><p class="text-xl font-bold text-blue-800">{{ number_format((int) ($requestSummary['awaiting_customer'] ?? 0)) }}</p></div>
                <div class="rounded-xl bg-emerald-50 p-3"><p class="text-xs text-emerald-700">Approved</p><p class="text-xl font-bold text-emerald-800">{{ number_format((int) ($requestSummary['approved'] ?? 0)) }}</p></div>
            </div>
        </section>

        <div class="grid gap-4 xl:grid-cols-3">
            <section class="xl:col-span-2 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <form method="GET" action="{{ route('workspace.appointments.requests.index') }}" class="mb-3 grid gap-2 md:grid-cols-5">
                    <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="بحث بالعميل أو الجوال أو البريد" class="rounded-lg border-slate-300 text-sm md:col-span-2">
                    <input type="date" name="date" value="{{ $filters['date'] }}" class="rounded-lg border-slate-300 text-sm">
                    <select name="status" class="rounded-lg border-slate-300 text-sm">
                        <option value="">كل الحالات</option>
                        @foreach($requestStatusLabels as $status => $label)
                            <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <select name="source" class="rounded-lg border-slate-300 text-sm">
                        <option value="">كل المصادر</option>
                        @foreach($sourceLabels as $source => $label)
                            <option value="{{ $source }}" @selected(($filters['source'] ?? '') === $source)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <button class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white md:col-span-5">Apply</button>
                </form>

                <div class="space-y-2">
                    @forelse($appointmentRequests as $appointmentRequest)
                        @php($status = (string) $appointmentRequest->status)
                        <article class="rounded-xl border border-slate-200 p-3">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p class="text-sm font-bold text-slate-900">{{ $appointmentRequest->customer_name }}</p>
                                    <p class="text-xs text-slate-500">{{ $appointmentRequest->customer_phone ?: $appointmentRequest->customer_email ?: '—' }}</p>
                                    <p class="mt-1 text-xs text-slate-600">{{ $appointmentRequest->service?->name ?: 'خدمة غير محددة' }} • {{ $appointmentRequest->staff?->name ?: 'بدون موظف' }}</p>
                                    <p class="text-xs text-slate-500">{{ $sourceLabels[$appointmentRequest->source] ?? $appointmentRequest->source }}</p>
                                </div>
                                <div class="flex flex-col items-end gap-2">
                                    @include('workspace.appointments.partials.status-badge', [
                                        'label' => $requestStatusLabels[$status] ?? $status,
                                        'tone' => match ($status) {
                                            'approved' => 'emerald',
                                            'rejected', 'cancelled', 'expired' => 'rose',
                                            'awaiting_customer' => 'blue',
                                            default => 'amber',
                                        }
                                    ])
                                    <a href="{{ route('workspace.appointments.requests.show', $appointmentRequest) }}" class="rounded-md border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">Review</a>
                                </div>
                            </div>

                            <div class="mt-3 flex flex-wrap gap-2">
                                @if($canManageRequests && ! in_array($appointmentRequest->status, ['approved', 'rejected', 'cancelled', 'expired'], true))
                                    <form method="POST" action="{{ route('workspace.appointments.requests.approve', $appointmentRequest) }}">
                                        @csrf
                                        <button class="rounded-md bg-slate-900 px-2 py-1 text-xs font-semibold text-white">Approve</button>
                                    </form>
                                    <a href="{{ route('workspace.appointments.requests.show', $appointmentRequest) }}#propose-slots" class="rounded-md border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">Suggest times</a>
                                    <a href="{{ route('workspace.appointments.requests.show', $appointmentRequest) }}#request-info" class="rounded-md border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">Ask for information</a>
                                    <form method="POST" action="{{ route('workspace.appointments.requests.reject', $appointmentRequest) }}">
                                        @csrf
                                        <input type="hidden" name="reason" value="تم الرفض من صندوق الطلبات">
                                        <button class="rounded-md border border-rose-300 px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50">Reject</button>
                                    </form>
                                @endif
                                @if($appointmentRequest->booking)
                                    <a href="{{ route('workspace.appointments.bookings.show', $appointmentRequest->booking) }}" class="rounded-md border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">Open Booking</a>
                                @endif
                            </div>
                        </article>
                    @empty
                        <div class="rounded-xl border border-dashed border-slate-300 p-5 text-center">
                            <p class="text-sm font-semibold text-slate-700">لا توجد طلبات جديدة حاليًا</p>
                            <p class="mt-1 text-xs text-slate-500">عند وصول أول طلب من AI أو WhatsApp أو الموقع سيظهر هنا.</p>
                        </div>
                    @endforelse
                </div>

                <div class="mt-3">{{ $appointmentRequests->links() }}</div>
            </section>

            @if($canManageRequests)
            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="mb-3 text-base font-bold text-slate-900">تسجيل Request جديد</h3>
                <form method="POST" action="{{ route('workspace.appointments.requests.store') }}" class="space-y-3">
                    @csrf
                    <select name="request_type" class="w-full rounded-lg border-slate-300 text-sm">
                        <option value="new">طلب جديد</option>
                        <option value="reschedule">إعادة جدولة</option>
                        <option value="cancellation">إلغاء</option>
                        <option value="information">استفسار</option>
                    </select>

                    <select name="customer_id" class="w-full rounded-lg border-slate-300 text-sm">
                        <option value="">عميل محفوظ (اختياري)</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->name }} • {{ $customer->phone }}</option>
                        @endforeach
                    </select>

                    <input type="text" name="customer_name" placeholder="اسم العميل" class="w-full rounded-lg border-slate-300 text-sm" required>
                    <div class="grid grid-cols-2 gap-2">
                        <input type="text" name="customer_phone" placeholder="الجوال" class="rounded-lg border-slate-300 text-sm">
                        <input type="email" name="customer_email" placeholder="البريد" class="rounded-lg border-slate-300 text-sm">
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <select name="requested_service_id" class="rounded-lg border-slate-300 text-sm">
                            <option value="">الخدمة</option>
                            @foreach($allServices as $service)
                                <option value="{{ $service->id }}">{{ $service->name }}</option>
                            @endforeach
                        </select>
                        <select name="requested_staff_id" class="rounded-lg border-slate-300 text-sm">
                            <option value="">الموظف</option>
                            @foreach($allStaff as $staff)
                                <option value="{{ $staff->id }}">{{ $staff->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <input type="date" name="requested_date" class="rounded-lg border-slate-300 text-sm">
                        <input type="time" name="requested_time" class="rounded-lg border-slate-300 text-sm">
                    </div>
                    <textarea name="notes" rows="3" class="w-full rounded-lg border-slate-300 text-sm" placeholder="رسالة العميل / ملاحظات"></textarea>
                    <button class="w-full rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white">تسجيل الطلب</button>
                </form>
            </section>
            @endif
        </div>
    </div>
@endsection
