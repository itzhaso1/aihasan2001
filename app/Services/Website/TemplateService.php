<?php

namespace App\Services\Website;

use App\Models\Website\Website;
use App\Models\Website\WebsitePage;
use App\Models\Website\WebsiteSection;
use App\Models\Website\WebsiteTemplate;
use Illuminate\Support\Collection;

class TemplateService
{
    /**
     * @return Collection<int, WebsiteTemplate>
     */
    public function listTemplates(): Collection
    {
        $this->ensureDefaultTemplates();

        return WebsiteTemplate::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function ensureDefaultTemplates(): void
    {
        foreach ($this->defaultTemplates() as $template) {
            WebsiteTemplate::query()->updateOrCreate(
                ['key' => $template['key']],
                $template
            );
        }
    }

    public function bootstrapWebsiteStructure(Website $website): void
    {
        $template = $website->template;
        if (! $template) {
            return;
        }

        $pages = collect([
            ['slug' => 'home', 'title' => 'Home', 'is_homepage' => true],
            ['slug' => 'booking', 'title' => 'Booking', 'is_homepage' => false],
            ['slug' => 'contact', 'title' => 'Contact', 'is_homepage' => false],
        ]);

        foreach ($pages as $pageConfig) {
            WebsitePage::withoutGlobalScopes()->firstOrCreate(
                [
                    'workspace_id' => $website->workspace_id,
                    'website_id' => $website->id,
                    'slug' => $pageConfig['slug'],
                ],
                [
                    'workspace_id' => $website->workspace_id,
                    'website_id' => $website->id,
                    'slug' => $pageConfig['slug'],
                    'title' => $pageConfig['title'],
                    'is_homepage' => $pageConfig['is_homepage'],
                    'is_published' => true,
                    'settings' => [],
                    'metadata' => [],
                ]
            );
        }

        $homePage = WebsitePage::withoutGlobalScopes()
            ->where('website_id', $website->id)
            ->where('slug', 'home')
            ->first();

        if (! $homePage) {
            return;
        }

        $defaultSections = is_array($template->default_sections) ? $template->default_sections : [];
        $position = 1;
        foreach ($defaultSections as $section) {
            if (! is_array($section)) {
                continue;
            }

            WebsiteSection::withoutGlobalScopes()->firstOrCreate(
                [
                    'workspace_id' => $website->workspace_id,
                    'website_id' => $website->id,
                    'website_page_id' => $homePage->id,
                    'section_key' => (string) ($section['section_key'] ?? $section['component_key'] ?? 'section_'.$position),
                ],
                [
                    'workspace_id' => $website->workspace_id,
                    'website_id' => $website->id,
                    'website_page_id' => $homePage->id,
                    'section_key' => (string) ($section['section_key'] ?? $section['component_key'] ?? 'section_'.$position),
                    'component_key' => (string) ($section['component_key'] ?? 'hero'),
                    'position' => (int) ($section['position'] ?? $position),
                    'is_enabled' => array_key_exists('is_enabled', $section) ? (bool) $section['is_enabled'] : true,
                    'config' => is_array($section['config'] ?? null) ? $section['config'] : [],
                    'metadata' => [],
                ]
            );

            $position++;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function defaultTemplates(): array
    {
        return [
            [
                'key' => 'dental-modern',
                'name' => 'Dental Clinic - Modern',
                'category' => 'dental',
                'description' => 'Modern clinic template focused on dental services and doctors.',
                'preview_image' => null,
                'layout' => ['style' => 'clean-grid', 'hero_layout' => 'split'],
                'theme_preset' => ['primary_color' => '#0f766e', 'secondary_color' => '#14b8a6', 'font' => 'Cairo', 'direction' => 'rtl'],
                'default_sections' => [
                    ['section_key' => 'hero', 'component_key' => 'hero', 'position' => 1, 'is_enabled' => true, 'config' => ['title' => 'ابتسامة صحية تبدأ من هنا', 'subtitle' => 'احجز موعدك بسهولة خلال دقائق.']],
                    ['section_key' => 'about', 'component_key' => 'about', 'position' => 2, 'is_enabled' => true, 'config' => ['title' => 'عن العيادة']],
                    ['section_key' => 'services', 'component_key' => 'services_grid', 'position' => 3, 'is_enabled' => true, 'config' => ['title' => 'الخدمات', 'show_price' => true, 'show_duration' => true]],
                    ['section_key' => 'staff', 'component_key' => 'staff_grid', 'position' => 4, 'is_enabled' => true, 'config' => ['title' => 'الأطباء']],
                    ['section_key' => 'booking_cta', 'component_key' => 'booking_cta', 'position' => 5, 'is_enabled' => true, 'config' => ['button_text' => 'احجز الآن']],
                    ['section_key' => 'business_hours', 'component_key' => 'business_hours', 'position' => 6, 'is_enabled' => true, 'config' => ['title' => 'ساعات العمل']],
                    ['section_key' => 'contact', 'component_key' => 'contact', 'position' => 7, 'is_enabled' => true, 'config' => ['title' => 'تواصل معنا']],
                    ['section_key' => 'footer', 'component_key' => 'footer', 'position' => 8, 'is_enabled' => true, 'config' => []],
                ],
                'metadata' => ['rtl_ready' => true],
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'key' => 'medical-classic',
                'name' => 'Medical Clinic - Classic',
                'category' => 'medical',
                'description' => 'General medical website with trust-focused structure.',
                'preview_image' => null,
                'layout' => ['style' => 'classic', 'hero_layout' => 'full'],
                'theme_preset' => ['primary_color' => '#1d4ed8', 'secondary_color' => '#3b82f6', 'font' => 'Cairo', 'direction' => 'rtl'],
                'default_sections' => [
                    ['section_key' => 'hero', 'component_key' => 'hero', 'position' => 1, 'config' => ['title' => 'رعاية طبية موثوقة لكل العائلة']],
                    ['section_key' => 'services', 'component_key' => 'services_grid', 'position' => 2, 'config' => ['layout' => 'cards']],
                    ['section_key' => 'testimonials', 'component_key' => 'testimonials', 'position' => 3, 'config' => ['title' => 'آراء العملاء']],
                    ['section_key' => 'faq', 'component_key' => 'faq', 'position' => 4, 'config' => ['title' => 'أسئلة شائعة']],
                    ['section_key' => 'booking_cta', 'component_key' => 'booking_cta', 'position' => 5, 'config' => ['button_text' => 'ابدأ الحجز']],
                    ['section_key' => 'footer', 'component_key' => 'footer', 'position' => 6, 'config' => []],
                ],
                'metadata' => ['rtl_ready' => true],
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'key' => 'beauty-salon-elegant',
                'name' => 'Beauty & Salon - Elegant',
                'category' => 'beauty',
                'description' => 'Elegant sections suitable for salons and beauty professionals.',
                'preview_image' => null,
                'layout' => ['style' => 'elegant', 'hero_layout' => 'overlay'],
                'theme_preset' => ['primary_color' => '#be185d', 'secondary_color' => '#ec4899', 'font' => 'Cairo', 'direction' => 'rtl'],
                'default_sections' => [
                    ['section_key' => 'hero', 'component_key' => 'hero', 'position' => 1, 'config' => ['title' => 'جمالك يبدأ من هنا']],
                    ['section_key' => 'gallery', 'component_key' => 'gallery', 'position' => 2, 'config' => ['title' => 'أعمالنا']],
                    ['section_key' => 'services', 'component_key' => 'services_grid', 'position' => 3, 'config' => ['layout' => 'masonry']],
                    ['section_key' => 'staff', 'component_key' => 'staff_grid', 'position' => 4, 'config' => ['title' => 'الفريق']],
                    ['section_key' => 'booking_cta', 'component_key' => 'booking_cta', 'position' => 5, 'config' => ['button_text' => 'احجزي موعدك']],
                    ['section_key' => 'contact', 'component_key' => 'contact', 'position' => 6, 'config' => ['title' => 'العنوان والتواصل']],
                    ['section_key' => 'footer', 'component_key' => 'footer', 'position' => 7, 'config' => []],
                ],
                'metadata' => ['rtl_ready' => true],
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'key' => 'law-firm-pro',
                'name' => 'Law Firm - Professional',
                'category' => 'legal',
                'description' => 'Professional legal landing focused on trust and booking consultations.',
                'preview_image' => null,
                'layout' => ['style' => 'corporate', 'hero_layout' => 'content-left'],
                'theme_preset' => ['primary_color' => '#0f172a', 'secondary_color' => '#334155', 'font' => 'Cairo', 'direction' => 'rtl'],
                'default_sections' => [
                    ['section_key' => 'hero', 'component_key' => 'hero', 'position' => 1, 'config' => ['title' => 'استشارات قانونية بخبرة عملية']],
                    ['section_key' => 'about', 'component_key' => 'about', 'position' => 2, 'config' => ['title' => 'من نحن']],
                    ['section_key' => 'services', 'component_key' => 'services_grid', 'position' => 3, 'config' => ['title' => 'الخدمات القانونية']],
                    ['section_key' => 'faq', 'component_key' => 'faq', 'position' => 4, 'config' => ['title' => 'الأسئلة الشائعة']],
                    ['section_key' => 'booking_cta', 'component_key' => 'booking_cta', 'position' => 5, 'config' => ['button_text' => 'احجز استشارة']],
                    ['section_key' => 'contact', 'component_key' => 'contact', 'position' => 6, 'config' => []],
                    ['section_key' => 'footer', 'component_key' => 'footer', 'position' => 7, 'config' => []],
                ],
                'metadata' => ['rtl_ready' => true],
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'key' => 'consultant-flex',
                'name' => 'Consultant - Professional',
                'category' => 'consulting',
                'description' => 'Flexible template for consultants and experts.',
                'preview_image' => null,
                'layout' => ['style' => 'minimal', 'hero_layout' => 'compact'],
                'theme_preset' => ['primary_color' => '#4338ca', 'secondary_color' => '#6366f1', 'font' => 'Cairo', 'direction' => 'rtl'],
                'default_sections' => [
                    ['section_key' => 'hero', 'component_key' => 'hero', 'position' => 1, 'config' => ['title' => 'حلول عملية لنجاح أعمالك']],
                    ['section_key' => 'services', 'component_key' => 'services_grid', 'position' => 2, 'config' => ['title' => 'الخدمات']],
                    ['section_key' => 'testimonials', 'component_key' => 'testimonials', 'position' => 3, 'config' => ['title' => 'نتائج العملاء']],
                    ['section_key' => 'booking_cta', 'component_key' => 'booking_cta', 'position' => 4, 'config' => ['button_text' => 'احجز جلسة']],
                    ['section_key' => 'contact', 'component_key' => 'contact', 'position' => 5, 'config' => []],
                    ['section_key' => 'footer', 'component_key' => 'footer', 'position' => 6, 'config' => []],
                ],
                'metadata' => ['rtl_ready' => true],
                'is_active' => true,
                'sort_order' => 5,
            ],
        ];
    }
}
