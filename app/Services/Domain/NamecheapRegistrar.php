<?php

namespace App\Services\Domain;

use App\Services\Domain\Contracts\DomainRegistrarInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class NamecheapRegistrar implements DomainRegistrarInterface
{
    public function __construct(
        private readonly NamecheapXmlParser $xmlParser,
    ) {}

    public function checkAvailability(array $domains): array
    {
        $domains = collect($domains)
            ->map(fn ($domain) => DomainName::normalize((string) $domain))
            ->filter()
            ->values()
            ->all();

        if ($domains === []) {
            return [];
        }

        $response = $this->request('namecheap.domains.check', [
            'DomainList' => implode(',', $domains),
        ]);

        $result = $response['command_response']['DomainCheckResult'] ?? [];
        if ($result === []) {
            return [];
        }

        $rows = array_is_list($result) ? $result : [$result];

        return array_map(function (array $row): array {
            $premiumRegistrationPrice = (float) ($row['PremiumRegistrationPrice'] ?? 0);
            $premiumRenewalPrice = (float) ($row['PremiumRenewalPrice'] ?? 0);
            $premiumTransferPrice = (float) ($row['PremiumTransferPrice'] ?? 0);
            $isPremium = filter_var($row['IsPremiumName'] ?? false, FILTER_VALIDATE_BOOLEAN);

            return [
                'domain' => strtolower((string) ($row['Domain'] ?? '')),
                'available' => filter_var($row['Available'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'is_premium' => $isPremium,
                'registration_price' => $isPremium ? $premiumRegistrationPrice : null,
                'renewal_price' => $isPremium ? $premiumRenewalPrice : null,
                'transfer_price' => $isPremium ? $premiumTransferPrice : null,
                'premium_registration_price' => $premiumRegistrationPrice,
                'premium_renewal_price' => $premiumRenewalPrice,
                'premium_transfer_price' => $premiumTransferPrice,
                'icann_fee' => (float) ($row['IcannFee'] ?? 0),
                'eap_fee' => (float) ($row['EapFee'] ?? 0),
            ];
        }, $rows);
    }

    public function register(string $domain, int $years, array $contacts, array $options = []): array
    {
        $domainName = DomainName::fromInput($domain);
        $years = max(1, min(10, $years));

        $registrant = $this->normalizeContactPayload($contacts['registrant'] ?? []);
        $admin = $this->normalizeContactPayload($contacts['admin'] ?? $registrant);
        $tech = $this->normalizeContactPayload($contacts['tech'] ?? $registrant);
        $auxBilling = $this->normalizeContactPayload($contacts['aux_billing'] ?? $registrant);

        $payload = [
            'DomainName' => $domainName->domain,
            'Years' => $years,
            'AddFreeWhoisguard' => 'no',
            'WGEnabled' => 'no',
            ...$this->withPrefix('Registrant', $registrant),
            ...$this->withPrefix('Admin', $admin),
            ...$this->withPrefix('Tech', $tech),
            ...$this->withPrefix('AuxBilling', $auxBilling),
        ];

        if (filled($options['promotion_code'] ?? null)) {
            $payload['PromotionCode'] = (string) $options['promotion_code'];
        }
        if (filled($options['nameservers'] ?? null)) {
            $payload['Nameservers'] = is_array($options['nameservers'])
                ? implode(',', $options['nameservers'])
                : (string) $options['nameservers'];
        }
        if (filled($options['idn_code'] ?? null)) {
            $payload['IdnCode'] = (string) $options['idn_code'];
        }
        if (($options['is_premium_domain'] ?? false) === true) {
            $payload['IsPremiumDomain'] = 'true';
            $payload['PremiumPrice'] = (string) ($options['premium_price'] ?? '0');
        }
        if (array_key_exists('eap_fee', $options)) {
            $payload['EapFee'] = (string) $options['eap_fee'];
        }

        $response = $this->request('namecheap.domains.create', $payload);
        $result = $response['command_response']['DomainCreateResult'] ?? [];
        if (! is_array($result) || $result === []) {
            throw new DomainProviderException('Namecheap domain create response is missing result data.');
        }

        return [
            'domain' => (string) ($result['Domain'] ?? $domainName->domain),
            'registered' => filter_var($result['Registered'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'charged_amount' => (float) ($result['ChargedAmount'] ?? 0),
            'provider_domain_id' => (string) ($result['DomainID'] ?? ''),
            'order_id' => (string) ($result['OrderID'] ?? ''),
            'transaction_id' => (string) ($result['TransactionID'] ?? ''),
            'provider_payload' => $result,
        ];
    }

    public function getInfo(string $domain): array
    {
        $normalized = DomainName::fromInput($domain);

        $response = $this->request('namecheap.domains.getInfo', [
            'DomainName' => $normalized->domain,
        ]);

        $result = $response['command_response']['DomainGetInfoResult'] ?? [];
        if (! is_array($result)) {
            $result = [];
        }

        return [
            'domain' => strtolower((string) ($result['DomainName'] ?? $normalized->domain)),
            'status' => strtolower((string) ($result['Status'] ?? 'unknown')),
            'provider_domain_id' => (string) ($result['ID'] ?? ''),
            'owner_name' => (string) ($result['OwnerName'] ?? ''),
            'is_owner' => filter_var($result['IsOwner'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'is_premium' => filter_var($result['IsPremium'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'raw' => $result,
        ];
    }

    public function getDomains(array $filters = []): array
    {
        $payload = [];
        foreach (['ListType', 'SearchTerm', 'Page', 'PageSize', 'SortBy'] as $key) {
            if (array_key_exists($key, $filters)) {
                $payload[$key] = (string) $filters[$key];
            }
        }

        $response = $this->request('namecheap.domains.getList', $payload);
        $result = $response['command_response']['DomainGetListResult']['Domain'] ?? [];
        $domains = [];

        foreach (array_is_list($result) ? $result : ($result === [] ? [] : [$result]) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $domains[] = [
                'id' => (string) ($item['ID'] ?? ''),
                'name' => strtolower((string) ($item['Name'] ?? '')),
                'expires_at' => (string) ($item['Expires'] ?? ''),
                'is_expired' => filter_var($item['IsExpired'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'auto_renew' => filter_var($item['AutoRenew'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'is_our_dns' => filter_var($item['IsOurDNS'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ];
        }

        return [
            'domains' => $domains,
            'paging' => Arr::wrap($response['command_response']['Paging'] ?? []),
        ];
    }

    public function getDnsRecords(string $domain): array
    {
        $domainName = DomainName::fromInput($domain);

        $response = $this->request('namecheap.domains.dns.getHosts', [
            'SLD' => $domainName->sld,
            'TLD' => $domainName->tld,
        ]);

        $result = $response['command_response']['DomainDNSGetHostsResult'] ?? [];
        $hosts = $result['Host'] ?? [];
        $hosts = array_is_list($hosts) ? $hosts : ($hosts === [] ? [] : [$hosts]);

        return [
            'domain' => strtolower((string) ($result['Domain'] ?? $domainName->domain)),
            'is_using_our_dns' => filter_var($result['IsUsingOurDNS'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'records' => array_map(function ($host): array {
                $record = is_array($host) ? $host : [];

                return [
                    'name' => (string) ($record['Name'] ?? '@'),
                    'type' => strtoupper((string) ($record['Type'] ?? 'A')),
                    'address' => (string) ($record['Address'] ?? ''),
                    'mx_pref' => (int) ($record['MXPref'] ?? 10),
                    'ttl' => (int) ($record['TTL'] ?? 1800),
                ];
            }, $hosts),
        ];
    }

    public function setDnsRecords(string $domain, array $records, ?string $emailType = null): array
    {
        $domainName = DomainName::fromInput($domain);
        $payload = [
            'SLD' => $domainName->sld,
            'TLD' => $domainName->tld,
        ];

        if ($emailType !== null && $emailType !== '') {
            $payload['EmailType'] = $emailType;
        }

        $index = 1;
        foreach ($records as $record) {
            $payload['HostName'.$index] = (string) ($record['name'] ?? '@');
            $payload['RecordType'.$index] = strtoupper((string) ($record['type'] ?? 'A'));
            $payload['Address'.$index] = (string) ($record['address'] ?? '');
            $payload['MXPref'.$index] = (string) ((int) ($record['mx_pref'] ?? 10));
            $payload['TTL'.$index] = (string) ((int) ($record['ttl'] ?? 1800));
            $index++;
        }

        $response = $this->request('namecheap.domains.dns.setHosts', $payload);
        $result = $response['command_response']['DomainDNSSetHostsResult'] ?? [];

        return [
            'domain' => strtolower((string) ($result['Domain'] ?? $domainName->domain)),
            'is_success' => filter_var($result['IsSuccess'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'provider_payload' => $result,
        ];
    }

    public function renew(string $domain, int $years, array $options = []): array
    {
        $domainName = DomainName::fromInput($domain);
        $payload = [
            'DomainName' => $domainName->domain,
            'Years' => max(1, min(10, $years)),
        ];

        if (filled($options['promotion_code'] ?? null)) {
            $payload['PromotionCode'] = (string) $options['promotion_code'];
        }
        if (($options['is_premium_domain'] ?? false) === true) {
            $payload['IsPremiumDomain'] = 'true';
            $payload['PremiumPrice'] = (string) ($options['premium_price'] ?? '0');
        }

        $response = $this->request('namecheap.domains.renew', $payload);
        $result = $response['command_response']['DomainRenewResult'] ?? [];
        if (! is_array($result)) {
            $result = [];
        }

        return [
            'domain' => strtolower((string) ($result['DomainName'] ?? $domainName->domain)),
            'renewed' => filter_var($result['Renew'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'provider_domain_id' => (string) ($result['DomainID'] ?? ''),
            'order_id' => (string) ($result['OrderID'] ?? ''),
            'transaction_id' => (string) ($result['TransactionID'] ?? ''),
            'charged_amount' => (float) ($result['ChargedAmount'] ?? 0),
            'provider_payload' => $result,
        ];
    }

    public function getTldPricing(array $tlds, int $years = 1): array
    {
        $years = max(1, min(10, $years));
        $normalizedTlds = collect($tlds)
            ->map(fn ($tld) => strtolower(trim((string) $tld)))
            ->filter(fn (string $tld) => $tld !== '')
            ->unique()
            ->values()
            ->all();

        if ($normalizedTlds === []) {
            return [];
        }

        $pricing = [];
        $response = $this->request('namecheap.users.getPricing', [
            'ProductType' => 'DOMAIN',
        ]);

        $productType = data_get($response, 'command_response.UserGetPricingResult.ProductType', []);
        $categories = data_get($productType, 'ProductCategory', []);
        $categoryRows = array_is_list($categories) ? $categories : ($categories === [] ? [] : [$categories]);

        foreach ($categoryRows as $category) {
            if (! is_array($category)) {
                continue;
            }

            $action = strtoupper((string) ($category['Name'] ?? ''));
            $priceKey = match ($action) {
                'REGISTER' => 'registration',
                'RENEW' => 'renewal',
                'TRANSFER' => 'transfer',
                default => null,
            };
            if ($priceKey === null) {
                continue;
            }

            $products = $category['Product'] ?? [];
            $productRows = array_is_list($products) ? $products : ($products === [] ? [] : [$products]);

            foreach ($productRows as $product) {
                if (! is_array($product)) {
                    continue;
                }

                $tld = strtolower((string) ($product['Name'] ?? ''));
                if ($tld === '' || ! in_array($tld, $normalizedTlds, true)) {
                    continue;
                }

                $prices = $product['Price'] ?? [];
                $priceRows = array_is_list($prices) ? $prices : ($prices === [] ? [] : [$prices]);
                $matched = null;
                foreach ($priceRows as $priceRow) {
                    if (! is_array($priceRow)) {
                        continue;
                    }
                    if ((int) ($priceRow['Duration'] ?? 0) === $years) {
                        $matched = $priceRow;
                        break;
                    }
                }
                if ($matched === null && isset($priceRows[0]) && is_array($priceRows[0])) {
                    $matched = $priceRows[0];
                }
                if ($matched === null) {
                    continue;
                }

                $amount = (float) ($matched['YourPrice'] ?? $matched['Price'] ?? 0);
                $pricing[$tld] ??= [
                    'registration' => null,
                    'renewal' => null,
                    'transfer' => null,
                    'currency' => (string) ($matched['Currency'] ?? 'USD'),
                ];
                $pricing[$tld][$priceKey] = $amount > 0 ? $amount : null;
                $pricing[$tld]['currency'] = (string) ($matched['Currency'] ?? $pricing[$tld]['currency'] ?? 'USD');
            }
        }

        return $pricing;
    }

    /**
     * @param  array<string, mixed>  payload
     * @return array<string, mixed>
     */
    private function request(string $command, array $payload): array
    {
        $baseUrl = $this->baseUrl();
        $params = [
            'ApiUser' => (string) config('services.namecheap.api_user'),
            'ApiKey' => (string) config('services.namecheap.api_key'),
            'UserName' => (string) config('services.namecheap.username'),
            'ClientIp' => (string) config('services.namecheap.client_ip'),
            'Command' => $command,
            ...$payload,
        ];

        foreach (['ApiUser', 'ApiKey', 'UserName', 'ClientIp'] as $required) {
            if (! filled($params[$required] ?? null)) {
                throw new RuntimeException("Missing Namecheap config parameter: {$required}");
            }
        }

        $attempt = 0;
        $maxAttempts = 3;
        do {
            $attempt++;

            $response = Http::asForm()
                ->connectTimeout((int) config('services.namecheap.connect_timeout', 8))
                ->timeout((int) config('services.namecheap.timeout', 20))
                ->post($baseUrl, $params);

            if ($response->successful()) {
                break;
            }

            if ($attempt < $maxAttempts) {
                usleep((int) (200000 * (2 ** ($attempt - 1))));
            }
        } while ($attempt < $maxAttempts);

        if (! isset($response) || ! $response->successful()) {
            throw new DomainProviderException("Namecheap API request failed for command {$command}.");
        }

        $parsed = $this->xmlParser->parse((string) $response->body());
        // Never log ApiKey / raw credential-bearing payloads.
        Log::info('namecheap.request', [
            'command' => $command,
            'status' => $parsed['status'] ?? 'unknown',
            'error_count' => count($parsed['errors'] ?? []),
            'attempt' => $attempt,
        ]);

        if (($parsed['status'] ?? 'ERROR') !== 'OK') {
            $errorMessage = collect($parsed['errors'] ?? [])
                ->map(fn (array $error): string => ($error['number'] ?? 'unknown').': '.($error['message'] ?? ''))
                ->implode(' | ');

            throw new DomainProviderException(
                message: $errorMessage !== '' ? $errorMessage : "Namecheap command {$command} failed.",
                errors: $parsed['errors'] ?? []
            );
        }

        return $parsed;
    }

    private function baseUrl(): string
    {
        $env = strtolower((string) config('services.namecheap.env', 'sandbox'));

        return $env === 'production'
            ? (string) config('services.namecheap.base_url_production')
            : (string) config('services.namecheap.base_url_sandbox');
    }

    /**
     * @param  array<string, mixed>  contact
     * @return array<string, string>
     */
    private function normalizeContactPayload(array $contact): array
    {
        $required = [
            'FirstName' => trim((string) ($contact['first_name'] ?? '')),
            'LastName' => trim((string) ($contact['last_name'] ?? '')),
            'Address1' => trim((string) ($contact['address1'] ?? '')),
            'City' => trim((string) ($contact['city'] ?? '')),
            'StateProvince' => trim((string) ($contact['state_province'] ?? '')),
            'PostalCode' => trim((string) ($contact['postal_code'] ?? '')),
            'Country' => strtoupper(trim((string) ($contact['country'] ?? 'US'))),
            'Phone' => trim((string) ($contact['phone'] ?? '')),
            'EmailAddress' => trim((string) ($contact['email'] ?? '')),
        ];

        foreach ($required as $key => $value) {
            if ($value === '') {
                throw new RuntimeException("Missing contact field {$key} for Namecheap registration.");
            }
        }

        return [
            ...$required,
            'Address2' => trim((string) ($contact['address2'] ?? '')),
            'OrganizationName' => trim((string) ($contact['organization_name'] ?? '')),
            'JobTitle' => trim((string) ($contact['job_title'] ?? '')),
            'PhoneExt' => trim((string) ($contact['phone_ext'] ?? '')),
            'Fax' => trim((string) ($contact['fax'] ?? '')),
        ];
    }

    /**
     * @param  array<string, string>  values
     * @return array<string, string>
     */
    private function withPrefix(string $prefix, array $values): array
    {
        $prefixed = [];

        foreach ($values as $key => $value) {
            if ($value === '') {
                continue;
            }
            $prefixed[$prefix.$key] = $value;
        }

        return $prefixed;
    }
}
