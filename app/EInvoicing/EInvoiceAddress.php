<?php

namespace App\EInvoicing;

final readonly class EInvoiceAddress
{
    public function __construct(
        public ?string $line,
        public ?string $buildingNumber,
        public ?string $street,
        public ?string $district,
        public ?string $city,
        public ?string $postalCode,
        public ?string $countryCode,
        public ?string $additionalNumber,
    ) {}

    /**
     * @param  array<string, mixed>  $party
     */
    public static function fromSnapshotParty(array $party): self
    {
        return new self(
            line: self::stringOrNull($party['address'] ?? $party['address_line'] ?? null),
            buildingNumber: self::stringOrNull($party['building_number'] ?? null),
            street: self::stringOrNull($party['street'] ?? null),
            district: self::stringOrNull($party['district'] ?? null),
            city: self::stringOrNull($party['city'] ?? null),
            postalCode: self::stringOrNull($party['postal_code'] ?? null),
            countryCode: self::stringOrNull($party['country_code'] ?? null),
            additionalNumber: self::stringOrNull($party['additional_number'] ?? null),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'line' => $this->line,
            'building_number' => $this->buildingNumber,
            'street' => $this->street,
            'district' => $this->district,
            'city' => $this->city,
            'postal_code' => $this->postalCode,
            'country_code' => $this->countryCode,
            'additional_number' => $this->additionalNumber,
        ];
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
