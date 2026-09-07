<?php

namespace App\Jobs;

use App\Models\EmailMessage;
use App\Services\Email\WorkspaceEmailSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendEmailMessageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        public readonly int $emailMessageId,
    ) {}

    public function handle(WorkspaceEmailSender $workspaceEmailSender): void
    {
        $emailMessage = EmailMessage::withoutGlobalScopes()
            ->with(['account', 'attachments'])
            ->find($this->emailMessageId);

        if (! $emailMessage || $emailMessage->type !== 'outbound') {
            return;
        }

        try {
            $emailMessage->forceFill([
                'delivery_status' => 'sending',
                'delivery_error' => null,
            ])->save();

            $emailLog = $workspaceEmailSender->send($emailMessage);

            $emailMessage->forceFill([
                'delivery_status' => 'sent',
                'delivery_error' => null,
                'delivered_at' => now(),
                'message_id' => $emailLog->provider_message_id ?: $emailMessage->message_id,
            ])->save();
        } catch (\Throwable $exception) {
            Log::error('email-send-job-failed', [
                'email_message_id' => $this->emailMessageId,
                'workspace_id' => $emailMessage->workspace_id,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        $emailMessage = EmailMessage::withoutGlobalScopes()->find($this->emailMessageId);
        if (! $emailMessage) {
            return;
        }

        $emailMessage->forceFill([
            'delivery_status' => 'failed',
            'delivery_error' => $exception->getMessage(),
        ])->save();
    }
}
