<?php

namespace App\Services\Appointments;

use App\Models\Appointment\AppointmentBooking;
use App\Models\Appointment\AppointmentSetting;
use App\Models\Finance\FinanceInvoice;
use App\Models\Finance\FinanceTreasuryAccount;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Finance\InvoicePaymentService;
use App\Services\Finance\InvoiceService;
use App\Services\Merchant\MerchantPaymentEligibilityService;
use App\Services\Notification\DomainNotificationService;
use App\Services\Payment\PaymentService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AppointmentBillingService
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly InvoicePaymentService $invoicePaymentService,
        private readonly PaymentService $paymentService,
        private readonly DomainNotificationService $domainNotificationService,
        private readonly MerchantPaymentEligibilityService $merchantPaymentEligibilityService,
    ) {}

    public function createInvoiceAndPaymentLink(AppointmentBooking $booking, ?int $actorUserId): AppointmentBooking
    {
        $booking->loadMissing(['service', 'workspace']);
        $service = $booking->service;
        if (! $service) {
            throw new RuntimeException('الخدمة غير متاحة لإنشاء الفاتورة.');
        }

        $basePrice = (float) $service->price;
        if ((bool) $service->requires_payment === false || $basePrice <= 0) {
            $booking->update(['payment_status' => 'paid']);

            return $booking->refresh();
        }

        if ($booking->payment_link) {
            return $booking->refresh(['invoice', 'latestPayment']);
        }

        $payableAmount = $this->resolvePayableAmount($service->payment_mode ?? 'postpaid', $basePrice, (float) ($service->deposit_amount ?? 0));
        $actorUserId = $actorUserId ?: (int) ($booking->booked_by ?: $booking->workspace?->owner_user_id ?: 0);
        if ($actorUserId <= 0) {
            throw new RuntimeException('تعذر تحديد المستخدم المسؤول لإنشاء فاتورة الموعد.');
        }

        return DB::transaction(function () use ($booking, $actorUserId, $payableAmount, $basePrice): AppointmentBooking {
            $booking = AppointmentBooking::withoutGlobalScopes()
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->first();

            if (! $booking) {
                throw new RuntimeException('تعذر العثور على الموعد لإنشاء رابط الدفع.');
            }

            if ($booking->payment_link) {
                return $booking->fresh(['invoice', 'latestPayment']);
            }

            $booking->loadMissing(['service', 'workspace']);

            $invoice = $booking->finance_invoice_id
                ? FinanceInvoice::withoutGlobalScopes()->whereKey($booking->finance_invoice_id)->lockForUpdate()->first()
                : null;

            if (! $invoice) {
                $invoice = $this->invoiceService->create($booking->workspace, [
                    'type' => 'sales',
                    'customer_id' => $booking->customer_id,
                    'customer_name' => $booking->customer_name,
                    'issue_date' => now()->toDateString(),
                    'due_date' => $booking->starts_at?->toDateString(),
                    'currency' => 'SAR',
                    'invoice_status' => 'issued',
                    'notes' => 'فاتورة مرتبطة بموعد رقم '.$booking->booking_number,
                    'items' => [[
                        'product_name' => (string) ($booking->service?->name ?? 'خدمة موعد'),
                        'description' => 'رسوم الموعد #'.$booking->booking_number,
                        'quantity' => 1,
                        'unit_price' => $payableAmount,
                        'discount' => 0,
                        'tax_rate' => 0,
                        'tax_type' => 'out_of_scope',
                    ]],
                ], $actorUserId);
            }

            $order = $booking->order_id
                ? Order::withoutGlobalScopes()->whereKey($booking->order_id)->lockForUpdate()->first()
                : null;

            if (! $order) {
                $order = Order::withoutGlobalScopes()->create([
                    'workspace_id' => $booking->workspace_id,
                    'customer_id' => $booking->customer_id,
                    'order_number' => $this->nextOrderNumber(),
                    'status' => 'confirmed',
                    'payment_status' => 'pending',
                    'fulfillment_status' => 'unfulfilled',
                    'shipping_status' => 'not_shipped',
                    'currency' => $invoice->currency ?: 'SAR',
                    'subtotal' => $payableAmount,
                    'discount_amount' => 0,
                    'shipping_amount' => 0,
                    'total_amount' => $payableAmount,
                    'notes' => sprintf(
                        'Order generated for appointment %s / invoice %s (base %.2f)',
                        $booking->booking_number,
                        $invoice->invoice_number,
                        $basePrice
                    ),
                    'placed_at' => now(),
                ]);
            }

            $eligibility = $this->merchantPaymentEligibilityService->statusSnapshot($booking->workspace);
            $payment = null;
            $paymentLink = null;
            $metadata = is_array($booking->metadata) ? $booking->metadata : [];
            $localCheckout = strtolower((string) config('payment.default_provider', 'local')) === 'local';

            if ($eligibility['eligible']) {
                $payment = $this->paymentService->createPaymentLink($order, null, 'merchant_booking');
                $paymentLink = $payment->payment_link;
                unset($metadata['payment_blocked_reason'], $metadata['local_checkout']);
            } elseif ($localCheckout) {
                $payment = $this->paymentService->createPaymentLink($order, null, 'local_sandbox');
                $paymentLink = $payment->payment_link;
                unset($metadata['payment_blocked_reason']);
                $metadata['local_checkout'] = true;
            } else {
                $metadata['payment_blocked_reason'] = implode(' ', $eligibility['blockers']);
            }

            $booking->update([
                'finance_invoice_id' => $invoice->id,
                'order_id' => $order->id,
                'latest_payment_id' => $payment?->id,
                'payment_link' => $paymentLink,
                'payment_status' => 'pending',
                'metadata' => $metadata,
            ]);

            if ($payment) {
                $this->domainNotificationService->notifyAppointmentBookingStatusChanged(
                    $booking,
                    'تم إنشاء رابط دفع للموعد',
                    'تم إصدار فاتورة ورابط دفع مرتبط بموعد العميل.'
                );
            } else {
                $this->domainNotificationService->notifyAppointmentBookingStatusChanged(
                    $booking,
                    'تم إنشاء فاتورة بدون رابط دفع',
                    'تم إصدار الفاتورة، لكن استقبال المدفوعات غير مفعّل حالياً للتاجر.'
                );
            }

            return $booking->fresh(['invoice', 'order', 'latestPayment']);
        });
    }

    public function syncAfterPaymentConfirmed(Payment $payment): void
    {
        $booking = AppointmentBooking::withoutGlobalScopes()
            ->where('order_id', $payment->order_id)
            ->first();

        if (! $booking) {
            return;
        }

        $booking->loadMissing(['workspace', 'service']);

        DB::transaction(function () use ($booking, $payment): void {
            $booking = AppointmentBooking::withoutGlobalScopes()
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->first();

            if (! $booking) {
                return;
            }

            $invoice = $booking->finance_invoice_id
                ? FinanceInvoice::withoutGlobalScopes()->whereKey($booking->finance_invoice_id)->lockForUpdate()->first()
                : null;

            if ($invoice && (float) $invoice->amount_due > 0) {
                $treasuryAccountId = FinanceTreasuryAccount::withoutGlobalScopes()
                    ->where('workspace_id', $booking->workspace_id)
                    ->where('is_active', true)
                    ->orderByRaw("case when type = 'bank' then 0 else 1 end")
                    ->orderBy('id')
                    ->value('id');

                $actorUserId = (int) ($booking->booked_by ?: $booking->workspace?->owner_user_id ?: 0);
                if ($actorUserId <= 0) {
                    $actorUserId = (int) ($booking->workspace?->owner_user_id ?? 0);
                }

                if ($actorUserId > 0) {
                    $this->invoicePaymentService->recordPayment($invoice, [
                        'payment_date' => now()->toDateString(),
                        'amount' => min((float) $payment->amount, (float) $invoice->amount_due),
                        'method' => 'other',
                        'reference' => $payment->provider_payment_id ?: $payment->id,
                        'notes' => 'Auto payment from appointment checkout',
                        'treasury_account_id' => $treasuryAccountId,
                    ], $actorUserId);

                    $invoice = $invoice->refresh();
                }
            }

            $newPaymentStatus = 'paid';
            if ($invoice) {
                if ((float) $invoice->amount_due <= 0.009) {
                    $newPaymentStatus = 'paid';
                } elseif ((float) $invoice->amount_paid > 0) {
                    $newPaymentStatus = 'partially_paid';
                } else {
                    $newPaymentStatus = 'pending';
                }
            }

            $payload = [
                'payment_status' => $newPaymentStatus,
                'latest_payment_id' => $payment->id,
            ];

            $setting = AppointmentSetting::withoutGlobalScopes()
                ->where('workspace_id', $booking->workspace_id)
                ->first();
            if (
                $newPaymentStatus === 'paid'
                && ($setting?->auto_confirm_after_payment ?? true)
                && $booking->appointment_status === 'scheduled'
            ) {
                $payload['appointment_status'] = 'confirmed';
                $payload['status'] = 'confirmed';
                $payload['confirmed_at'] = now();
            }

            $booking->update($payload);

            $this->domainNotificationService->notifyAppointmentBookingStatusChanged(
                $booking->refresh(),
                'تم تأكيد دفع موعد',
                'تم تحديث حالة الدفع للموعد بعد وصول إشعار الدفع.'
            );
        });
    }

    private function resolvePayableAmount(string $paymentMode, float $basePrice, float $depositAmount): float
    {
        if ($paymentMode === 'deposit' && $depositAmount > 0) {
            return round(min($basePrice, $depositAmount), 2);
        }

        return round(max(0, $basePrice), 2);
    }

    private function nextOrderNumber(): string
    {
        $lastId = (Order::withoutGlobalScopes()->max('id') ?? 0) + 1;

        return 'APT-ORD-'.str_pad((string) $lastId, 8, '0', STR_PAD_LEFT);
    }
}
