<?php

namespace App\Jobs;

use App\Models\EmailAccount;
use App\Services\Email\ImapSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncEmailInboxJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;
    public int $timeout = 180;

    public function __construct(
        public readonly int $emailAccountId,
    ) {}

    public function handle(ImapSyncService $imapSyncService): void
    {
        $emailAccount = EmailAccount::withoutGlobalScopes()->find($this->emailAccountId);
        if (! $emailAccount) {
            return;
        }

        try {
            $imapSyncService->syncAccount($emailAccount, 40);
        } catch (\Throwable $exception) {
            Log::error('email-sync-job-failed', [
                'email_account_id' => $this->emailAccountId,
                'workspace_id' => $emailAccount->workspace_id,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
