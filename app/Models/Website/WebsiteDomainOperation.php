<?php

namespace App\Models\Website;

use App\Models\Concerns\BelongsToWorkspace;
use App\Models\WorkspaceScopedModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'workspace_id',
    'website_id',
    'website_domain_id',
    'operation_type',
    'provider',
    'status',
    'idempotency_key',
    'request_payload',
    'response_payload',
    'error_message',
    'processed_at',
])]
class WebsiteDomainOperation extends WorkspaceScopedModel
{
    use BelongsToWorkspace;

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(WebsiteDomain::class, 'website_domain_id');
    }
}
