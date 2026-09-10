<?php

namespace App\Services\Payment;

use App\Services\Payment\Contracts\BillableCheckoutPort;
use App\Services\Payment\Contracts\BillableCheckoutRequest;
use App\Services\Payment\Contracts\BillableCheckoutResult;

/**
 * Default shared adapter while PaymentService can only checkout Orders.
 *
 * Do not generate a payment link, mark invoices paid, or create POS orders.
 * Gateway confirmation must continue to flow through PaymentService webhooks.
 */
class OrderBoundBillableCheckout implements BillableCheckoutPort
{
    public function createCheckout(BillableCheckoutRequest $request): BillableCheckoutResult
    {
        return BillableCheckoutResult::unsupported(
            'payment_service_order_bound',
            'رابط الدفع المشترك مرتبط حالياً بسجلات الطلبات (Order) وليس بفواتير المالية. '
            .'تأكيد الدفع يتم عبر بوابة الدفع المشتركة ولا يُعلَن من إنشاء الرابط. '
            .'سجّل التحصيل من دفعة الفاتورة المالية حتى يوفّر المحرك المشترك تسوية لفواتير Finance.'
        );
    }
}
