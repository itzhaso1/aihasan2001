<?php

namespace Tests\Feature\Feature\Appointments;

use App\Models\Appointment\AppointmentBooking;
use App\Models\Appointment\AppointmentService;
use App\Models\Appointment\AppointmentSetting;
use App\Models\User;
use App\Models\Website\Website;
use App\Models\Website\WebsiteDomain;
use App\Models\Website\WebsiteTemplate;
use App\Models\Workspace;
use App\Models\WorkspaceFeatureFlag;
use App\Services\Website\TemplateService;
use App\Services\Website\WebsiteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PublicWebsiteModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    public function test_workspace_owner_can_create_customize_and_publish_website(): void
    {
        [$owner, $workspace] = $this->createWorkspaceOwner('company');
        $this->enableWebsiteFeatures($workspace);
        config()->set('website.platform_domain', 'example.test');
        app(TemplateService::class)->ensureDefaultTemplates();

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('workspace.appointments.website.store'), [
                'name' => 'Clinic Website',
                'slug' => 'clinic-site',
            ])
            ->assertRedirect();

        $website = Website::withoutGlobalScopes()->where('workspace_id', $workspace->id)->firstOrFail();
        $this->assertSame('draft', $website->status);
        $this->assertSame('clinic-site', $website->slug);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('workspace.appointments.website.templates.select', $website), [
                'template_id' => WebsiteTemplate::query()->value('id'),
            ])
            ->assertRedirect(route('workspace.appointments.website.customize', $website));

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('workspace.appointments.website.customize.update', $website), [
                'business_name' => 'Clinic Name',
                'hero_title' => 'Book with us',
                'seo_title' => 'Clinic SEO',
            ])
            ->assertRedirect();

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('workspace.appointments.website.publish', $website))
            ->assertRedirect();

        $website = $website->fresh();
        $this->assertSame('published', $website->status);
        $this->assertNotNull($website->published_at);
        $this->assertNotNull($website->primary_domain_id);
    }

    public function test_public_services_endpoint_is_workspace_scoped(): void
    {
        [$ownerA, $workspaceA] = $this->createWorkspaceOwner('company');
        [, $workspaceB] = $this->createWorkspaceOwner('company');
        $this->enableWebsiteFeatures($workspaceA);
        config()->set('website.platform_domain', 'example.test');

        $website = Website::withoutGlobalScopes()->create([
            'workspace_id' => $workspaceA->id,
            'name' => 'A Website',
            'slug' => 'a-website',
            'status' => 'published',
            'preview_token' => 'tokentest001',
            'settings' => [],
            'theme' => [],
            'metadata' => [],
        ]);

        $domain = WebsiteDomain::withoutGlobalScopes()->create([
            'workspace_id' => $workspaceA->id,
            'website_id' => $website->id,
            'domain' => 'a-website.example.test',
            'normalized_domain' => 'a-website.example.test',
            'type' => 'platform_subdomain',
            'provider' => 'platform',
            'status' => 'active',
            'verification_status' => 'verified',
            'ssl_status' => 'pending',
            'dns_status' => 'configured',
            'is_primary' => true,
            'metadata' => [],
        ]);
        $website->update(['primary_domain_id' => $domain->id]);

        AppointmentService::withoutGlobalScopes()->create([
            'workspace_id' => $workspaceA->id,
            'name' => 'Service A',
            'duration_minutes' => 30,
            'price' => 120,
            'is_active' => true,
        ]);
        AppointmentService::withoutGlobalScopes()->create([
            'workspace_id' => $workspaceB->id,
            'name' => 'Service B',
            'duration_minutes' => 30,
            'price' => 90,
            'is_active' => true,
        ]);

        $response = $this->getJson(route('public.api.services', $website->slug));

        $response->assertOk();
        $response->assertJsonFragment(['name' => 'Service A']);
        $response->assertJsonMissing(['name' => 'Service B']);
    }

    public function test_public_booking_creates_real_appointment_booking(): void
    {
        [$owner, $workspace] = $this->createWorkspaceOwner('company');
        $this->enableWebsiteFeatures($workspace);

        AppointmentSetting::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'business_type' => 'general',
            'business_label' => $workspace->name,
            'timezone' => 'Asia/Riyadh',
            'slot_interval_minutes' => 30,
            'start_hour' => '08:00:00',
            'end_hour' => '22:00:00',
            'allow_walk_in' => true,
            'automation_mode' => 'APPROVAL',
            'auto_confirm_after_payment' => true,
            'reminder_offsets' => [120],
            'metadata' => [],
        ]);

        $service = AppointmentService::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Consultation',
            'duration_minutes' => 30,
            'price' => 100,
            'is_active' => true,
            'requires_payment' => false,
        ]);

        $website = Website::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Booking Website',
            'slug' => 'booking-website',
            'status' => 'published',
            'preview_token' => 'previewtoken123',
            'settings' => ['business_name' => 'Booking Website'],
            'theme' => ['direction' => 'rtl'],
            'metadata' => [],
        ]);

        $startsAt = $this->nextOpenAppointmentSlot()->toDateTimeString();
        $response = $this->postJson(route('public.api.booking.store', $website->slug), [
            'service_id' => $service->id,
            'starts_at' => $startsAt,
            'customer_name' => 'Public Customer',
            'customer_phone' => '0551111222',
            'customer_email' => 'public@example.test',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.payment_status', 'paid');

        $booking = AppointmentBooking::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->where('customer_name', 'Public Customer')
            ->firstOrFail();

        $this->assertSame('website', $booking->source_channel);
        $this->assertSame('scheduled', $booking->appointment_status);
    }

    public function test_public_booking_rejects_past_dates(): void
    {
        [$owner, $workspace] = $this->createWorkspaceOwner('company');
        $this->enableWebsiteFeatures($workspace);

        $service = AppointmentService::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Past Validation',
            'duration_minutes' => 30,
            'price' => 50,
            'is_active' => true,
        ]);

        $website = Website::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Past Site',
            'slug' => 'past-site',
            'status' => 'published',
            'preview_token' => 'prevtokpast',
            'settings' => ['business_name' => 'Past Site'],
            'theme' => ['direction' => 'rtl'],
            'metadata' => [],
        ]);

        $response = $this->postJson(route('public.api.booking.store', $website->slug), [
            'service_id' => $service->id,
            'starts_at' => Carbon::now()->subDay()->toDateTimeString(),
            'customer_name' => 'Past Person',
        ]);

        $response->assertStatus(422);
    }

    public function test_slug_mode_home_links_to_slug_booking_page_and_booking_route_works(): void
    {
        config()->set('website.platform_domain', 'platform.test');
        [$owner, $workspace] = $this->createWorkspaceOwner('company');
        $this->enableWebsiteFeatures($workspace);

        /** @var WebsiteService $websiteService */
        $websiteService = app(WebsiteService::class);
        /** @var TemplateService $templateService */
        $templateService = app(TemplateService::class);

        $website = $websiteService->createWebsite($workspace, [
            'name' => 'Slug Booking Site',
            'slug' => 'hsn-khald-hsn-alhsynat',
        ]);
        $template = $templateService->listTemplates()->firstOrFail();
        $websiteService->selectTemplate($website, $template->id);
        $websiteService->updateSettings($website, [
            'business_name' => 'Slug Booking Site',
            'hero_title' => 'Book now',
        ]);
        $website = $websiteService->publish($website->refresh());

        $home = $this->get('/public/'.$website->slug);
        $home->assertOk();
        $home->assertSee('/public/'.$website->slug.'/booking', false);
        $home->assertDontSee('href="'.url('/booking').'"', false);

        $booking = $this->get('/public/'.$website->slug.'/booking');
        $booking->assertOk();
        $booking->assertSee('تأكيد الحجز', false);
    }

    public function test_unpublished_slug_booking_page_returns_not_found(): void
    {
        [$owner, $workspace] = $this->createWorkspaceOwner('company');
        $this->enableWebsiteFeatures($workspace);

        $website = Website::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Draft Site',
            'slug' => 'draft-booking-site',
            'status' => 'draft',
            'preview_token' => 'draftpreviewtoken',
            'settings' => ['business_name' => 'Draft Site'],
            'theme' => ['direction' => 'rtl'],
            'metadata' => [],
        ]);

        $this->get('/public/'.$website->slug.'/booking')->assertNotFound();
    }

    /**
     * @return array{0: User, 1: Workspace}
     */
    private function createWorkspaceOwner(string $workspaceType): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create([
            'owner_user_id' => $user->id,
            'type' => $workspaceType,
            'status' => 'active',
        ]);

        $workspace->users()->attach($user->id, [
            'membership_role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        return [$user, $workspace];
    }

    private function enableWebsiteFeatures(Workspace $workspace): void
    {
        foreach (['website_builder', 'custom_domains', 'public_booking', 'appointments'] as $feature) {
            WorkspaceFeatureFlag::withoutGlobalScopes()->updateOrCreate(
                ['workspace_id' => $workspace->id, 'feature_key' => $feature],
                ['workspace_id' => $workspace->id, 'feature_key' => $feature, 'enabled' => true, 'source' => 'manual']
            );
        }
    }
}
