<?php

namespace App\Services\Domain;

use App\Jobs\ConfigureDomainDnsJob;
use App\Jobs\ProvisionSslJob;
use App\Jobs\RegisterDomainJob;
use App\Jobs\RenewDomainJob;
use App\Jobs\SyncDomainStatusJob;
use App\Jobs\VerifyDomainJob;
use App\Models\Website\Website;
use App\Models\Website\WebsiteDomain;
use App\Models\Website\WebsiteDomainContact;
use App\Models\Website\WebsiteDomainOperation;
use App\Models\Website\WebsiteDomainReminderLog;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\DomainExpirationReminderNotification;
use App\Services\Domain\Contracts\DomainRegistrarInterface;
use App\Services\Website\WebsiteResolverService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use RuntimeException;

class DomainService
{
    public function __construct(
        private readonly DomainRegistrarInterface $registrar,
        private readonly WebsiteResolverService $websiteResolverService,
        private readonly SslService $sslService,
    ) {}

    /**
     * @param  array<int, string>|null  $extensions
     * @return array<int, array<string, mixed>>
     */
    public function searchDomains(string $query, ?array $extensions = null): array
    {
        $base = strtolower(trim($query));
        $base = preg_replace('/[^a-z0-9-]/', '', $base) ?? '';
        $base = trim($base, '-');
        if ($base === '') {
            throw new RuntimeException('Domain query is required.');
        }

        $extensions = $extensions ?: (array) config('website.domain_search_tlds', ['com']);
        $extensions = collect($extensions)
            ->map(fn ($tld) => strtolower(trim((string) $tld)))
            ->filter(fn (string $tld) => preg_match('/^[a-z0-9.-]{2,}$/', $tld) === 1)
            ->unique()
            ->take(20)
            ->values()
            ->all();

        $domains = collect($extensions)->map(fn (string $tld): string => "{$base}.{$tld}")->all();
        $results = $this->registrar->checkAvailability($domains);
        $tldPricing = $this->cachedTldPricing($extensions);

        $markupPercent = (float) config('website.domain_markup_percent', 0);

        return array_map(function (array $result) use ($markupPercent, $tldPricing): array {
            $domain = (string) ($result['domain'] ?? '');
            $tld = str_contains($domain, '.')
                ? strtolower(substr($domain, strpos($domain, '.') + 1))
                : '';
            $isPremium = (bool) ($result['is_premium'] ?? false);

            $rawRegistration = $isPremium
                ? (float) ($result['premium_registration_price'] ?? $result['registration_price'] ?? 0)
                : (float) ($result['registration_price'] ?? data_get($tldPricing, "{$tld}.registration") ?? 0);
            $rawRenewal = $isPremium
                ? (float) ($result['premium_renewal_price'] ?? $result['renewal_price'] ?? 0)
                : (float) ($result['renewal_price'] ?? data_get($tldPricing, "{$tld}.renewal") ?? 0);
            $rawTransfer = $isPremium
                ? (float) ($result['premium_transfer_price'] ?? $result['transfer_price'] ?? 0)
                : (float) ($result['transfer_price'] ?? data_get($tldPricing, "{$tld}.transfer") ?? 0);

            $withMarkup = fn (float $amount): ?float => $amount > 0
                ? round($amount + ($amount * $markupPercent / 100), 2)
                : null;

            return [
                ...$result,
                'registration_price' => $withMarkup($rawRegistration),
                'renewal_price' => $withMarkup($rawRenewal),
                'transfer_price' => $withMarkup($rawTransfer),
                'currency' => data_get($tldPricing, "{$tld}.currency", 'USD'),
                'markup_percent' => $markupPercent,
                'pricing_source' => $isPremium ? 'domains.check.premium' : 'users.getPricing',
            ];
        }, $results);
    }

