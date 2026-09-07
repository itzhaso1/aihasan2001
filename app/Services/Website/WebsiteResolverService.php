<?php

namespace App\Services\Website;

use App\Models\Website\Website;
use App\Models\Website\WebsiteDomain;
use App\Services\Domain\DomainName;
use Illuminate\Support\Facades\Cache;

class WebsiteResolverService
{
    public function resolveByHost(string $host): ?Website
    {
        $normalizedHost = DomainName::normalize($host);
        if ($normalizedHost === '') {
            return null;
        }

        $cacheKey = $this->cacheKeyForHost($normalizedHost);
        $websiteId = Cache::remember(
            $cacheKey,
            now()->addSeconds((int) config('website.resolver_cache_ttl_seconds', 300)),
            function () use ($normalizedHost): ?int {
                $domain = WebsiteDomain::withoutGlobalScopes()
                    ->where('normalized_domain', $normalizedHost)
                    ->whereNull('deleted_at')
                    ->first();

                if ($domain) {
                    return (int) $domain->website_id;
                }

                $platformDomain = trim((string) config('website.platform_domain'));
                if ($platformDomain !== '' && str_ends_with($normalizedHost, '.'.$platformDomain)) {
                    $slug = str_replace('.'.$platformDomain, '', $normalizedHost);
                    if ($slug !== '') {
                        $website = Website::withoutGlobalScopes()
                            ->where('slug', $slug)
                            ->whereNull('deleted_at')
                            ->first();

                        return $website?->id;
                    }
                }

                if (str_ends_with($normalizedHost, '.localhost')) {
                    $slug = str_replace('.localhost', '', $normalizedHost);
                    if ($slug !== '') {
                        $website = Website::withoutGlobalScopes()
                            ->where('slug', $slug)
                            ->whereNull('deleted_at')
                            ->first();

                        return $website?->id;
                    }
                }

                return null;
            }
        );

        if (! $websiteId) {
            return null;
        }

        return Website::withoutGlobalScopes()
            ->with(['template', 'primaryDomain'])
            ->whereKey($websiteId)
            ->whereNull('deleted_at')
            ->whereIn('status', ['published', 'unpublished', 'draft', 'suspended'])
            ->first();
    }

    public function resolvePublishedBySlug(string $slug): ?Website
    {
        return Website::withoutGlobalScopes()
            ->with(['template', 'primaryDomain'])
            ->where('slug', $slug)
            ->where('status', 'published')
            ->whereNull('deleted_at')
            ->first();
    }

    public function invalidateForWebsite(Website $website): void
    {
        $domains = WebsiteDomain::withoutGlobalScopes()
            ->where('website_id', $website->id)
            ->pluck('normalized_domain');

        foreach ($domains as $domain) {
            Cache::forget($this->cacheKeyForHost((string) $domain));
        }

        Cache::forget($this->cacheKeyForHost($website->slug.'.localhost'));

        $platformDomain = trim((string) config('website.platform_domain'));
        if ($platformDomain !== '') {
            Cache::forget($this->cacheKeyForHost($website->slug.'.'.$platformDomain));
        }
    }

    private function cacheKeyForHost(string $host): string
    {
        return 'website:resolver:host:'.$host;
    }
}
