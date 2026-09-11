<?php

namespace App\Services\Payment;

use App\Events\PaymentConfirmed;
use App\Models\Finance\FinanceInvoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\WebhookEvent;
use App\Models\Workspace;
use App\Services\Finance\InvoicePaymentService;
use App\Services\Finance\InvoiceStateService;
use App\Services\Merchant\MerchantPaymentEligibilityService;
use App\Services\Payment\Contracts\BillableCheckoutRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class PaymentService
{
    public function __construct(
        private readonly PaymentGatewayManager $paymentGatewayManager,
        private readonly MerchantPaymentEligibilityService $merchantPaymentEligibilityService,
        private readonly InvoicePaymentService $invoicePaymentService,
    ) {}

    public function createPaymentLink(
        Order $order,
        ?PaymentGateway $gateway = null,
        string $paymentContext = 'merchant_order',
    ): Payment {
        if ($order->payment_status === 'paid') {
            throw new RuntimeException('Order is already paid.');
        }

        $moneyBucket = $this->resolveMoneyBucket($paymentContext);

        if (str_starts_with($paymentContext, 'merchant_')) {
            $workspace = Workspace::query()->findOrFail($order->workspace_id);
            $this->merchantPaymentEligibilityService->assertCanAcceptCustomerPayments($workspace);
        }

        $gateway = $gateway ?? PaymentGateway::query()->first();
        $provider = $this->paymentGatewayManager->resolve($gateway);
        $idempotencyKey = (string) Str::uuid();
        $checkoutReference = (string) $order->order_number;

        $result = $provider->createPaymentLink(
            reference: $checkoutReference,
            amount: (float) $order->total_amount,
            currency: $order->currency,
            metadata: [
                'workspace_id' => $order->workspace_id,
                'order_id' => $order->id,
                'payment_context' => $paymentContext,
                'money_bucket' => $moneyBucket,
            ]
        );

        return DB::transaction(function () use ($order, $gateway, $result, $idempotencyKey, $paymentContext, $moneyBucket, $checkoutReference): Payment {
            $payment = Payment::query()->create([
                'workspace_id' => $order->workspace_id,
                'order_id' => $order->id,
                'billable_type' => null,
                'billable_id' => null,
                'checkout_reference' => $checkoutReference,
                'payment_gateway_id' => $gateway?->id,
                'provider' => $gateway?->provider ?? config('payment.default_provider', 'local'),
                'provider_payment_id' => $result['provider_payment_id'],
                'idempotency_key' => $idempotencyKey,
                'status' => 'pending',
                'amount' => $order->total_amount,
                'currency' => $order->currency,
                'payment_link' => $result['payment_link'],
                'provider_payload' => $result['payload'],
                'payment_context' => $paymentContext,
                'money_bucket' => $moneyBucket,
            ]);

            $order->update(['payment_link' => $payment->payment_link]);

            return $payment;
        });
    }

    public function createBillablePaymentLink(
        BillableCheckoutRequest $request,
        ?PaymentGateway $gateway = null,
    ): Payment {
        if ($request->billableType !== Payment::BILLABLE_FINANCE_INVOICE) {
            throw new RuntimeException('Unsupported billable type for shared checkout.');
        }

        $amount = round($request->amount, 2);
        if ($amount <= 0) {
            throw new RuntimeException('Checkout amount must be greater than zero.');
        }

        $workspace = Workspace::query()->findOrFail($request->workspaceId);
        $paymentContext = Payment::CONTEXT_MERCHANT_INVOICE;
        $moneyBucket = $this->resolveMoneyBucket($paymentContext);
        $this->merchantPaymentEligibilityService->assertCanAcceptCustomerPayments($workspace);

        $gateway = $gateway ?? PaymentGateway::withoutGlobalScopes()
            ->where('workspace_id', $request->workspaceId)
            ->orderBy('id')
            ->first();
        $provider = $this->paymentGatewayManager->resolve($gateway);
        $checkoutReference = self::financeCheckoutReference($request->workspaceId, $request->billableId);

        return DB::transaction(function () use ($request, $gateway, $provider, $paymentContext, $moneyBucket, $checkoutReference, $amount): Payment {
            $existing = Payment::withoutGlobalScopes()
                ->where('workspace_id', $request->workspaceId)
                ->where('billable_type', $request->billableType)
                ->where('billable_id', $request->billableId)
                ->whereNull('order_id')
                ->whereIn('status', ['pending', 'processing'])
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if (
                $existing
                && abs((float) $existing->amount - $amount) <= InvoiceStateService::PAYMENT_TOLERANCE
                && filled($existing->payment_link)
            ) {
                return $existing;
            }

            if ($existing) {
                $existing->update([
                    'status' => 'failed',
                    'failed_at' => now(),
                    'failure_reason' => 'superseded_by_new_checkout',
                ]);
            }

            $result = $provider->createPaymentLink(
                reference: $checkoutReference,
                amount: $amount,
                currency: $request->currency,
                metadata: array_merge($request->metadata, [
                    'workspace_id' => $request->workspaceId,
                    'billable_type' => $request->billableType,
                    'billable_id' => $request->billableId,
                    'payment_context' => $paymentContext,
                    'money_bucket' => $moneyBucket,
                    'description' => 'Invoice '.$request->reference,
                ])
            );

            return Payment::withoutGlobalScopes()->create([
                'workspace_id' => $request->workspaceId,
                'order_id' => null,
                'billable_type' => $request->billableType,
                'billable_id' => $request->billableId,
                'checkout_reference' => $checkoutReference,
                'payment_gateway_id' => $gateway?->id,
                'provider' => $gateway?->provider ?? config('payment.default_provider', 'local'),
                'provider_payment_id' => $result['provider_payment_id'],
                'idempotency_key' => (string) Str::uuid(),
                'status' => 'pending',
                'amount' => $amount,
                'currency' => strtoupper($request->currency),
                'payment_link' => $result['payment_link'],
                'provider_payload' => $result['payload'],
                'payment_context' => $paymentContext,
                'money_bucket' => $moneyBucket,
            ]);
        });
    }

    public function findPendingBillablePayment(string $billableType, int $billableId, int $workspaceId): ?Payment
    {
        return Payment::withoutGlobalScopes()
            ->where('workspace_id', $workspaceId)
            ->where('billable_type', $billableType)
            ->where('billable_id', $billableId)
            ->whereNull('order_id')
            ->whereIn('status', ['pending', 'processing'])
            ->latest('id')
            ->first();
    }

    public static function financeCheckoutReference(int $workspaceId, int $invoiceId): string
    {
        return 'fininv:'.$workspaceId.':'.$invoiceId;
    }

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $payload
     */
    public function processWebhook(string $providerName, array $headers, array $payload, ?string $rawBody = null): void
    {
        $gateway = PaymentGateway::withoutGlobalScopes()
            ->where('provider', $providerName)
            ->first();

        $provider = $this->paymentGatewayManager->resolve($gateway);
        $verification = $provider->verifyWebhook($headers, $payload, $rawBody);
        $eventId = $verification['event_id'] ?? (string) Str::uuid();
        $reference = $verification['reference'] ?? null;

        $matchedPayment = is_string($reference) && $reference !== ''
            ? Payment::withoutGlobalScopes()
                ->where('checkout_reference', $reference)
                ->where('provider', $providerName)
                ->latest('id')
                ->first()
            : null;

        $order = is_string($reference) && $reference !== ''
            ? Order::withoutGlobalScopes()->where('order_number', $reference)->first()
            : null;

        $workspaceId = $matchedPayment?->workspace_id ?? $gateway?->workspace_id ?? $order?->workspace_id;

        if (! $workspaceId) {
            return;
        }

        $webhookEvent = WebhookEvent::withoutGlobalScopes()->firstOrCreate(
            [
                'workspace_id' => $workspaceId,
                'provider' => 'payment:'.$providerName,
                'external_event_id' => $eventId,
            ],
            [
                'workspace_id' => $workspaceId,
                'event_type' => $verification['status'] ?? 'unknown',
                'idempotency_key' => $eventId,
                'headers' => $headers,
                'payload' => $payload,
                'status' => $verification['verified'] ? 'pending' : 'invalid',
            ]
        );

        if (! $webhookEvent->wasRecentlyCreated) {
            return;
        }

        if (! $verification['verified']) {
            return;
        }

        if (($verification['status'] ?? null) !== 'paid') {
            return;
        }

        if (! $reference) {
            return;
        }

        DB::transaction(function () use ($providerName, $verification, $reference, $workspaceId): void {
            $payment = Payment::withoutGlobalScopes()
                ->where('checkout_reference', $reference)
                ->where('provider', $providerName)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($payment) {
                if ((int) $payment->workspace_id !== (int) $workspaceId) {
                    return;
                }

                if ($payment->isFinanceInvoiceBillable()) {
                    $this->settleFinanceInvoicePayment($payment, $verification);

                    return;
                }

                if ($payment->order_id) {
                    $this->settleOrderPayment($payment, $verification);

                    return;
                }

                return;
            }

            $this->settleOrderByNumber($providerName, $verification, $reference);
        });
    }

    /**
     * @param  array<string, mixed>  $verification
     */
    private function settleOrderPayment(Payment $payment, array $verification): void
    {
        $order = Order::withoutGlobalScopes()
            ->whereKey($payment->order_id)
            ->lockForUpdate()
            ->first();

        if (! $order || $order->payment_status === 'paid') {
            return;
        }

        $this->markPaymentPaid($payment, $verification);

        $order->update([
            'payment_status' => 'paid',
            'status' => $order->status === 'draft' ? 'confirmed' : $order->status,
        ]);

        event(new PaymentConfirmed($payment->fresh() ?? $payment));
    }

    /**
     * @param  array<string, mixed>  $verification
     */
    private function settleOrderByNumber(string $providerName, array $verification, string $reference): void
    {
        $order = Order::withoutGlobalScopes()
            ->where('order_number', $reference)
            ->lockForUpdate()
            ->first();

        if (! $order || $order->payment_status === 'paid') {
            return;
        }

        $payment = Payment::query()
            ->where('order_id', $order->id)
            ->where('provider', $providerName)
            ->latest('id')
            ->lockForUpdate()
            ->first();

        if (! $payment) {
            return;
        }

        $this->markPaymentPaid($payment, $verification);

        $order->update([
            'payment_status' => 'paid',
            'status' => $order->status === 'draft' ? 'confirmed' : $order->status,
        ]);

        event(new PaymentConfirmed($payment->fresh() ?? $payment));
    }

    /**
     * @param  array<string, mixed>  $verification
     */
    private function settleFinanceInvoicePayment(Payment $payment, array $verification): void
    {
        if (! $this->financeWebhookMatchesPayment($payment, $verification)) {
            return;
        }

        $invoice = FinanceInvoice::withoutGlobalScopes()
            ->where('workspace_id', $payment->workspace_id)
            ->whereKey($payment->billable_id)
            ->lockForUpdate()
            ->first();

        if (! $invoice || $invoice->trashed() || $invoice->isCancelled() || ! $invoice->isIssued()) {
            return;
        }

        $checkoutAmount = round((float) $payment->amount, 2);
        $due = round((float) $invoice->amount_due, 2);
        if ($checkoutAmount - $due > InvoiceStateService::PAYMENT_TOLERANCE) {
            Log::warning('Finance checkout webhook refused: provider amount exceeds invoice due.', [
                'workspace_id' => $payment->workspace_id,
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'checkout_amount' => $checkoutAmount,
                'amount_due' => $due,
            ]);

            return;
        }

        $alreadyPaid = (string) $payment->status === 'paid';
        if (! $alreadyPaid) {
            $this->markPaymentPaid($payment, $verification);
        }

        $workspace = Workspace::withoutGlobalScopes()->find($payment->workspace_id);
        $actorUserId = (int) ($workspace?->owner_user_id ?? 0);
        if ($actorUserId <= 0) {
            return;
        }

        $this->invoicePaymentService->recordPayment($invoice, [
            'payment_date' => now()->toDateString(),
            'amount' => $checkoutAmount,
            'method' => 'other',
            'reference' => $this->financeSettlementReference($payment),
            'notes' => 'Online checkout via shared payments',
        ], $actorUserId);

        if (! $alreadyPaid) {
            event(new PaymentConfirmed($payment->fresh() ?? $payment));
        }
    }

    /**
     * @param  array<string, mixed>  $verification
     */
    private function financeWebhookMatchesPayment(Payment $payment, array $verification): bool
    {
        if (array_key_exists('amount', $verification) && $verification['amount'] !== null) {
            $reported = round((float) $verification['amount'], 2);
            if (abs($reported - (float) $payment->amount) > InvoiceStateService::PAYMENT_TOLERANCE) {
                return false;
            }
        }

        if (array_key_exists('currency', $verification) && is_string($verification['currency']) && $verification['currency'] !== '') {
            if (strtoupper($verification['currency']) !== strtoupper((string) $payment->currency)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $verification
     */
    private function markPaymentPaid(Payment $payment, array $verification): void
    {
        $payment->update([
            'status' => 'paid',
            'paid_at' => now(),
            'provider_payload' => $verification['payload'] ?? $payment->provider_payload,
        ]);
    }

    public function financeSettlementReference(Payment $payment): string
    {
        return 'checkout:'.$payment->id;
    }

    private function resolveMoneyBucket(string $paymentContext): string
    {
        return match ($paymentContext) {
            'platform_subscription' => 'platform_revenue',
            'platform_commerce' => 'platform_commerce',
            'merchant_booking', 'merchant_order', Payment::CONTEXT_MERCHANT_INVOICE => 'merchant_gmv',
            'local_sandbox' => 'local_sandbox',
            default => str_starts_with($paymentContext, 'platform_')
                ? 'platform_commerce'
                : 'merchant_gmv',
        };
    }
}
