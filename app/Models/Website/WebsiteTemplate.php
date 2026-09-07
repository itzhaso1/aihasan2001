<?php

namespace App\Models\Website;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'key',
    'name',
    'category',
    'description',
    'preview_image',
    'layout',
    'default_sections',
    'theme_preset',
    'metadata',
    'is_active',
    'sort_order',
])]
class WebsiteTemplate extends Model
{
    protected function casts(): array
    {
        return [
            'layout' => 'array',
            'default_sections' => 'array',
            'theme_preset' => 'array',
            'metadata' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function websites(): HasMany
    {
        return $this->hasMany(Website::class, 'template_id');
    }
}
