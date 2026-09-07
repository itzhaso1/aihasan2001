<section class="mx-auto max-w-4xl px-4 py-12" x-data="publicBookingFunnel()" dir="rtl">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h1 class="text-2xl font-bold text-slate-900">حجز موعد</h1>
        <p class="mt-2 text-sm text-slate-500">اختر الخدمة والموظف والوقت المناسب ثم أكمل بيانات الحجز.</p>

        <div class="mt-6 grid gap-4 md:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">الخدمة</label>
                <select x-model="form.service_id" @change="onServiceChange()" class="w-full rounded-xl border-slate-300 text-sm">
                    <option value="">اختر الخدمة</option>
                    <template x-for="service in services" :key="service.id">
                        <option :value="service.id" x-text="`${service.name} (${service.duration_minutes} د)`"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">الموظف</label>
                <select x-model="form.staff_id" class="w-full rounded-xl border-slate-300 text-sm">
                    <option value="">أي موظف</option>
                    <template x-for="member in staff" :key="member.id">
                        <option :value="member.id" x-text="member.name"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">التاريخ</label>
                <input type="date" x-model="form.date" class="w-full rounded-xl border-slate-300 text-sm">
            </div>
            <div class="flex items-end">
                <button type="button" @click="loadAvailability()" class="ws-btn w-full rounded-xl px-4 py-2 text-sm font-semibold">عرض الأوقات المتاحة</button>
            </div>
        </div>

        <div class="mt-5">
            <p class="mb-2 text-xs font-semibold text-slate-600">الأوقات المتاحة</p>
            <div class="flex flex-wrap gap-2">
                <template x-for="slot in slots" :key="slot.starts_at">
                    <button type="button" @click="selectSlot(slot)" :class="selectedSlot === slot.starts_at ? 'ws-btn text-white' : 'border-slate-300 text-slate-700'" class="rounded-lg border px-3 py-1.5 text-xs font-semibold">
                        <span x-text="slot.start_local_time ?? slot.start_local"></span>
                    </button>
                </template>
            </div>
            <p x-show="slots.length === 0" class="mt-2 text-xs text-slate-500">لم يتم تحميل أوقات بعد.</p>
        </div>

        <div class="mt-6 grid gap-4 md:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">اسم العميل</label>
                <input type="text" x-model="form.customer_name" class="w-full rounded-xl border-slate-300 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">الجوال</label>
                <input type="text" x-model="form.customer_phone" class="w-full rounded-xl border-slate-300 text-sm">
            </div>
            <div class="md:col-span-2">
                <label class="mb-1 block text-xs font-semibold text-slate-600">البريد الإلكتروني</label>
                <input type="email" x-model="form.customer_email" class="w-full rounded-xl border-slate-300 text-sm">
            </div>
            <div class="md:col-span-2">
                <label class="mb-1 block text-xs font-semibold text-slate-600">ملاحظات (اختياري)</label>
                <textarea x-model="form.notes" rows="3" class="w-full rounded-xl border-slate-300 text-sm"></textarea>
            </div>
        </div>

        <div class="mt-6 flex items-center gap-3">
            <button type="button" @click="submitBooking()" class="ws-btn rounded-xl px-5 py-2.5 text-sm font-semibold">تأكيد الحجز</button>
            <span x-show="loading" class="text-xs text-slate-500">جارٍ المعالجة...</span>
        </div>

        <p x-show="errorMessage" x-text="errorMessage" class="mt-3 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700"></p>

        <div x-show="confirmation" class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
            <p class="font-semibold">تم تأكيد الحجز</p>
            <p class="mt-1">المرجع: <span x-text="confirmation?.booking_number"></span></p>
            <p class="mt-1">حالة الدفع: <span x-text="confirmation?.payment_status"></span></p>
            <a x-show="confirmation?.payment_link" :href="confirmation?.payment_link" target="_blank" class="mt-2 inline-flex rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white">إكمال الدفع</a>
        </div>
    </div>
</section>

<script>
    function publicBookingFunnel() {
        const cfg = window.PublicBookingConfig || { routes: {} };
        return {
            loading: false,
            services: [],
            staff: [],
            slots: [],
            selectedSlot: null,
            confirmation: null,
            errorMessage: '',
            form: {
                service_id: '',
                staff_id: '',
                date: '',
                starts_at: '',
                customer_name: '',
                customer_phone: '',
                customer_email: '',
                notes: '',
            },
            async init() {
                await this.loadServices();
            },
            async loadServices() {
                const response = await fetch(cfg.routes.services, { headers: { 'Accept': 'application/json' } });
                const json = await response.json();
                this.services = json.data || [];
            },
            async onServiceChange() {
                this.staff = [];
                this.form.staff_id = '';
                this.slots = [];
                this.selectedSlot = null;
                if (!this.form.service_id) return;
                const url = cfg.routes.serviceStaff.replace('__SERVICE__', this.form.service_id);
                const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
                const json = await response.json();
                this.staff = json.data || [];
            },
            async loadAvailability() {
                this.errorMessage = '';
                this.slots = [];
                this.selectedSlot = null;
                if (!this.form.service_id || !this.form.date) {
                    this.errorMessage = 'اختر الخدمة والتاريخ أولًا.';
                    return;
                }
                const params = new URLSearchParams({
                    service_id: this.form.service_id,
                    date: this.form.date,
                });
                if (this.form.staff_id) params.append('staff_id', this.form.staff_id);

                const response = await fetch(`${cfg.routes.availability}?${params.toString()}`, { headers: { 'Accept': 'application/json' } });
                const json = await response.json();
                this.slots = json.data?.slots || [];
            },
            selectSlot(slot) {
                this.selectedSlot = slot.starts_at;
                this.form.starts_at = slot.starts_at;
            },
            async submitBooking() {
                this.errorMessage = '';
                this.confirmation = null;
                if (!this.form.starts_at) {
                    this.errorMessage = 'يرجى اختيار وقت.';
                    return;
                }
                this.loading = true;
                try {
                    const payload = {
                        service_id: Number(this.form.service_id),
                        staff_id: this.form.staff_id ? Number(this.form.staff_id) : null,
                        starts_at: this.form.starts_at,
                        customer_name: this.form.customer_name,
                        customer_phone: this.form.customer_phone || null,
                        customer_email: this.form.customer_email || null,
                        notes: this.form.notes || null,
                    };

                    const response = await fetch(cfg.routes.store, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        },
                        body: JSON.stringify(payload),
                    });

                    const json = await response.json();
                    if (!response.ok) {
                        this.errorMessage = json.message || 'فشل الحجز.';
                        return;
                    }

                    this.confirmation = json.data;
                } finally {
                    this.loading = false;
                }
            }
        };
    }
</script>
