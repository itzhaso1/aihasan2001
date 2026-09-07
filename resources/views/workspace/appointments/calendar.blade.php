@extends('layouts.appointments', ['pageTitle' => 'Calendar'])

@section('content')
    <div
        x-data="appointmentsCalendar({
            endpoint: '{{ route('workspace.appointments.calendar.events') }}',
            initialDate: '{{ $defaultDate }}',
            staff: @js($allStaff->map(fn($staff) => ['id' => $staff->id, 'name' => $staff->name])->values())
        })"
        x-init="init()"
        class="space-y-4"
    >
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Calendar</h2>
                    <p class="text-xs text-slate-500">جميع المواعيد تعرض بتوقيت النشاط: {{ $timezone }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <input type="date" x-model="date" @change="load()" class="rounded-lg border-slate-300 text-sm">
                    <select x-model="staffId" @change="load()" class="rounded-lg border-slate-300 text-sm">
                        <option value="">كل الموظفين</option>
                        <template x-for="staff in staffOptions" :key="staff.id">
                            <option :value="String(staff.id)" x-text="staff.name"></option>
                        </template>
                    </select>
                    <div class="inline-flex rounded-lg border border-slate-200 p-1 text-xs">
                        <button type="button" @click="mode='day'; load()" :class="mode==='day' ? 'bg-slate-900 text-white' : 'text-slate-600'" class="rounded-md px-3 py-1">Day</button>
                        <button type="button" @click="mode='week'; load()" :class="mode==='week' ? 'bg-slate-900 text-white' : 'text-slate-600'" class="rounded-md px-3 py-1">Week</button>
                        <button type="button" @click="mode='month'; load()" :class="mode==='month' ? 'bg-slate-900 text-white' : 'text-slate-600'" class="rounded-md px-3 py-1">Month</button>
                    </div>
                </div>
            </div>
        </div>

        <template x-if="loading">
            <div class="rounded-2xl border border-slate-200 bg-white p-10 text-center text-sm text-slate-500">جاري تحميل المواعيد...</div>
        </template>
        <template x-if="!loading && errorMessage">
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
                <p x-text="errorMessage"></p>
            </div>
        </template>

        <template x-if="!loading && !errorMessage && mode === 'day'">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="mb-3 text-sm font-bold text-slate-900">مواعيد اليوم حسب الموظف</h3>
                <div class="overflow-x-auto">
                    <div class="grid gap-3" :style="`grid-template-columns: repeat(${staffColumns().length}, minmax(220px, 1fr));`">
                        <template x-for="staff in staffColumns()" :key="staff.id">
                            <article class="rounded-xl border border-slate-200 p-3">
                                <h4 class="text-xs font-bold text-slate-700" x-text="staff.name"></h4>
                                <div class="mt-2 space-y-2">
                                    <template x-for="event in dayEventsByStaff(staff.id)" :key="event.id">
                                        <a :href="bookingUrl(event.id)" class="block rounded-lg border border-slate-200 p-2 transition hover:border-slate-300 hover:bg-slate-50">
                                            <p class="text-xs font-semibold text-slate-900" x-text="event.customer"></p>
                                            <p class="text-[11px] text-slate-600" x-text="event.time_label"></p>
                                            <p class="text-[11px] text-slate-500" x-text="`${event.service || 'خدمة'} • ${statusLabel(event.appointment_status)}`"></p>
                                        </a>
                                    </template>
                                    <p x-show="dayEventsByStaff(staff.id).length === 0" class="text-[11px] text-slate-400">لا توجد مواعيد</p>
                                </div>
                            </article>
                        </template>
                    </div>
                </div>
            </div>
        </template>

        <template x-if="!loading && !errorMessage && mode === 'week'">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="mb-3 text-sm font-bold text-slate-900">Week View حسب الموظف</h3>
                <div class="overflow-x-auto">
                    <div class="grid gap-3" :style="`grid-template-columns: repeat(${staffColumns().length}, minmax(260px, 1fr));`">
                        <template x-for="staff in staffColumns()" :key="staff.id">
                            <article class="rounded-xl border border-slate-200 p-3">
                                <h4 class="text-xs font-bold text-slate-800" x-text="staff.name"></h4>
                                <div class="mt-2 space-y-3">
                                    <template x-for="day in weekDays()" :key="`${staff.id}-${day.key}`">
                                        <div>
                                            <p class="mb-1 text-[11px] font-semibold text-slate-500" x-text="day.label"></p>
                                            <div class="space-y-1">
                                                <template x-for="event in eventsByStaffAndDay(staff.id, day.key)" :key="event.id">
                                                    <a :href="bookingUrl(event.id)" class="block rounded-lg border border-slate-200 p-2 transition hover:border-slate-300 hover:bg-slate-50">
                                                        <p class="text-xs font-semibold text-slate-900" x-text="event.customer"></p>
                                                        <p class="text-[11px] text-slate-500" x-text="event.time_label"></p>
                                                        <p class="text-[11px] text-slate-600" x-text="`${event.service || 'خدمة'} • ${paymentLabel(event.payment_status)}`"></p>
                                                    </a>
                                                </template>
                                                <p x-show="eventsByStaffAndDay(staff.id, day.key).length === 0" class="text-[11px] text-slate-400">—</p>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </article>
                        </template>
                    </div>
                </div>
            </div>
        </template>

        <template x-if="!loading && !errorMessage && mode === 'month'">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="mb-2 grid grid-cols-7 gap-2 text-center text-xs font-bold text-slate-500">
                    <span>الأحد</span><span>الاثنين</span><span>الثلاثاء</span><span>الأربعاء</span><span>الخميس</span><span>الجمعة</span><span>السبت</span>
                </div>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-7">
                    <template x-for="day in monthDays()" :key="day.key">
                        <article class="min-h-32 rounded-xl border border-slate-200 p-2">
                            <p class="text-xs font-bold text-slate-700" x-text="day.label"></p>
                            <div class="mt-2 space-y-1">
                                <template x-for="event in eventsByDay(day.key).slice(0, 3)" :key="event.id">
                                    <a :href="bookingUrl(event.id)" class="block truncate rounded-md bg-slate-100 px-2 py-1 text-[11px] text-slate-700" :title="event.customer + ' ' + event.time_label" x-text="event.customer + ' • ' + event.time_label"></a>
                                </template>
                                <p x-show="eventsByDay(day.key).length > 3" class="text-[11px] text-slate-500" x-text="'+' + (eventsByDay(day.key).length - 3) + ' مواعيد'"></p>
                            </div>
                        </article>
                    </template>
                </div>
            </div>
        </template>
    </div>

    <script>
        function appointmentsCalendar(config) {
            return {
                endpoint: config.endpoint,
                date: config.initialDate,
                mode: 'week',
                staffOptions: Array.isArray(config.staff) ? config.staff : [],
                staffId: '',
                loading: false,
                events: [],
                errorMessage: '',
                init() {
                    this.load();
                },
                async load() {
                    this.loading = true;
                    this.errorMessage = '';
                    const params = new URLSearchParams({ view: this.mode, date: this.date });
                    if (this.staffId) {
                        params.set('staff_id', this.staffId);
                    }
                    try {
                        const response = await fetch(`${this.endpoint}?${params.toString()}`);
                        if (!response.ok) {
                            throw new Error(`HTTP_${response.status}`);
                        }
                        const payload = await response.json();
                        this.events = Array.isArray(payload.data) ? payload.data : [];
                    } catch (error) {
                        this.events = [];
                        this.errorMessage = 'تعذر تحميل بيانات التقويم حاليًا. حاول تحديث الصفحة أو تغيير الفلتر.';
                    } finally {
                        this.loading = false;
                    }
                },
                bookingUrl(id) {
                    return `{{ url('/workspace/appointments/bookings') }}/${id}`;
                },
                dayEvents() {
                    return this.events
                        .filter((event) => event.date_key === this.date)
                        .sort((a, b) => (a.start_ts || 0) - (b.start_ts || 0));
                },
                dayEventsByStaff(staffId) {
                    return this.dayEvents().filter((event) => this.matchesStaff(event, staffId));
                },
                eventsByDay(dateKey) {
                    return this.events
                        .filter((event) => event.date_key === dateKey)
                        .sort((a, b) => (a.start_ts || 0) - (b.start_ts || 0));
                },
                eventsByStaffAndDay(staffId, dayKey) {
                    return this.eventsByDay(dayKey).filter((event) => this.matchesStaff(event, staffId));
                },
                staffColumns() {
                    const unassigned = { id: 'unassigned', name: 'بدون موظف' };
                    if (this.staffId) {
                        const selected = this.staffOptions.find((item) => String(item.id) === String(this.staffId));
                        return selected ? [selected] : [unassigned];
                    }

                    return [...this.staffOptions, unassigned];
                },
                matchesStaff(event, staffId) {
                    if (staffId === 'unassigned') {
                        return !event.staff;
                    }

                    return String(event.staff_id || '') === String(staffId);
                },
                weekDays() {
                    const selected = new Date(this.date + 'T12:00:00');
                    const day = selected.getDay();
                    const start = new Date(selected);
                    start.setDate(selected.getDate() - day);
                    const labels = ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
                    return labels.map((label, index) => {
                        const d = new Date(start);
                        d.setDate(start.getDate() + index);
                        return {
                            key: this.toDateKey(d),
                            label: `${label} ${d.getDate()}`,
                        };
                    });
                },
                monthDays() {
                    const selected = new Date(this.date + 'T12:00:00');
                    const year = selected.getFullYear();
                    const month = selected.getMonth();
                    const daysInMonth = new Date(year, month + 1, 0).getDate();
                    const days = [];
                    for (let i = 1; i <= daysInMonth; i++) {
                        const day = new Date(year, month, i, 12, 0, 0);
                        days.push({
                            key: this.toDateKey(day),
                            label: i,
                        });
                    }
                    return days;
                },
                toDateKey(dateObj) {
                    const y = dateObj.getFullYear();
                    const m = String(dateObj.getMonth() + 1).padStart(2, '0');
                    const d = String(dateObj.getDate()).padStart(2, '0');
                    return `${y}-${m}-${d}`;
                },
                statusLabel(status) {
                    return {
                        scheduled: 'مجدول',
                        confirmed: 'مؤكد',
                        checked_in: 'تم تسجيل الحضور',
                        in_progress: 'قيد التنفيذ',
                        completed: 'مكتمل',
                        cancelled: 'ملغي',
                        no_show: 'لم يحضر',
                    }[status] || status;
                },
                paymentLabel(status) {
                    return {
                        unpaid: 'غير مدفوع',
                        pending: 'قيد الانتظار',
                        paid: 'مدفوع',
                        failed: 'فشل الدفع',
                        refunded: 'مسترجع',
                        partially_paid: 'جزئي',
                    }[status] || status;
                }
            };
        }
    </script>
@endsection
