@extends('layouts.financial', ['pageTitle' => 'عرض سعر '.$quote->quote_number])

@php
    $status = $quote->status;
    $isDraft = $quote->isDraft();
    $isCancelled = $quote->isCancelled();
    $statusClass = match ($status) {
        'cancelled' => 'bg-slate-100 text-slate-600',
        'draft' => 'bg-slate-50 text-slate-500',
        'issued' => 'bg-indigo-50 text-indigo-700',
        default => 'bg-amber-50 text-amber-700',
    };
    $statusLabels = ['draft' => 'مسودة', 'issued' => 'صادر', 'cancelled' => 'ملغى'];
    $taxProfileLabels = [
        'standard' => 'قياسية',
        'zero_rated' => 'صفرية',
        'exempt' => 'معفاة',
        'out_of_scope' => 'خارج النطاق',
    ];
    $snapshotsAuthoritative = $quote->snapshotsAreAuthoritative();
    $companyName = data_get($quote->company_snapshot, 'company_name_ar') ?: data_get($quote->company_snapshot, 'company_name');
    $companyVat = data_get($quote->company_snapshot, 'vat_number');
    $companyCr = data_get($quote->company_snapshot, 'commercial_registration');
    $recipientName = $snapshotsAuthoritative
        ? (data_get($quote->recipient_snapshot, 'name') ?: $quote->customer?->name)
        : $quote->customer?->name;
    $recipientVat = $snapshotsAuthoritative
        ? data_get($quote->recipient_snapshot, 'vat_number')
        : $quote->customer?->vat_number;
    $recipientCr = $snapshotsAuthoritative
        ? data_get($quote->recipient_snapshot, 'commercial_registration')
        : $quote->customer?->commercial_registration;
@endphp

