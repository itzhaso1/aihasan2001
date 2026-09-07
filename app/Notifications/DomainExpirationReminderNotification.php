<?php

namespace App\Notifications;

use App\Models\Website\WebsiteDomain;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class DomainExpirationReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly WebsiteDomain $domain,
        public readonly int $daysBefore,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'domain_expiration_reminder',
            'domain' => $this->domain->normalized_domain,
            'website_domain_id' => $this->domain->id,
            'website_id' => $this->domain->website_id,
            'workspace_id' => $this->domain->workspace_id,
            'days_before' => $this->daysBefore,
            'expires_at' => optional($this->domain->expires_at)?->toIso8601String(),
            'message' => sprintf(
                'Domain %s expires in %d day(s).',
                $this->domain->normalized_domain,
                $this->daysBefore
            ),
        ];
    }
}
