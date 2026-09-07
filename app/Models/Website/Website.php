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
    'template_id',
    'primary_domain_id',
    'name',
    'slug',
    'status',
    'published_at',
    'preview_token',
    'settings',
    'theme',
    'metadata',
])]
class Website extends WorkspaceScopedModel
{
    use BelongsToWorkspace, SoftDeletes;

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'settings' => 'array',
            'theme' => 'array',
            'metadata' => 'array',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WebsiteTemplate::class, 'template_id');
    }

    public function pages(): HasMany
    {
        return $this->hasMany(WebsitePage::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(WebsiteSection::class);
    }

    public function domains(): HasMany
    {
        return $this->hasMany(WebsiteDomain::class);
    }

    public function primaryDomain(): BelongsTo
    {
        return $this->belongsTo(WebsiteDomain::class, 'primary_domain_id');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(WebsiteAsset::class);
    }
}
