@extends('layouts.appointments', ['pageTitle' => 'ملف العميل'])

@section('content')
    @php
        $statusLabels = [
            'scheduled' => 'مجدول',
            'confirmed' => 'مؤكد',
            'checked_in' => 'تم تسجيل الحضور',
            'in_progress' => 'قيد التنفيذ',
            'completed' => 'مكتمل',
            'cancelled' => 'ملغي',
            'no_show' => 'لم يحضر',
        ];
        $requestLabels = [
            'new' => 'جديد',
            'reviewing' => 'قيد المراجعة',
            'awaiting_customer' => 'بانتظار العميل',
            'approved' => 'تمت الموافقة',
            'rejected' => 'مرفوض',
            'expired' => 'منتهي',
            'cancelled' => 'ملغي',
        ];
        $upcomingCount = $upcomingBookings->count();
        $pastCount = $pastBookings->count();
        $requestsCount = $appointmentRequests->count();
        $invoicesCount = $invoices->count();
        $paymentsCount = $payments->count();
        $timelineCount = $conversations->count() + $emails->count();
    @endphp
    <div
        x-data="{
            tab: 'overview',
            setTab(value) {
                this.tab = value;
                history.replaceState(null, '', `${location.pathname}#${value}`);
            },
            init() {
                const hash = window.location.hash.replace('#', '');
                if (['overview', 'appointments', 'requests', 'billing', 'communication'].includes(hash)) {
                    this.tab = hash;
                }
            }
        }"
        x-init="init()"
        class="space-y-4"
    >
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-lg font-bold text-slate-900">{{ $customer->name }}</h1>
                    <p class="mt-1 text-sm text-slate-500">{{ $customer->phone }} @if($customer->email) — {{ $customer->email }} @endif</p>
                </div>
                <a href="{{ route('workspace.appointments.customers.index') }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100">العودة لقائمة العملاء</a>
            </div>

            @if($customer->notes)
                <p class="mt-3 rounded-lg bg-slate-50 p-3 text-sm text-slate-700">{{ $customer->notes }}</p>
            @endif

            <div class="mt-4 grid gap-2 sm:grid-cols-2 xl:grid-cols-6">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-2 text-center">
                    <p class="text-[11px] text-slate-500">قادم</p>
                    <p class="text-sm font-bold text-slate-900">{{ $upcomingCount }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-2 text-center">
                    <p class="text-[11px] text-slate-500">سابق</p>
                    <p class="text-sm font-bold text-slate-900">{{ $pastCount }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-2 text-center">
                    <p class="text-[11px] text-slate-500">الطلبات</p>
                    <p class="text-sm font-bold text-slate-900">{{ $requestsCount }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-2 text-center">
                    <p class="text-[11px] text-slate-500">الفواتير</p>
                    <p class="text-sm font-bold text-slate-900">{{ $invoicesCount }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-2 text-center">
                    <p class="text-[11px] text-slate-500">المدفوعات</p>
                    <p class="text-sm font-bold text-slate-900">{{ $paymentsCount }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-2 text-center">
                    <p class="text-[11px] text-slate-500">التفاعل</p>
                    <p class="text-sm font-bold text-slate-900">{{ $timelineCount }}</p>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-2 border-t border-slate-100 pt-3">
                <button type="button" @click="setTab('overview')" :class="tab === 'overview' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'" class="rounded-xl px-3 py-2 text-xs font-semibold transition">Overview</button>
                <button type="button" @click="setTab('appointments')" :class="tab === 'appointments' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'" class="rounded-xl px-3 py-2 text-xs font-semibold transition">Appointments</button>
                <button type="button" @click="setTab('requests')" :class="tab === 'requests' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'" class="rounded-xl px-3 py-2 text-xs font-semibold transition">Requests</button>
                <button type="button" @click="setTab('billing')" :class="tab === 'billing' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'" class="rounded-xl px-3 py-2 text-xs font-semibold transition">Billing</button>
                <button type="button" @click="setTab('communication')" :class="tab === 'communication' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'" class="rounded-xl px-3 py-2 text-xs font-semibold transition">Communication</button>
            </div>
        </section>

        <section x-show="tab === 'overview'" class="grid gap-4 xl:grid-cols-3">
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm xl:col-span-2">
                <h2 class="mb-3 text-sm font-bold">آخر موعد قادم</h2>
                @if($upcomingBookings->first())
                    @php($nextBooking = $upcomingBookings->first())
                    <div class="rounded-xl border border-slate-200 p-4">
                        <p class="text-sm font-semibold text-slate-900">{{ $nextBooking->booking_number }} — {{ $statusLabels[$nextBooking->appointment_status] ?? $nextBooking->appointment_status }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $nextBooking->starts_at?->timezone($timezone)->locale('ar')->translatedFormat('l، j F - g:i A') }}</p>
                        <a href="{{ route('workspace.appointments.bookings.show', $nextBooking) }}" class="mt-3 inline-flex rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">فتح الحجز</a>
                    </div>
                @else
                    <p class="text-sm text-slate-500">لا يوجد موعد قادم حالياً.</p>
                @endif
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="mb-3 text-sm font-bold">ملخص سريع</h2>
                <ul class="space-y-2 text-xs text-slate-700">
                    <li class="flex justify-between"><span>المواعيد القادمة</span><strong>{{ $upcomingCount }}</strong></li>
                    <li class="flex justify-between"><span>الطلبات الحالية</span><strong>{{ $requestsCount }}</strong></li>
                    <li class="flex justify-between"><span>الفواتير</span><strong>{{ $invoicesCount }}</strong></li>
                    <li class="flex justify-between"><span>المدفوعات</span><strong>{{ $paymentsCount }}</strong></li>
                </ul>
            </article>
        </section>

        <section x-show="tab === 'appointments'" class="grid gap-4 xl:grid-cols-2">
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="mb-3 text-sm font-bold">المواعيد القادمة</h2>
                <div class="space-y-2 text-sm">
                    @forelse($upcomingBookings as $booking)
                        <a href="{{ route('workspace.appointments.bookings.show', $booking) }}" class="block rounded-lg border border-slate-200 p-3 transition hover:bg-slate-50">
                            <p class="font-semibold">{{ $booking->booking_number }} — {{ $statusLabels[$booking->appointment_status] ?? $booking->appointment_status }}</p>
                            <p class="text-xs text-slate-500">{{ $booking->starts_at?->timezone($timezone)->locale('ar')->translatedFormat('l، j F - g:i A') }}</p>
                        </a>
                    @empty
                        <p class="text-sm text-slate-500">لا توجد مواعيد قادمة.</p>
                    @endforelse
                </div>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="mb-3 text-sm font-bold">سجل المواعيد</h2>
                <div class="space-y-2 text-sm">
                    @forelse($pastBookings as $booking)
                        <a href="{{ route('workspace.appointments.bookings.show', $booking) }}" class="block rounded-lg border border-slate-200 p-3 transition hover:bg-slate-50">
                            <p class="font-semibold">{{ $booking->booking_number }} — {{ $statusLabels[$booking->appointment_status] ?? $booking->appointment_status }}</p>
                            <p class="text-xs text-slate-500">{{ $booking->starts_at?->timezone($timezone)->locale('ar')->translatedFormat('l، j F - g:i A') }}</p>
                        </a>
                    @empty
                        <p class="text-sm text-slate-500">لا يوجد سجل سابق.</p>
                    @endforelse
                </div>
            </article>
        </section>

        <section x-show="tab === 'requests'" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="mb-3 text-sm font-bold">طلبات المواعيد</h2>
            <div class="space-y-2 text-sm">
                @forelse($appointmentRequests as $request)
                    <a href="{{ route('workspace.appointments.requests.show', $request) }}" class="block rounded-lg border border-slate-200 p-3 transition hover:bg-slate-50">
                        <p class="font-semibold">طلب #{{ $request->id }} — {{ $requestLabels[$request->status] ?? $request->status }}</p>
                        <p class="text-xs text-slate-500">{{ $request->created_at?->timezone($timezone)->locale('ar')->translatedFormat('l، j F - g:i A') }}</p>
                    </a>
                @empty
                    <p class="text-sm text-slate-500">لا توجد طلبات مواعيد.</p>
                @endforelse
            </div>
        </section>

        <section x-show="tab === 'billing'" class="grid gap-4 xl:grid-cols-2">
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="mb-3 text-sm font-bold">الفواتير</h2>
                <div class="space-y-2 text-sm">
                    @forelse($invoices as $invoice)
                        <a href="{{ route('workspace.finance.invoices.show', $invoice->id) }}" class="block rounded-lg border border-slate-200 p-3 transition hover:bg-slate-50">
                            <p class="font-semibold">{{ $invoice->invoice_number }} — {{ $invoice->status }}</p>
                            <p class="text-xs text-slate-500">الإجمالي: {{ number_format((float) $invoice->total, 2) }} {{ $invoice->currency }}</p>
                        </a>
                    @empty
                        <p class="text-sm text-slate-500">لا توجد فواتير.</p>
                    @endforelse
                </div>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="mb-3 text-sm font-bold">المدفوعات</h2>
                <div class="space-y-2 text-sm">
                    @forelse($payments as $payment)
                        <div class="rounded-lg border border-slate-200 p-3">
                            <p class="font-semibold">دفعة #{{ $payment->id }} — {{ number_format((float) $payment->amount, 2) }}</p>
                            <p class="text-xs text-slate-500">{{ $payment->payment_date?->format('Y-m-d') }} — {{ $payment->method }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">لا توجد مدفوعات.</p>
                    @endforelse
                </div>
            </article>
        </section>

        <section x-show="tab === 'communication'" class="grid gap-4 xl:grid-cols-2">
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="mb-3 text-sm font-bold">المحادثات</h2>
                <div class="space-y-2 text-sm">
                    @forelse($conversations as $conversation)
                        <div class="rounded-lg border border-slate-200 p-3">
                            <p class="font-semibold">قناة: {{ $conversation->channel }}</p>
                            <p class="text-xs text-slate-500">آخر رسالة: {{ optional($conversation->messages->first())->created_at?->format('Y-m-d H:i') }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">لا توجد محادثات مرتبطة.</p>
                    @endforelse
                </div>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="mb-3 text-sm font-bold">البريد الإلكتروني</h2>
                <div class="space-y-2 text-sm">
                    @forelse($emails as $email)
                        <div class="rounded-lg border border-slate-200 p-3">
                            <p class="font-semibold">{{ $email->subject ?: '(بدون عنوان)' }}</p>
                            <p class="text-xs text-slate-500">{{ $email->sender }} → {{ $email->recipient }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">لا توجد رسائل بريد مرتبطة.</p>
                    @endforelse
                </div>
            </article>
        </section>
    </div>
@endsection