    /**
     * @param  array<string, array<string, mixed>>  $contacts
     */
    public function purchaseDomain(
        Website $website,
        string $domain,
        int $years,
        array $contacts,
        ?int $actorUserId = null,
    ): WebsiteDomain {
        $normalizedDomain = DomainName::normalize($domain);
        if ($normalizedDomain === '') {
            throw new RuntimeException('Invalid domain name.');
        }

        $years = max(1, min(10, $years));

        $existing = WebsiteDomain::withoutGlobalScopes()
            ->where('normalized_domain', $normalizedDomain)
            ->first();

        if ($existing && (int) $existing->website_id !== (int) $website->id) {
            throw new RuntimeException('Domain is already connected to another website.');
        }

        if ($existing && in_array($existing->status, ['registered', 'dns_pending', 'dns_configured', 'verifying', 'verified', 'ssl_pending', 'active'], true)) {
            RegisterDomainJob::dispatch($existing->id, $years, $contacts, $actorUserId);

            return $existing->refresh();
        }

        $websiteDomain = DB::transaction(function () use ($website, $domain, $normalizedDomain, $existing): WebsiteDomain {
            $model = $existing ?: new WebsiteDomain();
            if ($existing && method_exists($existing, 'trashed') && $existing->trashed()) {
                $existing->restore();
            }
            $model->workspace_id = $website->workspace_id;
            $model->website_id = $website->id;
            $model->domain = $domain;
            $model->normalized_domain = $normalizedDomain;
            $model->type = 'custom_domain';
            $model->provider = 'namecheap';
            $model->status = 'registering';
            $model->verification_status = 'unverified';
            $model->dns_status = 'pending';
            $model->ssl_status = 'not_requested';
            $model->save();

            return $model->refresh();
        });

        RegisterDomainJob::dispatch($websiteDomain->id, $years, $contacts, $actorUserId);
        $this->websiteResolverService->invalidateForWebsite($website);

        return $websiteDomain->refresh();
    }

    /**
     * @param  array<string, array<string, mixed>>  $contacts
     */
    public function executeDomainRegistration(WebsiteDomain $websiteDomain, int $years, array $contacts, ?int $actorUserId = null): WebsiteDomain
    {
        $years = max(1, min(10, $years));
        $idempotencyKey = 'register:'.$websiteDomain->normalized_domain.':'.$years;

        $operation = WebsiteDomainOperation::withoutGlobalScopes()->firstOrCreate(
            [
                'provider' => 'namecheap',
                'idempotency_key' => $idempotencyKey,
            ],
            [
                'workspace_id' => $websiteDomain->workspace_id,
                'website_id' => $websiteDomain->website_id,
                'website_domain_id' => $websiteDomain->id,
                'operation_type' => 'purchase',
                'status' => 'processing',
                'request_payload' => [
                    'domain' => $websiteDomain->domain,
                    'years' => $years,
                ],
            ]
        );

        if ($operation->status === 'succeeded' && filled($websiteDomain->provider_domain_id)) {
            return $websiteDomain;
        }

        if ($operation->status === 'processing' && $operation->wasRecentlyCreated === false) {
            $reconciled = $this->reconcileExistingRegistration($websiteDomain, $operation, $years, $contacts, $actorUserId);
            if ($reconciled !== null) {
                return $reconciled;
            }

            // Another worker is actively processing; avoid duplicate provider create.
            if ($operation->updated_at && $operation->updated_at->gt(now()->subMinutes(5))) {
                return $websiteDomain->refresh();
            }
        }

        if (in_array($operation->status, ['failed', 'recovery_required'], true)) {
            $operation->update([
                'status' => 'processing',
                'error_message' => null,
                'processed_at' => null,
            ]);
        }

        $websiteDomain->update(['status' => 'registering']);

        try {
            $check = $this->registrar->checkAvailability([$websiteDomain->domain]);
            $first = $check[0] ?? null;
            $available = ($first['available'] ?? false) === true;

            if (! $available) {
                $reconciled = $this->reconcileExistingRegistration($websiteDomain, $operation, $years, $contacts, $actorUserId);
                if ($reconciled !== null) {
                    return $reconciled;
                }

                throw new RuntimeException('Domain is no longer available for registration.');
            }

            $registration = $this->registrar->register($websiteDomain->domain, $years, $contacts);
            $this->persistSuccessfulRegistration($websiteDomain, $operation, $registration, $contacts, $years);
            ConfigureDomainDnsJob::dispatch($websiteDomain->id, $actorUserId);
        } catch (\Throwable $exception) {
            // Timeout / DB failure after provider success: try reconcile before marking failed.
            $reconciled = $this->reconcileExistingRegistration($websiteDomain, $operation, $years, $contacts, $actorUserId);
            if ($reconciled !== null) {
                return $reconciled;
            }

            $operation->update([
                'status' => 'recovery_required',
                'error_message' => $exception->getMessage(),
                'processed_at' => now(),
            ]);

            $websiteDomain->update([
                'status' => 'recovery_required',
                'verification_status' => 'failed',
                'metadata' => array_merge(
                    is_array($websiteDomain->metadata) ? $websiteDomain->metadata : [],
                    [
                        'last_error' => $exception->getMessage(),
                        'recovery_required_at' => now()->toIso8601String(),
                    ]
                ),
            ]);

            throw $exception;
        }

        return $websiteDomain->refresh();
    }

