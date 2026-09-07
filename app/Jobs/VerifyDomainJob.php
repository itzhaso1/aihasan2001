<?php

namespace App\Jobs;

use App\Models\Website\WebsiteDomain;
use App\Services\Domain\DomainService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class VerifyDomainJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public int $tries = 8;
    public int $timeout = 120;

    /** @var array<int, int> */
    public array $backoff = [30, 60, 120, 300];

    public function __construct(
        public readonly int $websiteDomainId,
        public readonly ?int $actorUserId = null,
    ) {}

    public function handle(DomainService $domainService): void
    {
        $domain = WebsiteDomain::withoutGlobalScopes()->find($this->websiteDomainId);
        if (! $domain) {
            return;
        }

        $domainService->verifyDomain($domain);
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('job.verify_domain_failed', [
            'website_domain_id' => $this->websiteDomainId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
