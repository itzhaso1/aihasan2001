<?php

namespace App\Http\Middleware;

use App\Models\Website\Website;
use App\Services\Domain\DomainName;
use App\Services\Website\WebsiteResolverService;
use App\Support\Tenancy\WorkspaceContext;
use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class ResolvePublicWebsite
{
    public function __construct(
        private readonly WebsiteResolverService $websiteResolverService,
        private readonly WorkspaceContext $workspaceContext,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $website = $this->resolveWebsite($request);
        if (! $website) {
            abort(404);
        }

        $primary = $website->primaryDomain()->withoutGlobalScopes()->first();
        if (
            ! $request->route('website')
            && ! str_starts_with((string) $request->path(), 'public/')
            && $primary
            && $website->status === 'published'
            && ! str_contains((string) $request->getHost(), 'localhost')
        ) {
            $currentHost = DomainName::normalize((string) $request->getHost());
            if ($currentHost !== '' && $currentHost !== (string) $primary->normalized_domain) {
                return redirect()->to('https://'.$primary->normalized_domain.$request->getRequestUri(), 301);
            }
        }

        $workspace = $website->workspace()->withoutGlobalScopes()->first();
        if (! $workspace) {
            abort(404);
        }

        $this->workspaceContext->set($workspace);
        $request->attributes->set('workspace', $workspace);
        $request->attributes->set('website', $website);
        app(PermissionRegistrar::class)->setPermissionsTeamId($workspace->id);

        try {
            return $next($request);
        } finally {
            app(PermissionRegistrar::class)->setPermissionsTeamId(null);
            $this->workspaceContext->clear();
        }
    }

    private function resolveWebsite(Request $request): ?Website
    {
        $bound = $request->route('website');
        if ($bound instanceof Website) {
            return $bound;
        }

        if (is_string($bound) && trim($bound) !== '') {
            return Website::withoutGlobalScopes()
                ->where('slug', trim($bound))
                ->whereNull('deleted_at')
                ->first();
        }

        return $this->websiteResolverService->resolveByHost((string) $request->getHost());
    }
}
