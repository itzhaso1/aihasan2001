<?php

namespace App\Services\Appointments;

use App\Models\Appointment\AppointmentBooking;
use App\Models\Appointment\AppointmentRequest;
use App\Models\Appointment\AppointmentRequestSlot;
use App\Models\Appointment\AppointmentService as AppointmentServiceModel;
use App\Models\Appointment\AppointmentSetting;
use App\Models\Appointment\AppointmentStaff;
use App\Models\Customer;
use App\Models\Workspace;
use App\Services\Notification\DomainNotificationService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AppointmentRequestService
{
    /** @var array<int, string> */
    public const REQUEST_STATUSES = ['new', 'reviewing', 'awaiting_customer', 'approved', 'rejected', 'expired', 'cancelled'];

    public function __construct(
        private readonly AppointmentService $appointmentService,
        private readonly AppointmentBillingService $appointmentBillingService,
        private readonly DomainNotificationService $domainNotificationService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createRequest(Workspace $workspace, array $payload, ?int $actorUserId = null, bool $aiGenerated = false): AppointmentRequest
    {
        $this->appointmentService->ensureSetup($workspace);
        $setting = AppointmentSetting::query()->first();
        $customer = $this->resolveCustomer($payload);

        $customerName = trim((string) ($payload['customer_name'] ?? ''));
        if ($customerName === '' && $customer) {
            $customerName = (string) $customer->name;
        }
        if ($customerName === '') {
            throw new RuntimeException('اسم العميل مطلوب لإنشاء طلب الموعد.');
        }

        $this->assertRequestPolicy($workspace, $setting, $payload);

        $source = (string) ($payload['source'] ?? 'dashboard');
        if (! in_array($source, AppointmentService::BOOKING_SOURCES, true)) {
            $source = 'dashboard';
        }

        $automationMode = (string) ($payload['automation_mode'] ?? $setting?->automation_mode ?? 'APPROVAL');
        if (! in_array($automationMode, ['AUTO', 'APPROVAL', 'MANUAL'], true)) {
            $automationMode = 'APPROVAL';
        }
        $status = (string) ($payload['status'] ?? 'new');
        if (! in_array($status, self::REQUEST_STATUSES, true)) {
            $status = 'new';
        }

        $request = DB::transaction(function () use ($workspace, $payload, $customer, $customerName, $source, $automationMode, $aiGenerated, $status): AppointmentRequest {
            return AppointmentRequest::withoutGlobalScopes()->create([
                'workspace_id' => $workspace->id,
                'request_type' => (string) ($payload['request_type'] ?? 'new'),
                'target_booking_id' => isset($payload['target_booking_id']) ? (int) $payload['target_booking_id'] : null,
                'customer_id' => $customer?->id,
                'conversation_id' => isset($payload['conversation_id']) ? (int) $payload['conversation_id'] : null,
                'requested_service_id' => isset($payload['requested_service_id']) ? (int) $payload['requested_service_id'] : null,
                'requested_staff_id' => isset($payload['requested_staff_id']) ? (int) $payload['requested_staff_id'] : null,
                'customer_name' => $customerName,
                'customer_phone' => trim((string) ($payload['customer_phone'] ?? $customer?->phone ?? '')) ?: null,
                'customer_email' => trim((string) ($payload['customer_email'] ?? $customer?->email ?? '')) ?: null,
                'customer_age' => isset($payload['customer_age']) ? max(1, (int) $payload['customer_age']) : null,
                'requested_date' => ! empty($payload['requested_date']) ? Carbon::parse((string) $payload['requested_date'])->toDateString() : null,
                'requested_time' => trim((string) ($payload['requested_time'] ?? '')) ?: null,
                'requested_time_end' => trim((string) ($payload['requested_time_end'] ?? '')) ?: null,
                'status' => $status,
                'appointment_status' => null,
                'payment_status' => 'unpaid',
                'source' => $source,
                'automation_mode' => $automationMode,
                'notes' => trim((string) ($payload['notes'] ?? '')) ?: null,
                'ai_generated' => $aiGenerated || (bool) ($payload['ai_generated'] ?? false),
                'ai_payload' => is_array($payload['ai_payload'] ?? null) ? $payload['ai_payload'] : null,
                'expires_at' => ! empty($payload['expires_at']) ? Carbon::parse((string) $payload['expires_at']) : null,
            ]);
        });

        $this->domainNotificationService->notifyAppointmentRequestCreated($request);

        if (
            $automationMode === 'AUTO'
            && ! empty($payload['requested_service_id'])
            && ! empty($payload['starts_at'])
        ) {
            $this->approveRequest($request, [
                'service_id' => (int) $payload['requested_service_id'],
                'staff_id' => isset($payload['requested_staff_id']) ? (int) $payload['requested_staff_id'] : null,
                'starts_at' => (string) $payload['starts_at'],
                'ends_at' => (string) ($payload['ends_at'] ?? ''),
                'source_channel' => $source,
                'notes' => trim((string) ($payload['notes'] ?? '')),
                'appointment_status' => 'scheduled',
            ], $actorUserId);
        }

        return $request->fresh(['service', 'staff', 'customer', 'slots']);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function approveRequest(AppointmentRequest $request, array $payload, ?int $actorUserId): AppointmentBooking
    {
        $booking = DB::transaction(function () use ($request, $payload, $actorUserId): AppointmentBooking {
            $lockedRequest = AppointmentRequest::withoutGlobalScopes()
                ->whereKey($request->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($lockedRequest->status, ['approved', 'rejected', 'cancelled', 'expired'], true)) {
                throw new RuntimeException('لا يمكن اعتماد طلب موعد مغلق.');
            }

            $serviceId = (int) ($payload['service_id'] ?? $lockedRequest->requested_service_id ?? 0);
            if ($serviceId <= 0) {
                throw new RuntimeException('الخدمة مطلوبة لاعتماد الطلب.');
            }

            $service = AppointmentServiceModel::query()->whereKey($serviceId)->firstOrFail();
            $staffId = isset($payload['staff_id'])
                ? (int) $payload['staff_id']
                : (isset($lockedRequest->requested_staff_id) ? (int) $lockedRequest->requested_staff_id : null);

            if ($staffId !== null && $staffId > 0) {
                AppointmentStaff::query()->whereKey($staffId)->firstOrFail();
            }

            [$startsAt, $endsAt] = $this->resolveBookingWindow($lockedRequest, $service, $payload);
            $appointmentStatus = (string) ($payload['appointment_status'] ?? 'scheduled');
            if (! in_array($appointmentStatus, AppointmentService::APPOINTMENT_STATUSES, true)) {
                $appointmentStatus = 'scheduled';
            }

            $booking = $this->appointmentService->createBooking($lockedRequest->workspace, [
                'request_id' => $lockedRequest->id,
                'service_id' => $service->id,
                'staff_id' => $staffId,
                'customer_id' => $lockedRequest->customer_id,
                'customer_name' => $lockedRequest->customer_name,
                'customer_phone' => $lockedRequest->customer_phone,
                'customer_email' => $lockedRequest->customer_email,
                'customer_age' => $lockedRequest->customer_age,
                'starts_at' => $startsAt->toDateTimeString(),
                'ends_at' => $endsAt->toDateTimeString(),
                'allow_custom_duration' => true,
                'appointment_status' => $appointmentStatus,
                'status' => $appointmentStatus,
                'payment_status' => $service->requires_payment ? 'unpaid' : 'paid',
                'source_channel' => $payload['source_channel'] ?? $lockedRequest->source,
                'notes' => $payload['notes'] ?? $lockedRequest->notes,
                'resource_ids' => $payload['resource_ids'] ?? [],
                'metadata' => [
                    'approved_from_request_id' => $lockedRequest->id,
                    'request_type' => $lockedRequest->request_type,
                ],
            ], $actorUserId);

            $lockedRequest->update([
                'requested_service_id' => $service->id,
                'requested_staff_id' => $staffId,
                'status' => 'approved',
                'appointment_status' => $booking->appointment_status,
                'payment_status' => $booking->payment_status,
                'approved_by' => $actorUserId,
                'approved_at' => now(),
                'rejected_by' => null,
                'rejected_at' => null,
                'cancelled_at' => null,
            ]);

            if ($service->requires_payment && (float) $service->price > 0) {
                $booking = $this->appointmentBillingService->createInvoiceAndPaymentLink($booking, $actorUserId);
                $lockedRequest->update(['payment_status' => $booking->payment_status]);
            }

            return $booking;
        });

        $this->domainNotificationService->notifyAppointmentBookingStatusChanged(
            $booking,
            'تم اعتماد طلب الموعد',
            'تمت الموافقة على طلب الموعد وإنشاء الحجز بنجاح.'
        );
        $this->domainNotificationService->notifyAppointmentRequestStatusChanged(
            $request->refresh(),
            'تم اعتماد طلب الموعد',
            'تمت الموافقة على الطلب وتحويله إلى حجز فعلي.'
        );

        return $booking;
    }

    /**
     * @param  array<int, array<string,mixed>>  $slots
     * @return array<int, AppointmentRequestSlot>
     */
    public function proposeSlots(AppointmentRequest $request, array $slots, ?int $actorUserId): array
    {
        if ($slots === []) {
            throw new RuntimeException('يجب تقديم موعد واحد على الأقل.');
        }

        $created = [];

        $timezone = $this->appointmentService->workspaceTimezone($request->workspace_id);
        DB::transaction(function () use ($request, $slots, $actorUserId, &$created, $timezone): void {
            foreach ($slots as $slotPayload) {
                $startsAt = Carbon::parse((string) ($slotPayload['starts_at'] ?? ''), $timezone)->utc();
                $endsAt = Carbon::parse((string) ($slotPayload['ends_at'] ?? ''), $timezone)->utc();
                if ($endsAt->lte($startsAt)) {
                    throw new RuntimeException('نطاق وقت الموعد المقترح غير صحيح.');
                }

                $created[] = AppointmentRequestSlot::withoutGlobalScopes()->create([
                    'workspace_id' => $request->workspace_id,
                    'request_id' => $request->id,
                    'service_id' => isset($slotPayload['service_id']) ? (int) $slotPayload['service_id'] : $request->requested_service_id,
                    'staff_id' => isset($slotPayload['staff_id']) ? (int) $slotPayload['staff_id'] : $request->requested_staff_id,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'status' => 'proposed',
                    'proposed_by' => $actorUserId,
                    'expires_at' => ! empty($slotPayload['expires_at']) ? Carbon::parse((string) $slotPayload['expires_at'], $timezone)->utc() : null,
                    'metadata' => is_array($slotPayload['metadata'] ?? null) ? $slotPayload['metadata'] : null,
                ]);
            }

            $request->update([
                'status' => 'awaiting_customer',
                'notes' => trim((string) ($request->notes ?? ''))."\nتم إرسال مواعيد مقترحة للعميل.",
            ]);
        });

        $this->domainNotificationService->notifyAppointmentRequestStatusChanged(
            $request->refresh(),
            'تم اقتراح مواعيد للعميل',
            'تم إرسال خيارات متعددة للعميل وبانتظار اختياره.'
        );

        return $created;
    }

    public function selectSlot(AppointmentRequest $request, AppointmentRequestSlot $slot, ?int $actorUserId): AppointmentBooking
    {
        if ((int) $slot->request_id !== (int) $request->id) {
            throw new RuntimeException('الموعد المقترح لا ينتمي إلى نفس الطلب.');
        }

        DB::transaction(function () use ($request, $slot): void {
            AppointmentRequestSlot::query()
                ->where('request_id', $request->id)
                ->where('status', 'proposed')
                ->update(['status' => 'rejected']);

            $slot->update(['status' => 'selected']);
            $request->update([
                'status' => 'reviewing',
                'last_customer_response_at' => now(),
            ]);
        });

        return $this->approveRequest($request, [
            'slot_id' => $slot->id,
            'service_id' => $slot->service_id ?? $request->requested_service_id,
            'staff_id' => $slot->staff_id ?? $request->requested_staff_id,
            'starts_at' => $slot->starts_at?->toDateTimeString(),
            'ends_at' => $slot->ends_at?->toDateTimeString(),
            'appointment_status' => 'scheduled',
            'source_channel' => $request->source,
        ], $actorUserId);
    }

    public function rejectRequest(AppointmentRequest $request, ?int $actorUserId, ?string $reason = null): AppointmentRequest
    {
        $request->update([
            'status' => 'rejected',
            'rejected_by' => $actorUserId,
            'rejected_at' => now(),
            'notes' => trim((string) ($request->notes ?? '')).($reason ? "\nسبب الرفض: {$reason}" : ''),
        ]);
        $request = $request->refresh();
        $this->domainNotificationService->notifyAppointmentRequestStatusChanged(
            $request,
            'تم رفض طلب الموعد',
            'تم رفض طلب الموعد من فريق العمل.'
        );

        return $request;
    }

    public function cancelRequest(AppointmentRequest $request, ?string $reason = null): AppointmentRequest
    {
        $request->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'notes' => trim((string) ($request->notes ?? '')).($reason ? "\nسبب الإلغاء: {$reason}" : ''),
        ]);
        $request = $request->refresh();
        $this->domainNotificationService->notifyAppointmentRequestStatusChanged(
            $request,
            'تم إلغاء طلب الموعد',
            'تم إلغاء الطلب حسب طلب العميل أو فريق العمل.'
        );

        return $request;
    }

    public function markAwaitingCustomer(AppointmentRequest $request, string $message): AppointmentRequest
    {
        $request->update([
            'status' => 'awaiting_customer',
            'notes' => trim((string) ($request->notes ?? ''))."\n".$message,
        ]);
        $request = $request->refresh();
        $this->domainNotificationService->notifyAppointmentRequestStatusChanged(
            $request,
            'الطلب بانتظار العميل',
            'تم طلب معلومات/اختيار إضافي من العميل لإكمال الحجز.'
        );

        return $request;
    }

    /**
     * @param  array<string,mixed>  $filters
     */
    public function listRequests(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $source = trim((string) ($filters['source'] ?? ''));
        $date = trim((string) ($filters['date'] ?? ''));
        $staffUserId = (int) ($filters['staff_user_id'] ?? 0);
        $timezone = (string) ($filters['timezone'] ?? $this->appointmentService->workspaceTimezone());
        $range = $date !== ''
            ? [
                Carbon::parse($date, $timezone)->startOfDay()->utc(),
                Carbon::parse($date, $timezone)->endOfDay()->utc(),
            ]
            : null;

        return AppointmentRequest::query()
            ->with(['service', 'staff', 'customer', 'slots', 'booking'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('customer_name', 'like', '%'.$search.'%')
                        ->orWhere('customer_phone', 'like', '%'.$search.'%')
                        ->orWhere('customer_email', 'like', '%'.$search.'%')
                        ->orWhereHas('service', fn ($serviceQuery) => $serviceQuery->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($source !== '', fn ($query) => $query->where('source', $source))
            ->when($staffUserId > 0, fn ($query) => $query->whereHas('staff', fn ($staffQuery) => $staffQuery->where('user_id', $staffUserId)))
            ->when($range !== null, fn ($query) => $query->whereBetween('created_at', $range))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array{0: Carbon,1: Carbon}
     */
    private function resolveBookingWindow(AppointmentRequest $request, AppointmentServiceModel $service, array $payload): array
    {
        $timezone = $this->appointmentService->workspaceTimezone($request->workspace_id);

        if (! empty($payload['slot_id'])) {
            $slot = AppointmentRequestSlot::query()
                ->where('request_id', $request->id)
                ->whereKey((int) $payload['slot_id'])
                ->firstOrFail();

            return [
                Carbon::parse($slot->starts_at)->timezone($timezone),
                Carbon::parse($slot->ends_at)->timezone($timezone),
            ];
        }

        if (! empty($payload['starts_at'])) {
            $startsAt = Carbon::parse((string) $payload['starts_at'], $timezone);
            if (! empty($payload['ends_at'])) {
                return [$startsAt, Carbon::parse((string) $payload['ends_at'], $timezone)];
            }

            return [$startsAt, $startsAt->copy()->addMinutes((int) $service->duration_minutes)];
        }

        if ($request->requested_date && $request->requested_time) {
            $startsAt = Carbon::parse($request->requested_date->toDateString().' '.$request->requested_time, $timezone);
            $endsAt = $request->requested_time_end
                ? Carbon::parse($request->requested_date->toDateString().' '.$request->requested_time_end, $timezone)
                : $startsAt->copy()->addMinutes((int) $service->duration_minutes);

            return [$startsAt, $endsAt];
        }

        throw new RuntimeException('وقت الموعد غير مكتمل لاعتماد الطلب.');
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function resolveCustomer(array $payload): ?Customer
    {
        if (! empty($payload['customer_id'])) {
            return Customer::query()->whereKey((int) $payload['customer_id'])->first();
        }

        $phone = trim((string) ($payload['customer_phone'] ?? ''));
        if ($phone !== '') {
            $byPhone = Customer::query()->where('phone', $phone)->first();
            if ($byPhone) {
                return $byPhone;
            }
        }

        $email = trim((string) ($payload['customer_email'] ?? ''));
        if ($email !== '') {
            return Customer::query()
                ->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])
                ->first();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  payload
     */
    private function assertRequestPolicy(Workspace $workspace, ?AppointmentSetting $setting, array $payload): void
    {
        $requestType = (string) ($payload['request_type'] ?? 'new');
        if (! in_array($requestType, ['reschedule', 'cancellation'], true)) {
            return;
        }

        $targetBookingId = (int) ($payload['target_booking_id'] ?? 0);
        if ($targetBookingId <= 0) {
            throw new RuntimeException('يجب تحديد الموعد المراد تعديله أو إلغاؤه.');
        }

        $targetBooking = AppointmentBooking::query()->whereKey($targetBookingId)->firstOrFail();
        if ((int) $targetBooking->workspace_id !== (int) $workspace->id) {
            throw new RuntimeException('لا يمكن الوصول إلى هذا الموعد.');
        }

        $rules = $this->appointmentService->cancellationRules($setting);
        $timezone = $this->appointmentService->workspaceTimezone($workspace->id, $setting);
        $targetStart = $targetBooking->starts_at?->copy()->timezone($timezone);
        if (! $targetStart) {
            throw new RuntimeException('هذا الموعد لا يحتوي على وقت صالح.');
        }
        $hoursBeforeStart = now($timezone)->diffInHours($targetStart, false);

        $minimumNoticeHours = max(
            (int) ($rules['minimum_notice_hours'] ?? 0),
            (int) ($requestType === 'cancellation'
                ? ($rules['cancellation_window_hours'] ?? 0)
                : ($rules['reschedule_window_hours'] ?? 0))
        );
        if ($minimumNoticeHours > 0 && $hoursBeforeStart < $minimumNoticeHours) {
            $actionLabel = $requestType === 'cancellation' ? 'إلغاء الموعد' : 'إعادة الجدولة';
            throw new RuntimeException("لا يمكن {$actionLabel} قبل أقل من {$minimumNoticeHours} ساعة من الموعد.");
        }

        if ($requestType === 'reschedule') {
            $maxReschedules = max(0, (int) ($rules['maximum_reschedules'] ?? 0));
            if ($maxReschedules > 0) {
                $rescheduleCount = AppointmentRequest::query()
                    ->where('target_booking_id', $targetBookingId)
                    ->where('request_type', 'reschedule')
                    ->whereIn('status', ['approved', 'new', 'reviewing', 'awaiting_customer'])
                    ->count();

                if ($rescheduleCount >= $maxReschedules) {
                    throw new RuntimeException('تم الوصول للحد الأقصى لإعادة جدولة هذا الموعد.');
                }
            }
        }
    }
}
