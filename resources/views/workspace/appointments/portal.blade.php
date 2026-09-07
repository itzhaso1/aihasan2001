<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>بوابة الموعد</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
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
        $paymentLabels = [
            'unpaid' => 'غير مدفوع',
            'pending' => 'قيد الانتظار',
            'paid' => 'مدفوع',
            'failed' => 'فشل الدفع',
            'refunded' => 'مسترجع',
            'partially_paid' => 'مدفوع جزئيًا',
        ];
    @endphp
    <main class="mx-auto max-w-3xl px-4 py-8">
        @include('partials.flash')
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h1 class="text-xl font-bold">تفاصيل الموعد</h1>
            <p class="mt-1 text-sm text-slate-500">رقم الموعد: {{ $booking->booking_number }}</p>

            <div class="mt-6 grid gap-3 text-sm md:grid-cols-2">
                <div class="rounded-xl bg-slate-50 p-3">
                    <p class="text-xs text-slate-500">العميل</p>
                    <p class="font-semibold">{{ $booking->customer_name }}</p>
                    <p>{{ $booking->customer_phone ?: '—' }}</p>
                </div>
                <div class="rounded-xl bg-slate-50 p-3">
                    <p class="text-xs text-slate-500">الخدمة / الطاقم</p>
                    <p class="font-semibold">{{ $booking->service?->name ?: '—' }}</p>
                    <p>{{ $booking->staff?->name ?: 'غير محدد' }}</p>
                </div>
                <div class="rounded-xl bg-slate-50 p-3">
                    <p class="text-xs text-slate-500">موعد الحجز</p>
                    <p class="font-semibold">{{ $booking->starts_at?->timezone($timezone)->locale('ar')->translatedFormat('l، j F - g:i A') }}</p>
                    <p>حتى {{ $booking->ends_at?->timezone($timezone)->locale('ar')->translatedFormat('g:i A') }}</p>
                </div>
                <div class="rounded-xl bg-slate-50 p-3">
                    <p class="text-xs text-slate-500">الحالة</p>
                    <p class="font-semibold">الموعد: {{ $statusLabels[$booking->appointment_status] ?? $booking->appointment_status }}</p>
                    <p class="font-semibold">الدفع: {{ $paymentLabels[$booking->payment_status] ?? $booking->payment_status }}</p>
                </div>
            </div>

            <div class="mt-4 rounded-xl border border-slate-200 p-3 text-sm">
                <h2 class="mb-2 text-sm font-bold">الدفع والفاتورة</h2>
                <div class="grid gap-2 sm:grid-cols-2">
                    <p><span class="text-slate-500">السعر:</span> {{ number_format((float) ($booking->service?->price ?? 0), 2) }}</p>
                    <p><span class="text-slate-500">المدفوع:</span> {{ number_format((float) ($booking->invoice?->amount_paid ?? 0), 2) }}</p>
                    <p><span class="text-slate-500">المتبقي:</span> {{ number_format((float) ($booking->invoice?->amount_due ?? 0), 2) }}</p>
                    <p><span class="text-slate-500">الفاتورة:</span> {{ $booking->invoice?->invoice_number ?: 'غير متاحة' }}</p>
                </div>

                @if(($booking->invoice?->payments?->count() ?? 0) > 0)
                    <div class="mt-3 space-y-2">
                        @foreach($booking->invoice->payments as $paymentItem)
                            <div class="rounded-lg bg-slate-50 p-2 text-xs">
                                <p>المبلغ: {{ number_format((float) $paymentItem->amount, 2) }}</p>
                                <p>الطريقة: {{ $paymentItem->method ?: '—' }}</p>
                                <p>التاريخ: {{ $paymentItem->payment_date?->timezone($timezone)->locale('ar')->translatedFormat('l، j F') ?: '—' }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="mt-6 grid gap-3 md:grid-cols-2">
                <form method="POST" action="{{ route('appointments.portal.confirm', $booking->public_token) }}">
                    @csrf
                    <button class="w-full rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">تأكيد الحضور</button>
                </form>
                @if($booking->payment_link)
                    <a href="{{ $booking->payment_link }}" target="_blank" rel="noopener" class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">
                        دفع الفاتورة
                    </a>
                @endif
            </div>

            @if($contactPhone || $contactWhatsapp)
                <div class="mt-4 grid gap-2 sm:grid-cols-2">
                    @if($contactPhone)
                        <a href="tel:{{ $contactPhone }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100">اتصال بالشركة</a>
                    @endif
                    @if($contactWhatsapp)
                        <a href="https://wa.me/{{ preg_replace('/\D+/', '', (string) $contactWhatsapp) }}" target="_blank" rel="noopener" class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100">تواصل واتساب</a>
                    @endif
                </div>
            @endif

            <div class="mt-6 grid gap-4 md:grid-cols-2">
                <form method="POST" action="{{ route('appointments.portal.reschedule', $booking->public_token) }}" class="space-y-2 rounded-xl border border-slate-200 p-3">
                    @csrf
                    <h2 class="text-sm font-bold">طلب إعادة جدولة</h2>
                    <input type="date" name="requested_date" class="w-full rounded-lg border-slate-300 text-sm" required>
                    <div class="grid grid-cols-2 gap-2">
                        <input type="time" name="requested_time" class="rounded-lg border-slate-300 text-sm">
                        <input type="time" name="requested_time_end" class="rounded-lg border-slate-300 text-sm">
                    </div>
                    <textarea name="notes" rows="2" class="w-full rounded-lg border-slate-300 text-sm" placeholder="ملاحظات إضافية"></textarea>
                    <button class="w-full rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white">إرسال الطلب</button>
                </form>

                <form method="POST" action="{{ route('appointments.portal.cancel', $booking->public_token) }}" class="space-y-2 rounded-xl border border-rose-200 p-3">
                    @csrf
                    <h2 class="text-sm font-bold text-rose-700">طلب إلغاء الموعد</h2>
                    <textarea name="notes" rows="4" class="w-full rounded-lg border-rose-200 text-sm" placeholder="سبب الإلغاء (اختياري)"></textarea>
                    <button class="w-full rounded-lg bg-rose-600 px-3 py-2 text-xs font-semibold text-white">إرسال طلب الإلغاء</button>
                </form>
            </div>
        </section>
    </main>
</body>
</html>
