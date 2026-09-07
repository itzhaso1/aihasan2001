@extends('layouts.pos', ['pageTitle' => 'الطلبات الجارية'])

@section('content')
    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        @if(session('payment_link'))
            <div class="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                رابط الدفع: <a href="{{ session('payment_link') }}" target="_blank" class="font-bold underline">{{ session('payment_link') }}</a>
            </div>
        @endif
        <h2 class="mb-3 text-base font-bold text-slate-900">طلبات POS / QR الجارية</h2>

        <div class="space-y-3">
            @forelse($orders as $order)
                <article class="rounded-xl border border-slate-200 p-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">#{{ $order->order_number }}</p>
                            <p class="text-xs text-slate-500">
                                المصدر: {{ strtoupper($order->source) }}
                                @if($order->table)
                                    • {{ $order->table->name }}
                                @endif
                                • {{ $order->payment_status === 'paid' ? 'مدفوع' : 'غير مدفوع' }}
                            </p>
                        </div>
                        <p class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">{{ $posStatuses[$order->pos_status] ?? $order->pos_status }}</p>
                    </div>

                    <ul class="mt-2 space-y-1 text-xs text-slate-600">
                        @foreach($order->items as $item)
                            <li>{{ $item->product_name }}{{ $item->variant_name ? ' - '.$item->variant_name : '' }} × {{ $item->quantity }} = {{ number_format((float) $item->total_amount, 2) }}</li>
                        @endforeach
                    </ul>

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <form method="POST" action="{{ route('workspace.pos.orders.status', $order) }}" class="flex items-center gap-2">
                            @csrf
                            <select name="pos_status" class="rounded-lg border-slate-300 text-xs">
                                @foreach($posStatuses as $key => $label)
                                    <option value="{{ $key }}" @selected($order->pos_status === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <button class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white">تحديث الحالة</button>
                        </form>

                        @if($order->payment_status !== 'paid')
                            <form method="POST" action="{{ route('workspace.pos.orders.payment-link', $order) }}">
                                @csrf
                                <button class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100">رابط دفع</button>
                            </form>
                        @endif

                        @if(!$order->table_session_id && !$order->pos_cashier_invoice_id && $order->pos_status !== 'cancelled')
                            <form method="POST" action="{{ route('workspace.pos.orders.invoice', $order) }}">
                                @csrf
                                <button class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100">إصدار فاتورة كاشير</button>
                            </form>
                        @endif

                        <a href="{{ route('workspace.pos.orders.print', $order) }}" target="_blank" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100">طباعة</a>
                        @if($order->pos_status !== 'cancelled')
                            <a href="{{ route('workspace.pos.orders.returns.create', $order) }}" class="rounded-lg border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50">مرتجع / استرجاع</a>
                        @endif
                        <p class="mr-auto text-sm font-bold text-slate-900">Total: {{ number_format((float) $order->total_amount, 2) }} {{ $order->currency }}</p>
                    </div>
                </article>
            @empty
                <p class="text-sm text-slate-500">لا توجد طلبات جارية حاليًا.</p>
            @endforelse
        </div>

        <div class="mt-4">{{ $orders->links() }}</div>
    </section>
@endsection
