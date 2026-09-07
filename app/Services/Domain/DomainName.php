<?php

namespace App\Services\Domain;

use RuntimeException;

class DomainName
{
    public function __construct(
        public readonly string $domain,
        public readonly string $sld,
        public readonly string $tld,
    ) {}

    public static function fromInput(string $input): self
    {
        $domain = self::normalize($input);
        $parts = explode('.', $domain);
        if (count($parts) < 2) {
            throw new RuntimeException('Invalid domain name.');
        }

        $sld = array_shift($parts);
        $tld = implode('.', $parts);
        if ($sld === null || $tld === null || $sld === '' || $tld === '') {
            throw new RuntimeException('Invalid domain name.');
        }

        return new self(
            domain: $domain,
            sld: strtolower($sld),
            tld: strtolower($tld),
        );
    }

    public static function normalize(string $input): string
    {
        $domain = trim(strtolower($input));
        $domain = preg_replace('#^https?://#', '', $domain) ?? $domain;
        $domain = explode('/', $domain)[0] ?? $domain;
        $domain = explode(':', $domain)[0] ?? $domain;
        $domain = rtrim($domain, '.');
        $domain = preg_replace('/^www\./', '', $domain) ?? $domain;

        if (function_exists('idn_to_ascii')) {
            $converted = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            if (is_string($converted) && $converted !== '') {
                $domain = strtolower($converted);
            }
        }

        return $domain;
    }
}
