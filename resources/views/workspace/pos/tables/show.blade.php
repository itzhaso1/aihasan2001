@extends('layouts.pos', ['pageTitle' => $table->name])

@section('content')
    @php($menuGroups = $menuItems->groupBy(fn ($item) => $item->category?->name ?: ($item->item_type ?: 'عام')))
    @php($defaultCategory = (string) ($menuGroups->keys()->first() ?? ''))
    @php($hasCurrentSession = (bool) $currentSession)
    @php($openedAtIso = $currentSession?->opened_at?->toIso8601String())
    @php($tableOpenLabel = $hasCurrentSession ? 'مفتوحة' : 'مغلقة')
    @php($billableOrders = $sessionOrders->reject(fn ($order) => $order->pos_status === 'cancelled'))
    @php($sessionSubtotal = (float) $billableOrders->sum('subtotal'))
    @php($sessionDiscount = (float) $billableOrders->sum('discount_amount'))
    @php($sessionTotal = (float) $billableOrders->sum('total_amount'))
    @php($ordersCount = $billableOrders->count())
    @php($itemsCount = (int) $billableOrders->sum(fn ($order) => $order->items->sum('quantity')))
    @php($sessionNote = (string) ($billableOrders->firstWhere(fn ($o) => filled($o->notes))?->notes ?? ''))
    @php($splitItems = $billableOrders->flatMap(fn ($order) => $order->items->map(fn ($item) => [
        'id' => $item->id,
        'name' => $item->product_name.($item->variant_name ? ' - '.$item->variant_name : ''),
        'quantity' => (int) $item->quantity,
        'unit_price' => (float) $item->unit_price,
        'total' => (float) $item->total_amount,
    ]))->values())
    @php($otherTablesJson = $otherTables->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'status' => $t->status])->values())

    <div
        class="pb-28"
        x-data="tableShowBoard({
            defaultCategory: @js($defaultCategory),
            splitItems: @js($splitItems),
            otherTables: @js($otherTablesJson),
            sessionNote: @js($sessionNote),
            hasSession: @js($hasCurrentSession),
        })"
    >
        <div class="mb-3 flex items-center justify-between gap-2">
            <a href="{{ route('workspace.pos.tables.index') }}" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">← رجوع للطاولات</a>
            <h1 class="text-sm font-bold text-slate-900">{{ $table->name }}</h1>
        </div>

        {{-- RTL: first column = RIGHT = table info --}}
        <section class="grid gap-3 xl:grid-cols-12">
            {{-- RIGHT: معلومات الطاولة + خيارات --}}
            <aside class="space-y-3 xl:col-span-4 xl:order-1">
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <h2 class="text-sm font-bold text-slate-900">معلومات الطاولة</h2>
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <p class="text-2xl font-extrabold text-slate-900">{{ $table->name }}</p>
                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $table->status === 'occupied' ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700' }}">
                            <span class="h-1.5 w-1.5 rounded-full {{ $table->status === 'occupied' ? 'bg-rose-500' : 'bg-emerald-500' }}"></span>
                            {{ $table->status === 'occupied' ? 'مشغولة' : 'فارغة' }}
                        </span>
                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $hasCurrentSession ? 'bg-slate-100 text-slate-700' : 'bg-slate-100 text-slate-600' }}">
                            {{ $tableOpenLabel }}
                        </span>
                    </div>
                    <p class="mt-2 text-xs text-slate-500">
                        @if($hasCurrentSession)
                            المدة: <span class="font-semibold text-slate-700" data-opened-at="{{ $openedAtIso }}">00:00:00</span>
                        @else
                            لا توجد جلسة نشطة
                        @endif
                    </p>

                    <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                        <div class="rounded-xl border border-slate-100 bg-slate-50 px-2 py-2">
                            <p class="text-sm font-bold text-slate-900">{{ $ordersCount }}</p>
                            <p class="text-[10px] text-slate-500">طلبات</p>
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-slate-50 px-2 py-2">
                            <p class="text-sm font-bold text-slate-900">{{ $itemsCount }}</p>
                            <p class="text-[10px] text-slate-500">أصناف</p>
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-slate-50 px-2 py-2">
                            <p class="text-sm font-bold text-emerald-700">{{ number_format($sessionTotal, 2) }}</p>
                            <p class="text-[10px] text-slate-500">الإجمالي</p>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-2">
                        <button type="button" @click="panel = 'addOrder'" :disabled="!hasSession" class="rounded-xl border border-emerald-600 bg-emerald-600 px-3 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50">+ إضافة طلب</button>
                    </div>
                </article>

                <article class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
                    <h3 class="px-1 text-sm font-bold text-slate-900">خيارات الطاولة</h3>
                    <div class="mt-2 divide-y divide-slate-100">
                        <button type="button" @click="panel = 'transfer'" :disabled="!hasSession" class="flex w-full items-center justify-between gap-2 px-2 py-2.5 text-right text-sm text-slate-700 hover:bg-slate-50 disabled:opacity-40">
                            <span>نقل الطاولة</span>
                            <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                        </button>
                        <button type="button" @click="panel = 'split'" :disabled="!hasSession || splitItems.length === 0" class="flex w-full items-center justify-between gap-2 px-2 py-2.5 text-right text-sm text-slate-700 hover:bg-slate-50 disabled:opacity-40">
                            <span>تقسيم الحساب</span>
                            <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h7v7H4V6zm9 0h7v7h-7V6zM4 15h7v5H4v-5zm9 0h7v5h-7v-5z"/></svg>
                        </button>
                        <button type="button" @click="panel = 'merge'" :disabled="!hasSession" class="flex w-full items-center justify-between gap-2 px-2 py-2.5 text-right text-sm text-slate-700 hover:bg-slate-50 disabled:opacity-40">
                            <span>دمج طاولة</span>
                            <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 7h4v4H7V7zm6 0h4v4h-4V7zM7 13h4v4H7v-4zm6 0h4v4h-4v-4z"/></svg>
                        </button>
                        <button type="button" @click="panel = 'addOrder'" :disabled="!hasSession" class="flex w-full items-center justify-between gap-2 px-2 py-2.5 text-right text-sm text-slate-700 hover:bg-slate-50 disabled:opacity-40">
                            <span>إضافة طلب</span>
                            <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v6m3-3H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </button>
                        <button type="button" @click="panel = 'note'" :disabled="!hasSession" class="flex w-full items-center justify-between gap-2 px-2 py-2.5 text-right text-sm text-slate-700 hover:bg-slate-50 disabled:opacity-40">
                            <span>إضافة ملاحظة</span>
                            <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15.232 5.232l3.536 3.536M4 20h4.586a1 1 0 00.707-.293l9.414-9.414a2 2 0 000-2.828l-2.172-2.172a2 2 0 00-2.828 0L4.293 14.707A1 1 0 004 15.414V20z"/></svg>
                        </button>
                        <button type="button" onclick="window.print()" class="flex w-full items-center justify-between gap-2 px-2 py-2.5 text-right text-sm text-slate-700 hover:bg-slate-50">
                            <span>طباعة الحساب</span>
                            <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 9V4h12v5M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v6H6v-6z"/></svg>
                        </button>
                        @if($currentSession)
                            <button type="button" @click="panel = 'closeConfirm'" class="flex w-full items-center justify-between gap-2 px-2 py-2.5 text-right text-sm text-slate-700 hover:bg-slate-50">
                                <span>إغلاق الطاولة</span>
                                <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            </button>
                            <button type="button" @click="panel = 'cancelConfirm'" class="flex w-full items-center justify-between gap-2 px-2 py-2.5 text-right text-sm font-semibold text-rose-600 hover:bg-rose-50">
                                <span>إلغاء الطاولة</span>
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16"/></svg>
                            </button>
                        @else
                            <form method="POST" action="{{ route('workspace.pos.tables.sessions.open', $table) }}">
                                @csrf
                                <button class="flex w-full items-center justify-between gap-2 px-2 py-2.5 text-right text-sm font-semibold text-emerald-700 hover:bg-emerald-50">
                                    <span>فتح جلسة</span>
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 11V7a4 4 0 118 0v4m-9 0h10a2 2 0 012 2v6a2 2 0 01-2 2H7a2 2 0 01-2-2v-6a2 2 0 012-2z"/></svg>
                                </button>
                            </form>
                        @endif
                    </div>
                </article>
            </aside>

            {{-- LEFT: تفاصيل الطلبات --}}
            <section class="xl:col-span-8 xl:order-2">
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-sm font-bold text-slate-900">تفاصيل طلبات الطاولة</h2>
                        <div class="flex flex-wrap gap-1.5">
                            <button type="button" @click="filter = 'all'" :class="filter === 'all' ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-slate-600 border-slate-200'" class="rounded-full border px-3 py-1 text-[11px] font-semibold">الكل ({{ $sessionOrders->count() }})</button>
                            <button type="button" @click="filter = 'open'" :class="filter === 'open' ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-slate-600 border-slate-200'" class="rounded-full border px-3 py-1 text-[11px] font-semibold">مفتوحة ({{ $billableOrders->reject(fn($o) => $o->payment_status === 'paid')->count() }})</button>
                            <button type="button" @click="filter = 'paid'" :class="filter === 'paid' ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-slate-600 border-slate-200'" class="rounded-full border px-3 py-1 text-[11px] font-semibold">مدفوعة ({{ $billableOrders->where('payment_status', 'paid')->count() }})</button>
                            <button type="button" @click="filter = 'cancelled'" :class="filter === 'cancelled' ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-slate-600 border-slate-200'" class="rounded-full border px-3 py-1 text-[11px] font-semibold">ملغية ({{ $sessionOrders->where('pos_status', 'cancelled')->count() }})</button>
                        </div>
                    </div>
                    <input x-model="search" type="search" placeholder="ابحث في الطلبات..." class="mt-3 w-full rounded-xl border-slate-200 text-sm" />

                    <div class="mt-4 space-y-3">
                        @forelse($sessionOrders as $order)
                            @php($cashierName = (string) data_get($order->metadata, 'created_by_name', data_get($order->metadata, 'payment_method') === 'cashier' ? 'كاشير مباشر' : 'طلب'))
                            @php($statusKey = $order->pos_status === 'cancelled' ? 'cancelled' : ($order->payment_status === 'paid' ? 'paid' : 'open'))
                            <article
                                class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm"
                                data-order-card
                                data-status="{{ $statusKey }}"
                                data-search="{{ strtolower($order->order_number.' '.$cashierName) }}"
                                x-show="matchesOrder(@js($statusKey), @js(strtolower($order->order_number.' '.$cashierName)))"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-lg bg-emerald-50 px-2 py-1 text-[11px] font-bold text-emerald-700">#{{ $order->order_number }}</span>
                                        <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $order->pos_status === 'cancelled' ? 'bg-rose-50 text-rose-700' : ($order->payment_status === 'paid' ? 'bg-slate-100 text-slate-700' : 'bg-emerald-50 text-emerald-700') }}">
                                            {{ $posStatuses[$order->pos_status] ?? $order->pos_status }}
                                        </span>
                                        <span class="text-[11px] text-slate-500">{{ $cashierName }} · {{ optional($order->created_at)->format('H:i') }}</span>
                                    </div>
                                    <div class="relative" x-data="{ open: false }">
                                        <button type="button" @click="open = !open" class="rounded-lg border border-slate-200 px-2 py-1 text-sm font-bold text-slate-500">⋯</button>
                                        <div x-show="open" @click.outside="open = false" x-cloak class="absolute left-0 z-20 mt-1 w-44 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 text-xs shadow-lg">
                                            <a href="{{ route('workspace.pos.orders.print', $order) }}" target="_blank" class="block px-3 py-2 text-slate-700 hover:bg-slate-50">طباعة الطلب</a>
                                            @if($order->pos_status !== 'cancelled' && $order->payment_status !== 'paid' && ! $order->pos_cashier_invoice_id)
                                                <button type="button" @click="open = false; editingOrderId = {{ $order->id }}" class="block w-full px-3 py-2 text-right text-slate-700 hover:bg-slate-50">تعديل الطلب</button>
                                                <form method="POST" action="{{ route('workspace.pos.orders.status', $order) }}" onsubmit="return confirm('حذف هذا الطلب بالكامل؟');">
                                                    @csrf
                                                    <input type="hidden" name="pos_status" value="cancelled" />
                                                    <button class="block w-full px-3 py-2 text-right font-semibold text-rose-600 hover:bg-rose-50">حذف الطلب</button>
                                                </form>
                                            @endif
                                            <button type="button" @click="open = false; panel = 'note'" class="block w-full px-3 py-2 text-right text-slate-700 hover:bg-slate-50">إضافة ملاحظة</button>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-3 overflow-x-auto">
                                    <table class="min-w-full text-xs">
                                        <thead>
                                            <tr class="text-slate-400">
                                                <th class="pb-2 text-right font-semibold">الصنف</th>
                                                <th class="pb-2 text-center font-semibold">الكمية</th>
                                                <th class="pb-2 text-center font-semibold">السعر</th>
                                                <th class="pb-2 text-left font-semibold">الإجمالي</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100">
                                            @foreach($order->items as $item)
                                                <tr>
                                                    <td class="py-1.5 font-semibold text-slate-800">{{ $item->product_name }}{{ $item->variant_name ? ' - '.$item->variant_name : '' }}</td>
                                                    <td class="py-1.5 text-center text-slate-600">x {{ $item->quantity }}</td>
                                                    <td class="py-1.5 text-center text-slate-600">{{ number_format((float) $item->unit_price, 2) }}</td>
                                                    <td class="py-1.5 text-left font-semibold text-slate-800">{{ number_format((float) $item->total_amount, 2) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                @if($order->pos_status !== 'cancelled' && $order->payment_status !== 'paid' && ! $order->pos_cashier_invoice_id)
                                    <div x-show="editingOrderId === {{ $order->id }}" x-cloak class="mt-3 rounded-xl border border-emerald-100 bg-emerald-50/40 p-3">
                                        <form method="POST" action="{{ route('workspace.pos.orders.update-items', $order) }}" class="space-y-2">
                                            @csrf
                                            <p class="text-xs font-bold text-slate-800">تعديل أصناف الطلب</p>
                                            @foreach($order->items as $index => $item)
                                                <div class="flex flex-wrap items-center gap-2 rounded-lg border border-slate-200 bg-white px-2 py-2">
                                                    <input type="hidden" name="items[{{ $index }}][id]" value="{{ $item->id }}" />
                                                    <input type="hidden" name="items[{{ $index }}][unit_price]" value="{{ number_format((float) $item->unit_price, 2, '.', '') }}" />
                                                    <span class="min-w-0 flex-1 text-xs font-semibold text-slate-800">{{ $item->product_name }}{{ $item->variant_name ? ' - '.$item->variant_name : '' }}</span>
                                                    <input type="number" name="items[{{ $index }}][quantity]" min="1" value="{{ (int) $item->quantity }}" class="w-20 rounded-lg border-slate-200 text-sm" />
                                                    <label class="inline-flex items-center gap-1 text-[11px] font-semibold text-rose-600">
                                                        <input type="checkbox" name="items[{{ $index }}][remove]" value="1" class="rounded border-slate-300 text-rose-600" />
                                                        حذف
                                                    </label>
                                                </div>
                                            @endforeach
                                            <div class="flex flex-wrap gap-2 pt-1">
                                                <button class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white">حفظ التعديل</button>
                                                <button type="button" @click="editingOrderId = null" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700">إلغاء</button>
                                            </div>
                                        </form>
                                    </div>
                                @endif

                                <div class="mt-3 grid grid-cols-2 gap-2 border-t border-slate-100 pt-2 text-[11px] text-slate-500 sm:grid-cols-4">
                                    <div>المجموع الفرعي <span class="font-semibold text-slate-800">{{ number_format((float) $order->subtotal, 2) }}</span></div>
                                    <div>ض.ق.م <span class="font-semibold text-slate-800">{{ number_format((float) $order->tax_amount, 2) }}</span></div>
                                    <div>الخصم <span class="font-semibold text-slate-800">{{ number_format((float) $order->discount_amount, 2) }}</span></div>
                                    <div class="font-bold text-emerald-700">الإجمالي {{ number_format((float) $order->total_amount, 2) }} {{ $order->currency }}</div>
                                </div>
                            </article>
                        @empty
                            <p class="py-10 text-center text-sm text-slate-400">لا توجد طلبات في هذه الجلسة.</p>
                        @endforelse
                    </div>
                </article>
            </section>
        </section>

        {{-- Bottom sticky bar --}}
        <div class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200 bg-white/95 backdrop-blur">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-3">
                <div class="flex flex-wrap items-center gap-4">
                    <div>
                        <p class="text-[11px] text-slate-500">الإجمالي الكلي</p>
                        <p class="text-lg font-extrabold text-emerald-700">{{ number_format($sessionTotal, 2) }}</p>
                    </div>
                    <div class="text-xs text-slate-600">الخصم: <span class="font-semibold">{{ number_format($sessionDiscount, 2) }}</span></div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" @click="panel = 'discount'" :disabled="!hasSession" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50">الخصم</button>
                    <button type="button" @click="panel = 'note'" :disabled="!hasSession" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50">ملاحظة</button>
                    <button type="button" onclick="window.print()" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">طباعة</button>
                    <a href="#bill-anchor" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">الحساب</a>
                    @if($currentSession)
                        <button type="button" @click="panel = 'closeConfirm'" class="rounded-xl bg-rose-600 px-4 py-2 text-xs font-bold text-white hover:bg-rose-700">إغلاق الطاولة</button>
                    @endif
                </div>
            </div>
        </div>
        <div id="bill-anchor" class="sr-only">الحساب</div>

        {{-- Panels / dialogs --}}
        <div x-show="panel" x-cloak class="fixed inset-0 z-40 flex items-end justify-center bg-slate-900/40 p-4 sm:items-center" @keydown.escape.window="panel = null">
            <div class="max-h-[85vh] w-full max-w-lg overflow-y-auto rounded-2xl border border-slate-200 bg-white p-4 shadow-xl" @click.outside="panel = null">
                {{-- Add order (single entry point) --}}
                <div x-show="panel === 'addOrder'">
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="text-sm font-bold text-slate-900">إضافة طلب</h3>
                        <button type="button" @click="panel = null" class="text-slate-400">✕</button>
                    </div>
                    @if($currentSession)
                        <form method="POST" action="{{ route('workspace.pos.tables.orders.store', $table) }}" class="space-y-3">
                            @csrf
                            <input type="hidden" name="items[0][pos_menu_item_id]" :value="selectedItemId" />
                            <div class="flex gap-2 overflow-x-auto pb-1">
                                @foreach($menuGroups as $categoryName => $groupItems)
                                    <button type="button" @click="selectedCategory = @js($categoryName)" :class="selectedCategory === @js($categoryName) ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-slate-700 border-slate-200'" class="shrink-0 rounded-lg border px-3 py-1.5 text-xs font-semibold">{{ $categoryName }}</button>
                                @endforeach
                            </div>
                            <div class="max-h-56 space-y-1.5 overflow-y-auto rounded-xl border border-slate-100 p-2">
                                @foreach($menuGroups as $categoryName => $groupItems)
                                    <div x-show="selectedCategory === @js($categoryName)" class="space-y-1.5">
                                        @foreach($groupItems as $item)
                                            <button type="button" @click="selectedItemId = String({{ $item->id }})" :class="selectedItemId === String({{ $item->id }}) ? 'border-emerald-500 bg-emerald-50' : 'border-slate-200'" class="flex w-full items-center justify-between rounded-xl border px-3 py-2 text-right hover:border-emerald-500">
                                                <span class="text-sm font-semibold text-slate-800">{{ $item->name }}{{ $item->size_label ? ' - '.$item->size_label : '' }}</span>
                                                <span class="text-xs font-bold text-emerald-700">{{ number_format((float) $item->price, 2) }}</span>
                                            </button>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-600">الكمية</label>
                                <input type="number" name="items[0][quantity]" min="1" value="1" class="w-full rounded-lg border-slate-200 text-sm" />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-600">ملاحظة</label>
                                <textarea name="notes" rows="2" class="w-full rounded-lg border-slate-200 text-sm"></textarea>
                            </div>
                            <button class="w-full rounded-xl bg-emerald-600 px-3 py-2.5 text-sm font-bold text-white disabled:opacity-50" :disabled="!selectedItemId">إنشاء الطلب</button>
                        </form>
                    @endif
                </div>

                {{-- Transfer --}}
                <div x-show="panel === 'transfer'">
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="text-sm font-bold text-slate-900">نقل الطاولة</h3>
                        <button type="button" @click="panel = null" class="text-slate-400">✕</button>
                    </div>
                    <p class="mb-3 text-xs text-slate-500">نقل جميع الطلبات والحساب للطاولة الجديدة.</p>
                    @if($currentSession)
                        <form method="POST" action="{{ route('workspace.pos.tables.sessions.transfer', ['table' => $table, 'session' => $currentSession]) }}" class="space-y-3">
                            @csrf
                            <select name="target_table_id" required class="w-full rounded-lg border-slate-200 text-sm">
                                <option value="">اختر الطاولة الجديدة</option>
                                @foreach($otherTables as $t)
                                    <option value="{{ $t->id }}">{{ $t->name }} — {{ $t->status === 'occupied' ? 'مشغولة' : 'فارغة' }}</option>
                                @endforeach
                            </select>
                            <button class="w-full rounded-xl bg-emerald-600 px-3 py-2.5 text-sm font-bold text-white">تأكيد النقل</button>
                        </form>
                    @endif
                </div>

                {{-- Merge --}}
                <div x-show="panel === 'merge'">
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="text-sm font-bold text-slate-900">دمج طاولة</h3>
                        <button type="button" @click="panel = null" class="text-slate-400">✕</button>
                    </div>
                    <p class="mb-3 text-xs text-slate-500">دمج طلبات هذه الطاولة مع طاولة أخرى دون فقدان البيانات.</p>
                    @if($currentSession)
                        <form method="POST" action="{{ route('workspace.pos.tables.sessions.merge', ['table' => $table, 'session' => $currentSession]) }}" class="space-y-3">
                            @csrf
                            <select name="target_table_id" required class="w-full rounded-lg border-slate-200 text-sm">
                                <option value="">اختر الطاولة</option>
                                @foreach($otherTables as $t)
                                    <option value="{{ $t->id }}">{{ $t->name }} — {{ $t->status === 'occupied' ? 'مشغولة' : 'فارغة' }}</option>
                                @endforeach
                            </select>
                            <button class="w-full rounded-xl bg-emerald-600 px-3 py-2.5 text-sm font-bold text-white">تأكيد الدمج</button>
                        </form>
                    @endif
                </div>

                {{-- Split --}}
                <div x-show="panel === 'split'">
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="text-sm font-bold text-slate-900">تقسيم الحساب</h3>
                        <button type="button" @click="panel = null" class="text-slate-400">✕</button>
                    </div>
                    <p class="mb-2 text-xs text-slate-500">وزّع كل الأصناف على حسابين أو أكثر. يجب أن يساوي مجموع الأجزاء إجمالي الطاولة.</p>
                    <p class="mb-3 text-xs font-semibold text-slate-700">إجمالي الأجزاء: <span x-text="splitAllocatedTotal().toFixed(2)"></span> / {{ number_format($sessionTotal, 2) }}</p>
                    @if($currentSession)
                        <form method="POST" action="{{ route('workspace.pos.tables.sessions.split', ['table' => $table, 'session' => $currentSession]) }}" class="space-y-4" @submit="if (!splitIsValid()) { $event.preventDefault(); alert('يجب توزيع كل الأصناف بالكامل على الحسابات.'); }">
                            @csrf
                            <template x-for="(group, gIndex) in splitGroups" :key="gIndex">
                                <div class="rounded-xl border border-slate-200 p-3">
                                    <p class="mb-2 text-xs font-bold text-slate-800">حساب <span x-text="gIndex + 1"></span></p>
                                    <template x-for="(item, iIndex) in splitItems" :key="item.id">
                                        <div class="mb-2 flex items-center justify-between gap-2 text-xs">
                                            <span class="font-semibold text-slate-700" x-text="item.name + ' (متاح ' + item.quantity + ')'"></span>
                                            <input type="hidden" :name="`groups[${gIndex}][items][${iIndex}][order_item_id]`" :value="item.id" />
                                            <input type="number" min="0" :max="item.quantity" :name="`groups[${gIndex}][items][${iIndex}][quantity]`" x-model.number="group.qty[item.id]" class="w-20 rounded border-slate-200 text-sm" />
                                        </div>
                                    </template>
                                </div>
                            </template>
                            <button type="button" @click="addSplitGroup()" class="text-xs font-semibold text-emerald-700 underline">+ إضافة حساب</button>
                            <button class="w-full rounded-xl bg-emerald-600 px-3 py-2.5 text-sm font-bold text-white">تأكيد التقسيم</button>
                        </form>
                    @endif
                </div>

                {{-- Note --}}
                <div x-show="panel === 'note'">
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="text-sm font-bold text-slate-900">إضافة ملاحظة</h3>
                        <button type="button" @click="panel = null" class="text-slate-400">✕</button>
                    </div>
                    @if($currentSession)
                        <form method="POST" action="{{ route('workspace.pos.tables.sessions.note', ['table' => $table, 'session' => $currentSession]) }}" class="space-y-3">
                            @csrf
                            <textarea name="notes" rows="4" required class="w-full rounded-lg border-slate-200 text-sm">{{ $sessionNote }}</textarea>
                            <button class="w-full rounded-xl bg-emerald-600 px-3 py-2.5 text-sm font-bold text-white">حفظ الملاحظة</button>
                        </form>
                    @endif
                </div>

                {{-- Discount --}}
                <div x-show="panel === 'discount'">
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="text-sm font-bold text-slate-900">خصم الطاولة</h3>
                        <button type="button" @click="panel = null" class="text-slate-400">✕</button>
                    </div>
                    @if($currentSession)
                        <form method="POST" action="{{ route('workspace.pos.tables.sessions.discount', ['table' => $table, 'session' => $currentSession]) }}" class="space-y-3">
                            @csrf
                            <input type="number" name="discount_amount" min="0" step="0.01" value="{{ number_format($sessionDiscount, 2, '.', '') }}" class="w-full rounded-lg border-slate-200 text-sm" />
                            <button class="w-full rounded-xl bg-emerald-600 px-3 py-2.5 text-sm font-bold text-white">تطبيق الخصم</button>
                        </form>
                    @endif
                </div>

                {{-- Close confirm --}}
                <div x-show="panel === 'closeConfirm'">
                    <h3 class="text-base font-bold text-slate-900">هل أنت متأكد من إغلاق الطاولة؟</h3>
                    <p class="mt-1 text-sm text-slate-500">سيتم إصدار فاتورة كاشير نهائية إن وُجدت طلبات.</p>
                    <div class="mt-5 grid grid-cols-2 gap-2">
                        <button type="button" @click="panel = null" class="rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold">إلغاء</button>
                        @if($currentSession)
                            <form method="POST" action="{{ route('workspace.pos.tables.sessions.close', ['table' => $table, 'session' => $currentSession]) }}">
                                @csrf
                                <button class="w-full rounded-xl bg-rose-600 px-3 py-2 text-sm font-bold text-white">إغلاق الطاولة</button>
                            </form>
                        @endif
                    </div>
                </div>

                {{-- Cancel confirm --}}
                <div x-show="panel === 'cancelConfirm'">
                    <h3 class="text-base font-bold text-rose-700">إلغاء الطاولة؟</h3>
                    <p class="mt-1 text-sm text-slate-500">سيتم إلغاء الجلسة والطلبات غير المفوترة. لا يمكن التراجع.</p>
                    <div class="mt-5 grid grid-cols-2 gap-2">
                        <button type="button" @click="panel = null" class="rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold">تراجع</button>
                        @if($currentSession)
                            <form method="POST" action="{{ route('workspace.pos.tables.sessions.cancel', ['table' => $table, 'session' => $currentSession]) }}">
                                @csrf
                                <button class="w-full rounded-xl bg-rose-600 px-3 py-2 text-sm font-bold text-white">تأكيد الإلغاء</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function tableShowBoard({ defaultCategory, splitItems, otherTables, sessionNote, hasSession }) {
            const emptyQty = () => Object.fromEntries(splitItems.map((item) => [item.id, 0]));
            return {
                panel: null,
                filter: 'all',
                search: '',
                selectedCategory: defaultCategory || '',
                selectedItemId: '',
                editingOrderId: null,
                hasSession,
                sessionNote,
                otherTables,
                splitItems,
                splitGroups: [
                    { qty: emptyQty() },
                    { qty: emptyQty() },
                ],
                matchesOrder(status, haystack) {
                    if (this.filter === 'open' && status !== 'open') return false;
                    if (this.filter === 'paid' && status !== 'paid') return false;
                    if (this.filter === 'cancelled' && status !== 'cancelled') return false;
                    const term = this.search.trim().toLowerCase();
                    if (!term) return true;
                    return String(haystack || '').includes(term);
                },
                addSplitGroup() {
                    this.splitGroups.push({ qty: emptyQty() });
                },
                splitAllocatedTotal() {
                    let total = 0;
                    this.splitGroups.forEach((group) => {
                        this.splitItems.forEach((item) => {
                            const qty = Number(group.qty[item.id] || 0);
                            total += qty * Number(item.unit_price || 0);
                        });
                    });
                    return total;
                },
                splitIsValid() {
                    return this.splitItems.every((item) => {
                        const used = this.splitGroups.reduce((sum, group) => sum + Number(group.qty[item.id] || 0), 0);
                        return used === Number(item.quantity);
                    });
                },
            };
        }

        const formatDuration = (openedAt) => {
            if (!openedAt) return '00:00:00';
            const start = new Date(openedAt).getTime();
            if (Number.isNaN(start)) return '00:00:00';
            const diff = Math.max(0, Math.floor((Date.now() - start) / 1000));
            const hours = String(Math.floor(diff / 3600)).padStart(2, '0');
            const minutes = String(Math.floor((diff % 3600) / 60)).padStart(2, '0');
            const seconds = String(diff % 60).padStart(2, '0');
            return `${hours}:${minutes}:${seconds}`;
        };

        const timerNodes = document.querySelectorAll('[data-opened-at]');
        if (timerNodes.length > 0) {
            const tick = () => timerNodes.forEach((node) => { node.textContent = formatDuration(node.dataset.openedAt); });
            tick();
            setInterval(tick, 1000);
        }
    </script>
@endsection
