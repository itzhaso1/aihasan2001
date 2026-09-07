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
    'slug',
    'title',
    'is_homepage',
    'is_published',
    'settings',
    'metadata',
])]
class WebsitePage extends WorkspaceScopedModel
{
    use BelongsToWorkspace, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_homepage' => 'boolean',
            'is_published' => 'boolean',
            'settings' => 'array',
            'metadata' => 'array',
        ];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(WebsiteSection::class, 'website_page_id');
    }
}
