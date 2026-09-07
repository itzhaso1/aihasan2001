<?php

namespace App\Jobs;

use App\Models\Website\WebsiteDomain;
use App\Models\Website\WebsiteDomainOperation;
use App\Services\Domain\SslService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class ProvisionSslJob implements ShouldQueue, ShouldBeUnique
{
    use InteractsWithQueue, Queueable;

    public int $tries = 5;
    public int $timeout = 240;
    public int $uniqueFor = 300;

    /** @var array<int, int> */
    public array $backoff = [30, 60, 120, 300];

    public function __construct(public readonly int $websiteDomainId) {}

    public function uniqueId(): string
    {
        return 'provision-ssl:'.$this->websiteDomainId;
    }

    public function handle(SslService $sslService): void
    {
        $domain = WebsiteDomain::withoutGlobalScopes()->find($this->websiteDomainId);
        if (! $domain) {
            return;
        }

        $idempotencyKey = 'ssl:'.$domain->normalized_domain.':'.($domain->ssl_expires_at?->format('Ymd') ?: 'bootstrap');

        $operation = WebsiteDomainOperation::withoutGlobalScopes()->firstOrCreate(
            [
                'provider' => 'ssl',
                'idempotency_key' => $idempotencyKey,
            ],
            [
                'workspace_id' => $domain->workspace_id,
                'website_id' => $domain->website_id,
                'website_domain_id' => $domain->id,
                'operation_type' => 'provision_ssl',
                'status' => 'processing',
                'request_payload' => ['domain' => $domain->normalized_domain],
            ]
        );

        if ($operation->status === 'succeeded' && $domain->ssl_status === 'active') {
            return;
        }

        try {
            $result = $sslService->requestProvisioning($domain);
            $verified = (bool) ($result['certificate_verified'] ?? false);

            $operation->update([
                'status' => $verified ? 'succeeded' : (($result['status'] ?? '') === 'pending' ? 'succeeded' : 'failed'),
                'response_payload' => $result,
                'error_message' => $verified || ($result['status'] ?? '') === 'pending' ? null : (string) ($result['message'] ?? 'SSL not verified'),
                'processed_at' => now(),
            ]);

            // Pending (SSL disabled / waiting for infra) is not a hard failure and should not retry forever.
            if (! $verified && ($result['status'] ?? '') !== 'pending') {
                throw new \RuntimeException((string) ($result['message'] ?? 'SSL certificate was not verified.'));
            }
        } catch (\Throwable $exception) {
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
        Log::error('job.provision_ssl_failed', [
            'website_domain_id' => $this->websiteDomainId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
