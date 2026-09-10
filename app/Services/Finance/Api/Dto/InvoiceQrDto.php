<?php

namespace App\Services\Finance\Api\Dto;

final readonly class InvoiceQrDto
{
    /**
     * @param  array<int, string>  $tags
     */
    public function __construct(
        public string $base64,
        public string $profile,
        public array $tags,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'qr_base64' => $this->base64,
            'profile' => $this->profile,
            'tags' => $this->tags,
        ];
    }
}
