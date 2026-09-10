<?php

namespace App\Services\Payment\Contracts;

/**
 * Shared checkout boundary for non-Order billables (Finance invoices, etc.).
 *
 * PaymentService::createPaymentLink() remains Order-bound: it writes
 * payments.order_id and webhooks settle Order by order_number.
 * Finance must not invent a second gateway or a dummy POS order.
 */
interface BillableCheckoutPort
{
    public function createCheckout(BillableCheckoutRequest $request): BillableCheckoutResult;
}
