<?php

namespace Tests\Feature\Feature\Appointments;

use App\Models\Appointment\AppointmentBooking;
use App\Models\Appointment\AppointmentService;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Website\Website;
use App\Models\Workspace;
use App\Services\Website\TemplateService;
use App\Services\Website\WebsiteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PublicBookingApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    public function test_public_booking_api_creates_real_booking_for_published_website(): void
    {
        config()->set('website.platform_domain', 'platform.test');
        [$workspace] = $this->createWorkspaceWithSubscription('company');
        $website = $this->createPublishedWebsite($workspace);

        $service = AppointmentService::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => 'General Consultation',
            'description' => 'Initial consultation',
            'duration_minutes' => 30,
            'price' => 100,
            'is_active' => true,
            'requires_confirmation' => false,
            'requires_payment' => false,
            'payment_mode' => 'postpaid',
            'approval_required' => false,
            'metadata' => [],
        ]);

        $servicesResponse = $this->getJson(route('public.api.services', ['website' => $website->slug]));
        $servicesResponse->assertOk();
        $servicesResponse->assertJsonPath('data.0.id', $service->id);

        $date = Carbon::now('Asia/Riyadh')->next(Carbon::MONDAY)->toDateString();
        $availabilityResponse = $this->getJson(route('public.api.availability', [
            'website' => $website->slug,
            'service_id' => $service->id,
            'date' => $date,
        ]));
        $availabilityResponse->assertOk();

        $firstSlot = data_get($availabilityResponse->json(), 'data.slots.0.starts_at');
        $this->assertNotNull($firstSlot, 'Expected at least one available slot for public booking API test.');

        $bookingResponse = $this->postJson(route('public.api.booking.store', ['website' => $website->slug]), [
            'service_id' => $service->id,
            'starts_at' => $firstSlot,
            'customer_name' => 'Guest Customer',
            'customer_phone' => '0500000000',
            'customer_email' => 'guest@example.com',
            'notes' => 'Testing public booking',
        ]);

        $bookingResponse->assertCreated();

        $this->assertDatabaseHas('appointment_bookings', [
            'workspace_id' => $workspace->id,
            'service_id' => $service->id,
            'customer_name' => 'Guest Customer',
            'source_channel' => 'website',
        ]);

        $booking = AppointmentBooking::withoutGlobalScopes()->latest('id')->firstOrFail();
        $this->assertSame('paid', $booking->payment_status);
    }

    public function test_public_booking_rejects_second_concurrent_unstaffed_booking_for_same_slot(): void
    {
        config()->set('website.platform_domain', 'platform.test');
        config()->set('cache.default', 'array');
        [$workspace] = $this->createWorkspaceWithSubscription('company');
        $website = $this->createPublishedWebsite($workspace);

        $service = AppointmentService::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Any Staff Service',
            'description' => 'Capacity test',
            'duration_minutes' => 30,
            'price' => 50,
            'is_active' => true,
            'requires_confirmation' => false,
            'requires_payment' => false,
            'payment_mode' => 'postpaid',
            'approval_required' => false,
            'metadata' => [],
        ]);

        // No staff rows => capacity falls back to 1 for unstaffed bookings.
        $date = Carbon::now('Asia/Riyadh')->next(Carbon::MONDAY)->toDateString();
        $availabilityResponse = $this->getJson(route('public.api.availability', [
            'website' => $website->slug,
            'service_id' => $service->id,
            'date' => $date,
        ]));
        $availabilityResponse->assertOk();
        $firstSlot = data_get($availabilityResponse->json(), 'data.slots.0.starts_at');
        $this->assertNotNull($firstSlot);

        $first = $this->postJson(route('public.api.booking.store', ['website' => $website->slug]), [
            'service_id' => $service->id,
            'starts_at' => $firstSlot,
            'customer_name' => 'Customer A',
            'customer_phone' => '0501111111',
        ]);
        $first->assertCreated();

        $second = $this->postJson(route('public.api.booking.store', ['website' => $website->slug]), [
            'service_id' => $service->id,
            'starts_at' => $firstSlot,
            'customer_name' => 'Customer B',
            'customer_phone' => '0502222222',
        ]);
        $second->assertStatus(422);

        $this->assertSame(1, AppointmentBooking::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->where('service_id', $service->id)
            ->count());
    }

    public function test_public_booking_rejects_past_slots_and_cross_workspace_service_ids(): void
    {
        config()->set('website.platform_domain', 'platform.test');
        [$workspaceA] = $this->createWorkspaceWithSubscription('company');
        [$workspaceB] = $this->createWorkspaceWithSubscription('company');
        $websiteA = $this->createPublishedWebsite($workspaceA);

        $serviceA = AppointmentService::withoutGlobalScopes()->create([
            'workspace_id' => $workspaceA->id,
            'name' => 'Workspace A Service',
            'description' => null,
            'duration_minutes' => 30,
            'price' => 80,
            'is_active' => true,
            'requires_confirmation' => false,
            'requires_payment' => false,
            'payment_mode' => 'postpaid',
            'approval_required' => false,
            'metadata' => [],
        ]);
        $serviceB = AppointmentService::withoutGlobalScopes()->create([
            'workspace_id' => $workspaceB->id,
            'name' => 'Workspace B Service',
            'description' => null,
            'duration_minutes' => 30,
            'price' => 90,
            'is_active' => true,
            'requires_confirmation' => false,
            'requires_payment' => false,
            'payment_mode' => 'postpaid',
            'approval_required' => false,
            'metadata' => [],
        ]);

        $crossWorkspace = $this->postJson(route('public.api.booking.store', ['website' => $websiteA->slug]), [
            'service_id' => $serviceB->id,
            'starts_at' => Carbon::now('UTC')->addDay()->startOfHour()->toIso8601String(),
            'customer_name' => 'Cross Tenant',
        ]);
        $crossWorkspace->assertStatus(422);

        $pastAttempt = $this->postJson(route('public.api.booking.store', ['website' => $websiteA->slug]), [
            'service_id' => $serviceA->id,
            'starts_at' => Carbon::now('UTC')->subDay()->toIso8601String(),
            'customer_name' => 'Past Slot',
        ]);
        $pastAttempt->assertStatus(422);
    }

    private function createPublishedWebsite(Workspace $workspace): Website
    {
        /** @var WebsiteService $websiteService */
        $websiteService = app(WebsiteService::class);
        /** @var TemplateService $templateService */
        $templateService = app(TemplateService::class);

        $website = $websiteService->createWebsite($workspace, [
            'name' => 'Workspace Website '.$workspace->id,
            'slug' => 'workspace-'.$workspace->id,
        ]);

        $template = $templateService->listTemplates()->firstOrFail();
        $websiteService->selectTemplate($website, $template->id);
        $websiteService->updateSettings($website, [
            'business_name' => 'Workspace '.$workspace->id,
            'hero_title' => 'Book now',
            'hero_description' => 'Public booking',
        ]);

        return $websiteService->publish($website->refresh());
    }

    /**
     * @return array{0: Workspace, 1: User}
     */
    private function createWorkspaceWithSubscription(string $workspaceType): array
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

        return [$workspace, $user];
    }
}
