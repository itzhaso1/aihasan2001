@extends('layouts.appointments', ['pageTitle' => 'Request Details'])

@section('content')
    @php($status = (string) $appointmentRequest->status)

    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <p class="text-xs text-slate-500">Request #{{ $appointmentRequest->id }}</p>
                <h2 class="text-xl font-bold text-slate-900">تفاصيل طلب الموعد</h2>
            </div>
            <a href="{{ route('workspace.appointments.requests.index') }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100">العودة إلى الطلبات</a>
        </div>

        <div class="grid gap-4 xl:grid-cols-3">
            <section class="xl:col-span-2 space-y-4">
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <h3 class="mb-3 text-sm font-bold text-slate-900">Request Information</h3>
                    <p class="mb-3 rounded-lg bg-slate-50 p-2 text-xs text-slate-600">
                        <strong>Request:</strong> طلب لم يتحول بعد إلى حجز نهائي.
                        @if($appointmentRequest->booking)
                            <br><strong>Booking المرتبط:</strong>
                            <a href="{{ route('workspace.appointments.bookings.show', $appointmentRequest->booking) }}" class="font-semibold text-slate-800 underline">#{{ $appointmentRequest->booking->booking_number }}</a>
                        @endif
                    </p>
                    <dl class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs text-slate-500">العميل</dt>
                            <dd class="text-sm font-semibold text-slate-900">{{ $appointmentRequest->customer_name }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500">الجوال / البريد</dt>
                            <dd class="text-sm font-semibold text-slate-900">{{ $appointmentRequest->customer_phone ?: $appointmentRequest->customer_email ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500">الخدمة المطلوبة</dt>
                            <dd class="text-sm font-semibold text-slate-900">{{ $appointmentRequest->service?->name ?: 'غير محددة' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500">الموظف المطلوب</dt>
                            <dd class="text-sm font-semibold text-slate-900">{{ $appointmentRequest->staff?->name ?: 'غير محدد' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500">التاريخ المطلوب</dt>
                            <dd class="text-sm font-semibold text-slate-900">
                                @if($appointmentRequest->requested_date)
                                    {{ $appointmentRequest->requested_date->locale('ar')->translatedFormat('l، j F') }}
                                @else
                                    غير محدد
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500">الفترة المطلوبة</dt>
                            <dd class="text-sm font-semibold text-slate-900">
                                {{ $appointmentRequest->requested_time ?: 'أي وقت' }}
                                @if($appointmentRequest->requested_time_end)
                                    - {{ $appointmentRequest->requested_time_end }}
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500">المصدر</dt>
                            <dd class="text-sm font-semibold text-slate-900">{{ $sourceLabels[$appointmentRequest->source] ?? $appointmentRequest->source }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500">الحالة</dt>
                            <dd class="mt-1">
                                @include('workspace.appointments.partials.status-badge', [
                                    'label' => $requestStatusLabels[$status] ?? $status,
                                    'tone' => match ($status) {
                                        'approved' => 'emerald',
                                        'rejected', 'cancelled', 'expired' => 'rose',
                                        'awaiting_customer' => 'blue',
                                        default => 'amber',
                                    }
                                ])
                            </dd>
                        </div>
                    </dl>
                    <div class="mt-4 rounded-lg bg-slate-50 p-3 text-sm text-slate-700">
                        <p class="font-semibold text-slate-900">Customer Message / Notes</p>
                        <p class="mt-1">{{ $appointmentRequest->notes ?: 'لا توجد ملاحظات.' }}</p>
                    </div>
                    @if($appointmentRequest->conversation_id)
                        <a href="{{ route('workspace.conversations.index', ['conversation' => $appointmentRequest->conversation_id]) }}" class="mt-3 inline-flex rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">Open Conversation</a>
                    @endif
                    @if($appointmentRequest->customer_id)
                        <a href="{{ route('workspace.appointments.customers.profile', $appointmentRequest->customer_id) }}" class="mt-3 inline-flex rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">Open Customer</a>
                    @endif
                </article>

                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <h3 class="mb-3 text-sm font-bold text-slate-900">Proposed Slots</h3>
                    <div class="space-y-2">
                        @forelse($appointmentRequest->slots as $slot)
                            <div class="rounded-xl border border-slate-200 p-3">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <p class="text-sm font-semibold text-slate-900">
                                        {{ $slot->starts_at?->timezone($timezone)->locale('ar')->translatedFormat('l، j F - g:i A') }}
                                        —
                                        {{ $slot->ends_at?->timezone($timezone)->locale('ar')->translatedFormat('g:i A') }}
                                    </p>
                                    <div class="flex items-center gap-2">
                                        @include('workspace.appointments.partials.status-badge', [
                                            'label' => match($slot->status) {
                                                'selected' => 'تم الاختيار',
                                                'rejected' => 'مرفوض',
                                                'expired' => 'منتهي',
                                                default => 'مقترح',
                                            },
                                            'tone' => match($slot->status) {
                                                'selected' => 'emerald',
                                                'rejected', 'expired' => 'rose',
                                                default => 'blue',
                                            }
                                        ])
                                        @if($canManageRequests && $slot->status === 'proposed')
                                            <form method="POST" action="{{ route('workspace.appointments.requests.slots.select', [$appointmentRequest, $slot]) }}">
                                                @csrf
                                                <button class="rounded-md border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">اعتماد هذا الموعد</button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-xl border border-dashed border-slate-300 p-4 text-center">
                                <p class="text-sm font-semibold text-slate-700">لا توجد مواعيد مقترحة بعد</p>
                                <p class="mt-1 text-xs text-slate-500">يمكنك إضافة خيارات مواعيد متعددة للعميل من النموذج الجانبي.</p>
                            </div>
                        @endforelse
                    </div>
                </article>

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
                            <p class="text-xs text-slate-500">لا يوجد نشاط مسجل لهذا الطلب.</p>
                        @endforelse
                    </div>
                </article>
            </section>

            <aside class="space-y-4">
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <h3 class="mb-3 text-sm font-bold text-slate-900">Actions</h3>
                    @if($canManageRequests && ! in_array($appointmentRequest->status, ['approved', 'rejected', 'cancelled', 'expired'], true))
                        <div class="space-y-2">
                            <form method="POST" action="{{ route('workspace.appointments.requests.approve', $appointmentRequest) }}">
                                @csrf
                                <button class="w-full rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white">Approve</button>
                            </form>
                            <form id="request-info" method="POST" action="{{ route('workspace.appointments.requests.awaiting-customer', $appointmentRequest) }}" class="space-y-2">
                                @csrf
                                <textarea name="message" rows="2" class="w-full rounded-lg border-slate-300 text-xs" placeholder="رسالة للعميل"></textarea>
                                <button class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100">Request Information</button>
                            </form>
                            <form method="POST" action="{{ route('workspace.appointments.requests.reject', $appointmentRequest) }}" class="space-y-2">
                                @csrf
                                <textarea name="reason" rows="2" class="w-full rounded-lg border-slate-300 text-xs" placeholder="سبب الرفض"></textarea>
                                <button class="w-full rounded-lg border border-rose-300 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50">Reject</button>
                            </form>
                            <form method="POST" action="{{ route('workspace.appointments.requests.cancel', $appointmentRequest) }}" class="space-y-2">
                                @csrf
                                <textarea name="reason" rows="2" class="w-full rounded-lg border-slate-300 text-xs" placeholder="سبب الإلغاء"></textarea>
                                <button class="w-full rounded-lg border border-rose-300 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50">Cancel Request</button>
                            </form>
                        </div>
                    @else
                        <p class="text-xs text-slate-500">لا توجد إجراءات متاحة على هذا الطلب.</p>
                    @endif
                </article>

                @if($canManageRequests)
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <h3 class="mb-3 text-sm font-bold text-slate-900">Propose Slots</h3>
                    <form id="propose-slots" method="POST" action="{{ route('workspace.appointments.requests.slots.store', $appointmentRequest) }}" class="space-y-3">
                        @csrf
                        @for($i = 0; $i < 3; $i++)
                            <div class="rounded-lg border border-slate-200 p-2">
                                <p class="mb-2 text-xs font-semibold text-slate-600">الخيار {{ $i + 1 }}</p>
                                <input type="datetime-local" name="slots[{{ $i }}][starts_at]" class="mb-2 w-full rounded-lg border-slate-300 text-xs">
                                <input type="datetime-local" name="slots[{{ $i }}][ends_at]" class="w-full rounded-lg border-slate-300 text-xs">
                                <input type="hidden" name="slots[{{ $i }}][staff_id]" value="{{ $appointmentRequest->requested_staff_id }}">
                                <input type="hidden" name="slots[{{ $i }}][service_id]" value="{{ $appointmentRequest->requested_service_id }}">
                            </div>
                        @endfor
                        <button class="w-full rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white">Send to Customer</button>
                    </form>
                </article>
                @endif
            </aside>
        </div>
    </div>
@endsection
