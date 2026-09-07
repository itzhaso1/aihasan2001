<?php

namespace App\Services\Website;

use App\Models\Website\Website;
use App\Models\Website\WebsiteAsset;
use App\Models\Website\WebsiteDomain;
use App\Models\Website\WebsitePage;
use App\Models\Website\WebsiteSection;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class WebsiteService
{
    public function __construct(
        private readonly TemplateService $templateService,
        private readonly WebsiteResolverService $websiteResolverService,
    ) {}

    /**
     * @param  array<string, mixed>  payload
     */
    public function createWebsite(Workspace $workspace, array $payload): Website
    {
        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            throw new RuntimeException('Website name is required.');
        }

        $slug = trim((string) ($payload['slug'] ?? ''));
        $slug = $slug !== '' ? Str::slug($slug) : Str::slug($name);
        if ($slug === '') {
            $slug = Str::lower(Str::random(8));
        }
        if (Website::withoutGlobalScopes()->where('slug', $slug)->exists()) {
            $slug .= '-'.Str::lower(Str::random(4));
        }

        /** @var Website $website */
        $website = DB::transaction(function () use ($workspace, $name, $slug): Website {
            $website = Website::withoutGlobalScopes()->create([
                'workspace_id' => $workspace->id,
                'name' => $name,
                'slug' => $slug,
                'status' => 'draft',
                'preview_token' => Str::lower((string) Str::ulid()),
                'settings' => $this->defaultSettings($workspace),
                'theme' => $this->defaultTheme(),
                'metadata' => [],
            ]);

            $platformDomain = trim((string) config('website.platform_domain'));
            if ($platformDomain !== '') {
                $host = "{$slug}.{$platformDomain}";
                $platform = WebsiteDomain::withoutGlobalScopes()->create([
                    'workspace_id' => $workspace->id,
                    'website_id' => $website->id,
                    'domain' => $host,
                    'normalized_domain' => $host,
                    'type' => 'platform_subdomain',
                    'provider' => 'platform',
                    'status' => 'active',
                    'verification_status' => 'verified',
                    'ssl_status' => 'pending',
                    'dns_status' => 'configured',
                    'is_primary' => true,
                    'metadata' => [],
                ]);
                $website->update(['primary_domain_id' => $platform->id]);
            }

            WebsitePage::withoutGlobalScopes()->create([
                'workspace_id' => $workspace->id,
                'website_id' => $website->id,
                'slug' => 'home',
                'title' => 'Home',
                'is_homepage' => true,
                'is_published' => true,
                'settings' => [],
                'metadata' => [],
            ]);
            WebsitePage::withoutGlobalScopes()->create([
                'workspace_id' => $workspace->id,
                'website_id' => $website->id,
                'slug' => 'booking',
                'title' => 'Booking',
                'is_homepage' => false,
                'is_published' => true,
                'settings' => [],
                'metadata' => [],
            ]);
            WebsitePage::withoutGlobalScopes()->create([
                'workspace_id' => $workspace->id,
                'website_id' => $website->id,
                'slug' => 'contact',
                'title' => 'Contact',
                'is_homepage' => false,
                'is_published' => true,
                'settings' => [],
                'metadata' => [],
            ]);

            return $website->refresh();
        });

        $this->websiteResolverService->invalidateForWebsite($website);

        return $website;
    }

    public function selectTemplate(Website $website, int $templateId): Website
    {
        $template = $this->templateService->listTemplates()->firstWhere('id', $templateId);
        if (! $template) {
            throw new RuntimeException('Template not found.');
        }

        $website->update([
            'template_id' => $template->id,
            'theme' => $template->theme_preset ?: $website->theme,
        ]);

        $this->templateService->bootstrapWebsiteStructure($website->refresh());
        $this->websiteResolverService->invalidateForWebsite($website);

        return $website->refresh();
    }

    /**
     * @param  array<string, mixed>  payload
     */
    public function updateSettings(Website $website, array $payload): Website
    {
        $settings = is_array($website->settings) ? $website->settings : [];
        $theme = is_array($website->theme) ? $website->theme : [];

        $settings['business_name'] = trim((string) ($payload['business_name'] ?? ($settings['business_name'] ?? $website->name)));
        $settings['hero_title'] = trim((string) ($payload['hero_title'] ?? ($settings['hero_title'] ?? '')));
        $settings['hero_description'] = trim((string) ($payload['hero_description'] ?? ($settings['hero_description'] ?? '')));
        $settings['cta_text'] = trim((string) ($payload['cta_text'] ?? ($settings['cta_text'] ?? '')));
        $settings['about_text'] = trim((string) ($payload['about_text'] ?? ($settings['about_text'] ?? '')));
        $settings['contact_phone'] = trim((string) ($payload['contact_phone'] ?? ($settings['contact_phone'] ?? '')));
        $settings['contact_email'] = trim((string) ($payload['contact_email'] ?? ($settings['contact_email'] ?? '')));
        $settings['contact_address'] = trim((string) ($payload['contact_address'] ?? ($settings['contact_address'] ?? '')));
        $settings['seo_title'] = trim((string) ($payload['seo_title'] ?? ($settings['seo_title'] ?? '')));
        $settings['seo_description'] = trim((string) ($payload['seo_description'] ?? ($settings['seo_description'] ?? '')));
        $settings['footer_text'] = trim((string) ($payload['footer_text'] ?? ($settings['footer_text'] ?? '')));
        $settings['logo_alt'] = trim((string) ($payload['logo_alt'] ?? ($settings['logo_alt'] ?? '')));
        $settings['social_links'] = is_array($payload['social_links'] ?? null) ? $payload['social_links'] : ($settings['social_links'] ?? []);

        $theme['primary_color'] = trim((string) ($payload['primary_color'] ?? ($theme['primary_color'] ?? '#0f766e')));
        $theme['secondary_color'] = trim((string) ($payload['secondary_color'] ?? ($theme['secondary_color'] ?? '#14b8a6')));
        $theme['font'] = trim((string) ($payload['font'] ?? ($theme['font'] ?? 'Cairo')));
        $theme['direction'] = in_array(($payload['direction'] ?? null), ['rtl', 'ltr'], true)
            ? $payload['direction']
            : ($theme['direction'] ?? 'rtl');

        $website->update([
            'settings' => $settings,
            'theme' => $theme,
            'metadata' => array_merge(
                is_array($website->metadata) ? $website->metadata : [],
                ['updated_at' => now()->toDateTimeString()]
            ),
        ]);

        $this->websiteResolverService->invalidateForWebsite($website);

        return $website->refresh();
    }

    public function storeAsset(Website $website, string $assetType, UploadedFile $file): WebsiteAsset
    {
        // SVG is intentionally blocked to prevent stored XSS via uploaded assets.
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (! in_array((string) $file->getMimeType(), $allowed, true)) {
            throw new RuntimeException('نوع الملف غير مدعوم. يُسمح فقط بصور JPEG وPNG وWebP وGIF.');
        }

        if ($file->getSize() > 5 * 1024 * 1024) {
            throw new RuntimeException('حجم الصورة يجب ألا يتجاوز 5 ميجابايت.');
        }

        $path = $file->store("website-assets/{$website->id}", 'public');

        return WebsiteAsset::withoutGlobalScopes()->create([
            'workspace_id' => $website->workspace_id,
            'website_id' => $website->id,
            'asset_type' => $assetType,
            'disk' => 'public',
            'path' => $path,
            'mime_type' => (string) $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'metadata' => [],
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  sections
     */
    public function updateSections(Website $website, array $sections): Website
    {
        DB::transaction(function () use ($website, $sections): void {
            foreach ($sections as $section) {
                if (! is_array($section) || empty($section['id'])) {
                    continue;
                }

                $model = WebsiteSection::withoutGlobalScopes()
                    ->where('website_id', $website->id)
                    ->whereKey((int) $section['id'])
                    ->first();

                if (! $model) {
                    continue;
                }

                $model->update([
                    'position' => max(0, (int) ($section['position'] ?? $model->position)),
                    'is_enabled' => array_key_exists('is_enabled', $section) ? (bool) $section['is_enabled'] : $model->is_enabled,
                    'config' => is_array($section['config'] ?? null) ? $section['config'] : (is_array($model->config) ? $model->config : []),
                ]);
            }
        });

        $this->websiteResolverService->invalidateForWebsite($website);

        return $website->refresh();
    }

    public function publish(Website $website): Website
    {
        $workspace = $website->workspace()->withoutGlobalScopes()->first();
        if (! $workspace || $workspace->status !== 'active') {
            throw new RuntimeException('Workspace must be active before publishing website.');
        }

        if (! $website->template_id) {
            throw new RuntimeException('Template must be selected before publishing.');
        }

        $settings = is_array($website->settings) ? $website->settings : [];
        if (trim((string) ($settings['business_name'] ?? '')) === '') {
            throw new RuntimeException('Business name is required before publishing.');
        }

        $hasDomain = WebsiteDomain::withoutGlobalScopes()
            ->where('website_id', $website->id)
            ->whereIn('status', ['active', 'verified', 'ssl_pending'])
            ->exists();

        if (! $hasDomain) {
            throw new RuntimeException('At least one verified or active domain is required for publishing.');
        }

        $website->update([
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->websiteResolverService->invalidateForWebsite($website);

        return $website->refresh();
    }

    public function unpublish(Website $website): Website
    {
        $website->update(['status' => 'unpublished']);
        $this->websiteResolverService->invalidateForWebsite($website);

        return $website->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultSettings(Workspace $workspace): array
    {
        return [
            'business_name' => $workspace->name,
            'hero_title' => 'احجز موعدك الآن بسهولة',
            'hero_description' => 'اختر الخدمة والوقت المناسب وأكمل الحجز في دقائق.',
            'cta_text' => 'احجز الآن',
            'about_text' => '',
            'contact_phone' => '',
            'contact_email' => '',
            'contact_address' => '',
            'seo_title' => $workspace->name,
            'seo_description' => '',
            'social_links' => [],
            'footer_text' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultTheme(): array
    {
        return [
            'primary_color' => '#0f766e',
            'secondary_color' => '#14b8a6',
            'font' => 'Cairo',
            'direction' => 'rtl',
        ];
    }
}