    public function recoverDomainRegistration(WebsiteDomain $websiteDomain, int $years = 1, array $contacts = [], ?int $actorUserId = null): WebsiteDomain
    {
        $operation = WebsiteDomainOperation::withoutGlobalScopes()
            ->where('provider', 'namecheap')
            ->where('idempotency_key', 'register:'.$websiteDomain->normalized_domain.':'.$years)
            ->first();

        if (! $operation) {
            $operation = WebsiteDomainOperation::withoutGlobalScopes()->create([
                'workspace_id' => $websiteDomain->workspace_id,
                'website_id' => $websiteDomain->website_id,
                'website_domain_id' => $websiteDomain->id,
                'operation_type' => 'recover_purchase',
                'provider' => 'namecheap',
                'status' => 'processing',
                'idempotency_key' => 'recover:'.$websiteDomain->normalized_domain.':'.$years,
                'request_payload' => ['domain' => $websiteDomain->domain, 'years' => $years],
            ]);
        }

        $reconciled = $this->reconcileExistingRegistration($websiteDomain, $operation, $years, $contacts, $actorUserId);
        if ($reconciled === null) {
            throw new RuntimeException('Unable to confirm provider registration for recovery.');
        }

        return $reconciled;
    }

    public function configureDns(WebsiteDomain $websiteDomain, ?int $actorUserId = null): WebsiteDomain
    {
        $websiteDomain->update([
            'status' => 'dns_pending',
            'dns_status' => 'pending',
            'metadata' => array_merge(
                is_array($websiteDomain->metadata) ? $websiteDomain->metadata : [],
                ['verification_attempts' => 0]
            ),
        ]);

        VerifyDomainJob::dispatch($websiteDomain->id, $actorUserId)->delay(now()->addSeconds(5));

        return $websiteDomain;
    }

    public function setPrimaryDomain(WebsiteDomain $domain): WebsiteDomain
    {
        DB::transaction(function () use ($domain): void {
            WebsiteDomain::withoutGlobalScopes()
                ->where('website_id', $domain->website_id)
                ->update(['is_primary' => false]);

            $domain->update(['is_primary' => true]);

            $website = $domain->website()->withoutGlobalScopes()->firstOrFail();
            $website->update(['primary_domain_id' => $domain->id]);
            $this->websiteResolverService->invalidateForWebsite($website);
        });

        return $domain->refresh();
    }

    public function setAutoRenew(WebsiteDomain $websiteDomain, bool $enabled): WebsiteDomain
    {
        $websiteDomain->update(['auto_renew' => $enabled]);

        $website = $websiteDomain->website()->withoutGlobalScopes()->first();
        if ($website) {
            $this->websiteResolverService->invalidateForWebsite($website);
        }

        return $websiteDomain->refresh();
    }

