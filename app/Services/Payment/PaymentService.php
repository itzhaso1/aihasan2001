<?php

namespace App\Services\Payment;

use App\Events\PaymentConfirmed;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\WebhookEvent;
use App\Models\Workspace;
use App\Services\Merchant\MerchantPaymentEligibilityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentService
{
    public function __construct(
        private readonly PaymentGatewayManager $paymentGatewayManager,
        private readonly MerchantPaymentEligibilityService $merchantPaymentEligibilityService,
    ) {}

    public function createPaymentLink(
        Order $order,
        ?PaymentGateway $gateway = null,
        string $paymentContext = 'merchant_order',
    ): Payment {
        if ($order->payment_status === 'paid') {
            throw new \RuntimeException('Order is already paid.');
        }

        $moneyBucket = $this->resolveMoneyBucket($paymentContext);

        if (str_starts_with($paymentContext, 'merchant_')) {
            $workspace = Workspace::query()->findOrFail($order->workspace_id);
            $this->merchantPaymentEligibilityService->assertCanAcceptCustomerPayments($workspace);
        }

        $gateway = $gateway ?? PaymentGateway::query()->first();
        $provider = $this->paymentGatewayManager->resolve($gateway);
        $idempotencyKey = (string) Str::uuid();

        $result = $provider->createPaymentLink(
            reference: $order->order_number,
            amount: (float) $order->total_amount,
            currency: $order->currency,
            metadata: [
                'workspace_id' => $order->workspace_id,
                'order_id' => $order->id,
                'payment_context' => $paymentContext,
                'money_bucket' => $moneyBucket,
            ]
        );

        return DB::transaction(function () use ($order, $gateway, $result, $idempotencyKey, $paymentContext, $moneyBucket): Payment {
            $payment = Payment::query()->create([
                'workspace_id' => $order->workspace_id,
                'order_id' => $order->id,
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

        $order = Order::withoutGlobalScopes()->where('order_number', $verification['reference'] ?? null)->first();
        $workspaceId = $gateway?->workspace_id ?? $order?->workspace_id;

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

        $reference = $verification['reference'] ?? null;
        if (! $reference) {
            return;
        }

        DB::transaction(function () use ($providerName, $verification, $reference): void {
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

            $payment->update([
                'status' => 'paid',
                'paid_at' => now(),
                'provider_payload' => $verification['payload'],
            ]);

            $order->update([
                'payment_status' => 'paid',
                'status' => $order->status === 'draft' ? 'confirmed' : $order->status,
            ]);

            event(new PaymentConfirmed($payment));
        });
    }

    private function resolveMoneyBucket(string $paymentContext): string
    {
        return match ($paymentContext) {
            'platform_subscription' => 'platform_revenue',
            'platform_commerce' => 'platform_commerce',
            'merchant_booking', 'merchant_order' => 'merchant_gmv',
            'local_sandbox' => 'local_sandbox',
            default => str_starts_with($paymentContext, 'platform_')
                ? 'platform_commerce'
                : 'merchant_gmv',
        };
    }
}
