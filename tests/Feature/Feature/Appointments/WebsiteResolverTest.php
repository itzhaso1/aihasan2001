<?php

namespace Tests\Feature\Feature\Appointments;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Website\Website;
use App\Models\Website\WebsiteDomain;
use App\Models\Workspace;
use App\Services\Website\TemplateService;
use App\Services\Website\WebsiteResolverService;
use App\Services\Website\WebsiteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsiteResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolver_returns_website_by_custom_domain_and_platform_subdomain(): void
    {
        config()->set('website.platform_domain', 'platform.test');

        $workspace = $this->createWorkspaceWithWebsiteFeatures('company');
        $website = $this->createPublishedWebsite($workspace);

        WebsiteDomain::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'website_id' => $website->id,
            'domain' => 'book.example.test',
            'normalized_domain' => 'book.example.test',
            'type' => 'custom_domain',
            'provider' => 'namecheap',
            'status' => 'active',
            'verification_status' => 'verified',
            'ssl_status' => 'active',
            'dns_status' => 'configured',
            'is_primary' => false,
            'metadata' => [],
        ]);

        $resolver = app(WebsiteResolverService::class);

        $byCustom = $resolver->resolveByHost('book.example.test');
        $this->assertNotNull($byCustom);
        $this->assertSame($website->id, $byCustom?->id);

        $byPlatform = $resolver->resolveByHost($website->slug.'.platform.test');
        $this->assertNotNull($byPlatform);
        $this->assertSame($website->id, $byPlatform?->id);
    }

    public function test_host_resolved_public_booking_page_uses_public_website_middleware(): void
    {
        config()->set('website.platform_domain', 'platform.test');

        $workspace = $this->createWorkspaceWithWebsiteFeatures('company');
        $website = $this->createPublishedWebsite($workspace);

        $this->get('http://'.$website->slug.'.platform.test/booking')
            ->assertOk()
            ->assertSee('تأكيد الحجز', false);
    }

    private function createPublishedWebsite(Workspace $workspace): Website
    {
        /** @var WebsiteService $websiteService */
        $websiteService = app(WebsiteService::class);
        /** @var TemplateService $templateService */
        $templateService = app(TemplateService::class);

        $website = $websiteService->createWebsite($workspace, [
            'name' => 'Resolver Website '.$workspace->id,
            'slug' => 'resolver-'.$workspace->id,
        ]);

        $template = $templateService->listTemplates()->firstOrFail();
        $websiteService->selectTemplate($website, $template->id);
        $websiteService->updateSettings($website, [
            'business_name' => 'Resolver Workspace '.$workspace->id,
            'hero_title' => 'Book now',
            'hero_description' => 'Booking by host resolution',
        ]);

        return $websiteService->publish($website->refresh());
    }

    private function createWorkspaceWithWebsiteFeatures(string $workspaceType): Workspace
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create([
            'owner_user_id' => $user->id,
            'type' => $workspaceType,
        ]);

        $workspace->users()->attach($user->id, [
            'membership_role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $plan = Plan::query()->create([
            'code' => 'plan-'.$workspace->id.'-'.uniqid(),
            'name' => 'Workspace Plan',
            'workspace_type' => $workspaceType,
            'billing_period' => 'monthly',
            'currency' => 'USD',
            'price' => 99,
            'is_active' => true,
            'features' => ['appointments', 'website_builder', 'custom_domains', 'public_booking'],
            'limits' => [],
        ]);

        Subscription::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'current_period_start' => now()->subDay(),
            'current_period_end' => now()->addMonth(),
            'metadata' => [],
        ]);

        return $workspace;
    }
}