    public function verifyDomain(WebsiteDomain $websiteDomain): WebsiteDomain
    {
        $previousStatus = $websiteDomain->status;
        $previousVerification = $websiteDomain->verification_status;

        $operation = WebsiteDomainOperation::withoutGlobalScopes()->create([
            'workspace_id' => $websiteDomain->workspace_id,
            'website_id' => $websiteDomain->website_id,
            'website_domain_id' => $websiteDomain->id,
            'operation_type' => 'verify',
            'provider' => 'namecheap',
            'status' => 'processing',
            'idempotency_key' => 'verify:'.$websiteDomain->normalized_domain.':'.now()->format('YmdHi').':'.substr(md5((string) microtime(true)), 0, 6),
            'request_payload' => [
                'domain' => $websiteDomain->domain,
                'target' => config('website.dns_target'),
                'target_type' => config('website.dns_target_type', 'A'),
            ],
        ]);

        $websiteDomain->update([
            'status' => 'verifying',
            'verification_status' => 'verifying',
        ]);

        try {
            $dns = $this->registrar->getDnsRecords($websiteDomain->domain);
            $target = trim((string) config('website.dns_target'));
            $targetType = strtoupper((string) config('website.dns_target_type', 'A'));
            $wwwTarget = (string) config('website.dns_www_target', '@');

            $verifiedApex = collect($dns['records'] ?? [])->contains(function ($record) use ($target, $targetType): bool {
                if (! is_array($record)) {
                    return false;
                }

                return strtolower((string) ($record['name'] ?? '')) === '@'
                    && strtoupper((string) ($record['type'] ?? '')) === $targetType
                    && (string) ($record['address'] ?? '') === $target;
            });

            $verifiedWww = collect($dns['records'] ?? [])->contains(function ($record) use ($wwwTarget): bool {
                if (! is_array($record)) {
                    return false;
                }

                return strtolower((string) ($record['name'] ?? '')) === 'www'
                    && strtoupper((string) ($record['type'] ?? '')) === 'CNAME'
                    && (string) ($record['address'] ?? '') === $wwwTarget;
            });

            $verified = $verifiedApex && $verifiedWww;

            $metadata = is_array($websiteDomain->metadata) ? $websiteDomain->metadata : [];
            $attempts = max(0, (int) ($metadata['verification_attempts'] ?? 0)) + 1;
            $metadata['verification_attempts'] = $attempts;

            if ($verified) {
                $websiteDomain->update([
                    'status' => 'verified',
                    'verification_status' => 'verified',
                    'dns_status' => 'configured',
                    'metadata' => $metadata,
                ]);
                $operation->update([
                    'status' => 'succeeded',
                    'response_payload' => [
                        'records' => $dns['records'] ?? [],
                        'verified' => true,
                        'apex' => $verifiedApex,
                        'www' => $verifiedWww,
                        'attempt' => $attempts,
                    ],
                    'processed_at' => now(),
                ]);
                ProvisionSslJob::dispatch($websiteDomain->id);
            } else {
                $maxAttempts = max(1, (int) config('website.domain_verification_max_attempts', 12));
                if ($attempts >= $maxAttempts) {
                    $websiteDomain->update([
                        'status' => 'failed',
                        'verification_status' => 'failed',
                        'dns_status' => 'failed',
                        'metadata' => array_merge($metadata, [
                            'last_error' => 'DNS verification attempts exceeded limit.',
                        ]),
                    ]);
                    $operation->update([
                        'status' => 'failed',
                        'error_message' => 'DNS verification attempts exceeded limit.',
                        'response_payload' => ['records' => $dns['records'] ?? [], 'verified' => false, 'attempt' => $attempts],
                        'processed_at' => now(),
                    ]);
                } else {
                    $websiteDomain->update([
                        'status' => 'dns_pending',
                        'verification_status' => 'verifying',
                        'dns_status' => 'pending',
                        'metadata' => $metadata,
                    ]);
                    $operation->update([
                        'status' => 'succeeded',
                        'response_payload' => [
                            'records' => $dns['records'] ?? [],
                            'verified' => false,
                            'apex' => $verifiedApex,
                            'www' => $verifiedWww,
                            'attempt' => $attempts,
                        ],
                        'processed_at' => now(),
                    ]);

                    $retryDelay = max(30, (int) config('website.domain_verification_retry_seconds', 600));
                    VerifyDomainJob::dispatch($websiteDomain->id)->delay(now()->addSeconds($retryDelay));
                }
            }
        } catch (\Throwable $exception) {
            // Do not permanently destroy a previously verified local state on provider timeout.
            $safeStatus = in_array($previousStatus, ['verified', 'ssl_pending', 'active'], true)
                ? $previousStatus
                : 'dns_pending';

            $websiteDomain->update([
                'status' => $safeStatus,
                'verification_status' => in_array($safeStatus, ['verified', 'active', 'ssl_pending'], true)
                    ? 'verified'
                    : ($previousVerification ?: 'verifying'),
                'metadata' => array_merge(
                    is_array($websiteDomain->metadata) ? $websiteDomain->metadata : [],
                    ['last_verify_error' => $exception->getMessage()]
                ),
            ]);
            $operation->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'processed_at' => now(),
            ]);

