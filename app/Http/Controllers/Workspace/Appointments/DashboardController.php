<?php

namespace App\Http\Controllers\Workspace\Appointments;

use App\Models\Appointment\AppointmentBooking;
use App\Models\Appointment\AppointmentResource;
use App\Models\Appointment\AppointmentService as AppointmentServiceModel;
use App\Models\Appointment\AppointmentStaff;
use App\Services\Appointments\AppointmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DashboardController extends AppointmentsBaseController
{
    public function __construct(private readonly AppointmentService $appointmentService) {}

    public function updateSettings(Request $request): RedirectResponse
    {
        $this->authorizeAppointments($request, 'appointments.settings.manage');
        $workspace = $this->currentWorkspace();

        $validated = $request->validate([
            'business_type' => ['required', Rule::in(['pharmacy', 'clinic', 'hospital', 'salon', 'law_firm', 'consulting', 'education', 'maintenance', 'photography', 'training', 'general', 'other'])],
            'business_label' => ['nullable', 'string', 'max:255'],
            'timezone' => ['required', 'string', 'max:64'],
            'slot_interval_minutes' => ['required', 'integer', 'min:5', 'max:240'],
            'start_hour' => ['required', 'date_format:H:i'],
            'end_hour' => ['required', 'date_format:H:i', 'after:start_hour'],
            'allow_walk_in' => ['nullable', 'boolean'],
            'automation_mode' => ['required', Rule::in(['AUTO', 'APPROVAL', 'MANUAL'])],
            'auto_confirm_after_payment' => ['nullable', 'boolean'],
            'reminder_offsets' => ['nullable', 'string', 'max:255'],
            'reminder_channels' => ['nullable', 'array'],
            'reminder_channels.*' => ['string', Rule::in(['in_app', 'email', 'whatsapp', 'sms'])],
            'business_hours' => ['nullable', 'array'],
            'business_hours.*.closed' => ['nullable', 'boolean'],
            'business_hours.*.ranges' => ['nullable', 'array'],
            'business_hours.*.ranges.*.start' => ['nullable', 'date_format:H:i'],
            'business_hours.*.ranges.*.end' => ['nullable', 'date_format:H:i'],
            'booking_rules.min_booking_notice_minutes' => ['nullable', 'integer', 'min:0'],
            'booking_rules.max_advance_booking_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'booking_rules.slot_interval_minutes' => ['nullable', 'integer', 'min:5', 'max:240'],
            'booking_rules.buffer_minutes' => ['nullable', 'integer', 'min:0', 'max:180'],
            'booking_rules.max_bookings_per_day' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'cancellation_rules.minimum_notice_hours' => ['nullable', 'integer', 'min:0', 'max:720'],
            'cancellation_rules.cancellation_window_hours' => ['nullable', 'integer', 'min:0', 'max:720'],
            'cancellation_rules.reschedule_window_hours' => ['nullable', 'integer', 'min:0', 'max:720'],
            'cancellation_rules.maximum_reschedules' => ['nullable', 'integer', 'min:0', 'max:50'],
        ]);

        $reminderOffsets = collect(explode(',', (string) ($validated['reminder_offsets'] ?? '1440,120')))
            ->map(fn (string $value): int => max(1, (int) trim($value)))
            ->filter(fn (int $value): bool => $value > 0)
            ->values()
            ->all();

        $this->appointmentService->updateSetting($workspace, $validated + [
            'allow_walk_in' => $request->boolean('allow_walk_in'),
            'auto_confirm_after_payment' => $request->boolean('auto_confirm_after_payment', true),
            'reminder_offsets' => $reminderOffsets === [] ? [1440, 120] : $reminderOffsets,
            'metadata' => [
                'business_hours' => $validated['business_hours'] ?? [],
                'booking_rules' => $validated['booking_rules'] ?? [],
                'cancellation_rules' => $validated['cancellation_rules'] ?? [],
                'reminder_channels' => $validated['reminder_channels'] ?? ['in_app'],
            ],
        ]);

        return back()->with('success', 'تم تحديث إعدادات المواعيد.');
    }

    public function storeService(Request $request): RedirectResponse
    {
        $this->authorizeAppointments($request, 'appointments.settings.manage');
        $workspace = $this->currentWorkspace();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:600'],
            'price' => ['required', 'numeric', 'min:0'],
            'color' => ['nullable', 'string', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
            'requires_confirmation' => ['nullable', 'boolean'],
            'requires_payment' => ['nullable', 'boolean'],
            'payment_mode' => ['nullable', Rule::in(['full', 'deposit', 'postpaid'])],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'approval_required' => ['nullable', 'boolean'],
            'staff_ids' => ['nullable', 'array'],
            'staff_ids.*' => ['integer'],
        ]);

        $this->appointmentService->createService($workspace, $validated + [
            'is_active' => $request->boolean('is_active', true),
            'requires_confirmation' => $request->boolean('requires_confirmation'),
            'requires_payment' => $request->boolean('requires_payment'),
            'approval_required' => $request->boolean('approval_required'),
        ]);

        return back()->with('success', 'تمت إضافة خدمة الموعد.');
    }

    public function updateService(Request $request, AppointmentServiceModel $service): RedirectResponse
    {
        $this->authorizeAppointments($request, 'appointments.settings.manage');
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:600'],
            'price' => ['required', 'numeric', 'min:0'],
            'color' => ['nullable', 'string', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
            'requires_confirmation' => ['nullable', 'boolean'],
            'requires_payment' => ['nullable', 'boolean'],
            'payment_mode' => ['nullable', Rule::in(['full', 'deposit', 'postpaid'])],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'approval_required' => ['nullable', 'boolean'],
            'staff_ids' => ['nullable', 'array'],
            'staff_ids.*' => ['integer'],
        ]);

        $this->appointmentService->updateService($service, $validated + [
            'is_active' => $request->boolean('is_active', true),
            'requires_confirmation' => $request->boolean('requires_confirmation'),
            'requires_payment' => $request->boolean('requires_payment'),
            'approval_required' => $request->boolean('approval_required'),
        ]);

        return back()->with('success', 'تم تحديث الخدمة.');
    }

    public function storeStaff(Request $request): RedirectResponse
    {
        $this->authorizeAppointments($request, 'appointments.settings.manage');
        $workspace = $this->currentWorkspace();
        $validated = $request->validate([
            'user_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
            'working_days' => ['nullable', 'array'],
            'working_days.*' => ['string', Rule::in(['sat', 'sun', 'mon', 'tue', 'wed', 'thu', 'fri'])],
            'working_hours' => ['nullable', 'array'],
            'vacation_periods' => ['nullable', 'array'],
            'staff_permissions' => ['nullable', 'array'],
            'working_hours_json' => ['nullable', 'string'],
            'vacation_periods_json' => ['nullable', 'string'],
            'staff_permissions_json' => ['nullable', 'string'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['integer'],
        ]);

        $this->appointmentService->createStaff($workspace, $validated + [
            'is_active' => $request->boolean('is_active', true),
            'working_hours' => $validated['working_hours'] ?? $this->parseJsonField($request, 'working_hours_json'),
            'vacation_periods' => $validated['vacation_periods'] ?? $this->parseJsonField($request, 'vacation_periods_json'),
            'staff_permissions' => $validated['staff_permissions'] ?? $this->parseJsonField($request, 'staff_permissions_json'),
        ]);

        return back()->with('success', 'تمت إضافة عضو الطاقم.');
    }

    public function updateStaff(Request $request, AppointmentStaff $staff): RedirectResponse
    {
        $this->authorizeAppointments($request, 'appointments.settings.manage');
        $validated = $request->validate([
            'user_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
            'working_days' => ['nullable', 'array'],
            'working_days.*' => ['string', Rule::in(['sat', 'sun', 'mon', 'tue', 'wed', 'thu', 'fri'])],
            'working_hours' => ['nullable', 'array'],
            'vacation_periods' => ['nullable', 'array'],
            'staff_permissions' => ['nullable', 'array'],
            'working_hours_json' => ['nullable', 'string'],
            'vacation_periods_json' => ['nullable', 'string'],
            'staff_permissions_json' => ['nullable', 'string'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['integer'],
        ]);

        $this->appointmentService->updateStaff($staff, $validated + [
            'is_active' => $request->boolean('is_active', true),
            'working_hours' => $validated['working_hours'] ?? $this->parseJsonField($request, 'working_hours_json'),
            'vacation_periods' => $validated['vacation_periods'] ?? $this->parseJsonField($request, 'vacation_periods_json'),
            'staff_permissions' => $validated['staff_permissions'] ?? $this->parseJsonField($request, 'staff_permissions_json'),
        ]);

        return back()->with('success', 'تم تحديث بيانات الطاقم.');
    }

    public function storeBooking(Request $request): RedirectResponse
    {
        $this->authorizeAppointments($request, 'appointments.manage');
        $workspace = $this->currentWorkspace();
        $validated = $request->validate([
            'service_id' => ['required', 'integer'],
            'staff_id' => ['nullable', 'integer'],
            'customer_id' => ['nullable', 'integer'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_age' => ['nullable', 'integer', 'min:1', 'max:120'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date'],
            'allow_custom_duration' => ['nullable', 'boolean'],
            'status' => ['nullable', Rule::in(['scheduled', 'confirmed', 'checked_in', 'in_progress', 'completed', 'cancelled', 'no_show'])],
            'payment_status' => ['nullable', Rule::in(['unpaid', 'pending', 'paid', 'failed', 'refunded', 'partially_paid'])],
            'source' => ['nullable', Rule::in(['dashboard', 'phone', 'walk_in', 'website', 'whatsapp', 'ai_chat', 'email', 'api'])],
            'notes' => ['nullable', 'string'],
            'resource_ids' => ['nullable', 'array'],
            'resource_ids.*' => ['integer'],
        ]);

        try {
            $this->appointmentService->createBooking($workspace, [
                ...$validated,
                'source_channel' => $validated['source'] ?? 'dashboard',
                'appointment_status' => $validated['status'] ?? 'scheduled',
                'allow_custom_duration' => $request->boolean('allow_custom_duration'),
            ], (int) $request->user()?->id);
        } catch (\RuntimeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'تم إنشاء الموعد بنجاح.');
    }

    public function updateBookingStatus(Request $request, AppointmentBooking $booking): RedirectResponse
    {
        $this->authorizeAppointments($request, 'appointments.manage');
        $this->assertSameWorkspace($booking->workspace_id);
        $validated = $request->validate([
            'status' => ['required', Rule::in(['scheduled', 'confirmed', 'checked_in', 'in_progress', 'completed', 'cancelled', 'no_show'])],
            'cancel_reason' => ['nullable', 'string'],
        ]);

        try {
            $this->appointmentService->updateBookingStatus($booking, [
                'appointment_status' => $validated['status'],
                'cancel_reason' => $validated['cancel_reason'] ?? null,
            ]);
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'تم تحديث حالة الموعد.');
    }

    public function storeResource(Request $request): RedirectResponse
    {
        $this->authorizeAppointments($request, 'appointments.settings.manage');
        $workspace = $this->currentWorkspace();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'resource_type' => ['required', Rule::in(['room', 'chair', 'equipment', 'meeting_room', 'other'])],
            'is_active' => ['nullable', 'boolean'],
        ]);

        AppointmentResource::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => trim((string) $validated['name']),
            'resource_type' => $validated['resource_type'],
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('success', 'تمت إضافة المورد بنجاح.');
    }

    public function updateResource(Request $request, AppointmentResource $resource): RedirectResponse
    {
        $this->authorizeAppointments($request, 'appointments.settings.manage');
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'resource_type' => ['required', Rule::in(['room', 'chair', 'equipment', 'meeting_room', 'other'])],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $resource->update([
            'name' => trim((string) $validated['name']),
            'resource_type' => $validated['resource_type'],
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('success', 'تم تحديث المورد.');
    }
}
