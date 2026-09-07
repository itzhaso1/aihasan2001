@extends('layouts.financial', ['pageTitle' => 'عملاء النظام المالي'])

@section('content')
    <div class="space-y-4">
        <h2 class="text-xl font-bold text-slate-900">العملاء (إعادة استخدام Customer الحالي)</h2>
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-3 py-3 text-right">الاسم</th>
                        <th class="px-3 py-3 text-right">الهاتف</th>
                        <th class="px-3 py-3 text-right">طلبات</th>
                        <th class="px-3 py-3 text-right">إجمالي الطلبات</th>
                        <th class="px-3 py-3 text-right">فواتير مالية</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($customers as $customer)
                        <tr>
                            <td class="px-3 py-3 font-semibold">{{ $customer->name }}</td>
                            <td class="px-3 py-3">{{ $customer->phone }}</td>
                            <td class="px-3 py-3">{{ $customer->orders_count }}</td>
                            <td class="px-3 py-3">{{ number_format((float) ($customer->orders_total_amount ?? 0), 2) }}</td>
                            <td class="px-3 py-3">{{ $invoiceCountByCustomer[$customer->id] ?? 0 }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-3 py-8 text-center text-slate-500">لا يوجد عملاء.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div>{{ $customers->links() }}</div>
    </div>
@endsection
