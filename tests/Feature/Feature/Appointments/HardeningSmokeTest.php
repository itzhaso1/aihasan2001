<?php

namespace Tests\Feature\Feature\Appointments;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Website\Website;
use App\Models\Workspace;
use App\Services\Website\WebsiteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HardeningSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_website_with_platform_domain(): void
    {
        config()->set('website.platform_domain', 'platform.test');

        try {
            $user = User::factory()->create();
            $workspace = Workspace::factory()->create([
                'owner_user_id' => $user->id,
                'type' => 'company',
            ]);
            $workspace->users()->attach($user->id, [
                'membership_role' => 'owner',
                'status' => 'active',
                'joined_at' => now(),
            ]);

            $plan = Plan::query()->create([
                'code' => 'plan-'.uniqid(),
                'name' => 'Plan',
                'workspace_type' => 'company',
                'billing_period' => 'monthly',
                'currency' => 'USD',
                'price' => 1,
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

            $website = app(WebsiteService::class)->createWebsite($workspace, [
                'name' => 'Smoke Site',
                'slug' => 'smoke-site',
            ]);

            $template = app(\App\Services\Website\TemplateService::class)->listTemplates()->firstOrFail();
            app(WebsiteService::class)->selectTemplate($website, $template->id);
            app(WebsiteService::class)->updateSettings($website, [
                'business_name' => 'Smoke',
                'hero_title' => 'Book',
            ]);
            $published = app(WebsiteService::class)->publish($website->refresh());

            $service = \App\Models\Appointment\AppointmentService::withoutGlobalScopes()->create([
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

            $this->assertSame('published', $published->status);
            $this->assertNotNull($service->id);
        } catch (\Throwable $e) {
            $this->fail($e->getMessage()."\n".$e->getFile().':'.$e->getLine()."\n".$e->getTraceAsString());
        }
    }
}
