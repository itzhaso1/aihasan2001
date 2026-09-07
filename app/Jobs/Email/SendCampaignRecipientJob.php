<?php

namespace App\Jobs\Email;

use App\Models\EmailCampaign;
use App\Models\EmailCampaignRecipient;
use App\Services\Email\EmailCampaignService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendCampaignRecipientJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(
        public readonly int $recipientId,
    ) {}

    public function handle(EmailCampaignService $emailCampaignService): void
    {
        $recipient = EmailCampaignRecipient::query()->find($this->recipientId);

        if (! $recipient) {
            return;
        }

        try {
            $emailCampaignService->sendRecipient($recipient);
        } catch (Throwable $exception) {
            Log::warning('campaign-recipient-send-failed', [
                'recipient_id' => $this->recipientId,
                'campaign_id' => $recipient->email_campaign_id,
                'error' => $exception->getMessage(),
            ]);
            // Failure already persisted by EmailCampaignService::sendRecipient.
        }

        $campaign = EmailCampaign::withoutGlobalScopes()->find($recipient->email_campaign_id);
        if ($campaign) {
            $emailCampaignService->refreshCampaignStatus($campaign);
        }
    }
}
