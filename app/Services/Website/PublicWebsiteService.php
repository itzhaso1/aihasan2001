<?php

namespace App\Services\Website;

use App\Models\Appointment\AppointmentService;
use App\Models\Appointment\AppointmentSetting;
use App\Models\Appointment\AppointmentStaff;
use App\Models\Website\Website;
use App\Models\Website\WebsitePage;
use App\Models\Website\WebsiteSection;
use Illuminate\Support\Collection;

class PublicWebsiteService
{
    /**
     * @return array<string, mixed>
     */
    public function buildWebsiteViewData(Website $website, string $pageSlug = 'home'): array
    {
        $website->loadMissing(['template', 'primaryDomain']);

        $pages = WebsitePage::withoutGlobalScopes()
            ->where('website_id', $website->id)
            ->where('is_published', true)
            ->orderBy('id')
            ->get();

        $page = $pages->firstWhere('slug', $pageSlug)
            ?: $pages->firstWhere('is_homepage', true)
            ?: $pages->first();

        $sections = collect();
        if ($page) {
            $sections = WebsiteSection::withoutGlobalScopes()
                ->where('website_id', $website->id)
                ->where('website_page_id', $page->id)
                ->where('is_enabled', true)
                ->orderBy('position')
                ->get();
        }

        $services = AppointmentService::withoutGlobalScopes()
            ->where('workspace_id', $website->workspace_id)
            ->where('is_active', true)
            ->with(['staffMembers:id,name'])
            ->orderBy('name')
            ->get(['id', 'workspace_id', 'name', 'description', 'duration_minutes', 'price', 'requires_payment']);

        $staff = AppointmentStaff::withoutGlobalScopes()
            ->where('workspace_id', $website->workspace_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'workspace_id', 'name', 'role', 'phone']);

        $settings = is_array($website->settings) ? $website->settings : [];
        $theme = is_array($website->theme) ? $website->theme : [];
        $appointmentSetting = AppointmentSetting::withoutGlobalScopes()
            ->where('workspace_id', $website->workspace_id)
            ->first();

        return [
            'website' => $website,
            'pages' => $pages,
            'current_page' => $page,
            'sections' => $sections,
            'services' => $services,
            'staff' => $staff,
            'settings' => $settings,
            'theme' => $theme,
            'business_hours' => $this->businessHours($appointmentSetting),
            'timezone' => $appointmentSetting?->timezone ?: config('app.timezone'),
            'seo' => [
                'title' => $settings['seo_title'] ?? $website->name,
                'description' => $settings['seo_description'] ?? null,
                'canonical' => $this->canonicalUrl($website, $pageSlug),
                'robots' => $website->status === 'published' ? 'index,follow' : 'noindex,nofollow',
                'favicon' => $settings['favicon_url'] ?? null,
                'og_title' => $settings['seo_title'] ?? $website->name,
                'og_description' => $settings['seo_description'] ?? null,
            ],
        ];
    }

    /**
     * @param  Collection<int, WebsiteSection>  $sections
     */
    public function findSectionConfig(Collection $sections, string $sectionKey): array
    {
        $section = $sections->firstWhere('section_key', $sectionKey)
            ?: $sections->firstWhere('component_key', $sectionKey);

        return is_array($section?->config) ? $section->config : [];
    }

    private function canonicalUrl(Website $website, string $pageSlug): ?string
    {
        $domain = $website->primaryDomain;
        $path = in_array($pageSlug, ['booking', 'contact'], true) ? '/'.$pageSlug : '';

        if ($domain && $domain->normalized_domain !== '') {
            return 'https://'.$domain->normalized_domain.$path;
        }

        if ($pageSlug === 'home') {
            return route('public.website.show', ['website' => $website->slug]);
        }

        return route('public.website.page', ['website' => $website->slug, 'page' => $pageSlug]);
    }

    /**
     * @return array<string, mixed>
     */
    private function businessHours(?AppointmentSetting $setting): array
    {
        $metadata = is_array($setting?->metadata) ? $setting->metadata : [];

        return is_array($metadata['business_hours'] ?? null)
            ? $metadata['business_hours']
            : [];
    }
}
