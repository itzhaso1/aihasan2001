<?php

namespace Tests\Feature\Feature\Appointments;

use App\Models\Appointment\AppointmentBooking;
use App\Models\Appointment\AppointmentService;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Website\Website;
use App\Models\Website\WebsiteTemplate;
use App\Models\Workspace;
use App\Services\Website\TemplateService;
use App\Services\Website\WebsiteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

class WebsiteBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    public function test_workspace_owner_can_create_select_template_and_publish_website(): void
    {
        config()->set('website.platform_domain', 'yourplatform.test');
        [$owner, $workspace] = $this->createWorkspaceOwnerWithWebsiteFeatures('company');

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('workspace.appointments.website.store'), [
                'name' => 'Clinic Public Website',
                'slug' => 'clinic-public',
            ])
            ->assertRedirect();

        $website = Website::withoutGlobalScopes()->where('workspace_id', $workspace->id)->firstOrFail();
        $this->assertSame('clinic-public', $website->slug);
        $this->assertNotNull($website->primaryDomain);
        $this->assertSame('active', $website->primaryDomain->status);

        app(TemplateService::class)->ensureDefaultTemplates();
        $template = WebsiteTemplate::query()->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('workspace.appointments.website.templates.select', $website), [
                'template_id' => $template->id,
            ])
            ->assertRedirect(route('workspace.appointments.website.customize', $website));

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('workspace.appointments.website.publish', $website))
            ->assertRedirect();

        $this->assertSame('published', $website->fresh()->status);
    }

    public function test_public_website_api_creates_real_booking_and_rejects_past_slot(): void
    {
        config()->set('website.platform_domain', 'yourplatform.test');

        [, $workspace] = $this->createWorkspaceOwnerWithWebsiteFeatures('company');
        app(TemplateService::class)->ensureDefaultTemplates();

        $website = Website::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Public Clinic Site',
            'slug' => 'public-clinic',
            'template_id' => WebsiteTemplate::query()->firstOrFail()->id,
            'status' => 'published',
            'preview_token' => 'previewtoken123',
            'settings' => ['business_name' => 'Public Clinic'],
            'theme' => ['direction' => 'rtl'],
            'metadata' => [],
        ]);

        AppointmentService::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Consultation',
            'description' => 'General consultation',
            'duration_minutes' => 30,
            'price' => 100,
            'is_active' => true,
            'requires_payment' => false,
            'payment_mode' => 'postpaid',
        ]);

        $service = AppointmentService::withoutGlobalScopes()->where('workspace_id', $workspace->id)->firstOrFail();

        $servicesResponse = $this->getJson(route('public.api.services', $website->slug));
        $servicesResponse->assertOk();
        $servicesResponse->assertJsonFragment(['id' => $service->id, 'name' => 'Consultation']);

        $pastResponse = $this->postJson(route('public.api.booking.store', $website->slug), [
            'service_id' => $service->id,
            'starts_at' => Carbon::now()->subHour()->toDateTimeString(),
            'customer_name' => 'Past Customer',
            'customer_phone' => '0500001111',
        ]);
        $pastResponse->assertStatus(422);

        $futureStart = $this->nextOpenAppointmentSlot('Asia/Riyadh', 11, 0);
        $createResponse = $this->postJson(route('public.api.booking.store', $website->slug), [
            'service_id' => $service->id,
            'starts_at' => $futureStart->toDateTimeString(),
            'customer_name' => 'Future Customer',
            'customer_phone' => '0500002222',
            'customer_email' => 'future@example.com',
        ]);

        $createResponse->assertStatus(201)->assertJsonStructure([
            'data' => ['booking_number', 'public_token', 'appointment_status', 'payment_status'],
        ]);

        $booking = AppointmentBooking::withoutGlobalScopes()->where('workspace_id', $workspace->id)->firstOrFail();
        $this->assertSame('website', $booking->source_channel);
        $this->assertSame('scheduled', $booking->appointment_status);
    }

    public function test_public_booking_rejects_service_from_another_workspace(): void
    {
        app(TemplateService::class)->ensureDefaultTemplates();

        [, $workspaceA] = $this->createWorkspaceOwnerWithWebsiteFeatures('company');
        [, $workspaceB] = $this->createWorkspaceOwnerWithWebsiteFeatures('store');

        $websiteA = Website::withoutGlobalScopes()->create([
            'workspace_id' => $workspaceA->id,
            'name' => 'Site A',
            'slug' => 'site-a',
            'template_id' => WebsiteTemplate::query()->firstOrFail()->id,
            'status' => 'published',
            'preview_token' => 'preview-a',
            'settings' => ['business_name' => 'Site A'],
            'theme' => ['direction' => 'rtl'],
            'metadata' => [],
        ]);

        $serviceB = AppointmentService::withoutGlobalScopes()->create([
            'workspace_id' => $workspaceB->id,
            'name' => 'Service B',
            'duration_minutes' => 30,
            'price' => 50,
            'is_active' => true,
            'requires_payment' => false,
            'payment_mode' => 'postpaid',
        ]);

        $response = $this->postJson(route('public.api.booking.store', $websiteA->slug), [
            'service_id' => $serviceB->id,
            'starts_at' => Carbon::now()->addDays(3)->setTime(10, 0)->toDateTimeString(),
            'customer_name' => 'Cross Tenant',
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, AppointmentBooking::withoutGlobalScopes()->where('workspace_id', $workspaceA->id)->count());
    }

    public function test_publish_requires_active_or_verified_domain_when_platform_subdomain_not_configured(): void
    {
        config()->set('website.platform_domain', '');
        [, $workspace] = $this->createWorkspaceOwnerWithWebsiteFeatures('company');

        /** @var WebsiteService $websiteService */
        $websiteService = app(WebsiteService::class);
        $website = $websiteService->createWebsite($workspace, [
            'name' => 'No Domain Website',
            'slug' => 'no-domain-site',
        ]);
        $template = app(TemplateService::class)->listTemplates()->firstOrFail();
        $websiteService->selectTemplate($website, $template->id);
        $websiteService->updateSettings($website, ['business_name' => 'No Domain Business']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('At least one verified or active domain is required for publishing.');

        $websiteService->publish($website->fresh());
    }

    /**
     * @return array{0: User, 1: Workspace}
     */
    private function createWorkspaceOwnerWithWebsiteFeatures(string $workspaceType): array
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

        return [$user, $workspace];
    }
}
