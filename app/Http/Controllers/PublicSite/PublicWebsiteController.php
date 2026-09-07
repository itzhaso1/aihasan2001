<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Website\Website;
use App\Services\Website\PublicWebsiteService;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicWebsiteController extends Controller
{
    public function __construct(
        private readonly PublicWebsiteService $publicWebsiteService,
    ) {}

    public function showResolved(Request $request, ?string $page = null): View
    {
        /** @var Website|null $website */
        $website = $request->attributes->get('website');
        abort_unless($website, 404);

        if ($website->status !== 'published') {
            abort(404);
        }

        return view('public.website.show', $this->publicWebsiteService->buildWebsiteViewData(
            website: $website,
            pageSlug: $this->normalizePage($page)
        ));
    }

    public function showBySlug(Request $request, string $website, ?string $page = null): View
    {
        $resolved = Website::withoutGlobalScopes()
            ->where('slug', $website)
            ->whereNull('deleted_at')
            ->firstOrFail();

        if ($resolved->status !== 'published') {
            abort(404, 'Website is not published yet. Publish it from the dashboard before sharing public booking links.');
        }

        return view('public.website.show', $this->publicWebsiteService->buildWebsiteViewData(
            website: $resolved,
            pageSlug: $this->normalizePage($page)
        ));
    }

    public function preview(string $token, ?string $page = null): View
    {
        $website = Website::withoutGlobalScopes()
            ->where('preview_token', $token)
            ->whereNull('deleted_at')
            ->firstOrFail();

        return view('public.website.show', $this->publicWebsiteService->buildWebsiteViewData(
            website: $website,
            pageSlug: $this->normalizePage($page)
        ) + ['isPreview' => true]);
    }

    public function robotsResolved(Request $request): Response
    {
        /** @var Website|null $website */
        $website = $request->attributes->get('website');
        abort_unless($website, 404);

        return $this->robotsResponse($website, true);
    }

    public function sitemapResolved(Request $request): Response
    {
        /** @var Website|null $website */
        $website = $request->attributes->get('website');
        abort_unless($website, 404);

        return $this->sitemapResponse($website, true);
    }

    public function robotsBySlug(string $website): Response
    {
        $resolved = Website::withoutGlobalScopes()
            ->where('slug', $website)
            ->whereNull('deleted_at')
            ->firstOrFail();

        return $this->robotsResponse($resolved, false);
    }

    public function sitemapBySlug(string $website): Response
    {
        $resolved = Website::withoutGlobalScopes()
            ->where('slug', $website)
            ->whereNull('deleted_at')
            ->firstOrFail();

        return $this->sitemapResponse($resolved, false);
    }

    private function normalizePage(?string $page): string
    {
        $page = trim((string) $page, '/ ');

        if ($page === '') {
            return 'home';
        }

        return in_array($page, ['home', 'booking', 'contact'], true) ? $page : 'home';
    }

    private function robotsResponse(Website $website, bool $hostResolved): Response
    {
        $sitemapUrl = $hostResolved ? url('/sitemap.xml') : url('/public/'.$website->slug.'/sitemap.xml');
        $robots = $website->status === 'published'
            ? "User-agent: *\nAllow: /\nSitemap: ".$sitemapUrl
            : "User-agent: *\nDisallow: /";

        return response($robots, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    private function sitemapResponse(Website $website, bool $hostResolved): Response
    {
        if ($website->status !== 'published') {
            return response('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>', 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
        }

        $base = $hostResolved ? url('/') : url('/public/'.$website->slug);
        $urls = [
            $base,
            $base.'/booking',
            $base.'/contact',
        ];

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($urls as $url) {
            $xml .= '<url><loc>'.e($url).'</loc></url>';
        }
        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
