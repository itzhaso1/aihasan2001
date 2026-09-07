<?php

namespace Tests\Feature\Feature\Appointments;

use App\Models\Appointment\AppointmentBooking;
use App\Models\Appointment\AppointmentReminder;
use App\Models\Appointment\AppointmentRequest;
use App\Models\Appointment\AppointmentService;
use App\Models\Appointment\AppointmentSetting;
use App\Models\Appointment\AppointmentStaff;
use App\Models\Payment;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Appointments\AppointmentAiActionService;
use App\Services\Appointments\AppointmentBillingService;
use App\Services\Appointments\AppointmentReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

class AppointmentModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    public function test_appointments_dashboard_is_isolated_and_accessible(): void
    {
        [$user, $workspace] = $this->createWorkspaceOwner('company');

        $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('workspace.appointments.dashboard'))
            ->assertOk()
            ->assertSee('Overview');
    }

    public function test_create_booking_and_prevent_time_overlap_for_same_staff(): void
    {
        [$owner, $workspace] = $this->createWorkspaceOwner('company');
        $employee = User::factory()->create();
        $workspace->users()->attach($employee->id, [
            'membership_role' => 'agent',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('workspace.appointments.services.store'), [
                'name' => 'كشف طبي',
                'duration_minutes' => 30,
                'price' => 100,
            ])
            ->assertRedirect();

        $service = AppointmentService::query()->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('workspace.appointments.staff.store'), [
                'user_id' => $employee->id,
                'name' => 'د. أحمد',
                'role' => 'طبيب',
            ])
            ->assertRedirect();

        $staff = AppointmentStaff::query()->firstOrFail();

        $starts = Carbon::now()->next(Carbon::MONDAY)->setTime(10, 0, 0);
        $ends = $starts->copy()->addMinutes(30);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('workspace.appointments.bookings.store'), [
                'service_id' => $service->id,
                'staff_id' => $staff->id,
                'customer_name' => 'عميل أول',
                'customer_phone' => '0500001111',
                'starts_at' => $starts->toDateTimeString(),
                'ends_at' => $ends->toDateTimeString(),
                'status' => 'scheduled',
                'source' => 'dashboard',
            ])
            ->assertRedirect();

        $this->assertSame(1, AppointmentBooking::query()->count());

        $overlapResponse = $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('workspace.appointments.bookings.store'), [
                'service_id' => $service->id,
                'staff_id' => $staff->id,
                'customer_name' => 'عميل ثاني',
                'customer_phone' => '0500002222',
                'starts_at' => $starts->copy()->addMinutes(10)->toDateTimeString(),
                'ends_at' => $ends->copy()->addMinutes(10)->toDateTimeString(),
                'status' => 'scheduled',
                'source' => 'dashboard',
            ]);

        $overlapResponse->assertRedirect();
        $overlapResponse->assertSessionHas('error');
        $this->assertSame(1, AppointmentBooking::query()->count());
    }

    public function test_appointments_booking_isolation_between_workspaces(): void
    {
        [$ownerA, $workspaceA] = $this->createWorkspaceOwner('company');
        [, $workspaceB] = $this->createWorkspaceOwner('store');

        $serviceB = AppointmentService::withoutGlobalScopes()->create([
            'workspace_id' => $workspaceB->id,
            'name' => 'خدمة مساحة B',
            'duration_minutes' => 20,
            'price' => 50,
            'is_active' => true,
        ]);

        $bookingB = AppointmentBooking::withoutGlobalScopes()->create([
            'workspace_id' => $workspaceB->id,
            'booking_number' => 'APT-20300101-0001',
            'service_id' => $serviceB->id,
            'customer_name' => 'عميل مساحة B',
            'starts_at' => '2030-01-01 10:00:00',
            'ends_at' => '2030-01-01 10:30:00',
            'status' => 'scheduled',
            'source' => 'dashboard',
        ]);

        $this->actingAs($ownerA)
            ->withSession(['current_workspace_id' => $workspaceA->id])
            ->post(route('workspace.appointments.bookings.status', $bookingB), [
                'status' => 'completed',
            ])
            ->assertNotFound();
    }

    public function test_appointment_request_can_be_approved_and_converted_to_booking(): void
    {
        [$owner, $workspace] = $this->createWorkspaceOwner('company');

        $service = AppointmentService::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => 'استشارة',
            'duration_minutes' => 45,
            'price' => 0,
            'is_active' => true,
            'requires_payment' => false,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('workspace.appointments.requests.store'), [
                'customer_name' => 'عميل اختبار',
                'customer_phone' => '0551111222',
                'requested_service_id' => $service->id,
                'requested_date' => Carbon::now()->next(Carbon::MONDAY)->toDateString(),
                'requested_time' => '11:00',
                'source' => 'dashboard',
            ])
            ->assertRedirect();

        $appointmentRequest = AppointmentRequest::query()->firstOrFail();

        $approvedDate = Carbon::now()->next(Carbon::MONDAY)->setTime(11, 0);
        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('workspace.appointments.requests.approve', $appointmentRequest), [
                'service_id' => $service->id,
                'starts_at' => $approvedDate->toDateTimeString(),
                'ends_at' => $approvedDate->copy()->addMinutes(45)->toDateTimeString(),
            ])
            ->assertRedirect();

        $booking = AppointmentBooking::query()->firstOrFail();
        $this->assertSame($appointmentRequest->id, $booking->request_id);
        $this->assertSame('approved', $appointmentRequest->fresh()->status);
        $this->assertSame('scheduled', $booking->appointment_status);
        $this->assertSame('paid', $booking->payment_status);
    }

    public function test_paid_service_generates_payment_link_and_syncs_after_payment_confirmation(): void
    {
        [$owner, $workspace] = $this->createWorkspaceOwner('company');

        $service = AppointmentService::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => 'جلسة علاج',
            'duration_minutes' => 30,
            'price' => 150,
            'is_active' => true,
            'requires_payment' => true,
            'payment_mode' => 'full',
        ]);

        $bookingDate = Carbon::now()->next(Carbon::TUESDAY)->setTime(16, 0);
        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('workspace.appointments.bookings.store'), [
                'service_id' => $service->id,
                'customer_name' => 'عميل دفع',
                'customer_phone' => '0553333444',
                'starts_at' => $bookingDate->toDateTimeString(),
                'ends_at' => $bookingDate->copy()->addMinutes(30)->toDateTimeString(),
                'status' => 'scheduled',
                'source' => 'dashboard',
            ])
            ->assertRedirect();

        $booking = AppointmentBooking::query()->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('workspace.appointments.bookings.payment-link', $booking))
            ->assertRedirect();

        $booking = $booking->fresh();
        $this->assertNotNull($booking->payment_link);
        $this->assertSame('pending', $booking->payment_status);
        $this->assertNotNull($booking->order_id);

        $payment = Payment::query()->whereKey($booking->latest_payment_id)->firstOrFail();
        $payment->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        app(AppointmentBillingService::class)->syncAfterPaymentConfirmed($payment->fresh());

        $booking = $booking->fresh();
        $this->assertSame('paid', $booking->payment_status);
        $this->assertSame('confirmed', $booking->appointment_status);
    }

    public function test_ai_action_create_request_respects_workspace_isolation(): void
    {
        [$ownerA, $workspaceA] = $this->createWorkspaceOwner('company');
        [, $workspaceB] = $this->createWorkspaceOwner('store');

        app(AppointmentAiActionService::class)->execute(
            workspace: $workspaceA,
            action: 'create_appointment_request',
            payload: [
                'customer_name' => 'AI Customer',
                'customer_phone' => '0567777888',
                'source' => 'ai_chat',
            ],
            actor: $ownerA,
        );

        $this->assertSame(1, AppointmentRequest::withoutGlobalScopes()->where('workspace_id', $workspaceA->id)->count());
        $this->assertSame(0, AppointmentRequest::withoutGlobalScopes()->where('workspace_id', $workspaceB->id)->count());
    }

    public function test_booking_end_time_is_calculated_from_service_duration_using_workspace_timezone(): void
    {
        [$owner, $workspace] = $this->createWorkspaceOwner('company');

        AppointmentSetting::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'business_type' => 'general',
            'business_label' => 'Test Workspace',
            'timezone' => 'Asia/Riyadh',
            'slot_interval_minutes' => 30,
            'start_hour' => '08:00:00',
            'end_hour' => '22:00:00',
            'allow_walk_in' => true,
            'automation_mode' => 'APPROVAL',
            'auto_confirm_after_payment' => true,
            'reminder_offsets' => [1440, 120],
            'metadata' => [
                'business_hours' => [
                    'sun' => ['closed' => false, 'ranges' => [['start' => '09:00', 'end' => '20:00']]],
                    'mon' => ['closed' => false, 'ranges' => [['start' => '09:00', 'end' => '20:00']]],
                    'tue' => ['closed' => false, 'ranges' => [['start' => '09:00', 'end' => '20:00']]],
                    'wed' => ['closed' => false, 'ranges' => [['start' => '09:00', 'end' => '20:00']]],
                    'thu' => ['closed' => false, 'ranges' => [['start' => '09:00', 'end' => '20:00']]],
                    'fri' => ['closed' => false, 'ranges' => [['start' => '09:00', 'end' => '20:00']]],
                    'sat' => ['closed' => false, 'ranges' => [['start' => '09:00', 'end' => '20:00']]],
                ],
                'booking_rules' => [
                    'min_booking_notice_minutes' => 0,
                    'max_advance_booking_days' => 365,
                    'slot_interval_minutes' => 30,
                    'buffer_minutes' => 0,
                    'max_bookings_per_day' => 0,
                ],
                'cancellation_rules' => [
                    'minimum_notice_hours' => 0,
                    'cancellation_window_hours' => 0,
                    'reschedule_window_hours' => 0,
                    'maximum_reschedules' => 3,
                ],
            ],
        ]);

        $service = AppointmentService::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => 'خدمة 30 دقيقة',
            'duration_minutes' => 30,
            'price' => 100,
            'is_active' => true,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('workspace.appointments.bookings.store'), [
                'service_id' => $service->id,
                'customer_name' => 'عميل التوقيت',
                'customer_phone' => '0500009999',
                'starts_at' => Carbon::now('Asia/Riyadh')->next(Carbon::MONDAY)->setTime(18, 0)->toDateTimeString(),
                'status' => 'scheduled',
                'source' => 'dashboard',
            ])
            ->assertRedirect();

        $booking = AppointmentBooking::query()->firstOrFail();
        $this->assertEquals(30, $booking->starts_at->diffInMinutes($booking->ends_at));
        $this->assertSame('18:00', $booking->starts_at->copy()->timezone('Asia/Riyadh')->format('H:i'));
        $this->assertSame('18:30', $booking->ends_at->copy()->timezone('Asia/Riyadh')->format('H:i'));
    }

    public function test_appointments_navigation_pages_are_split_and_accessible(): void
    {
        [$owner, $workspace] = $this->createWorkspaceOwner('company');

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('workspace.appointments.overview'))
            ->assertOk()
            ->assertSee('Overview');

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('workspace.appointments.bookings.index'))
            ->assertOk()
            ->assertSee('Bookings');

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('workspace.appointments.calendar.index'))
            ->assertOk()
            ->assertSee('Calendar');

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('workspace.appointments.requests.index'))
            ->assertOk()
            ->assertSee('Appointment Requests');

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('workspace.appointments.customers.index'))
            ->assertOk()
            ->assertSee('Customers');

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('workspace.appointments.settings.index'))
            ->assertOk()
            ->assertSee('Settings');
    }

    public function test_reschedule_booking_prevents_staff_conflict(): void
    {
        [$owner, $workspace] = $this->createWorkspaceOwner('company');

        $service = AppointmentService::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => 'جلسة متابعة',
            'duration_minutes' => 30,
            'price' => 0,
            'is_active' => true,
        ]);
        $staff = AppointmentStaff::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => 'موظف 1',
            'is_active' => true,
        ]);

        $day = Carbon::now('Asia/Riyadh')->next(Carbon::MONDAY);
        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('workspace.appointments.bookings.store'), [
                'service_id' => $service->id,
                'staff_id' => $staff->id,
                'customer_name' => 'عميل أول',
                'customer_phone' => '0501234567',
                'starts_at' => $day->copy()->setTime(10, 0)->toDateTimeString(),
                'status' => 'scheduled',
                'source' => 'dashboard',
            ])
            ->assertRedirect();

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('workspace.appointments.bookings.store'), [
                'service_id' => $service->id,
                'staff_id' => $staff->id,
                'customer_name' => 'عميل ثاني',
                'customer_phone' => '0501234568',
                'starts_at' => $day->copy()->setTime(11, 0)->toDateTimeString(),
                'status' => 'scheduled',
                'source' => 'dashboard',
            ])
            ->assertRedirect();

        $bookingToMove = AppointmentBooking::query()->orderByDesc('id')->firstOrFail();
        $response = $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('workspace.appointments.bookings.reschedule', $bookingToMove), [
                'starts_at' => $day->copy()->setTime(10, 15)->toDateTimeString(),
                'staff_id' => $staff->id,
                'reason' => 'اختبار منع التعارض',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_reminder_scheduling_is_idempotent_for_same_booking_slot(): void
    {
        [$owner, $workspace] = $this->createWorkspaceOwner('company');
        $service = AppointmentService::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => 'خدمة تذكير',
            'duration_minutes' => 30,
            'price' => 0,
            'is_active' => true,
        ]);
        $booking = AppointmentBooking::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'booking_number' => 'APT-20300101-0009',
            'service_id' => $service->id,
            'customer_name' => 'عميل التذكير',
            'starts_at' => Carbon::now('UTC')->addDays(2),
            'ends_at' => Carbon::now('UTC')->addDays(2)->addMinutes(30),
            'status' => 'scheduled',
            'appointment_status' => 'scheduled',
            'payment_status' => 'unpaid',
            'source' => 'dashboard',
            'source_channel' => 'dashboard',
            'booked_by' => $owner->id,
        ]);

        $serviceObj = app(AppointmentReminderService::class);
        $serviceObj->scheduleForBooking($booking, ['in_app'], [120]);
        $serviceObj->scheduleForBooking($booking, ['in_app'], [120]);

        $this->assertSame(1, AppointmentReminder::withoutGlobalScopes()->where('booking_id', $booking->id)->count());
    }

    public function test_ai_create_booking_is_blocked_in_approval_mode(): void
    {
        [$owner, $workspace] = $this->createWorkspaceOwner('company');
        AppointmentSetting::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'business_type' => 'general',
            'business_label' => 'Workspace',
            'timezone' => 'Asia/Riyadh',
            'slot_interval_minutes' => 30,
            'start_hour' => '08:00:00',
            'end_hour' => '22:00:00',
            'allow_walk_in' => true,
            'automation_mode' => 'APPROVAL',
            'auto_confirm_after_payment' => true,
            'reminder_offsets' => [1440, 120],
            'metadata' => [],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('APPROVAL');
        app(AppointmentAiActionService::class)->execute(
            workspace: $workspace,
            action: 'create_booking',
            payload: [],
            actor: $owner
        );
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
        ]);

        $workspace->users()->attach($user->id, [
            'membership_role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->enableWorkspaceFeature($workspace, 'appointments');

        return [$user, $workspace];
    }
}
