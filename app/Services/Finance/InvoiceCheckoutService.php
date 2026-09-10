<?php

namespace App\Services\Finance;

use App\Models\Finance\FinanceInvoice;
use App\Services\Payment\Contracts\BillableCheckoutPort;
use App\Services\Payment\Contracts\BillableCheckoutRequest;
use App\Services\Payment\Contracts\BillableCheckoutResult;

/**
 * Finance-side checkout request. Does not own a gateway.
 * Confirmation must never be inferred from generating a URL.
 */
class InvoiceCheckoutService
{
    public function __construct(
        private readonly BillableCheckoutPort $billableCheckoutPort,
    ) {}

    public function availability(FinanceInvoice $invoice): BillableCheckoutResult
    {
        if ((string) $invoice->type !== 'sales') {
            return BillableCheckoutResult::unsupported(
                'not_sales_invoice',
                'رابط التحصيل متاح لفواتير المبيعات الصادرة فقط.'
            );
        }

        if ($invoice->trashed() || $invoice->isCancelled() || ! $invoice->isIssued()) {
            return BillableCheckoutResult::unsupported(
                'invoice_not_collectible',
                'لا يمكن إنشاء رابط دفع إلا لفاتورة مبيعات صادرة وغير ملغاة.'
            );
        }

        if ((float) $invoice->amount_due <= InvoiceStateService::PAYMENT_TOLERANCE) {
            return BillableCheckoutResult::unsupported(
                'invoice_paid',
                'لا يوجد مبلغ مستحق على هذه الفاتورة.'
            );
        }

        return $this->billableCheckoutPort->createCheckout(new BillableCheckoutRequest(
            billableType: 'finance_invoice',
            billableId: (int) $invoice->id,
            workspaceId: (int) $invoice->workspace_id,
            reference: (string) $invoice->invoice_number,
            amount: round((float) $invoice->amount_due, 2),
            currency: strtoupper((string) ($invoice->currency ?: 'SAR')),
            metadata: [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'customer_id' => $invoice->customer_id,
            ],
        ));
    }
}
