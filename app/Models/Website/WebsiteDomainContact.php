<?php

namespace App\Models\Website;

use App\Models\Concerns\BelongsToWorkspace;
use App\Models\WorkspaceScopedModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'workspace_id',
    'website_domain_id',
    'contact_type',
    'organization_name',
    'job_title',
    'first_name',
    'last_name',
    'address1',
    'address2',
    'city',
    'state_province',
    'postal_code',
    'country',
    'phone',
    'email',
    'metadata',
])]
class WebsiteDomainContact extends WorkspaceScopedModel
{
    use BelongsToWorkspace;

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(WebsiteDomain::class, 'website_domain_id');
    }
}