@section('content')
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-bold text-slate-900">عرض سعر {{ $quote->quote_number }}</h2>
                <p class="mt-1 text-xs text-slate-500">{{ $recipientName }}</p>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <span class="rounded-full px-3 py-1 text-xs font-bold {{ $statusClass }}">{{ $statusLabels[$status] ?? $status }}</span>
                    <span class="rounded-full bg-slate-50 px-3 py-1 text-xs font-bold text-slate-600">{{ $quote->currency }}</span>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('workspace.finance.quotes.index') }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">رجوع</a>
                <a href="{{ route('workspace.finance.quotes.pdf', $quote) }}" class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">PDF</a>
                @if($isDraft)
                    <a href="{{ route('workspace.finance.quotes.edit', $quote) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">تعديل</a>
                    <form method="POST" action="{{ route('workspace.finance.quotes.issue', $quote) }}" onsubmit="return confirm('إصدار عرض السعر؟ سيتم قفل البنود والمبالغ.')">
                        @csrf
                        <button class="rounded-lg bg-[#06C2A4] px-3 py-2 text-sm font-semibold text-white hover:bg-[#05ab91]">إصدار</button>
                    </form>
                    <form method="POST" action="{{ route('workspace.finance.quotes.destroy', $quote) }}" onsubmit="return confirm('حذف مسودة عرض السعر؟')">
                        @csrf
                        @method('DELETE')
                        <button class="rounded-lg border border-rose-300 px-3 py-2 text-sm font-semibold text-rose-600 hover:bg-rose-50">حذف المسودة</button>
                    </form>
                @endif
                @if($quote->isIssued())
                    <form method="POST" action="{{ route('workspace.finance.quotes.cancel', $quote) }}" onsubmit="return confirm('إلغاء عرض السعر الصادر؟')">
                        @csrf
                        <button class="rounded-lg border border-rose-300 px-3 py-2 text-sm font-semibold text-rose-600 hover:bg-rose-50">إلغاء</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-bold text-slate-900">الشركة</h3>
                <p class="mt-2 text-sm">{{ $companyName ?: '—' }}</p>
                <p class="text-xs text-slate-500">الرقم الضريبي: {{ $companyVat ?: '—' }}</p>
                <p class="text-xs text-slate-500">السجل التجاري: {{ $companyCr ?: '—' }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-bold text-slate-900">العميل</h3>
                <p class="mt-2 text-sm">{{ $recipientName ?: '—' }}</p>
                <p class="text-xs text-slate-500">الرقم الضريبي: {{ $recipientVat ?: '—' }}</p>
                <p class="text-xs text-slate-500">السجل التجاري: {{ $recipientCr ?: '—' }}</p>
                <p class="mt-2 text-xs text-slate-500">تاريخ الإصدار: {{ $quote->issue_date?->format('Y-m-d') }} · الصلاحية: {{ $quote->expiry_date?->format('Y-m-d') ?: '—' }}</p>
            </div>
        </div>

        <p class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">عرض السعر ليس فاتورة ضريبية ولا ينشئ قيدًا محاسبيًا ولا دفعة ولا يدخل سلسلة ZATCA.</p>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-3 text-sm font-bold">البنود</div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-4 py-2 text-right">الوصف</th>
                            <th class="px-4 py-2 text-right">الوحدة</th>
                            <th class="px-4 py-2 text-right">الكمية</th>
                            <th class="px-4 py-2 text-right">السعر</th>
                            <th class="px-4 py-2 text-right">الخصم</th>
                            <th class="px-4 py-2 text-right">تصنيف الضريبة</th>
                            <th class="px-4 py-2 text-right">النسبة</th>
                            <th class="px-4 py-2 text-right">الضريبة</th>
                            <th class="px-4 py-2 text-right">الإجمالي</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($quote->items as $item)
                            <tr class="border-t border-slate-100">
                                <td class="px-4 py-2">{{ $item->lineTitle() }}</td>
                                <td class="px-4 py-2">{{ $item->displayUnit() !== '' ? $item->displayUnit() : '-' }}</td>
                                <td class="px-4 py-2">{{ number_format((float) $item->quantity, 2) }}</td>
                                <td class="px-4 py-2">{{ number_format((float) $item->unit_price, 2) }}</td>
                                <td class="px-4 py-2">{{ number_format((float) $item->discount, 2) }}</td>
                                <td class="px-4 py-2">{{ $taxProfileLabels[$item->tax_profile_type] ?? $item->tax_profile_type }}</td>
                                <td class="px-4 py-2">{{ number_format((float) $item->tax_rate, 2) }}%</td>
                                <td class="px-4 py-2">{{ number_format((float) $item->tax_amount, 2) }}</td>
                                <td class="px-4 py-2 font-semibold">{{ number_format((float) $item->total, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="px-4 py-8 text-center text-slate-500">لا توجد بنود.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm text-sm">
            <div class="flex justify-between"><span>الإجمالي قبل الضريبة</span><span>{{ number_format((float) $quote->subtotal, 2) }} {{ $quote->currency }}</span></div>
            <div class="flex justify-between"><span>الخصم</span><span>{{ number_format((float) $quote->discount, 2) }} {{ $quote->currency }}</span></div>
            <div class="flex justify-between"><span>ضريبة القيمة المضافة</span><span>{{ number_format((float) $quote->tax_amount, 2) }} {{ $quote->currency }}</span></div>
            <div class="mt-2 flex justify-between border-t pt-2 font-bold"><span>الإجمالي</span><span>{{ number_format((float) $quote->total, 2) }} {{ $quote->currency }}</span></div>
        </div>

        @if($quote->terms)
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-bold">الشروط</h3>
                <p class="mt-2 whitespace-pre-line text-sm text-slate-600">{{ $quote->terms }}</p>
            </div>
        @endif
        @if($quote->notes)
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-bold">ملاحظات</h3>
                <p class="mt-2 whitespace-pre-line text-sm text-slate-600">{{ $quote->notes }}</p>
            </div>
        @endif
    </div>
@endsection