            throw $exception;
        }

        $website = $websiteDomain->website()->withoutGlobalScopes()->first();
        if ($website) {
            $this->websiteResolverService->invalidateForWebsite($website);
        }

        return $websiteDomain->refresh();
    }

    public function renewDomain(WebsiteDomain $websiteDomain, int $years = 1): WebsiteDomain
    {
        RenewDomainJob::dispatch($websiteDomain->id, max(1, min(10, $years)), false);

        return $websiteDomain;
    }

    public function executeRenewDomain(WebsiteDomain $websiteDomain, int $years = 1, bool $isAuto = false): WebsiteDomain
    {
        $years = max(1, min(10, $years));
        $periodKey = optional($websiteDomain->expires_at)?->format('Ymd') ?: 'unknown';
        $idempotencyKey = ($isAuto ? 'auto-renew:' : 'renew:').$websiteDomain->normalized_domain.':'.$years.':'.$periodKey;

        $existing = WebsiteDomainOperation::withoutGlobalScopes()
            ->where('provider', 'namecheap')
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing && $existing->status === 'succeeded') {
            return $websiteDomain->refresh();
        }

        $operation = $existing ?: WebsiteDomainOperation::withoutGlobalScopes()->create([
            'workspace_id' => $websiteDomain->workspace_id,
            'website_id' => $websiteDomain->website_id,
            'website_domain_id' => $websiteDomain->id,
            'operation_type' => $isAuto ? 'auto_renew' : 'renew',
            'provider' => 'namecheap',
            'status' => 'processing',
            'idempotency_key' => $idempotencyKey,
            'request_payload' => ['years' => $years, 'auto' => $isAuto],
        ]);

        try {
            $result = $this->registrar->renew($websiteDomain->domain, $years);
            if (($result['renewed'] ?? false) !== true && empty($result['order_id']) && empty($result['transaction_id'])) {
                throw new RuntimeException('Namecheap renew did not confirm success.');
            }

            $info = $this->registrar->getInfo($websiteDomain->domain);
            $expiresAt = $this->extractExpiryDate($info);

            $websiteDomain->update([
                'status' => 'active',
                'verification_status' => 'verified',
                'expires_at' => $expiresAt,
                'provider_domain_id' => $result['provider_domain_id'] ?: $websiteDomain->provider_domain_id,
                'provider_order_id' => $result['order_id'] ?: $websiteDomain->provider_order_id,
                'provider_transaction_id' => $result['transaction_id'] ?: $websiteDomain->provider_transaction_id,
                'metadata' => array_merge(
                    is_array($websiteDomain->metadata) ? $websiteDomain->metadata : [],
                    [
                        'renewal' => $result,
                        'last_renewed_at' => now()->toIso8601String(),
                        'last_renew_auto' => $isAuto,
                    ]
                ),
            ]);

            $operation->update([
                'status' => 'succeeded',
                'response_payload' => $result,
                'processed_at' => now(),
                'error_message' => null,
            ]);
        } catch (\Throwable $exception) {
            $operation->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'processed_at' => now(),
            ]);

            $websiteDomain->update([
                'metadata' => array_merge(
                    is_array($websiteDomain->metadata) ? $websiteDomain->metadata : [],
                    ['last_renew_error' => $exception->getMessage()]
                ),
            ]);

            throw $exception;
        }

        $website = $websiteDomain->website()->withoutGlobalScopes()->first();
        if ($website) {
            $this->websiteResolverService->invalidateForWebsite($website);
        }

        return $websiteDomain->refresh();
    }

    public function syncDomainStatus(WebsiteDomain $websiteDomain): WebsiteDomain
    {
        SyncDomainStatusJob::dispatch($websiteDomain->id);

        return $websiteDomain;
    }

    public function cancelDomain(WebsiteDomain $websiteDomain): WebsiteDomain
    {
        $website = $websiteDomain->website()->withoutGlobalScopes()->first();

        $websiteDomain->update([
            'status' => 'cancelled',
            'is_primary' => false,
        ]);
        $websiteDomain->delete();

        if ($website) {
            $this->websiteResolverService->invalidateForWebsite($website);
        }

        return $websiteDomain;
    }

    public function executeSyncDomainStatus(WebsiteDomain $websiteDomain): WebsiteDomain
    {
        try {
            $info = $this->registrar->getInfo($websiteDomain->domain);
            $expiresAt = $this->extractExpiryDate($info);
            $providerStatus = strtolower((string) ($info['status'] ?? 'unknown'));

            $list = $this->registrar->getDomains([
                'SearchTerm' => $websiteDomain->normalized_domain,
                'PageSize' => '20',
            ]);
            $matched = collect($list['domains'] ?? [])->first(
                fn ($row) => is_array($row) && strtolower((string) ($row['name'] ?? '')) === $websiteDomain->normalized_domain
            );

            $localStatus = $websiteDomain->status;
            if ($providerStatus === 'expired' || (($matched['is_expired'] ?? false) === true)) {
                $localStatus = 'expired';
            } elseif ($websiteDomain->status === 'recovery_required' && filled($info['provider_domain_id'] ?? null)) {
                $localStatus = 'registered';
            }

            $websiteDomain->update([
                'expires_at' => $expiresAt ?: $websiteDomain->expires_at,
                'provider_domain_id' => ($info['provider_domain_id'] ?? null) ?: $websiteDomain->provider_domain_id,
                'auto_renew' => is_array($matched) && array_key_exists('auto_renew', $matched)
                    ? (bool) $matched['auto_renew']
                    : $websiteDomain->auto_renew,
                'status' => $localStatus,
                'metadata' => array_merge(
                    is_array($websiteDomain->metadata) ? $websiteDomain->metadata : [],
                    [
                        'provider_info' => $info,
                        'provider_list_match' => $matched,
                        'synced_at' => now()->toIso8601String(),
                    ]
                ),
            ]);

            if (in_array($websiteDomain->ssl_status, ['pending', 'active', 'failed', 'expired'], true)) {
                $this->sslService->syncFromFilesystem($websiteDomain->fresh());
            }

            WebsiteDomainOperation::withoutGlobalScopes()->updateOrCreate(
                [
                    'provider' => 'namecheap',
                    'idempotency_key' => 'sync:'.$websiteDomain->normalized_domain.':'.now()->format('YmdH'),
                ],
                [
                    'workspace_id' => $websiteDomain->workspace_id,
                    'website_id' => $websiteDomain->website_id,
                    'website_domain_id' => $websiteDomain->id,
                    'operation_type' => 'sync_status',
                    'status' => 'succeeded',
                    'response_payload' => ['info' => $info, 'list_match' => $matched],
                    'processed_at' => now(),
                    'error_message' => null,
                ]
            );
        } catch (\Throwable $exception) {
            Log::warning('domain.sync_failed', [
                'domain' => $websiteDomain->normalized_domain,
                'error' => $exception->getMessage(),
            ]);

            WebsiteDomainOperation::withoutGlobalScopes()->create([
                'workspace_id' => $websiteDomain->workspace_id,
                'website_id' => $websiteDomain->website_id,
                'website_domain_id' => $websiteDomain->id,
                'operation_type' => 'sync_status',
                'provider' => 'namecheap',
                'status' => 'failed',
                'idempotency_key' => 'sync-fail:'.$websiteDomain->normalized_domain.':'.now()->format('YmdHi').':'.Str::random(4),
                'error_message' => $exception->getMessage(),
                'processed_at' => now(),
            ]);

            // Preserve local state on provider timeout.
            throw $exception;
        }

        $website = $websiteDomain->website()->withoutGlobalScopes()->first();
        if ($website) {
            $this->websiteResolverService->invalidateForWebsite($website);
        }

        return $websiteDomain->refresh();
    }

    public function processDueAutoRenewals(): int
    {
        $daysBefore = max(1, (int) config('website.domain_auto_renew_days_before', 14));
        $cutoff = now()->addDays($daysBefore);
        $count = 0;

        WebsiteDomain::withoutGlobalScopes()
            ->where('auto_renew', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $cutoff)
            ->where('expires_at', '>', now())
            ->whereIn('status', ['active', 'verified', 'ssl_pending', 'registered', 'dns_configured'])
            ->where('type', 'custom_domain')
            ->chunkById(50, function ($domains) use (&$count): void {
                foreach ($domains as $domain) {
                    RenewDomainJob::dispatch($domain->id, 1, true);
                    $count++;
                }
            });

        return $count;
    }

    public function processExpirationReminders(): int
    {
        $intervals = (array) config('website.domain_expiration_reminder_days', [30, 14, 7, 3, 1]);
        $sent = 0;

        WebsiteDomain::withoutGlobalScopes()
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now())
            ->whereIn('status', ['active', 'verified', 'ssl_pending', 'registered', 'dns_configured', 'dns_pending'])
            ->chunkById(100, function ($domains) use ($intervals, &$sent): void {
                foreach ($domains as $domain) {
                    foreach ($intervals as $daysBefore) {
                        $daysBefore = (int) $daysBefore;
                        if ($daysBefore <= 0 || ! $domain->expires_at) {
                            continue;
                        }

                        $targetDate = $domain->expires_at->copy()->subDays($daysBefore)->startOfDay();
                        if (! now()->betweenIncluded($targetDate, $targetDate->copy()->endOfDay())) {
                            // Allow a 24h window around the reminder day.
                            $diff = (int) now()->startOfDay()->diffInDays($domain->expires_at->copy()->startOfDay(), false);
                            if ($diff !== $daysBefore) {
                                continue;
                            }
                        }

                        $key = 'exp-reminder:'.$domain->id.':'.$daysBefore.':'.$domain->expires_at->format('Ymd');
                        if (WebsiteDomainReminderLog::withoutGlobalScopes()->where('idempotency_key', $key)->exists()) {
                            continue;
                        }

                        $this->notifyDomainExpiration($domain, $daysBefore);

                        WebsiteDomainReminderLog::withoutGlobalScopes()->create([
                            'workspace_id' => $domain->workspace_id,
                            'website_domain_id' => $domain->id,
                            'days_before' => $daysBefore,
                            'channel' => 'in_app',
                            'idempotency_key' => $key,
                            'sent_at' => now(),
                        ]);

                        WebsiteDomainOperation::withoutGlobalScopes()->create([
                            'workspace_id' => $domain->workspace_id,
                            'website_id' => $domain->website_id,
                            'website_domain_id' => $domain->id,
                            'operation_type' => 'expiration_reminder',
                            'provider' => 'system',
                            'status' => 'succeeded',
                            'idempotency_key' => $key,
                            'request_payload' => ['days_before' => $daysBefore],
                            'processed_at' => now(),
                        ]);

                        $sent++;
                    }
                }
            });

        return $sent;
    }

    public function processSslMaintenance(): int
    {
        $count = 0;
        $renewBefore = max(1, (int) config('website.ssl.renew_days_before', 30));

        WebsiteDomain::withoutGlobalScopes()
            ->whereIn('ssl_status', ['pending', 'active', 'failed', 'expired'])
            ->whereIn('status', ['verified', 'ssl_pending', 'active'])
            ->chunkById(50, function ($domains) use (&$count, $renewBefore): void {
                foreach ($domains as $domain) {
                    if ($domain->ssl_status === 'pending' || $domain->ssl_status === 'failed') {
                        ProvisionSslJob::dispatch($domain->id);
                        $count++;
                        continue;
                    }

                    if (
                        in_array($domain->ssl_status, ['active', 'expired'], true)
                        && $domain->ssl_expires_at
                        && $domain->ssl_expires_at->lte(now()->addDays($renewBefore))
                    ) {
                        // Prefer renewAndSync for nearing-expiry certs; falls back safely if certbot missing.
                        $this->sslService->renewAndSync($domain);
                        $count++;
                    } else {
                        $this->sslService->syncFromFilesystem($domain);
                        $count++;
                    }
                }
            });

        return $count;
    }

    /**
     * @param  array<int, string>  $tlds
     * @return array<string, array<string, mixed>>
     */
    private function cachedTldPricing(array $tlds): array
    {
        $cacheSeconds = max(60, (int) config('website.domain_pricing_cache_seconds', 21600));
        $cacheKey = 'namecheap:tld-pricing:'.md5(implode(',', $tlds));

        return Cache::remember($cacheKey, $cacheSeconds, function () use ($tlds): array {
            try {
                return $this->registrar->getTldPricing($tlds, 1);
            } catch (\Throwable $exception) {
                Log::warning('namecheap.pricing_unavailable', ['error' => $exception->getMessage()]);

                return [];
            }
        });
    }

    /**
     * @param  array<string, mixed>  $registration
     * @param  array<string, array<string, mixed>>  $contacts
     */
    private function persistSuccessfulRegistration(
        WebsiteDomain $websiteDomain,
        WebsiteDomainOperation $operation,
        array $registration,
        array $contacts,
        int $years,
    ): void {
        DB::transaction(function () use ($websiteDomain, $registration, $contacts, $operation, $years): void {
            $websiteDomain->update([
                'provider_domain_id' => $registration['provider_domain_id'] ?: $websiteDomain->provider_domain_id,
                'provider_order_id' => $registration['order_id'] ?: $websiteDomain->provider_order_id,
                'provider_transaction_id' => $registration['transaction_id'] ?: $websiteDomain->provider_transaction_id,
                'status' => 'registered',
                'verification_status' => 'verifying',
                'dns_status' => 'pending',
                'metadata' => array_merge(
                    is_array($websiteDomain->metadata) ? $websiteDomain->metadata : [],
                    [
                        'registration' => $registration,
                        'registration_years' => $years,
                        'verification_attempts' => 0,
                    ]
                ),
            ]);

            foreach (['registrant', 'admin', 'tech', 'aux_billing'] as $contactType) {
                if (! is_array($contacts[$contactType] ?? null)) {
                    continue;
                }
                WebsiteDomainContact::withoutGlobalScopes()->updateOrCreate(
                    [
                        'workspace_id' => $websiteDomain->workspace_id,
                        'website_domain_id' => $websiteDomain->id,
                        'contact_type' => $contactType,
                    ],
                    [
                        'workspace_id' => $websiteDomain->workspace_id,
                        'website_domain_id' => $websiteDomain->id,
                        'contact_type' => $contactType,
                        'organization_name' => trim((string) ($contacts[$contactType]['organization_name'] ?? '')) ?: null,
                        'job_title' => trim((string) ($contacts[$contactType]['job_title'] ?? '')) ?: null,
                        'first_name' => (string) ($contacts[$contactType]['first_name'] ?? ''),
                        'last_name' => (string) ($contacts[$contactType]['last_name'] ?? ''),
                        'address1' => (string) ($contacts[$contactType]['address1'] ?? ''),
                        'address2' => trim((string) ($contacts[$contactType]['address2'] ?? '')) ?: null,
                        'city' => (string) ($contacts[$contactType]['city'] ?? ''),
                        'state_province' => (string) ($contacts[$contactType]['state_province'] ?? ''),
                        'postal_code' => (string) ($contacts[$contactType]['postal_code'] ?? ''),
                        'country' => strtoupper((string) ($contacts[$contactType]['country'] ?? 'US')),
                        'phone' => (string) ($contacts[$contactType]['phone'] ?? ''),
                        'email' => (string) ($contacts[$contactType]['email'] ?? ''),
                        'metadata' => null,
                    ]
                );
            }

            $operation->update([
                'status' => 'succeeded',
                'operation_type' => $operation->operation_type === 'recover_purchase' ? 'recover_purchase' : 'purchase',
                'response_payload' => $registration,
                'processed_at' => now(),
                'error_message' => null,
            ]);
        });

        $website = $websiteDomain->website()->withoutGlobalScopes()->first();
        if ($website) {
            $this->websiteResolverService->invalidateForWebsite($website);
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $contacts
     */
    private function reconcileExistingRegistration(
        WebsiteDomain $websiteDomain,
        WebsiteDomainOperation $operation,
        int $years,
        array $contacts,
        ?int $actorUserId = null,
    ): ?WebsiteDomain {
        try {
            $info = $this->registrar->getInfo($websiteDomain->domain);
        } catch (\Throwable) {
            return null;
        }

        $providerDomainId = (string) ($info['provider_domain_id'] ?? '');
        $isOwner = (bool) ($info['is_owner'] ?? false);
        if ($providerDomainId === '' && ! $isOwner) {
            return null;
        }

        $registration = [
            'domain' => $websiteDomain->normalized_domain,
            'registered' => true,
            'charged_amount' => null,
            'provider_domain_id' => $providerDomainId,
            'order_id' => (string) data_get($websiteDomain->metadata, 'registration.order_id', ''),
            'transaction_id' => (string) data_get($websiteDomain->metadata, 'registration.transaction_id', ''),
            'provider_payload' => $info,
            'reconciled' => true,
        ];

        $this->persistSuccessfulRegistration($websiteDomain, $operation, $registration, $contacts, $years);

        if (! in_array($websiteDomain->fresh()->dns_status, ['configured'], true)) {
            ConfigureDomainDnsJob::dispatch($websiteDomain->id, $actorUserId);
        }

        return $websiteDomain->refresh();
    }

    private function notifyDomainExpiration(WebsiteDomain $domain, int $daysBefore): void
    {
        $website = $domain->website()->withoutGlobalScopes()->first();
        $workspace = $website
            ? Workspace::withoutGlobalScopes()->find($website->workspace_id)
            : null;
        $ownerId = $workspace?->owner_user_id;
        if (! $ownerId) {
            return;
        }

        $owner = User::query()->find($ownerId);
        if (! $owner) {
            return;
        }

        Notification::send($owner, new DomainExpirationReminderNotification($domain, $daysBefore));
    }

    private function extractExpiryDate(array $info): ?Carbon
    {
        $raw = data_get($info, 'raw.DomainDetails.ExpiredDate')
            ?: data_get($info, 'raw.DomainDetails.ExpirationDate');
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }
}
