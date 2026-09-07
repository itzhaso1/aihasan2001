@extends('layouts.appointments', ['pageTitle' => 'Booking Details'])

@section('content')
    @php
        $appointmentStatus = (string) $booking->appointment_status;
        $paymentStatus = (string) $booking->payment_status;
        $invoicePaid = (float) ($booking->invoice?->amount_paid ?? 0);
        $invoiceDue = (float) ($booking->invoice?->amount_due ?? 0);
        $detailActionKeys = match ($appointmentStatus) {
            'scheduled' => ['confirm', 'reschedule', 'cancel', 'reminder', 'payment'],
            'confirmed' => ['check_in', 'reschedule', 'cancel', 'reminder', 'payment'],
            'checked_in' => ['start'],
            'in_progress' => ['complete'],
            default => [],
        };
    @endphp

    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <p class="text-xs text-slate-500">رقم الحجز</p>
                <h2 class="text-xl font-bold text-slate-900">{{ $booking->booking_number }}</h2>
            </div>
            <a href="{{ route('workspace.appointments.bookings.index') }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100">العودة إلى الحجوزات</a>
        </div>

        <div class="grid gap-4 xl:grid-cols-3">
            <section class="xl:col-span-2 space-y-4">
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <h3 class="mb-3 text-sm font-bold text-slate-900">Appointment Details</h3>
                    <dl class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs text-slate-500">الخدمة</dt>
                            <dd class="text-sm font-semibold text-slate-900">{{ $booking->service?->name ?: 'غير محددة' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500">الموظف</dt>
                            <dd class="text-sm font-semibold text-slate-900">{{ $booking->staff?->name ?: 'غير محدد' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500">التاريخ والوقت</dt>
                            <dd class="text-sm font-semibold text-slate-900">{{ $booking->starts_at?->timezone($timezone)->locale('ar')->translatedFormat('l، j F') }}<br>{{ $booking->starts_at?->timezone($timezone)->locale('ar')->translatedFormat('g:i A') }} - {{ $booking->ends_at?->timezone($timezone)->locale('ar')->translatedFormat('g:i A') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500">المدة</dt>
                            <dd class="text-sm font-semibold text-slate-900">{{ max(1, (int) $booking->starts_at?->diffInMinutes($booking->ends_at)) }} دقيقة</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500">المصدر</dt>
                            <dd class="text-sm font-semibold text-slate-900">{{ $sourceLabels[$booking->source_channel] ?? $booking->source_channel }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500">الموارد</dt>
                            <dd class="text-sm font-semibold text-slate-900">
                                @if($booking->resources->isNotEmpty())
                                    {{ $booking->resources->pluck('name')->implode('، ') }}
                                @else
                                    غير مستخدم
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500">حالة الموعد</dt>
                            <dd class="mt-1">
                                @include('workspace.appointments.partials.status-badge', [
                                    'label' => $statusLabels[$appointmentStatus] ?? $appointmentStatus,
                                    'tone' => match ($appointmentStatus) {
                                        'confirmed', 'completed' => 'emerald',
                                        'cancelled', 'no_show' => 'rose',
                                        'checked_in', 'in_progress' => 'blue',
                                        default => 'amber',
                                    }
                                ])
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500">حالة الدفع</dt>
                            <dd class="mt-1">
                                @include('workspace.appointments.partials.status-badge', [
                                    'label' => $paymentStatusLabels[$paymentStatus] ?? $paymentStatus,
                                    'tone' => match ($paymentStatus) {
                                        'paid' => 'emerald',
                                        'failed', 'refunded' => 'rose',
                                        'pending', 'partially_paid' => 'amber',
                                        default => 'slate',
                                    }
                                ])
                            </dd>
                        </div>
                    </dl>
                </article>

                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <h3 class="mb-3 text-sm font-bold text-slate-900">Customer</h3>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <p class="text-sm text-slate-700"><span class="text-slate-500">الاسم:</span> {{ $booking->customer_name }}</p>
                        <p class="text-sm text-slate-700"><span class="text-slate-500">الجوال:</span> {{ $booking->customer_phone ?: '—' }}</p>
                        <p class="text-sm text-slate-700"><span class="text-slate-500">البريد:</span> {{ $booking->customer_email ?: '—' }}</p>
                        <p class="text-sm text-slate-700"><span class="text-slate-500">العمر:</span> {{ $booking->customer_age ?: '—' }}</p>
                    </div>
                    @if($booking->customer_id)
                        <a href="{{ route('workspace.appointments.customers.profile', $booking->customer_id) }}" class="mt-3 inline-flex rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">فتح ملف العميل</a>
                    @endif

                    <div class="mt-4 grid gap-3 md:grid-cols-2">
                        <div>
                            <p class="text-xs font-semibold text-slate-600">آخر موعد سابق</p>
                            @if($lastCustomerAppointment)
                                <p class="text-sm text-slate-700">{{ $lastCustomerAppointment->booking_number }} — {{ $lastCustomerAppointment->starts_at?->timezone($timezone)->locale('ar')->translatedFormat('l، j F - g:i A') }}</p>
                            @else
                                <p class="text-xs text-slate-500">لا يوجد موعد سابق</p>
                            @endif
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-slate-600">مواعيد قادمة للعميل</p>
                            <div class="space-y-1">
                                @forelse($customerUpcomingBookings as $item)
                                    <p class="text-xs text-slate-700">{{ $item->booking_number }} • {{ $item->starts_at?->timezone($timezone)->locale('ar')->translatedFormat('j F - g:i A') }}</p>
                                @empty
                                    <p class="text-xs text-slate-500">لا توجد مواعيد قادمة أخرى</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <p class="text-xs font-semibold text-slate-600">Previous bookings</p>
                        <div class="mt-1 space-y-1">
                            @forelse($customerPastBookings as $item)
                                <p class="text-xs text-slate-600">{{ $item->booking_number }} • {{ $item->starts_at?->timezone($timezone)->locale('ar')->translatedFormat('j F - g:i A') }}</p>
                            @empty
                                <p class="text-xs text-slate-500">لا يوجد تاريخ مواعيد سابق.</p>
                            @endforelse
                        </div>
                    </div>
                </article>

                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <h3 class="mb-3 text-sm font-bold text-slate-900">Payment</h3>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <p class="text-sm text-slate-700"><span class="text-slate-500">الحالة:</span> {{ $paymentStatusLabels[$paymentStatus] ?? $paymentStatus }}</p>
                        <p class="text-sm text-slate-700"><span class="text-slate-500">القيمة:</span> {{ $booking->service ? number_format((float) $booking->service->price, 2) : '0.00' }}</p>
                        <p class="text-sm text-slate-700"><span class="text-slate-500">المدفوع:</span> {{ number_format($invoicePaid, 2) }}</p>
                        <p class="text-sm text-slate-700"><span class="text-slate-500">المتبقي:</span> {{ number_format($invoiceDue, 2) }}</p>
                        <p class="text-sm text-slate-700"><span class="text-slate-500">الفاتورة:</span> {{ $booking->invoice?->invoice_number ?: 'غير متاحة' }}</p>
                        <p class="text-sm text-slate-700"><span class="text-slate-500">تاريخ الدفع:</span> {{ $booking->latestPayment?->paid_at?->timezone($timezone)->locale('ar')->translatedFormat('l، j F - g:i A') ?: '—' }}</p>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-2">
                        @if($booking->payment_link)
                            <a href="{{ $booking->payment_link }}" target="_blank" class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white">فتح رابط الدفع</a>
                        @elseif($canManageBilling)
                            <form method="POST" action="{{ route('workspace.appointments.bookings.payment-link', $booking) }}">
                                @csrf
                                <button class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">إنشاء رابط دفع</button>
                            </form>
                        @endif

                        @if($booking->finance_invoice_id)
                            <a href="{{ route('workspace.finance.invoices.show', $booking->finance_invoice_id) }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">فتح الفاتورة</a>
                        @endif
                    </div>

                    <div class="mt-4">
                        <h4 class="mb-2 text-xs font-semibold text-slate-600">Payment History</h4>
                        <div class="space-y-2">
                            @forelse($booking->invoice?->payments ?? [] as $paymentItem)
                                <div class="rounded-lg border border-slate-200 p-2 text-xs text-slate-700">
                                    <p>المبلغ: {{ number_format((float) $paymentItem->amount, 2) }}</p>
                                    <p>الطريقة: {{ $paymentItem->method ?: '—' }}</p>
                                    <p>التاريخ: {{ $paymentItem->payment_date?->timezone($timezone)->locale('ar')->translatedFormat('l، j F') ?: '—' }}</p>
                                </div>
                            @empty
                                <p class="text-xs text-slate-500">لا توجد دفعات مسجلة بعد.</p>
                            @endforelse
                        </div>
                    </div>
                </article>

                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <h3 class="mb-2 text-sm font-bold text-slate-900">Notes</h3>
                    <p class="text-sm text-slate-700">{{ $booking->notes ?: 'لا توجد ملاحظات داخلية.' }}</p>
                </article>

                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <h3 class="mb-2 text-sm font-bold text-slate-900">Conversation</h3>
                    @if($booking->request?->conversation_id)
                        <a href="{{ route('workspace.conversations.index', ['conversation' => $booking->request->conversation_id]) }}" class="inline-flex rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">فتح قائمة المحادثات</a>
                    @else
                        <p class="text-sm text-slate-500">لا توجد محادثة مرتبطة بهذا الحجز.</p>
                    @endif
                </article>
            </section>

            <aside class="space-y-4">
                @if($canManageBookings || $canManageBilling)
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <h3 class="mb-3 text-sm font-bold text-slate-900">Actions</h3>
                    <div class="space-y-2">
                        @if($detailActionKeys === [] || (! $canManageBookings && ! in_array('payment', $detailActionKeys, true)))
                            <p class="text-xs text-slate-500">لا توجد إجراءات تشغيلية متاحة لهذه الحالة.</p>
                        @endif

                        @if($canManageBookings && in_array('confirm', $detailActionKeys, true))
                            <form method="POST" action="{{ route('workspace.appointments.bookings.status', $booking) }}">
                                @csrf
                                <input type="hidden" name="status" value="confirmed">
                                <button class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100">Confirm</button>
                            </form>
                        @endif
                        @if($canManageBookings && in_array('check_in', $detailActionKeys, true))
                            <form method="POST" action="{{ route('workspace.appointments.bookings.status', $booking) }}">
                                @csrf
                                <input type="hidden" name="status" value="checked_in">
                                <button class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100">Check-in</button>
                            </form>
                        @endif
                        @if($canManageBookings && in_array('start', $detailActionKeys, true))
                            <form method="POST" action="{{ route('workspace.appointments.bookings.status', $booking) }}">
                                @csrf
                                <input type="hidden" name="status" value="in_progress">
                                <button class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100">Start appointment</button>
                            </form>
                        @endif
                        @if($canManageBookings && in_array('complete', $detailActionKeys, true))
                            <form method="POST" action="{{ route('workspace.appointments.bookings.status', $booking) }}">
                                @csrf
                                <input type="hidden" name="status" value="completed">
                                <button class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100">Complete</button>
                            </form>
                        @endif
                        @if($canManageBookings && in_array('reminder', $detailActionKeys, true))
                            <form method="POST" action="{{ route('workspace.appointments.bookings.send-reminder', $booking) }}">
                                @csrf
                                <button class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100">Send Reminder</button>
                            </form>
                        @endif
                        @if($canManageBookings && in_array('cancel', $detailActionKeys, true))
                            <form method="POST" action="{{ route('workspace.appointments.bookings.status', $booking) }}" class="space-y-2">
                                @csrf
                                <input type="hidden" name="status" value="cancelled">
                                <textarea name="cancel_reason" rows="2" placeholder="سبب الإلغاء" class="w-full rounded-lg border-slate-300 text-xs"></textarea>
                                <button class="w-full rounded-lg border border-rose-300 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50">Cancel</button>
                            </form>
                        @endif
                        @if(in_array('payment', $detailActionKeys, true) && $canManageBilling)
                            @if($booking->payment_link)
                                <a href="{{ $booking->payment_link }}" target="_blank" class="inline-flex w-full items-center justify-center rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100">Send Payment Link</a>
                            @else
                                <form method="POST" action="{{ route('workspace.appointments.bookings.payment-link', $booking) }}">
                                    @csrf
                                    <button class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100">Send Payment Link</button>
                                </form>
                            @endif
                        @endif
                    </div>
                </article>
                @endif

                @if($canManageBookings && in_array('reschedule', $detailActionKeys, true))
                <article id="reschedule-form" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <h3 class="mb-3 text-sm font-bold text-slate-900">Reschedule</h3>
                    <form method="POST" action="{{ route('workspace.appointments.bookings.reschedule', $booking) }}" class="space-y-2">
                        @csrf
                        <label class="block text-xs font-semibold text-slate-600">موعد البداية الجديد</label>
                        <input type="datetime-local" name="starts_at" class="w-full rounded-lg border-slate-300 text-xs" required>
                        <label class="block text-xs font-semibold text-slate-600">موظف جديد (اختياري)</label>
                        <select name="staff_id" class="w-full rounded-lg border-slate-300 text-xs">
                            <option value="">بدون تغيير</option>
                            @foreach($allStaff as $staffItem)
                                <option value="{{ $staffItem->id }}" @selected((int) $booking->staff_id === (int) $staffItem->id)>{{ $staffItem->name }}</option>
                            @endforeach
                        </select>
                        <textarea name="reason" rows="2" placeholder="سبب إعادة الجدولة" class="w-full rounded-lg border-slate-300 text-xs"></textarea>
                        <button class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100">تنفيذ إعادة الجدولة</button>
                    </form>
                </article>
                @endif

                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <h3 class="mb-3 text-sm font-bold text-slate-900">Activity Timeline</h3>
                    <div class="space-y-3">
                        @forelse($timelineEntries as $entry)
                            <div class="relative pr-4">
                                <span class="absolute right-0 top-1.5 h-2 w-2 rounded-full bg-slate-300"></span>
                                <p class="text-xs font-semibold text-slate-800">{{ $entry['title'] }}</p>
                                <p class="text-xs text-slate-500">{{ optional($entry['time'])->timezone($timezone)->locale('ar')->translatedFormat('l، j F - g:i A') }}</p>
                                <p class="text-xs text-slate-600">{{ $entry['description'] }}</p>
                                <p class="text-[11px] text-slate-400">بواسطة: {{ $entry['actor'] }}</p>
                            </div>
                        @empty
                            <p class="text-xs text-slate-500">لا توجد أحداث مسجلة بعد لهذا الحجز.</p>
                        @endforelse
                    </div>
                </article>
            </aside>
        </div>
    </div>
@endsection
