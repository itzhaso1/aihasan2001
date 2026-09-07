<?php

namespace App\Services\Domain\Contracts;

interface DomainRegistrarInterface
{
    /**
     * @param  array<int, string>  $domains
     * @return array<int, array<string, mixed>>
     */
    public function checkAvailability(array $domains): array;

    /**
     * @param  array<string, mixed>  $contacts
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function register(string $domain, int $years, array $contacts, array $options = []): array;

    /**
     * @return array<string, mixed>
     */
    public function getInfo(string $domain): array;

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getDomains(array $filters = []): array;

    /**
     * @return array<string, mixed>
     */
    public function getDnsRecords(string $domain): array;

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @return array<string, mixed>
     */
    public function setDnsRecords(string $domain, array $records, ?string $emailType = null): array;

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function renew(string $domain, int $years, array $options = []): array;

    /**
     * Fetch TLD retail pricing via namecheap.users.getPricing.
     *
     * @param  array<int, string>  $tlds
     * @return array<string, array{registration:?float,renewal:?float,transfer:?float,currency:?string}>
     */
    public function getTldPricing(array $tlds, int $years = 1): array;
}
