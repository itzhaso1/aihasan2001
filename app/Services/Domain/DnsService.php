<?php

namespace App\Services\Domain;

use App\Models\Website\WebsiteDomain;
use App\Services\Domain\Contracts\DomainRegistrarInterface;
use RuntimeException;

class DnsService
{
    public function __construct(
        private readonly DomainRegistrarInterface $registrar,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function configureWebsiteDns(WebsiteDomain $websiteDomain): array
    {
        $target = trim((string) config('website.dns_target'));
        if ($target === '') {
            throw new RuntimeException('WEBSITE_DNS_TARGET is not configured.');
        }

        $targetType = strtoupper((string) config('website.dns_target_type', 'A'));
        if (! in_array($targetType, ['A', 'AAAA', 'CNAME', 'ALIAS'], true)) {
            $targetType = 'A';
        }

        $existing = $this->registrar->getDnsRecords($websiteDomain->domain);
        $existingRecords = collect($existing['records'] ?? [])
            ->filter(fn ($record) => is_array($record))
            ->values();

        $preservedRecords = $existingRecords
            ->reject(function (array $record): bool {
                $name = strtolower((string) ($record['name'] ?? ''));
                $type = strtoupper((string) ($record['type'] ?? ''));

                if (! in_array($name, ['@', 'www'], true)) {
                    return false;
                }

                return in_array($type, ['A', 'AAAA', 'CNAME', 'ALIAS'], true);
            })
            ->values()
            ->all();

        $managedRecords = [
            [
                'name' => '@',
                'type' => $targetType,
                'address' => $target,
                'mx_pref' => 10,
                'ttl' => (int) config('website.dns_ttl', 300),
            ],
            [
                'name' => 'www',
                'type' => 'CNAME',
                'address' => (string) config('website.dns_www_target', '@'),
                'mx_pref' => 10,
                'ttl' => (int) config('website.dns_ttl', 300),
            ],
        ];

        $recordsToSet = [...$preservedRecords, ...$managedRecords];
        $write = $this->registrar->setDnsRecords(
            domain: $websiteDomain->domain,
            records: $recordsToSet
        );

        if (($write['is_success'] ?? false) !== true) {
            throw new RuntimeException('Failed to configure DNS records on provider.');
        }

        $verify = $this->registrar->getDnsRecords($websiteDomain->domain);
        $verified = collect($verify['records'] ?? [])->contains(function ($record) use ($target, $targetType): bool {
            if (! is_array($record)) {
                return false;
            }

            return strtolower((string) ($record['name'] ?? '')) === '@'
                && strtoupper((string) ($record['type'] ?? '')) === $targetType
                && (string) ($record['address'] ?? '') === $target;
        });

        return [
            'provider_write' => $write,
            'verified' => $verified,
            'records_sent' => $recordsToSet,
            'records_before' => $existing['records'] ?? [],
            'records_after' => $verify['records'] ?? [],
        ];
    }
}
