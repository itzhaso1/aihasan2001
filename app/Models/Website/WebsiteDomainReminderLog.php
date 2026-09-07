<?php

namespace App\Models\Website;

use App\Models\Concerns\BelongsToWorkspace;
use App\Models\WorkspaceScopedModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'workspace_id',
    'website_domain_id',
    'days_before',
    'channel',
    'idempotency_key',
    'sent_at',
])]
class WebsiteDomainReminderLog extends WorkspaceScopedModel
{
    use BelongsToWorkspace;

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'days_before' => 'integer',
        ];
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(WebsiteDomain::class, 'website_domain_id');
    }
}
