<?php

namespace App\Jobs;

use App\Models\Website\WebsiteDomain;
use App\Models\Website\WebsiteDomainOperation;
use App\Services\Domain\DnsService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class ConfigureDomainDnsJob implements ShouldQueue, ShouldBeUnique
{
    use InteractsWithQueue, Queueable;

    public int $tries = 5;
    public int $timeout = 180;
    public int $uniqueFor = 300;

    /** @var array<int, int> */
    public array $backoff = [20, 60, 120, 240];

    public function __construct(
        public readonly int $websiteDomainId,
        public readonly ?int $actorUserId = null,
    ) {}

    public function uniqueId(): string
    {
        return 'configure-dns:'.$this->websiteDomainId;
    }

    public function handle(DnsService $dnsService): void
    {
        $domain = WebsiteDomain::withoutGlobalScopes()->find($this->websiteDomainId);
        if (! $domain) {
            return;
        }

        $idempotencyKey = 'dns:'.$domain->normalized_domain.':configure';
        $operation = WebsiteDomainOperation::withoutGlobalScopes()->firstOrCreate(
            [
                'provider' => 'namecheap',
                'idempotency_key' => $idempotencyKey,
            ],
            [
                'workspace_id' => $domain->workspace_id,
                'website_id' => $domain->website_id,
                'website_domain_id' => $domain->id,
                'operation_type' => 'configure_dns',
                'status' => 'processing',
                'request_payload' => [
                    'domain' => $domain->domain,
                    'target' => config('website.dns_target'),
                    'target_type' => config('website.dns_target_type'),
                    'www_target' => config('website.dns_www_target'),
                ],
            ]
        );

        if ($operation->status === 'succeeded' && $domain->dns_status === 'configured') {
            VerifyDomainJob::dispatch($domain->id, $this->actorUserId)->delay(now()->addSeconds(5));

            return;
        }

        $domain->update([
            'status' => 'dns_pending',
            'dns_status' => 'pending',
        ]);

        $operation->update(['status' => 'processing', 'error_message' => null]);

        try {
            $result = $dnsService->configureWebsiteDns($domain);

            $domain->update([
                'status' => 'dns_configured',
                'dns_status' => ($result['verified'] ?? false) ? 'configured' : 'pending',
                'metadata' => array_merge(
                    is_array($domain->metadata) ? $domain->metadata : [],
                    ['dns' => $result]
                ),
            ]);

            $operation->update([
                'status' => 'succeeded',
                'response_payload' => $result,
                'processed_at' => now(),
            ]);

            VerifyDomainJob::dispatch($domain->id, $this->actorUserId)->delay(now()->addSeconds(5));
        } catch (\Throwable $exception) {
            $domain->update([
                'dns_status' => 'failed',
                'metadata' => array_merge(
                    is_array($domain->metadata) ? $domain->metadata : [],
                    ['dns_error' => $exception->getMessage()]
                ),
            ]);

            $operation->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'processed_at' => now(),
            ]);

            throw $exception;
        }
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('job.configure_dns_failed', [
            'website_domain_id' => $this->websiteDomainId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
