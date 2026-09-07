<?php

namespace App\Services\Payment\Providers;

use App\Services\Payment\Contracts\PaymentGatewayInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class StripePaymentGateway implements PaymentGatewayInterface
{
    public function createPaymentLink(string $reference, float $amount, string $currency, array $metadata = []): array
    {
        $secret = config('services.stripe.secret');

        if (! $secret) {
            throw new \RuntimeException('Stripe secret is not configured.');
        }

        $response = Http::asForm()
            ->withToken($secret)
            ->post('https://api.stripe.com/v1/payment_links', [
                'line_items[0][price_data][currency]' => strtolower($currency),
                'line_items[0][price_data][product_data][name]' => 'Order '.$reference,
                'line_items[0][price_data][unit_amount]' => (int) round($amount * 100),
                'line_items[0][quantity]' => 1,
                'metadata[reference]' => $reference,
            ])
            ->throw()
            ->json();

        return [
            'provider_payment_id' => (string) ($response['id'] ?? Str::lower(Str::random(24))),
            'payment_link' => (string) ($response['url'] ?? ''),
            'payload' => $response,
        ];
    }

    public function verifyWebhook(array $headers, array $payload, ?string $rawBody = null): array
    {
        $secret = (string) (config('payment.providers.stripe.webhook_secret') ?: config('services.stripe.webhook_secret'));
        if ($secret === '') {
            return [
                'verified' => false,
                'event_id' => (string) ($payload['id'] ?? null),
                'status' => null,
                'reference' => null,
                'payload' => $payload,
                'reason' => 'Stripe webhook secret is not configured.',
            ];
        }

        $signatureHeader = (string) ($headers['stripe-signature'] ?? '');
        if ($signatureHeader === '') {
            return [
                'verified' => false,
                'event_id' => (string) ($payload['id'] ?? null),
                'status' => null,
                'reference' => null,
                'payload' => $payload,
                'reason' => 'Missing Stripe signature header.',
            ];
        }

        $rawBody = $rawBody ?? json_encode($payload);
        if (! is_string($rawBody)) {
            $rawBody = '';
        }

        $parts = [];
        foreach (explode(',', $signatureHeader) as $part) {
            [$k, $v] = array_pad(explode('=', trim($part), 2), 2, null);
            if ($k !== null && $v !== null) {
                $parts[$k][] = $v;
            }
        }

        $timestamp = isset($parts['t'][0]) ? (int) $parts['t'][0] : 0;
        $tolerance = (int) config('payment.providers.stripe.webhook_tolerance_seconds', 300);
        if ($timestamp <= 0 || abs(time() - $timestamp) > $tolerance) {
            return [
                'verified' => false,
                'event_id' => (string) ($payload['id'] ?? null),
                'status' => null,
                'reference' => null,
                'payload' => $payload,
                'reason' => 'Webhook timestamp is outside allowed tolerance window.',
            ];
        }

        $signedPayload = $timestamp.'.'.$rawBody;
        $expected = hash_hmac('sha256', $signedPayload, $secret);
        $providedSignatures = $parts['v1'] ?? [];
        $matched = collect($providedSignatures)->contains(
            fn (string $signature): bool => hash_equals($expected, $signature)
        );

        if (! $matched) {
            return [
                'verified' => false,
                'event_id' => (string) ($payload['id'] ?? null),
                'status' => null,
                'reference' => null,
                'payload' => $payload,
                'reason' => 'Stripe signature mismatch.',
            ];
        }

        $eventId = $payload['id'] ?? null;
        $type = $payload['type'] ?? null;
        $reference = $payload['data']['object']['metadata']['reference'] ?? null;

        return [
            'verified' => true,
            'event_id' => $eventId,
            'status' => $type === 'checkout.session.completed' ? 'paid' : 'pending',
            'reference' => $reference,
            'payload' => $payload,
            'reason' => null,
        ];
    }
}
