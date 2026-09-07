<?php

namespace App\Models\Website;

use App\Models\Concerns\BelongsToWorkspace;
use App\Models\WorkspaceScopedModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'workspace_id',
    'website_id',
    'domain',
    'normalized_domain',
    'type',
    'provider',
    'provider_domain_id',
    'provider_order_id',
    'provider_transaction_id',
    'status',
    'verification_status',
    'ssl_status',
    'expires_at',
    'ssl_expires_at',
    'auto_renew',
    'dns_status',
    'is_primary',
    'metadata',
    'expiration_reminders_sent',
])]
class WebsiteDomain extends WorkspaceScopedModel
{
    use BelongsToWorkspace, SoftDeletes;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'ssl_expires_at' => 'datetime',
            'auto_renew' => 'boolean',
            'is_primary' => 'boolean',
            'metadata' => 'array',
            'expiration_reminders_sent' => 'array',
        ];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function operations(): HasMany
    {
        return $this->hasMany(WebsiteDomainOperation::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(WebsiteDomainContact::class);
    }

    public function reminderLogs(): HasMany
    {
        return $this->hasMany(WebsiteDomainReminderLog::class);
    }
}
