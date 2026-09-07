<?php

namespace App\Jobs;

use App\Models\Website\WebsiteDomain;
use App\Services\Domain\DomainService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class RegisterDomainJob implements ShouldQueue, ShouldBeUnique
{
    use InteractsWithQueue, Queueable;

    public int $tries = 5;
    public int $timeout = 180;
    public int $uniqueFor = 600;

    /** @var array<int, int> */
    public array $backoff = [15, 45, 90, 180];

    /**
     * @param  array<string, array<string, mixed>>  $contacts
     */
    public function __construct(
        public readonly int $websiteDomainId,
        public readonly int $years,
        public readonly array $contacts,
        public readonly ?int $actorUserId = null,
    ) {}

    public function uniqueId(): string
    {
        return 'register-domain:'.$this->websiteDomainId.':'.$this->years;
    }

    public function handle(DomainService $domainService): void
    {
        $domain = WebsiteDomain::withoutGlobalScopes()->find($this->websiteDomainId);
        if (! $domain) {
            return;
        }

        $domainService->executeDomainRegistration(
            websiteDomain: $domain,
            years: $this->years,
            contacts: $this->contacts,
            actorUserId: $this->actorUserId,
        );
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('job.register_domain_failed', [
            'website_domain_id' => $this->websiteDomainId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
