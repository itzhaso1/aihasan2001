<?php

namespace App\Services\Payment\Contracts;

final class BillableCheckoutResult
{
    public function __construct(
        public readonly bool $supported,
        public readonly ?string $checkoutUrl,
        public readonly string $code,
        public readonly string $message,
    ) {}

    public static function unsupported(string $code, string $message): self
    {
        return new self(false, null, $code, $message);
    }

    public static function supported(string $checkoutUrl): self
    {
        return new self(true, $checkoutUrl, 'ok', '');
    }
}
