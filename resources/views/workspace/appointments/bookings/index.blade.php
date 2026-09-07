@extends('layouts.appointments', ['pageTitle' => 'Bookings'])

@section('content')
    @php
        $todayDate = now($timezone)->toDateString();
        $summaryCards = [
            ['label' => 'Today', 'value' => (int) ($bookingStats['today'] ?? 0), 'query' => ['date' => $todayDate], 'tone' => 'slate'],
            ['label' => 'Upcoming', 'value' => (int) ($bookingStats['upcoming'] ?? 0), 'query' => ['from_date' => $todayDate], 'tone' => 'blue'],
            ['label' => 'Needs Confirmation', 'value' => (int) ($bookingStats['needsConfirmation'] ?? 0), 'query' => ['status' => 'scheduled', 'from_date' => $todayDate], 'tone' => 'amber'],
            ['label' => 'Completed', 'value' => (int) ($bookingStats['completed'] ?? 0), 'query' => ['status' => 'completed'], 'tone' => 'emerald'],
            ['label' => 'Cancelled', 'value' => (int) ($bookingStats['cancelled'] ?? 0), 'query' => ['status' => 'cancelled'], 'tone' => 'rose'],
            ['label' => 'No-show', 'value' => (int) ($bookingStats['noShow'] ?? 0), 'query' => ['status' => 'no_show'], 'tone' => 'violet'],
            ['label' => 'Unpaid / Pending', 'value' => (int) ($bookingStats['paymentAttention'] ?? 0), 'query' => ['payment_bucket' => 'attention'], 'tone' => 'orange'],
        ];
    @endphp

    <div x-data="{ showFilters: {{ ($activeFiltersCount ?? 0) > 0 ? 'true' : 'false' }} }" class="space-y-4">
        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-7">
            @foreach($summaryCards as $card)
                @php
                    $toneClasses = match($card['tone']) {
                        'blue' => 'border-blue-200 bg-blue-50 text-blue-800',
                        'amber' => 'border-amber-200 bg-amber-50 text-amber-800',
                        'emerald' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
                        'rose' => 'border-rose-200 bg-rose-50 text-rose-800',
                        'violet' => 'border-violet-200 bg-violet-50 text-violet-800',
                        'orange' => 'border-orange-200 bg-orange-50 text-orange-800',
                        default => 'border-slate-200 bg-white text-slate-900',
                    };
                @endphp
                <a href="{{ route('workspace.appointments.bookings.index', $card['query']) }}" class="rounded-2xl border p-3 shadow-sm transition hover:-translate-y-0.5 {{ $toneClasses }}">
                    <p class="text-[11px] font-semibold">{{ $card['label'] }}</p>
                    <p class="mt-1 text-2xl font-bold">{{ number_format($card['value']) }}</p>
                </a>
            @endforeach
        </section>

        <div class="grid gap-4 xl:grid-cols-3">
            <section class="xl:col-span-2 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">حجوزات اليوم والتشغيل اليومي</h2>
                        <p class="text-xs text-slate-500">ادخل مباشرة على الحجز، ثم استخدم القائمة للإجراءات المناسبة حسب الحالة.</p>
                    </div>
                    <a href="{{ route('workspace.appointments.calendar.index') }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">فتح Calendar</a>
                </div>

                <form method="GET" action="{{ route('workspace.appointments.bookings.index') }}" class="mb-4 space-y-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="بحث بالعميل / الجوال / رقم الحجز" class="min-w-56 flex-1 rounded-lg border-slate-300 text-sm">
                        <button type="button" @click="showFilters = !showFilters" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100">
                            Filters
                            @if(($activeFiltersCount ?? 0) > 0)
                                <span class="mr-1 rounded-full bg-slate-900 px-2 py-0.5 text-[10px] text-white">{{ $activeFiltersCount }}</span>
                            @endif
                        </button>
                        <a href="{{ route('workspace.appointments.bookings.index') }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100">Clear filters</a>
                        <button class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white">Apply</button>
                    </div>

                    <div x-show="showFilters" x-transition class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <div class="grid gap-2 md:grid-cols-2 xl:grid-cols-4">
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-600">Date</label>
                                <input type="date" name="date" value="{{ $filters['date'] }}" class="w-full rounded-lg border-slate-300 text-sm">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-600">From</label>
                                <input type="date" name="from_date" value="{{ $filters['from_date'] ?? '' }}" class="w-full rounded-lg border-slate-300 text-sm">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-600">To</label>
                                <input type="date" name="to_date" value="{{ $filters['to_date'] ?? '' }}" class="w-full rounded-lg border-slate-300 text-sm">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-600">Staff</label>
                                <select name="staff_id" class="w-full rounded-lg border-slate-300 text-sm">
                                    <option value="">كل الموظفين</option>
                                    @foreach($allStaff as $staff)
                                        <option value="{{ $staff->id }}" @selected((int) ($filters['staff_id'] ?? 0) === (int) $staff->id)>{{ $staff->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-600">Service</label>
                                <select name="service_id" class="w-full rounded-lg border-slate-300 text-sm">
                                    <option value="">كل الخدمات</option>
                                    @foreach($allServices as $service)
                                        <option value="{{ $service->id }}" @selected((int) ($filters['service_id'] ?? 0) === (int) $service->id)>{{ $service->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-600">Appointment status</label>
                                <select name="status" class="w-full rounded-lg border-slate-300 text-sm">
                                    <option value="">كل حالات الموعد</option>
                                    @foreach($statusLabels as $status => $label)
                                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-600">Payment status</label>
                                <select name="payment_status" class="w-full rounded-lg border-slate-300 text-sm">
                                    <option value="">كل حالات الدفع</option>
                                    @foreach($paymentStatusLabels as $status => $label)
                                        <option value="{{ $status }}" @selected(($filters['payment_status'] ?? '') === $status)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-600">Payment attention</label>
                                <select name="payment_bucket" class="w-full rounded-lg border-slate-300 text-sm">
                                    <option value="">الكل</option>
                                    <option value="attention" @selected(($filters['payment_bucket'] ?? '') === 'attention')>غير مدفوع / قيد الانتظار / جزئي</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-600">Source</label>
                                <select name="source" class="w-full rounded-lg border-slate-300 text-sm">
                                    <option value="">كل المصادر</option>
                                    @foreach($sourceLabels as $source => $label)
                                        <option value="{{ $source }}" @selected(($filters['source'] ?? '') === $source)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </form>

                <div class="hidden lg:block overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-2 py-2 text-right">Customer</th>
                                <th class="px-2 py-2 text-right">Service</th>
                                <th class="px-2 py-2 text-right">Staff</th>
                                <th class="px-2 py-2 text-right">Date & Time</th>
                                <th class="px-2 py-2 text-right">Duration</th>
                                <th class="px-2 py-2 text-right">Appointment Status</th>
                                <th class="px-2 py-2 text-right">Payment Status</th>
                                <th class="px-2 py-2 text-right">Source</th>
                                <th class="px-2 py-2 text-right">Last Updated</th>
                                <th class="px-2 py-2 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($bookings as $booking)
                                @php($status = (string) $booking->appointment_status)
                                @php($paymentStatus = (string) $booking->payment_status)
                                <tr>
                                    <td class="px-2 py-2">
                                        <p class="font-semibold text-slate-900">{{ $booking->customer_name }}</p>
                                        <p class="text-xs text-slate-500">{{ $booking->customer_phone ?: '—' }}</p>
                                        <p class="text-[11px] text-slate-400">{{ $booking->booking_number }}</p>
                                    </td>
                                    <td class="px-2 py-2 text-slate-700">{{ $booking->service?->name ?: '—' }}</td>
                                    <td class="px-2 py-2 text-slate-700">{{ $booking->staff?->name ?: 'غير محدد' }}</td>
                                    <td class="px-2 py-2 text-xs text-slate-600">
                                        <p>{{ $booking->starts_at?->timezone($timezone)->locale('ar')->translatedFormat('l، j F') }}</p>
                                        <p>{{ $booking->starts_at?->timezone($timezone)->locale('ar')->translatedFormat('g:i A') }} - {{ $booking->ends_at?->timezone($timezone)->locale('ar')->translatedFormat('g:i A') }}</p>
                                    </td>
                                    <td class="px-2 py-2 text-xs text-slate-600">{{ max(1, (int) $booking->starts_at?->diffInMinutes($booking->ends_at)) }} دقيقة</td>
                                    <td class="px-2 py-2">
                                        @include('workspace.appointments.partials.status-badge', [
                                            'label' => $statusLabels[$status] ?? $status,
                                            'tone' => match ($status) {
                                                'completed', 'confirmed' => 'emerald',
                                                'cancelled', 'no_show' => 'rose',
                                                'checked_in', 'in_progress' => 'blue',
                                                default => 'amber',
                                            }
                                        ])
                                    </td>
                                    <td class="px-2 py-2">
                                        @include('workspace.appointments.partials.status-badge', [
                                            'label' => $paymentStatusLabels[$paymentStatus] ?? $paymentStatus,
                                            'tone' => match ($paymentStatus) {
                                                'paid' => 'emerald',
                                                'failed', 'refunded' => 'rose',
                                                'pending', 'partially_paid' => 'amber',
                                                default => 'slate',
                                            }
                                        ])
                                    </td>
                                    <td class="px-2 py-2 text-xs text-slate-600">{{ $sourceLabels[$booking->source_channel] ?? $booking->source_channel }}</td>
                                    <td class="px-2 py-2 text-xs text-slate-500">{{ $booking->updated_at?->timezone($timezone)->locale('ar')->translatedFormat('j F - g:i A') }}</td>
                                    <td class="px-2 py-2">
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="{{ route('workspace.appointments.bookings.show', $booking) }}" class="rounded-md border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">Open Booking</a>
                                            @include('workspace.appointments.bookings.partials.actions-menu', ['booking' => $booking, 'canManageBookings' => $canManageBookings, 'canManageBilling' => $canManageBilling])
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="px-4 py-10 text-center">
                                        <p class="text-sm font-semibold text-slate-700">لا توجد حجوزات اليوم</p>
                                        <p class="mt-1 text-xs text-slate-500">عند إنشاء أول حجز سيظهر هنا مباشرة.</p>
                                        @if($canManageBookings)
                                            <a href="#new-booking" class="mt-3 inline-flex rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white">إنشاء حجز</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="space-y-3 lg:hidden">
                    @forelse($bookings as $booking)
                        @php($status = (string) $booking->appointment_status)
                        @php($paymentStatus = (string) $booking->payment_status)
                        <article class="rounded-xl border border-slate-200 p-3">
                            <p class="text-sm font-bold text-slate-900">{{ $booking->customer_name }}</p>
                            <p class="text-xs text-slate-500">{{ $booking->service?->name ?: '—' }} • {{ $booking->staff?->name ?: 'غير محدد' }}</p>
                            <p class="mt-1 text-xs text-slate-600">{{ $booking->starts_at?->timezone($timezone)->locale('ar')->translatedFormat('j F') }} · {{ $booking->starts_at?->timezone($timezone)->locale('ar')->translatedFormat('g:i A') }}</p>
                            <p class="text-xs text-slate-500">{{ max(1, (int) $booking->starts_at?->diffInMinutes($booking->ends_at)) }} دقيقة</p>
                            <div class="mt-2 flex flex-wrap gap-1">
                                @include('workspace.appointments.partials.status-badge', [
                                    'label' => $statusLabels[$status] ?? $status,
                                    'tone' => match ($status) {
                                        'completed', 'confirmed' => 'emerald',
                                        'cancelled', 'no_show' => 'rose',
                                        'checked_in', 'in_progress' => 'blue',
                                        default => 'amber',
                                    }
                                ])
                                @include('workspace.appointments.partials.status-badge', [
                                    'label' => $paymentStatusLabels[$paymentStatus] ?? $paymentStatus,
                                    'tone' => match ($paymentStatus) {
                                        'paid' => 'emerald',
                                        'failed', 'refunded' => 'rose',
                                        'pending', 'partially_paid' => 'amber',
                                        default => 'slate',
                                    }
                                ])
                            </div>
                            <div class="mt-3 flex items-center justify-between">
                                <a href="{{ route('workspace.appointments.bookings.show', $booking) }}" class="rounded-md border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">Open</a>
                                @include('workspace.appointments.bookings.partials.actions-menu', ['booking' => $booking, 'canManageBookings' => $canManageBookings, 'canManageBilling' => $canManageBilling])
                            </div>
                        </article>
                    @empty
                        <div class="rounded-xl border border-dashed border-slate-300 p-5 text-center">
                            <p class="text-sm font-semibold text-slate-700">لا توجد حجوزات مطابقة</p>
                            <p class="mt-1 text-xs text-slate-500">عند إنشاء أول حجز سيظهر هنا مباشرة.</p>
                        </div>
                    @endforelse
                </div>

                <div class="mt-3">{{ $bookings->links() }}</div>
            </section>

            @if($canManageBookings)
            <section
                id="new-booking"
                class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
                x-data="{
                    step: 1,
                    serviceId: '',
                    staffId: '',
                    customerId: '',
                    customerName: '',
                    customerPhone: '',
                    customerEmail: '',
                    startsAt: '',
                    source: '{{ array_key_first($sourceLabels) ?? 'dashboard' }}',
                    selectedResources: [],
                    services: @js($allServices->map(fn($item) => ['id' => (string) $item->id, 'name' => $item->name, 'duration' => $item->duration_minutes])->values()),
                    staffList: @js($allStaff->map(fn($item) => ['id' => (string) $item->id, 'name' => $item->name])->values()),
                    customersList: @js($customers->map(fn($item) => ['id' => (string) $item->id, 'name' => $item->name, 'phone' => $item->phone])->values()),
                    resourcesList: @js($allResources->map(fn($item) => ['id' => (string) $item->id, 'name' => $item->name])->values()),
                    sourceLabels: @js($sourceLabels),
                    findLabel(items, id, fallback = 'غير محدد') {
                        if (!id) return fallback;
                        const entry = items.find((item) => String(item.id) === String(id));
                        return entry ? entry.name : fallback;
                    },
                    selectedResourcesLabel() {
                        if (!Array.isArray(this.selectedResources) || this.selectedResources.length === 0) {
                            return 'بدون موارد';
                        }
                        return this.resourcesList
                            .filter((resource) => this.selectedResources.includes(String(resource.id)))
                            .map((resource) => resource.name)
                            .join('، ');
                    },
                    sourceLabel() {
                        return this.sourceLabels[this.source] || this.source || 'غير محدد';
                    },
                    syncCustomerFromList() {
                        const customer = this.customersList.find((item) => String(item.id) === String(this.customerId));
                        if (!customer) {
                            return;
                        }

                        if (!this.customerName) {
                            this.customerName = customer.name || '';
                        }
                        if (!this.customerPhone) {
                            this.customerPhone = customer.phone || '';
                        }
                    }
                }"
            >
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-base font-bold text-slate-900">New Booking</h2>
                    <p class="text-xs text-slate-500">Wizard بسيط من 5 خطوات</p>
                </div>
                <form method="POST" action="{{ route('workspace.appointments.bookings.store') }}" class="space-y-3">
                    @csrf

                    <div x-show="step === 1" class="space-y-2">
                        <p class="text-xs font-semibold text-slate-600">Step 1: Service</p>
                        <select x-model="serviceId" name="service_id" class="w-full rounded-lg border-slate-300 text-sm" required>
                            <option value="">اختر الخدمة</option>
                            @foreach($allServices as $service)
                                <option value="{{ $service->id }}">{{ $service->name }} ({{ $service->duration_minutes }} دقيقة)</option>
                            @endforeach
                        </select>
                    </div>

                    <div x-show="step === 2" class="space-y-2">
                        <p class="text-xs font-semibold text-slate-600">Step 2: Staff / Resource</p>
                        <select x-model="staffId" name="staff_id" class="w-full rounded-lg border-slate-300 text-sm">
                            <option value="">غير محدد</option>
                            @foreach($allStaff as $staff)
                                <option value="{{ $staff->id }}">{{ $staff->name }}</option>
                            @endforeach
                        </select>
                        <select x-model="selectedResources" name="resource_ids[]" multiple class="w-full rounded-lg border-slate-300 text-sm">
                            @foreach($allResources as $resource)
                                <option value="{{ $resource->id }}">{{ $resource->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div x-show="step === 3" class="space-y-2">
                        <p class="text-xs font-semibold text-slate-600">Step 3: Customer</p>
                        <select x-model="customerId" @change="syncCustomerFromList()" name="customer_id" class="w-full rounded-lg border-slate-300 text-sm">
                            <option value="">عميل جديد / بدون ربط</option>
                            @foreach($customers as $customer)
                                <option value="{{ $customer->id }}">{{ $customer->name }} • {{ $customer->phone }}</option>
                            @endforeach
                        </select>
                        <input x-model="customerName" type="text" name="customer_name" placeholder="اسم العميل" class="w-full rounded-lg border-slate-300 text-sm" required>
                        <input x-model="customerPhone" type="text" name="customer_phone" placeholder="رقم الجوال" class="w-full rounded-lg border-slate-300 text-sm">
                        <input x-model="customerEmail" type="email" name="customer_email" placeholder="البريد الإلكتروني" class="w-full rounded-lg border-slate-300 text-sm">
                    </div>

                    <div x-show="step === 4" class="space-y-2">
                        <p class="text-xs font-semibold text-slate-600">Step 4: Date & Time</p>
                        <input x-model="startsAt" type="datetime-local" name="starts_at" class="w-full rounded-lg border-slate-300 text-sm" required>
                        <select x-model="source" name="source" class="w-full rounded-lg border-slate-300 text-sm">
                            @foreach($sourceLabels as $source => $label)
                                <option value="{{ $source }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div x-show="step === 5" class="space-y-2 rounded-lg border border-slate-200 bg-slate-50 p-3">
                        <p class="text-xs font-semibold text-slate-600">Step 5: Confirmation</p>
                        <p class="text-xs text-slate-700">راجع البيانات المدخلة ثم اضغط Create Booking. سيتم التحقق من التداخل والقواعد في الـBackend.</p>
                        <dl class="grid gap-2 rounded-lg border border-slate-200 bg-white p-3 text-xs sm:grid-cols-2">
                            <div>
                                <dt class="text-slate-500">الخدمة</dt>
                                <dd class="font-semibold text-slate-800" x-text="findLabel(services, serviceId, 'غير محددة')"></dd>
                            </div>
                            <div>
                                <dt class="text-slate-500">الموظف</dt>
                                <dd class="font-semibold text-slate-800" x-text="findLabel(staffList, staffId, 'غير محدد')"></dd>
                            </div>
                            <div>
                                <dt class="text-slate-500">العميل</dt>
                                <dd class="font-semibold text-slate-800" x-text="customerName || findLabel(customersList, customerId, 'غير محدد')"></dd>
                            </div>
                            <div>
                                <dt class="text-slate-500">رقم الجوال</dt>
                                <dd class="font-semibold text-slate-800" x-text="customerPhone || '—'"></dd>
                            </div>
                            <div>
                                <dt class="text-slate-500">وقت البداية</dt>
                                <dd class="font-semibold text-slate-800" x-text="startsAt || 'غير محدد'"></dd>
                            </div>
                            <div>
                                <dt class="text-slate-500">قناة المصدر</dt>
                                <dd class="font-semibold text-slate-800" x-text="sourceLabel()"></dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt class="text-slate-500">الموارد</dt>
                                <dd class="font-semibold text-slate-800" x-text="selectedResourcesLabel()"></dd>
                            </div>
                        </dl>
                        <textarea name="notes" rows="3" class="w-full rounded-lg border-slate-300 text-sm" placeholder="ملاحظات داخلية (اختياري)"></textarea>
                    </div>

                    <div class="flex items-center justify-between">
                        <button type="button" @click="step = Math.max(1, step - 1)" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100">السابق</button>
                        <div class="text-xs text-slate-500">الخطوة <span x-text="step"></span> / 5</div>
                        <button type="button" x-show="step < 5" @click="step = Math.min(5, step + 1)" class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white">التالي</button>
                        <button x-show="step === 5" class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white">Create Booking</button>
                    </div>
                </form>
            </section>
            @endif
        </div>
    </div>
@endsection
