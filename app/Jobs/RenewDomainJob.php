<?php

namespace App\Jobs;

use App\Models\Website\WebsiteDomain;
use App\Services\Domain\DomainService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class RenewDomainJob implements ShouldQueue, ShouldBeUnique
{
    use InteractsWithQueue, Queueable;

    public int $tries = 5;
    public int $timeout = 180;
    public int $uniqueFor = 600;

    /** @var array<int, int> */
    public array $backoff = [30, 60, 120, 300];

    public function __construct(
        public readonly int $websiteDomainId,
        public readonly int $years = 1,
        public readonly bool $isAuto = false,
    ) {}

    public function uniqueId(): string
    {
        return 'renew-domain:'.$this->websiteDomainId.':'.$this->years.':'.($this->isAuto ? 'auto' : 'manual');
    }

    public function handle(DomainService $domainService): void
    {
        $domain = WebsiteDomain::withoutGlobalScopes()->find($this->websiteDomainId);
        if (! $domain) {
            return;
        }

        if ($this->isAuto && ! $domain->auto_renew) {
            return;
        }

        $domainService->executeRenewDomain($domain, $this->years, $this->isAuto);
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('job.renew_domain_failed', [
            'website_domain_id' => $this->websiteDomainId,
            'auto' => $this->isAuto,
            'error' => $exception?->getMessage(),
        ]);
    }
}
