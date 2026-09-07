<?php

namespace App\Services\Payment;

use App\Models\PaymentGateway;
use App\Services\Payment\Contracts\PaymentGatewayInterface;
use App\Services\Payment\Providers\LocalPaymentGateway;
use App\Services\Payment\Providers\StripePaymentGateway;

class PaymentGatewayManager
{
    public function resolve(?PaymentGateway $gateway = null): PaymentGatewayInterface
    {
        $provider = $gateway?->provider ?? config('payment.default_provider', 'local');

        return match ($provider) {
            'stripe' => app(StripePaymentGateway::class),
            default => app(LocalPaymentGateway::class),
        };
    }
}
