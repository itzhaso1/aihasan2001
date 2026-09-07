<?php

namespace App\Services\Appointments;

use App\Models\Appointment\AppointmentBooking;
use App\Models\Appointment\AppointmentHoliday;
use App\Models\Appointment\AppointmentResource;
use App\Models\Appointment\AppointmentService as AppointmentServiceModel;
use App\Models\Appointment\AppointmentSetting;
use App\Models\Appointment\AppointmentStaff;
use App\Models\Customer;
use App\Models\Workspace;
use App\Services\Notification\DomainNotificationService;
use App\Support\Tenancy\WorkspaceContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class AppointmentService
{
    /** @var array<int, string> */
    public const APPOINTMENT_STATUSES = ['scheduled', 'confirmed', 'checked_in', 'in_progress', 'completed', 'cancelled', 'no_show'];
    /** @var array<int, string> */
    public const PAYMENT_STATUSES = ['unpaid', 'pending', 'paid', 'failed', 'refunded', 'partially_paid'];
    /** @var array<int, string> */
    public const BOOKING_SOURCES = ['dashboard', 'phone', 'walk_in', 'website', 'whatsapp', 'ai_chat', 'email', 'api'];
    /** @var array<int, string> */
    private const ACTIVE_BOOKING_STATUSES = ['scheduled', 'confirmed', 'checked_in', 'in_progress'];
    /** @var array<int, string> */
    private const WEEK_DAYS = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];

    public function __construct(
        private readonly WorkspaceContext $workspaceContext,
        private readonly AppointmentReminderService $appointmentReminderService,
        private readonly DomainNotificationService $domainNotificationService,
    ) {}

    public function ensureSetup(Workspace $workspace): void
    {
        AppointmentSetting::withoutGlobalScopes()->firstOrCreate(
            ['workspace_id' => $workspace->id],
            [
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
                'reminder_offsets' => [1440, 120],
                'metadata' => $this->defaultSettingMetadata(),
            ]
        );
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    public function updateSetting(Workspace $workspace, array $payload): AppointmentSetting
    {
        $this->ensureSetup($workspace);
        $setting = AppointmentSetting::query()->firstOrFail();

        $metadata = is_array($setting->metadata) ? $setting->metadata : [];
        $incomingMetadata = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];

        $metadata['business_hours'] = $this->normalizeBusinessHoursInput(
            $incomingMetadata['business_hours'] ?? ($metadata['business_hours'] ?? [])
        );
        $metadata['booking_rules'] = $this->normalizeBookingRulesInput(
            $incomingMetadata['booking_rules'] ?? ($metadata['booking_rules'] ?? [])
        );
        $metadata['cancellation_rules'] = $this->normalizeCancellationRulesInput(
            $incomingMetadata['cancellation_rules'] ?? ($metadata['cancellation_rules'] ?? [])
        );
        $metadata['reminder_channels'] = $this->normalizeReminderChannelsInput(
            $incomingMetadata['reminder_channels'] ?? ($metadata['reminder_channels'] ?? ['in_app'])
        );

        $setting->update([
            'business_type' => $payload['business_type'],
            'business_label' => trim((string) ($payload['business_label'] ?? '')) ?: null,
            'timezone' => $payload['timezone'],
            'slot_interval_minutes' => (int) $payload['slot_interval_minutes'],
            'start_hour' => $payload['start_hour'],
            'end_hour' => $payload['end_hour'],
            'allow_walk_in' => (bool) ($payload['allow_walk_in'] ?? false),
            'automation_mode' => (string) ($payload['automation_mode'] ?? $setting->automation_mode ?? 'APPROVAL'),
            'auto_confirm_after_payment' => (bool) ($payload['auto_confirm_after_payment'] ?? true),
            'reminder_offsets' => $payload['reminder_offsets'] ?? [1440, 120],
            'metadata' => $metadata,
        ]);

        return $setting->refresh();
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    public function createService(Workspace $workspace, array $payload): AppointmentServiceModel
    {
        $service = AppointmentServiceModel::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => trim((string) $payload['name']),
            'description' => trim((string) ($payload['description'] ?? '')) ?: null,
            'duration_minutes' => (int) $payload['duration_minutes'],
            'price' => round((float) $payload['price'], 2),
            'color' => trim((string) ($payload['color'] ?? '')) ?: null,
            'is_active' => (bool) ($payload['is_active'] ?? true),
            'requires_confirmation' => (bool) ($payload['requires_confirmation'] ?? false),
            'requires_payment' => (bool) ($payload['requires_payment'] ?? false),
            'payment_mode' => (string) ($payload['payment_mode'] ?? 'postpaid'),
            'deposit_amount' => isset($payload['deposit_amount']) ? round((float) $payload['deposit_amount'], 2) : null,
            'approval_required' => (bool) ($payload['approval_required'] ?? false),
        ]);

        $staffIds = collect($payload['staff_ids'] ?? [])->map(fn ($id) => (int) $id)->filter(fn (int $id) => $id > 0)->all();
        if ($staffIds !== []) {
            $service->staffMembers()->sync(collect($staffIds)->mapWithKeys(
                fn (int $id): array => [$id => ['workspace_id' => $workspace->id, 'is_primary' => false]]
            )->all());
        }

        return $service->refresh();
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    public function updateService(AppointmentServiceModel $service, array $payload): AppointmentServiceModel
    {
        $service->update([
            'name' => trim((string) $payload['name']),
            'description' => trim((string) ($payload['description'] ?? '')) ?: null,
            'duration_minutes' => (int) $payload['duration_minutes'],
            'price' => round((float) $payload['price'], 2),
            'color' => trim((string) ($payload['color'] ?? '')) ?: null,
            'is_active' => (bool) ($payload['is_active'] ?? true),
            'requires_confirmation' => (bool) ($payload['requires_confirmation'] ?? false),
            'requires_payment' => (bool) ($payload['requires_payment'] ?? $service->requires_payment),
            'payment_mode' => (string) ($payload['payment_mode'] ?? $service->payment_mode ?? 'postpaid'),
            'deposit_amount' => isset($payload['deposit_amount'])
                ? round((float) $payload['deposit_amount'], 2)
                : $service->deposit_amount,
            'approval_required' => (bool) ($payload['approval_required'] ?? $service->approval_required),
        ]);

        if (is_array($payload['staff_ids'] ?? null)) {
            $staffIds = collect($payload['staff_ids'])->map(fn ($id) => (int) $id)->filter(fn (int $id) => $id > 0)->all();
            $service->staffMembers()->sync(collect($staffIds)->mapWithKeys(
                fn (int $id): array => [$id => ['workspace_id' => $service->workspace_id, 'is_primary' => false]]
            )->all());
        }

        return $service->refresh();
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    public function createStaff(Workspace $workspace, array $payload): AppointmentStaff
    {
        $staff = AppointmentStaff::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $payload['user_id'] ?? null,
            'name' => trim((string) $payload['name']),
            'role' => trim((string) ($payload['role'] ?? '')) ?: null,
            'phone' => trim((string) ($payload['phone'] ?? '')) ?: null,
            'color' => trim((string) ($payload['color'] ?? '')) ?: null,
            'is_active' => (bool) ($payload['is_active'] ?? true),
            'working_days' => is_array($payload['working_days'] ?? null) ? $payload['working_days'] : null,
            'working_hours' => is_array($payload['working_hours'] ?? null) ? $payload['working_hours'] : null,
            'vacation_periods' => is_array($payload['vacation_periods'] ?? null) ? $payload['vacation_periods'] : null,
            'staff_permissions' => is_array($payload['staff_permissions'] ?? null) ? $payload['staff_permissions'] : null,
        ]);

        if (is_array($payload['service_ids'] ?? null)) {
            $serviceIds = collect($payload['service_ids'])->map(fn ($id) => (int) $id)->filter(fn (int $id) => $id > 0)->all();
            $staff->services()->sync(collect($serviceIds)->mapWithKeys(
                fn (int $id): array => [$id => ['workspace_id' => $workspace->id, 'is_primary' => false]]
            )->all());
        }

        return $staff->refresh();
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    public function updateStaff(AppointmentStaff $staff, array $payload): AppointmentStaff
    {
        $staff->update([
            'user_id' => $payload['user_id'] ?? null,
            'name' => trim((string) $payload['name']),
            'role' => trim((string) ($payload['role'] ?? '')) ?: null,
            'phone' => trim((string) ($payload['phone'] ?? '')) ?: null,
            'color' => trim((string) ($payload['color'] ?? '')) ?: null,
            'is_active' => (bool) ($payload['is_active'] ?? true),
            'working_days' => is_array($payload['working_days'] ?? null) ? $payload['working_days'] : $staff->working_days,
            'working_hours' => is_array($payload['working_hours'] ?? null) ? $payload['working_hours'] : $staff->working_hours,
            'vacation_periods' => is_array($payload['vacation_periods'] ?? null) ? $payload['vacation_periods'] : $staff->vacation_periods,
            'staff_permissions' => is_array($payload['staff_permissions'] ?? null) ? $payload['staff_permissions'] : $staff->staff_permissions,
        ]);

        if (is_array($payload['service_ids'] ?? null)) {
            $serviceIds = collect($payload['service_ids'])->map(fn ($id) => (int) $id)->filter(fn (int $id) => $id > 0)->all();
            $staff->services()->sync(collect($serviceIds)->mapWithKeys(
                fn (int $id): array => [$id => ['workspace_id' => $staff->workspace_id, 'is_primary' => false]]
            )->all());
        }

        return $staff->refresh();
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    public function createBooking(Workspace $workspace, array $payload, ?int $actorUserId): AppointmentBooking
    {
        $this->ensureSetup($workspace);

        $service = AppointmentServiceModel::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->whereKey((int) $payload['service_id'])
            ->firstOrFail();
        $staff = null;
        if (! empty($payload['staff_id'])) {
            $staff = AppointmentStaff::withoutGlobalScopes()
                ->where('workspace_id', $workspace->id)
                ->whereKey((int) $payload['staff_id'])
                ->firstOrFail();
        }
        if (
            $staff &&
            $service->staffMembers()->exists() &&
            ! $service->staffMembers()->whereKey($staff->id)->exists()
        ) {
            throw new RuntimeException('الموظف المحدد غير مرتبط بهذه الخدمة.');
        }

        $setting = AppointmentSetting::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->first();
        $timezone = $this->workspaceTimezone($workspace->id, $setting);

        $startsAtLocal = $this->parseWorkspaceDateTime($payload['starts_at'] ?? null, $timezone);
        $allowCustomDuration = (bool) ($payload['allow_custom_duration'] ?? false);
        if ($allowCustomDuration && ! empty($payload['ends_at'])) {
            $endsAtLocal = $this->parseWorkspaceDateTime($payload['ends_at'], $timezone);
        } else {
            // Use service duration by default to avoid incorrect UI-provided ranges.
            $endsAtLocal = $startsAtLocal->copy()->addMinutes(max(5, (int) $service->duration_minutes));
        }

        if ($endsAtLocal->lte($startsAtLocal)) {
            throw new RuntimeException('وقت نهاية الموعد يجب أن يكون بعد وقت البداية.');
        }

        $rules = $this->bookingRules($setting);
        $this->assertBookingRules($workspace->id, $startsAtLocal, $endsAtLocal, $rules, $timezone);
        $this->assertWithinBusinessHours($startsAtLocal, $endsAtLocal, $setting);
        $this->assertNotHoliday($workspace->id, $startsAtLocal, $staff?->id);
        $this->assertStaffAvailability($staff, $startsAtLocal, $endsAtLocal, $timezone);

        $startsAt = $startsAtLocal->copy()->utc();
        $endsAt = $endsAtLocal->copy()->utc();

        $resourceIds = collect($payload['resource_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->values()
            ->all();

        $this->ensureNoOverlap(
            workspaceId: $workspace->id,
            startsAt: $startsAt,
            endsAt: $endsAt,
            staffId: $staff?->id,
            resourceIds: $resourceIds,
            bufferMinutes: (int) ($rules['buffer_minutes'] ?? 0),
            serviceId: $service->id,
        );

        $customerName = trim((string) ($payload['customer_name'] ?? ''));
        $customerPhone = trim((string) ($payload['customer_phone'] ?? '')) ?: null;
        $customerEmail = trim((string) ($payload['customer_email'] ?? '')) ?: null;
        $customerAge = isset($payload['customer_age']) ? max(1, (int) $payload['customer_age']) : null;
        $customerId = null;
        if (! empty($payload['customer_id'])) {
            $customer = Customer::withoutGlobalScopes()
                ->where('workspace_id', $workspace->id)
                ->whereKey((int) $payload['customer_id'])
                ->firstOrFail();
            $customerId = $customer->id;
            if ($customerName === '') {
                $customerName = $customer->name;
            }
            if ($customerPhone === null && filled($customer->phone)) {
                $customerPhone = $customer->phone;
            }
            if ($customerEmail === null && filled($customer->email)) {
                $customerEmail = $customer->email;
            }
        }

        if ($customerName === '') {
            throw new RuntimeException('اسم العميل مطلوب لإنشاء الحجز.');
        }

        $this->ensureNoDuplicateBooking(
            workspaceId: $workspace->id,
            startsAt: $startsAt,
            serviceId: $service->id,
            customerName: $customerName,
            customerPhone: $customerPhone,
            staffId: $staff?->id
        );

        $sourceChannel = trim((string) ($payload['source_channel'] ?? $payload['source'] ?? 'dashboard'));
        if (! in_array($sourceChannel, self::BOOKING_SOURCES, true)) {
            $sourceChannel = 'dashboard';
        }

        $legacyStatus = (string) ($payload['status'] ?? $payload['appointment_status'] ?? 'scheduled');
        if (! in_array($legacyStatus, ['scheduled', 'confirmed', 'completed', 'cancelled', 'no_show'], true)) {
            $legacyStatus = 'scheduled';
        }
        $appointmentStatus = (string) ($payload['appointment_status'] ?? $legacyStatus);
        if (! in_array($appointmentStatus, self::APPOINTMENT_STATUSES, true)) {
            $appointmentStatus = 'scheduled';
        }
        $paymentStatus = (string) ($payload['payment_status'] ?? ($service->requires_payment ? 'unpaid' : 'paid'));
        if (! in_array($paymentStatus, self::PAYMENT_STATUSES, true)) {
            $paymentStatus = $service->requires_payment ? 'unpaid' : 'paid';
        }

        return DB::transaction(function () use (
            $workspace,
            $service,
            $staff,
            $payload,
            $actorUserId,
            $startsAt,
            $endsAt,
            $customerId,
            $customerName,
            $customerPhone,
            $customerEmail,
            $customerAge,
            $sourceChannel,
            $legacyStatus,
            $appointmentStatus,
            $paymentStatus,
            $resourceIds,
            $rules,
            $timezone,
            $startsAtLocal,
            $endsAtLocal
        ): AppointmentBooking {
            $this->ensureNoOverlap(
                workspaceId: $workspace->id,
                startsAt: $startsAt,
                endsAt: $endsAt,
                staffId: $staff?->id,
                resourceIds: $resourceIds,
                bufferMinutes: (int) ($rules['buffer_minutes'] ?? 0),
                serviceId: $service->id,
                lockForUpdate: true,
            );

            $this->ensureNoDuplicateBooking(
                workspaceId: $workspace->id,
                startsAt: $startsAt,
                serviceId: $service->id,
                customerName: $customerName,
                customerPhone: $customerPhone,
                staffId: $staff?->id
            );

            $bookingNumber = $this->nextBookingNumber($workspace->id, $startsAt);

            $booking = AppointmentBooking::withoutGlobalScopes()->create([
                'workspace_id' => $workspace->id,
                'booking_number' => $bookingNumber,
                'request_id' => isset($payload['request_id']) ? (int) $payload['request_id'] : null,
                'service_id' => $service->id,
                'staff_id' => $staff?->id,
                'customer_id' => $customerId,
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'customer_email' => $customerEmail,
                'customer_age' => $customerAge,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => $legacyStatus,
                'source' => $this->resolveLegacySource($sourceChannel),
                'source_channel' => $sourceChannel,
                'appointment_status' => $appointmentStatus,
                'payment_status' => $paymentStatus,
                'finance_invoice_id' => isset($payload['finance_invoice_id']) ? (int) $payload['finance_invoice_id'] : null,
                'order_id' => isset($payload['order_id']) ? (int) $payload['order_id'] : null,
                'latest_payment_id' => isset($payload['latest_payment_id']) ? (int) $payload['latest_payment_id'] : null,
                'notes' => trim((string) ($payload['notes'] ?? '')) ?: null,
                'public_token' => Str::lower((string) Str::ulid()),
                'payment_link' => trim((string) ($payload['payment_link'] ?? '')) ?: null,
                'confirmed_at' => $appointmentStatus === 'confirmed' ? now() : null,
                'checked_in_at' => $appointmentStatus === 'checked_in' ? now() : null,
                'in_progress_at' => $appointmentStatus === 'in_progress' ? now() : null,
                'completed_at' => $appointmentStatus === 'completed' ? now() : null,
                'cancelled_at' => $appointmentStatus === 'cancelled' ? now() : null,
                'booked_by' => $actorUserId,
                'metadata' => array_merge(
                    is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [],
                    [
                        'workspace_timezone' => $timezone,
                        'start_local' => $startsAtLocal->format('Y-m-d H:i:s'),
                        'end_local' => $endsAtLocal->format('Y-m-d H:i:s'),
                    ]
                ),
            ]);

            if ($resourceIds !== []) {
                $resources = AppointmentResource::query()
                    ->withoutGlobalScopes()
                    ->where('workspace_id', $workspace->id)
                    ->whereIn('id', $resourceIds)
                    ->where('is_active', true)
                    ->pluck('id')
                    ->all();
                if ($resources !== []) {
                    $booking->resources()->sync(collect($resources)->mapWithKeys(
                        fn (int $id): array => [$id => ['workspace_id' => $workspace->id]]
                    )->all());
                }
            }

            $fresh = $booking->fresh(['service', 'staff', 'customer', 'resources']);
            $this->appointmentReminderService->scheduleForBooking($fresh);
            $this->domainNotificationService->notifyAppointmentBookingStatusChanged(
                $fresh,
                'تم إنشاء حجز جديد',
                'تم إنشاء حجز جديد بنجاح ضمن نظام المواعيد.'
            );

            return $fresh;
        });
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    public function updateBookingStatus(AppointmentBooking $booking, array $payload): AppointmentBooking
    {
        $booking->loadMissing('service');
        $requestedStatus = (string) ($payload['status'] ?? $payload['appointment_status'] ?? '');
        if (! in_array($requestedStatus, self::APPOINTMENT_STATUSES, true)) {
            throw new RuntimeException('حالة الموعد غير صالحة.');
        }
        $this->assertStatusTransition($booking->appointment_status ?? 'scheduled', $requestedStatus);

        if ($requestedStatus === 'cancelled') {
            $this->assertPolicyWindow($booking, 'cancellation');
        }

        $legacyStatus = in_array($requestedStatus, ['scheduled', 'confirmed', 'completed', 'cancelled', 'no_show'], true)
            ? $requestedStatus
            : ($requestedStatus === 'checked_in' || $requestedStatus === 'in_progress' ? 'confirmed' : 'scheduled');

        $booking->update([
            'status' => $legacyStatus,
            'appointment_status' => $requestedStatus,
            'cancel_reason' => $requestedStatus === 'cancelled'
                ? (trim((string) ($payload['cancel_reason'] ?? '')) ?: $booking->cancel_reason)
                : $booking->cancel_reason,
            'confirmed_at' => $requestedStatus === 'confirmed' ? now() : $booking->confirmed_at,
            'checked_in_at' => $requestedStatus === 'checked_in' ? now() : $booking->checked_in_at,
            'in_progress_at' => $requestedStatus === 'in_progress' ? now() : $booking->in_progress_at,
            'completed_at' => $requestedStatus === 'completed' ? now() : $booking->completed_at,
            'cancelled_at' => $requestedStatus === 'cancelled' ? now() : $booking->cancelled_at,
        ]);

        $fresh = $booking->refresh();
        if (in_array($requestedStatus, ['cancelled', 'completed', 'no_show'], true)) {
            $this->appointmentReminderService->cancelPendingReminders($fresh, 'appointment_closed');
        }
        $titles = [
            'confirmed' => 'تم تأكيد الموعد',
            'checked_in' => 'تم تسجيل حضور العميل',
            'in_progress' => 'الموعد قيد التنفيذ',
            'completed' => 'تم إكمال الموعد',
            'cancelled' => 'تم إلغاء الموعد',
            'no_show' => 'تم تسجيل عدم الحضور',
            'scheduled' => 'تم تحديث الموعد',
        ];
        $this->domainNotificationService->notifyAppointmentBookingStatusChanged(
            $fresh,
            $titles[$requestedStatus] ?? 'تم تحديث الموعد',
            'تم تحديث حالة الموعد بنجاح.'
        );

        return $fresh;
    }

    public function cancelBooking(AppointmentBooking $booking, ?string $reason = null): AppointmentBooking
    {
        $this->assertPolicyWindow($booking, 'cancellation');
        $booking->update([
            'status' => 'cancelled',
            'appointment_status' => 'cancelled',
            'cancel_reason' => trim((string) $reason) ?: null,
            'cancelled_at' => now(),
        ]);

        $fresh = $booking->refresh();
        $this->appointmentReminderService->cancelPendingReminders($fresh, 'booking_cancelled');
        $this->domainNotificationService->notifyAppointmentBookingStatusChanged(
            $fresh,
            'تم إلغاء الموعد',
            'تم إلغاء الموعد وتحديث السجل المرتبط به.'
        );

        return $fresh;
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function rescheduleBooking(AppointmentBooking $booking, array $payload, ?int $actorUserId = null): AppointmentBooking
    {
        $booking->loadMissing(['service', 'staff', 'resources']);
        if (! $booking->service) {
            throw new RuntimeException('الخدمة غير متاحة لإعادة الجدولة.');
        }
        if (in_array($booking->appointment_status, ['completed', 'cancelled', 'no_show'], true)) {
            throw new RuntimeException('لا يمكن إعادة جدولة موعد مغلق.');
        }

        $this->assertPolicyWindow($booking, 'reschedule');

        $setting = AppointmentSetting::withoutGlobalScopes()
            ->where('workspace_id', $booking->workspace_id)
            ->first();
        $timezone = $this->workspaceTimezone($booking->workspace_id, $setting);
        $rules = $this->bookingRules($setting);

        $service = $booking->service;
        $newStaffId = isset($payload['staff_id']) && (int) $payload['staff_id'] > 0
            ? (int) $payload['staff_id']
            : $booking->staff_id;
        $newStaff = null;
        if ($newStaffId) {
            $newStaff = AppointmentStaff::withoutGlobalScopes()
                ->where('workspace_id', $booking->workspace_id)
                ->whereKey($newStaffId)
                ->first();
        }
        if (
            $newStaff &&
            $service->staffMembers()->exists() &&
            ! $service->staffMembers()->whereKey($newStaff->id)->exists()
        ) {
            throw new RuntimeException('الموظف الجديد غير مرتبط بالخدمة.');
        }

        $startsAtLocal = $this->parseWorkspaceDateTime($payload['starts_at'] ?? null, $timezone);
        $allowCustomDuration = (bool) ($payload['allow_custom_duration'] ?? false);
        if ($allowCustomDuration && ! empty($payload['ends_at'])) {
            $endsAtLocal = $this->parseWorkspaceDateTime($payload['ends_at'], $timezone);
        } else {
            $endsAtLocal = $startsAtLocal->copy()->addMinutes(max(5, (int) $service->duration_minutes));
        }
        if ($endsAtLocal->lte($startsAtLocal)) {
            throw new RuntimeException('وقت نهاية الموعد يجب أن يكون بعد البداية.');
        }

        $resourceIds = collect($payload['resource_ids'] ?? $booking->resources->pluck('id')->all())
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->values()
            ->all();

        $startsAtUtc = $startsAtLocal->copy()->utc();
        $endsAtUtc = $endsAtLocal->copy()->utc();
        $this->assertBookingRules($booking->workspace_id, $startsAtLocal, $endsAtLocal, $rules, $timezone, $booking->id);
        $this->assertWithinBusinessHours($startsAtLocal, $endsAtLocal, $setting);
        $this->assertNotHoliday($booking->workspace_id, $startsAtLocal, $newStaff?->id);
        $this->assertStaffAvailability($newStaff, $startsAtLocal, $endsAtLocal, $timezone);
        $this->ensureNoOverlap(
            workspaceId: $booking->workspace_id,
            startsAt: $startsAtUtc,
            endsAt: $endsAtUtc,
            staffId: $newStaff?->id,
            resourceIds: $resourceIds,
            bufferMinutes: (int) ($rules['buffer_minutes'] ?? 0),
            ignoreBookingId: $booking->id,
            serviceId: $service?->id,
        );

        $this->ensureNoDuplicateBooking(
            workspaceId: $booking->workspace_id,
            startsAt: $startsAtUtc,
            serviceId: (int) $booking->service_id,
            customerName: (string) $booking->customer_name,
            customerPhone: $booking->customer_phone,
            staffId: $newStaff?->id,
            ignoreBookingId: $booking->id,
        );

        return DB::transaction(function () use (
            $booking,
            $startsAtUtc,
            $endsAtUtc,
            $startsAtLocal,
            $endsAtLocal,
            $newStaff,
            $resourceIds,
            $timezone,
            $payload,
            $actorUserId,
            $rules,
        ): AppointmentBooking {
            $this->ensureNoOverlap(
                workspaceId: $booking->workspace_id,
                startsAt: $startsAtUtc,
                endsAt: $endsAtUtc,
                staffId: $newStaff?->id,
                resourceIds: $resourceIds,
                bufferMinutes: (int) ($rules['buffer_minutes'] ?? 0),
                ignoreBookingId: $booking->id,
                serviceId: $service?->id,
                lockForUpdate: true,
            );

            $metadata = is_array($booking->metadata) ? $booking->metadata : [];
            $rescheduleCount = max(0, (int) ($metadata['reschedule_count'] ?? 0)) + 1;
            $metadata['last_rescheduled_at'] = now()->toDateTimeString();
            $metadata['last_rescheduled_by'] = $actorUserId;
            $metadata['reschedule_count'] = $rescheduleCount;
            $metadata['workspace_timezone'] = $timezone;
            $metadata['start_local'] = $startsAtLocal->format('Y-m-d H:i:s');
            $metadata['end_local'] = $endsAtLocal->format('Y-m-d H:i:s');
            $metadata['reschedule_reason'] = trim((string) ($payload['reason'] ?? '')) ?: null;

            $booking->update([
                'staff_id' => $newStaff?->id,
                'starts_at' => $startsAtUtc,
                'ends_at' => $endsAtUtc,
                'status' => in_array($booking->appointment_status, ['checked_in', 'in_progress'], true) ? 'confirmed' : $booking->status,
                'appointment_status' => in_array($booking->appointment_status, ['checked_in', 'in_progress'], true)
                    ? $booking->appointment_status
                    : 'scheduled',
                'notes' => trim((string) ($booking->notes ?? '')).(filled($payload['reason'] ?? null) ? "\nسبب إعادة الجدولة: ".trim((string) $payload['reason']) : ''),
                'metadata' => $metadata,
            ]);

            if ($resourceIds !== []) {
                $resources = AppointmentResource::query()
                    ->withoutGlobalScopes()
                    ->where('workspace_id', $booking->workspace_id)
                    ->whereIn('id', $resourceIds)
                    ->where('is_active', true)
                    ->pluck('id')
                    ->all();
                $booking->resources()->sync(collect($resources)->mapWithKeys(
                    fn (int $id): array => [$id => ['workspace_id' => $booking->workspace_id]]
                )->all());
            }

            $fresh = $booking->fresh(['service', 'staff', 'customer', 'resources']);
            $this->appointmentReminderService->cancelPendingReminders($fresh, 'rescheduled');
            $this->appointmentReminderService->scheduleForBooking($fresh);
            $this->domainNotificationService->notifyAppointmentBookingStatusChanged(
                $fresh,
                'تمت إعادة جدولة الموعد',
                'تم تعديل وقت الموعد بعد فحص التوفر والقواعد.'
            );

            return $fresh;
        });
    }

    /**
     * @param  array<string,mixed>  $filters
     */
    public function listBookings(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $timezone = (string) ($filters['timezone'] ?? $this->workspaceTimezone());
        $date = trim((string) ($filters['date'] ?? ''));
        $fromDate = trim((string) ($filters['from_date'] ?? ''));
        $toDate = trim((string) ($filters['to_date'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $paymentStatus = trim((string) ($filters['payment_status'] ?? ''));
        $paymentBucket = trim((string) ($filters['payment_bucket'] ?? ''));
        $staffId = (int) ($filters['staff_id'] ?? 0);
        $staffUserId = (int) ($filters['staff_user_id'] ?? 0);
        $serviceId = (int) ($filters['service_id'] ?? 0);
        $customerId = (int) ($filters['customer_id'] ?? 0);
        $source = trim((string) ($filters['source'] ?? ''));
        $search = trim((string) ($filters['search'] ?? ''));

        $dayRange = $date !== '' ? $this->dayRangeInUtc($date, $timezone) : null;
        $fromRange = $fromDate !== '' ? $this->dayRangeInUtc($fromDate, $timezone) : null;
        $toRange = $toDate !== '' ? $this->dayRangeInUtc($toDate, $timezone) : null;

        return AppointmentBooking::query()
            ->with(['service', 'staff', 'customer', 'booker', 'request', 'invoice', 'resources'])
            ->when($dayRange !== null, fn ($query) => $query->whereBetween('starts_at', [$dayRange['start'], $dayRange['end']]))
            ->when($fromRange !== null, fn ($query) => $query->where('starts_at', '>=', $fromRange['start']))
            ->when($toRange !== null, fn ($query) => $query->where('starts_at', '<=', $toRange['end']))
            ->when($status !== '', fn ($query) => $query->where('appointment_status', $status))
            ->when($paymentStatus !== '', fn ($query) => $query->where('payment_status', $paymentStatus))
            ->when(
                $paymentBucket === 'attention',
                fn ($query) => $query->whereIn('payment_status', ['unpaid', 'pending', 'partially_paid'])
            )
            ->when($staffId > 0, fn ($query) => $query->where('staff_id', $staffId))
            ->when($staffUserId > 0, fn ($query) => $query->whereHas('staff', fn ($staffQuery) => $staffQuery->where('user_id', $staffUserId)))
            ->when($serviceId > 0, fn ($query) => $query->where('service_id', $serviceId))
            ->when($customerId > 0, fn ($query) => $query->where('customer_id', $customerId))
            ->when($source !== '', fn ($query) => $query->where('source_channel', $source))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('booking_number', 'like', '%'.$search.'%')
                        ->orWhere('customer_name', 'like', '%'.$search.'%')
                        ->orWhere('customer_phone', 'like', '%'.$search.'%')
                        ->orWhereHas('service', fn ($serviceQuery) => $serviceQuery->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->orderBy('starts_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function calendarEvents(array $filters): array
    {
        $timezone = (string) ($filters['timezone'] ?? $this->workspaceTimezone());
        $view = (string) ($filters['view'] ?? 'week');
        $staffId = (int) ($filters['staff_id'] ?? 0);
        $staffUserId = (int) ($filters['staff_user_id'] ?? 0);
        $date = isset($filters['date'])
            ? Carbon::parse((string) $filters['date'], $timezone)
            : now($timezone);
        $rangeStartLocal = $view === 'month'
            ? $date->copy()->startOfMonth()->startOfDay()
            : ($view === 'day' ? $date->copy()->startOfDay() : $date->copy()->startOfWeek()->startOfDay());
        $rangeEndLocal = $view === 'month'
            ? $date->copy()->endOfMonth()->endOfDay()
            : ($view === 'day' ? $date->copy()->endOfDay() : $date->copy()->endOfWeek()->endOfDay());
        $rangeStartUtc = $rangeStartLocal->copy()->utc();
        $rangeEndUtc = $rangeEndLocal->copy()->utc();

        return AppointmentBooking::query()
            ->with(['service:id,name,duration_minutes', 'staff:id,name'])
            ->where('starts_at', '>=', $rangeStartUtc)
            ->where('starts_at', '<=', $rangeEndUtc)
            ->when($staffId > 0, fn ($query) => $query->where('staff_id', $staffId))
            ->when($staffUserId > 0, fn ($query) => $query->whereHas('staff', fn ($staffQuery) => $staffQuery->where('user_id', $staffUserId)))
            ->orderBy('starts_at')
            ->get()
            ->map(function (AppointmentBooking $booking) use ($timezone): array {
                $startLocal = $booking->starts_at?->copy()->timezone($timezone);
                $endLocal = $booking->ends_at?->copy()->timezone($timezone);

                return [
                    'id' => $booking->id,
                    'booking_number' => $booking->booking_number,
                    'title' => sprintf('%s - %s', (string) $booking->customer_name, (string) ($booking->service?->name ?? 'خدمة')),
                    'customer' => $booking->customer_name,
                    'service' => $booking->service?->name,
                    'staff_id' => $booking->staff_id,
                    'staff' => $booking->staff?->name,
                    'date_key' => $startLocal?->format('Y-m-d'),
                    'start_ts' => $startLocal?->timestamp,
                    'end_ts' => $endLocal?->timestamp,
                    'start_local' => $startLocal?->locale('ar')->translatedFormat('Y-m-d H:i'),
                    'end_local' => $endLocal?->locale('ar')->translatedFormat('Y-m-d H:i'),
                    'date_label' => $startLocal?->locale('ar')->translatedFormat('l، j F'),
                    'time_label' => $startLocal && $endLocal
                        ? $startLocal->locale('ar')->translatedFormat('g:i A').' - '.$endLocal->locale('ar')->translatedFormat('g:i A')
                        : null,
                    'duration_minutes' => $startLocal && $endLocal ? max(1, $startLocal->diffInMinutes($endLocal)) : null,
                    'appointment_status' => $booking->appointment_status,
                    'payment_status' => $booking->payment_status,
                ];
            })
            ->values()
            ->all();
    }

    public function workspaceTimezone(?int $workspaceId = null, ?AppointmentSetting $setting = null): string
    {
        if ($setting && filled($setting->timezone)) {
            return (string) $setting->timezone;
        }

        $workspaceId = $workspaceId ?? (int) $this->workspaceContext->workspaceId();
        if ($workspaceId > 0) {
            $setting = AppointmentSetting::withoutGlobalScopes()
                ->where('workspace_id', $workspaceId)
                ->first();
            if ($setting && filled($setting->timezone)) {
                return (string) $setting->timezone;
            }
        }

        return (string) config('app.timezone', 'UTC');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function availableSlots(Workspace $workspace, int $serviceId, string $date, ?int $staffId = null): array
    {
        $this->ensureSetup($workspace);

        $service = AppointmentServiceModel::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->whereKey($serviceId)
            ->where('is_active', true)
            ->firstOrFail();

        $staff = null;
        if ($staffId !== null && $staffId > 0) {
            $staff = AppointmentStaff::withoutGlobalScopes()
                ->where('workspace_id', $workspace->id)
                ->whereKey($staffId)
                ->where('is_active', true)
                ->firstOrFail();

            if (
                $service->staffMembers()->withoutGlobalScopes()->exists()
                && ! $service->staffMembers()->withoutGlobalScopes()->whereKey($staff->id)->exists()
            ) {
                throw new RuntimeException('الموظف المحدد غير مرتبط بهذه الخدمة.');
            }
        }

        $setting = AppointmentSetting::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->first();
        $timezone = $this->workspaceTimezone($workspace->id, $setting);
        $day = Carbon::parse($date, $timezone)->startOfDay();
        $today = now($timezone)->startOfDay();
        if ($day->lt($today)) {
            return [];
        }

        $rules = $this->bookingRules($setting);
        $slotInterval = max(5, (int) ($rules['slot_interval_minutes'] ?? 30));
        $duration = max(5, (int) $service->duration_minutes);
        $businessHours = $this->normalizeBusinessHoursInput(
            is_array($setting?->metadata) ? ($setting->metadata['business_hours'] ?? []) : []
        );

        $dayKey = $this->dayKey($day);
        $dayConfig = $businessHours[$dayKey] ?? ['closed' => false, 'ranges' => []];
        if (($dayConfig['closed'] ?? false) === true) {
            return [];
        }

        $ranges = is_array($dayConfig['ranges'] ?? null) ? $dayConfig['ranges'] : [];
        if ($ranges === []) {
            $ranges = [[
                'start' => Carbon::parse((string) ($setting?->start_hour ?? '09:00:00'))->format('H:i'),
                'end' => Carbon::parse((string) ($setting?->end_hour ?? '17:00:00'))->format('H:i'),
            ]];
        }

        $slots = [];
        foreach ($ranges as $range) {
            $rangeStart = trim((string) ($range['start'] ?? ''));
            $rangeEnd = trim((string) ($range['end'] ?? ''));
            if ($rangeStart === '' || $rangeEnd === '') {
                continue;
            }

            $cursor = Carbon::parse($day->toDateString().' '.$rangeStart, $timezone);
            $endBoundary = Carbon::parse($day->toDateString().' '.$rangeEnd, $timezone);

            while ($cursor->lt($endBoundary)) {
                $candidateStart = $cursor->copy();
                $candidateEnd = $candidateStart->copy()->addMinutes($duration);
                if ($candidateEnd->gt($endBoundary)) {
                    break;
                }

                $isPast = $candidateStart->lt(now($timezone));
                $isOutsideRules = ! $this->isWithinBookingRuleWindow($workspace->id, $candidateStart, $candidateEnd, $rules, $timezone);
                $isHoliday = $this->isHoliday($workspace->id, $candidateStart, $staff?->id);

                $staffAvailable = true;
                try {
                    $this->assertStaffAvailability($staff, $candidateStart, $candidateEnd, $timezone);
                } catch (RuntimeException) {
                    $staffAvailable = false;
                }

                $hasOverlap = $this->hasOverlap(
                    workspaceId: $workspace->id,
                    startsAt: $candidateStart->copy()->utc(),
                    endsAt: $candidateEnd->copy()->utc(),
                    staffId: $staff?->id,
                    resourceIds: [],
                    bufferMinutes: (int) ($rules['buffer_minutes'] ?? 0),
                );

                if (! $isPast && ! $isOutsideRules && ! $isHoliday && $staffAvailable && ! $hasOverlap) {
                    $slots[] = [
                        'starts_at' => $candidateStart->copy()->utc()->toIso8601String(),
                        'ends_at' => $candidateEnd->copy()->utc()->toIso8601String(),
                        'start_local' => $candidateStart->format('Y-m-d H:i:s'),
                        'end_local' => $candidateEnd->format('Y-m-d H:i:s'),
                        'start_local_time' => $candidateStart->format('H:i'),
                    ];
                }

                $cursor->addMinutes($slotInterval);
            }
        }

        return $slots;
    }

    /**
     * @return array<string, mixed>
     */
    public function bookingRules(?AppointmentSetting $setting): array
    {
        $metadata = is_array($setting?->metadata) ? $setting->metadata : [];

        return $this->normalizeBookingRulesInput($metadata['booking_rules'] ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    public function cancellationRules(?AppointmentSetting $setting): array
    {
        $metadata = is_array($setting?->metadata) ? $setting->metadata : [];

        return $this->normalizeCancellationRulesInput($metadata['cancellation_rules'] ?? []);
    }

    /**
     * @param  array<int, int>  $resourceIds
     */
    private function ensureNoOverlap(
        int $workspaceId,
        Carbon $startsAt,
        Carbon $endsAt,
        ?int $staffId = null,
        array $resourceIds = [],
        int $bufferMinutes = 0,
        ?int $ignoreBookingId = null,
        bool $lockForUpdate = false,
        ?int $serviceId = null,
    ): void {
        $buffer = max(0, $bufferMinutes);
        $windowStart = $startsAt->copy()->subMinutes($buffer);
        $windowEnd = $endsAt->copy()->addMinutes($buffer);

        $baseQuery = AppointmentBooking::withoutGlobalScopes()
            ->where('workspace_id', $workspaceId)
            ->whereIn('appointment_status', self::ACTIVE_BOOKING_STATUSES)
            ->when($ignoreBookingId !== null, fn ($query) => $query->where('id', '!=', $ignoreBookingId))
            ->where(function ($query) use ($windowStart, $windowEnd): void {
                $query->where('starts_at', '<', $windowEnd)
                    ->where('ends_at', '>', $windowStart);
            });

        if ($lockForUpdate) {
            $baseQuery->lockForUpdate();
        }

        $staffOverlap = $staffId !== null
            ? (clone $baseQuery)->where('staff_id', $staffId)->exists()
            : false;

        // When staff is unspecified ("any staff"), enforce service capacity based on active staff.
        $capacityOverlap = false;
        if ($staffId === null && $serviceId !== null) {
            $service = AppointmentServiceModel::withoutGlobalScopes()
                ->where('workspace_id', $workspaceId)
                ->whereKey($serviceId)
                ->first();

            $staffCapacity = 1;
            if ($service) {
                $assignedIds = $service->staffMembers()->pluck('appointment_staff.id')->all();
                if ($assignedIds !== []) {
                    $staffCapacity = max(1, AppointmentStaff::withoutGlobalScopes()
                        ->where('workspace_id', $workspaceId)
                        ->whereIn('id', $assignedIds)
                        ->where('is_active', true)
                        ->count());
                } else {
                    $staffCapacity = max(1, AppointmentStaff::withoutGlobalScopes()
                        ->where('workspace_id', $workspaceId)
                        ->where('is_active', true)
                        ->count());
                }
            }

            $overlappingCount = (clone $baseQuery)
                ->where('service_id', $serviceId)
                ->count();

            $capacityOverlap = $overlappingCount >= $staffCapacity;
        }

        $resourceOverlap = $resourceIds !== []
            ? (clone $baseQuery)->whereHas('resources', fn ($query) => $query->whereIn('appointment_resources.id', $resourceIds))->exists()
            : false;

        if ($staffOverlap) {
            throw new RuntimeException('يوجد تعارض: هذا الوقت محجوز بالفعل لنفس الطاقم.');
        }

        if ($capacityOverlap) {
            throw new RuntimeException('يوجد تعارض: لا توجد سعة متاحة لهذه الخدمة في نفس التوقيت.');
        }

        if ($resourceOverlap) {
            throw new RuntimeException('يوجد تعارض: أحد الموارد المطلوبة محجوز في نفس التوقيت.');
        }
    }

    private function nextBookingNumber(int $workspaceId, Carbon $date): string
    {
        $prefix = 'APT-'.$date->format('Ymd').'-';
        $last = AppointmentBooking::withoutGlobalScopes()
            ->where('workspace_id', $workspaceId)
            ->where('booking_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('id')
            ->first();

        if (! $last) {
            return $prefix.'0001';
        }

        $seq = (int) substr($last->booking_number, -4);

        return $prefix.str_pad((string) ($seq + 1), 4, '0', STR_PAD_LEFT);
    }

    private function resolveLegacySource(string $sourceChannel): string
    {
        return match ($sourceChannel) {
            'ai_chat', 'email', 'api' => 'dashboard',
            default => $sourceChannel,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultSettingMetadata(): array
    {
        return [
            'business_hours' => $this->normalizeBusinessHoursInput([]),
            'booking_rules' => $this->normalizeBookingRulesInput([]),
            'cancellation_rules' => $this->normalizeCancellationRulesInput([]),
            'reminder_channels' => $this->normalizeReminderChannelsInput(['in_app']),
        ];
    }

    /**
     * @param  mixed  value
     */
    private function parseWorkspaceDateTime(mixed $value, string $timezone): Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy()->timezone($timezone);
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            throw new RuntimeException('تاريخ الموعد غير صالح.');
        }

        // ISO-8601 values with an explicit offset/Z are absolute instants.
        // Parse them first, then convert into the workspace timezone.
        if (preg_match('/(?:Z|[+-]\d{2}:?\d{2})$/i', $raw) === 1) {
            return Carbon::parse($raw)->timezone($timezone);
        }

        return Carbon::parse($raw, $timezone);
    }

    /**
     * @return array{start: Carbon, end: Carbon}
     */
    private function dayRangeInUtc(string $date, string $timezone): array
    {
        $startLocal = Carbon::parse($date, $timezone)->startOfDay();
        $endLocal = $startLocal->copy()->endOfDay();

        return [
            'start' => $startLocal->copy()->utc(),
            'end' => $endLocal->copy()->utc(),
        ];
    }

    /**
     * @param  array<string, mixed>  rules
     */
    private function assertBookingRules(
        int $workspaceId,
        Carbon $startsAtLocal,
        Carbon $endsAtLocal,
        array $rules,
        string $timezone,
        ?int $ignoreBookingId = null
    ): void
    {
        $now = now($timezone);
        if ($startsAtLocal->lt($now)) {
            throw new RuntimeException('لا يمكن إنشاء موعد في وقت ماضٍ.');
        }
        $minNotice = max(0, (int) ($rules['min_booking_notice_minutes'] ?? 0));
        if ($minNotice > 0 && $startsAtLocal->lt($now->copy()->addMinutes($minNotice))) {
            throw new RuntimeException("يجب أن يكون الموعد قبل {$minNotice} دقيقة على الأقل.");
        }

        $maxAdvanceDays = max(1, (int) ($rules['max_advance_booking_days'] ?? 365));
        if ($startsAtLocal->gt($now->copy()->addDays($maxAdvanceDays))) {
            throw new RuntimeException("لا يمكن الحجز لأكثر من {$maxAdvanceDays} يوم مقدمًا.");
        }

        $slotInterval = max(1, (int) ($rules['slot_interval_minutes'] ?? 30));
        if (($startsAtLocal->hour * 60 + $startsAtLocal->minute) % $slotInterval !== 0) {
            throw new RuntimeException("بداية الموعد يجب أن تتبع فواصل كل {$slotInterval} دقيقة.");
        }

        $maxDaily = max(0, (int) ($rules['max_bookings_per_day'] ?? 0));
        if ($maxDaily > 0) {
            $dayStartUtc = $startsAtLocal->copy()->startOfDay()->utc();
            $dayEndUtc = $startsAtLocal->copy()->endOfDay()->utc();
            $count = AppointmentBooking::withoutGlobalScopes()
                ->where('workspace_id', $workspaceId)
                ->whereBetween('starts_at', [$dayStartUtc, $dayEndUtc])
                ->whereIn('appointment_status', self::ACTIVE_BOOKING_STATUSES)
                ->when($ignoreBookingId !== null, fn ($query) => $query->where('id', '!=', $ignoreBookingId))
                ->count();
            if ($count >= $maxDaily) {
                throw new RuntimeException('تم الوصول للحد الأقصى للحجوزات في هذا اليوم.');
            }
        }

        if ($endsAtLocal->diffInMinutes($startsAtLocal) > 1440) {
            throw new RuntimeException('مدة الموعد غير منطقية.');
        }
    }

    private function assertWithinBusinessHours(Carbon $startsAtLocal, Carbon $endsAtLocal, ?AppointmentSetting $setting): void
    {
        $businessHours = $this->normalizeBusinessHoursInput(
            is_array($setting?->metadata) ? ($setting->metadata['business_hours'] ?? []) : []
        );

        $dayKey = $this->dayKey($startsAtLocal);
        $dayConfig = $businessHours[$dayKey] ?? ['closed' => false, 'ranges' => []];
        if (($dayConfig['closed'] ?? false) === true) {
            throw new RuntimeException('لا يمكن إنشاء موعد في يوم مغلق.');
        }

        $ranges = is_array($dayConfig['ranges'] ?? null) ? $dayConfig['ranges'] : [];
        if ($ranges === []) {
            return;
        }

        $fits = false;
        foreach ($ranges as $range) {
            $startTime = trim((string) ($range['start'] ?? ''));
            $endTime = trim((string) ($range['end'] ?? ''));
            if ($startTime === '' || $endTime === '') {
                continue;
            }
            $rangeStart = Carbon::parse($startsAtLocal->toDateString().' '.$startTime, $startsAtLocal->getTimezone());
            $rangeEnd = Carbon::parse($startsAtLocal->toDateString().' '.$endTime, $startsAtLocal->getTimezone());
            if ($startsAtLocal->gte($rangeStart) && $endsAtLocal->lte($rangeEnd)) {
                $fits = true;
                break;
            }
        }

        if (! $fits) {
            throw new RuntimeException('الموعد خارج ساعات عمل النشاط.');
        }
    }

    private function assertNotHoliday(int $workspaceId, Carbon $startsAtLocal, ?int $staffId): void
    {
        if ($this->isHoliday($workspaceId, $startsAtLocal, $staffId)) {
            throw new RuntimeException('لا يمكن الحجز في يوم عطلة.');
        }
    }

    private function isHoliday(int $workspaceId, Carbon $startsAtLocal, ?int $staffId): bool
    {
        $query = AppointmentHoliday::withoutGlobalScopes()
            ->where('workspace_id', $workspaceId)
            ->whereDate('holiday_date', $startsAtLocal->toDateString())
            ->where(function ($inner) use ($staffId): void {
                $inner->whereNull('staff_id');
                if ($staffId !== null) {
                    $inner->orWhere('staff_id', $staffId);
                }
            });

        $holidays = $query->get();
        if ($holidays->isEmpty()) {
            return false;
        }

        foreach ($holidays as $holiday) {
            if (! $holiday->start_time || ! $holiday->end_time) {
                return true;
            }

            $start = Carbon::parse($startsAtLocal->toDateString().' '.$holiday->start_time, $startsAtLocal->getTimezone());
            $end = Carbon::parse($startsAtLocal->toDateString().' '.$holiday->end_time, $startsAtLocal->getTimezone());
            if ($startsAtLocal->betweenIncluded($start, $end)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  rules
     */
    private function isWithinBookingRuleWindow(
        int $workspaceId,
        Carbon $startsAtLocal,
        Carbon $endsAtLocal,
        array $rules,
        string $timezone
    ): bool {
        try {
            $this->assertBookingRules(
                workspaceId: $workspaceId,
                startsAtLocal: $startsAtLocal,
                endsAtLocal: $endsAtLocal,
                rules: $rules,
                timezone: $timezone,
            );

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * @param array<int,int> $resourceIds
     */
    private function hasOverlap(
        int $workspaceId,
        Carbon $startsAt,
        Carbon $endsAt,
        ?int $staffId,
        array $resourceIds,
        int $bufferMinutes
    ): bool {
        $buffer = max(0, $bufferMinutes);
        $windowStart = $startsAt->copy()->subMinutes($buffer);
        $windowEnd = $endsAt->copy()->addMinutes($buffer);

        $baseQuery = AppointmentBooking::withoutGlobalScopes()
            ->where('workspace_id', $workspaceId)
            ->whereIn('appointment_status', self::ACTIVE_BOOKING_STATUSES)
            ->where(function ($query) use ($windowStart, $windowEnd): void {
                $query->where('starts_at', '<', $windowEnd)
                    ->where('ends_at', '>', $windowStart);
            });

        $staffOverlap = $staffId !== null
            ? (clone $baseQuery)->where('staff_id', $staffId)->exists()
            : false;

        $resourceOverlap = $resourceIds !== []
            ? (clone $baseQuery)->whereHas('resources', fn ($query) => $query->whereIn('appointment_resources.id', $resourceIds))->exists()
            : false;

        return $staffOverlap || $resourceOverlap;
    }

    private function assertStaffAvailability(?AppointmentStaff $staff, Carbon $startsAtLocal, Carbon $endsAtLocal, string $timezone): void
    {
        if (! $staff) {
            return;
        }

        if (! $staff->is_active) {
            throw new RuntimeException('الموظف غير نشط ولا يمكن الحجز عليه.');
        }

        $dayKey = $this->dayKey($startsAtLocal);
        $workingDays = is_array($staff->working_days) ? $staff->working_days : [];
        if ($workingDays !== [] && ! in_array($dayKey, $workingDays, true)) {
            throw new RuntimeException('لا يمكن الحجز للموظف في هذا اليوم.');
        }

        $workingHours = is_array($staff->working_hours) ? $staff->working_hours : [];
        if (isset($workingHours[$dayKey]) && is_array($workingHours[$dayKey]) && $workingHours[$dayKey] !== []) {
            $fits = false;
            foreach ($workingHours[$dayKey] as $range) {
                $rangeStart = trim((string) ($range['start'] ?? ''));
                $rangeEnd = trim((string) ($range['end'] ?? ''));
                if ($rangeStart === '' || $rangeEnd === '') {
                    continue;
                }
                $start = Carbon::parse($startsAtLocal->toDateString().' '.$rangeStart, $timezone);
                $end = Carbon::parse($startsAtLocal->toDateString().' '.$rangeEnd, $timezone);
                if ($startsAtLocal->gte($start) && $endsAtLocal->lte($end)) {
                    $fits = true;
                    break;
                }
            }

            if (! $fits) {
                throw new RuntimeException('الموعد خارج ساعات عمل الموظف.');
            }
        }

        $vacations = is_array($staff->vacation_periods) ? $staff->vacation_periods : [];
        foreach ($vacations as $vacation) {
            $from = trim((string) ($vacation['from'] ?? ''));
            $to = trim((string) ($vacation['to'] ?? ''));
            if ($from === '' || $to === '') {
                continue;
            }
            $vacationStart = Carbon::parse($from, $timezone)->startOfDay();
            $vacationEnd = Carbon::parse($to, $timezone)->endOfDay();
            if ($startsAtLocal->betweenIncluded($vacationStart, $vacationEnd)) {
                throw new RuntimeException('لا يمكن الحجز في فترة إجازة الموظف.');
            }
        }
    }

    private function ensureNoDuplicateBooking(
        int $workspaceId,
        Carbon $startsAt,
        int $serviceId,
        string $customerName,
        ?string $customerPhone,
        ?int $staffId,
        ?int $ignoreBookingId = null,
    ): void {
        $duplicateExists = AppointmentBooking::withoutGlobalScopes()
            ->where('workspace_id', $workspaceId)
            ->where('service_id', $serviceId)
            ->where('starts_at', $startsAt)
            ->when($staffId !== null, fn ($query) => $query->where('staff_id', $staffId))
            ->when($ignoreBookingId !== null, fn ($query) => $query->where('id', '!=', $ignoreBookingId))
            ->where(function ($query) use ($customerName, $customerPhone): void {
                $query->whereRaw('LOWER(customer_name) = ?', [mb_strtolower($customerName)]);
                if ($customerPhone !== null) {
                    $query->orWhere('customer_phone', $customerPhone);
                }
            })
            ->whereIn('appointment_status', self::ACTIVE_BOOKING_STATUSES)
            ->exists();

        if ($duplicateExists) {
            throw new RuntimeException('يوجد حجز مكرر بنفس بيانات العميل والوقت.');
        }
    }

    /**
     * @param  mixed  input
     * @return array<string, mixed>
     */
    private function normalizeBookingRulesInput(mixed $input): array
    {
        $rules = is_array($input) ? $input : [];

        return [
            'min_booking_notice_minutes' => max(0, (int) ($rules['min_booking_notice_minutes'] ?? 0)),
            'max_advance_booking_days' => max(1, (int) ($rules['max_advance_booking_days'] ?? 180)),
            'slot_interval_minutes' => max(5, (int) ($rules['slot_interval_minutes'] ?? 30)),
            'buffer_minutes' => max(0, (int) ($rules['buffer_minutes'] ?? 0)),
            'max_bookings_per_day' => max(0, (int) ($rules['max_bookings_per_day'] ?? 0)),
        ];
    }

    /**
     * @param  mixed  input
     * @return array<string, mixed>
     */
    private function normalizeCancellationRulesInput(mixed $input): array
    {
        $rules = is_array($input) ? $input : [];

        return [
            'minimum_notice_hours' => max(0, (int) ($rules['minimum_notice_hours'] ?? 0)),
            'cancellation_window_hours' => max(0, (int) ($rules['cancellation_window_hours'] ?? 0)),
            'reschedule_window_hours' => max(0, (int) ($rules['reschedule_window_hours'] ?? 0)),
            'maximum_reschedules' => max(0, (int) ($rules['maximum_reschedules'] ?? 3)),
        ];
    }

    /**
     * @param mixed $input
     * @return array<int, string>
     */
    private function normalizeReminderChannelsInput(mixed $input): array
    {
        $channels = collect(is_array($input) ? $input : [])
            ->map(fn ($channel) => trim((string) $channel))
            ->filter(fn (string $channel): bool => in_array($channel, ['in_app', 'email', 'whatsapp', 'sms'], true))
            ->unique()
            ->values()
            ->all();

        return $channels === [] ? ['in_app'] : $channels;
    }

    /**
     * @param  mixed  input
     * @return array<string, array{closed: bool, ranges: array<int, array{start: string, end: string}>}>
     */
    private function normalizeBusinessHoursInput(mixed $input): array
    {
        $source = is_array($input) ? $input : [];
        $normalized = [];

        foreach (self::WEEK_DAYS as $day) {
            $dayData = is_array($source[$day] ?? null) ? $source[$day] : [];
            $closedDefault = $day === 'fri';
            $closed = array_key_exists('closed', $dayData) ? (bool) $dayData['closed'] : $closedDefault;
            $rawRanges = is_array($dayData['ranges'] ?? null) ? $dayData['ranges'] : [];
            $ranges = [];
            foreach ($rawRanges as $range) {
                $start = trim((string) ($range['start'] ?? ''));
                $end = trim((string) ($range['end'] ?? ''));
                if (
                    $start !== '' &&
                    $end !== '' &&
                    preg_match('/^\d{2}:\d{2}$/', $start) &&
                    preg_match('/^\d{2}:\d{2}$/', $end)
                ) {
                    $ranges[] = ['start' => $start, 'end' => $end];
                }
            }
            if ($ranges === [] && $day !== 'fri') {
                $ranges = [['start' => '09:00', 'end' => '17:00']];
            }

            $normalized[$day] = [
                'closed' => $closed,
                'ranges' => $ranges,
            ];
        }

        return $normalized;
    }

    private function dayKey(Carbon $date): string
    {
        return match ($date->dayOfWeek) {
            Carbon::SUNDAY => 'sun',
            Carbon::MONDAY => 'mon',
            Carbon::TUESDAY => 'tue',
            Carbon::WEDNESDAY => 'wed',
            Carbon::THURSDAY => 'thu',
            Carbon::FRIDAY => 'fri',
            Carbon::SATURDAY => 'sat',
        };
    }

    private function assertStatusTransition(string $from, string $to): void
    {
        if ($from === '' || $from === $to) {
            return;
        }

        $allowed = [
            'scheduled' => ['confirmed', 'checked_in', 'in_progress', 'completed', 'cancelled', 'no_show', 'scheduled'],
            'confirmed' => ['checked_in', 'in_progress', 'completed', 'cancelled', 'no_show', 'confirmed'],
            'checked_in' => ['in_progress', 'completed', 'cancelled', 'checked_in'],
            'in_progress' => ['completed', 'cancelled', 'in_progress'],
            'completed' => ['completed'],
            'cancelled' => ['cancelled'],
            'no_show' => ['no_show'],
        ];

        if (! in_array($to, $allowed[$from] ?? [], true)) {
            throw new RuntimeException("لا يمكن نقل الحالة من {$from} إلى {$to}.");
        }
    }

    private function assertPolicyWindow(AppointmentBooking $booking, string $type): void
    {
        $setting = AppointmentSetting::withoutGlobalScopes()
            ->where('workspace_id', $booking->workspace_id)
            ->first();
        $rules = $this->cancellationRules($setting);
        $timezone = $this->workspaceTimezone($booking->workspace_id, $setting);
        $start = $booking->starts_at?->copy()->timezone($timezone);
        if (! $start) {
            return;
        }

        $hoursBeforeStart = now($timezone)->diffInHours($start, false);
        $minHours = max(
            (int) ($rules['minimum_notice_hours'] ?? 0),
            (int) ($type === 'cancellation'
                ? ($rules['cancellation_window_hours'] ?? 0)
                : ($rules['reschedule_window_hours'] ?? 0))
        );

        if ($minHours > 0 && $hoursBeforeStart < $minHours) {
            $action = $type === 'cancellation' ? 'إلغاء الموعد' : 'إعادة جدولة الموعد';
            throw new RuntimeException("لا يمكن {$action} قبل أقل من {$minHours} ساعة.");
        }

        if ($type === 'reschedule') {
            $maxReschedules = max(0, (int) ($rules['maximum_reschedules'] ?? 0));
            $metadata = is_array($booking->metadata) ? $booking->metadata : [];
            $currentReschedules = max(0, (int) ($metadata['reschedule_count'] ?? 0));
            if ($maxReschedules > 0 && $currentReschedules >= $maxReschedules) {
                throw new RuntimeException('تم الوصول إلى الحد الأعلى لإعادة الجدولة لهذا الموعد.');
            }
        }
    }
}
