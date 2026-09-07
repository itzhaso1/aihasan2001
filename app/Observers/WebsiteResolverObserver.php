<?php

namespace App\Observers;

use App\Models\Website\Website;
use App\Models\Website\WebsiteDomain;
use App\Services\Website\WebsiteResolverService;

class WebsiteResolverObserver
{
    public function saved(Website|WebsiteDomain $model): void
    {
        $this->invalidate($model);
    }

    public function deleted(Website|WebsiteDomain $model): void
    {
        $this->invalidate($model);
    }

    public function restored(Website|WebsiteDomain $model): void
    {
        $this->invalidate($model);
    }

    public function forceDeleted(Website|WebsiteDomain $model): void
    {
        $this->invalidate($model);
    }

    private function invalidate(Website|WebsiteDomain $model): void
    {
        $website = $model instanceof Website
            ? $model
            : $model->website()->withoutGlobalScopes()->first();

        if (! $website) {
            return;
        }

        app(WebsiteResolverService::class)->invalidateForWebsite($website);
    }
}
