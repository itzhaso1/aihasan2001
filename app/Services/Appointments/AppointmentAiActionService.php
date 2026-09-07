<?php

namespace App\Services\Appointments;

use App\Models\Appointment\AppointmentBooking;
use App\Models\Appointment\AppointmentReminder;
use App\Models\Appointment\AppointmentRequest;
use App\Models\Appointment\AppointmentRequestSlot;
use App\Models\Appointment\AppointmentSetting;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\User;
use App\Models\Workspace;
use RuntimeException;

class AppointmentAiActionService
{
    /** @var array<int, string> */
    public const ALLOWED_ACTIONS = [
        'create_appointment_request',
        'get_appointment_request',
        'suggest_slots',
        'select_slot',
        'approve_request',
        'reject_request',
        'create_booking',
        'get_booking',
        'get_customer',
        'create_customer',
        'update_customer',
        'get_appointment',
        'request_reschedule',
        'request_cancellation',
        'send_payment_link',
        'send_confirmation',
        'send_reminder',
    ];

    public function __construct(
        private readonly AppointmentRequestService $appointmentRequestService,
        private readonly AppointmentBillingService $appointmentBillingService,
        private readonly AppointmentService $appointmentService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function execute(
        Workspace $workspace,
        string $action,
        array $payload,
        ?User $actor = null,
        ?Conversation $conversation = null
    ): array {
        if (! in_array($action, self::ALLOWED_ACTIONS, true)) {
            throw new RuntimeException('AI action غير مسموح.');
        }

        $setting = AppointmentSetting::query()->first();
        $automationMode = (string) ($setting?->automation_mode ?? 'APPROVAL');
        $actorId = $actor?->id;
        $this->assertAutomationModeForAction($automationMode, $action);

        return match ($action) {
            'create_appointment_request' => $this->createRequestAction($workspace, $payload, $actorId, $automationMode, $conversation),
            'get_appointment_request' => $this->getAppointmentRequestAction($payload),
            'suggest_slots' => $this->suggestSlotsAction($payload, $actorId),
            'select_slot' => $this->selectSlotAction($payload, $actorId),
            'approve_request' => $this->approveRequestAction($payload, $actorId),
            'reject_request' => $this->rejectRequestAction($payload, $actorId),
            'create_booking' => $this->createBookingAction($workspace, $payload, $actorId),
            'get_booking' => $this->getAppointmentAction($payload),
            'get_customer' => $this->getCustomerAction($payload),
            'create_customer' => $this->createCustomerAction($payload),
            'update_customer' => $this->updateCustomerAction($payload),
            'get_appointment' => $this->getAppointmentAction($payload),
            'request_reschedule' => $this->requestRescheduleAction($workspace, $payload, $actorId, $automationMode, $conversation),
            'request_cancellation' => $this->requestCancellationAction($workspace, $payload, $actorId, $automationMode, $conversation),
            'send_payment_link' => $this->sendPaymentLinkAction($payload, $actorId),
            'send_confirmation' => $this->sendConfirmationAction($payload, $automationMode),
            'send_reminder' => $this->sendReminderAction($payload),
            default => throw new RuntimeException('AI action غير مدعوم.'),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function createRequestAction(
        Workspace $workspace,
        array $payload,
        ?int $actorId,
        string $automationMode,
        ?Conversation $conversation
    ): array {
        $request = $this->appointmentRequestService->createRequest($workspace, [
            ...$payload,
            'source' => $payload['source'] ?? 'ai_chat',
            'conversation_id' => $payload['conversation_id'] ?? $conversation?->id,
            'automation_mode' => $automationMode,
            'ai_generated' => true,
        ], $actorId, true);

        return [
            'request_id' => $request->id,
            'status' => $request->status,
            'automation_mode' => $request->automation_mode,
            'message' => 'تم إنشاء طلب الموعد بنجاح.',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function getAppointmentRequestAction(array $payload): array
    {
        $query = AppointmentRequest::query()->with(['service', 'staff', 'booking', 'slots']);
        if (! empty($payload['request_id'])) {
            $query->whereKey((int) $payload['request_id']);
        } else {
            throw new RuntimeException('request_id مطلوب.');
        }
        $request = $query->firstOrFail();

        return [
            'request' => [
                'id' => $request->id,
                'status' => $request->status,
                'customer_name' => $request->customer_name,
                'service' => $request->service?->name,
                'staff' => $request->staff?->name,
                'requested_date' => $request->requested_date?->toDateString(),
                'requested_time' => $request->requested_time,
                'source' => $request->source,
                'booking_id' => $request->booking?->id,
                'slots' => $request->slots->map(fn (AppointmentRequestSlot $slot) => [
                    'id' => $slot->id,
                    'starts_at' => $slot->starts_at?->toDateTimeString(),
                    'ends_at' => $slot->ends_at?->toDateTimeString(),
                    'status' => $slot->status,
                ])->all(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function suggestSlotsAction(array $payload, ?int $actorId): array
    {
        $request = AppointmentRequest::query()->whereKey((int) ($payload['request_id'] ?? 0))->firstOrFail();
        $slots = is_array($payload['slots'] ?? null) ? $payload['slots'] : [];
        if ($slots === []) {
            throw new RuntimeException('يجب تمرير slots لاقتراح المواعيد.');
        }

        $created = $this->appointmentRequestService->proposeSlots($request, $slots, $actorId);

        return [
            'request_id' => $request->id,
            'created_slots' => collect($created)->map(fn ($slot) => [
                'id' => $slot->id,
                'starts_at' => $slot->starts_at?->toDateTimeString(),
                'ends_at' => $slot->ends_at?->toDateTimeString(),
                'status' => $slot->status,
            ])->all(),
            'message' => 'تم اقتراح المواعيد للعميل.',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function selectSlotAction(array $payload, ?int $actorId): array
    {
        $request = AppointmentRequest::query()->whereKey((int) ($payload['request_id'] ?? 0))->firstOrFail();
        $slot = AppointmentRequestSlot::query()
            ->where('request_id', $request->id)
            ->whereKey((int) ($payload['slot_id'] ?? 0))
            ->firstOrFail();
        $booking = $this->appointmentRequestService->selectSlot($request, $slot, $actorId);

        return [
            'request_id' => $request->id,
            'booking_id' => $booking->id,
            'booking_number' => $booking->booking_number,
            'status' => $booking->appointment_status,
            'message' => 'تم اختيار الموعد المقترح وتحويله إلى حجز.',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function approveRequestAction(array $payload, ?int $actorId): array
    {
        $request = AppointmentRequest::query()->whereKey((int) ($payload['request_id'] ?? 0))->firstOrFail();
        $booking = $this->appointmentRequestService->approveRequest($request, $payload, $actorId);

        return [
            'request_id' => $request->id,
            'booking_id' => $booking->id,
            'booking_number' => $booking->booking_number,
            'message' => 'تم اعتماد الطلب بنجاح.',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function rejectRequestAction(array $payload, ?int $actorId): array
    {
        $request = AppointmentRequest::query()->whereKey((int) ($payload['request_id'] ?? 0))->firstOrFail();
        $request = $this->appointmentRequestService->rejectRequest($request, $actorId, (string) ($payload['reason'] ?? ''));

        return [
            'request_id' => $request->id,
            'status' => $request->status,
            'message' => 'تم رفض الطلب.',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function createBookingAction(Workspace $workspace, array $payload, ?int $actorId): array
    {
        $booking = $this->appointmentService->createBooking($workspace, [
            ...$payload,
            'source_channel' => $payload['source_channel'] ?? 'ai_chat',
        ], $actorId);

        return [
            'booking_id' => $booking->id,
            'booking_number' => $booking->booking_number,
            'appointment_status' => $booking->appointment_status,
            'payment_status' => $booking->payment_status,
            'message' => 'تم إنشاء الحجز عبر AI وفق القواعد.',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function getCustomerAction(array $payload): array
    {
        $customer = null;
        if (! empty($payload['customer_id'])) {
            $customer = Customer::query()->whereKey((int) $payload['customer_id'])->first();
        } elseif (! empty($payload['phone'])) {
            $customer = Customer::query()->where('phone', trim((string) $payload['phone']))->first();
        } elseif (! empty($payload['email'])) {
            $email = mb_strtolower(trim((string) $payload['email']));
            $customer = Customer::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        }

        return [
            'customer' => $customer ? [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'notes' => $customer->notes,
            ] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function createCustomerAction(array $payload): array
    {
        $phone = trim((string) ($payload['phone'] ?? ''));
        if ($phone === '') {
            throw new RuntimeException('رقم الهاتف مطلوب لإنشاء العميل.');
        }

        $existing = Customer::query()->where('phone', $phone)->first();
        if ($existing) {
            return [
                'customer_id' => $existing->id,
                'created' => false,
                'message' => 'العميل موجود مسبقًا.',
            ];
        }

        $customer = Customer::query()->create([
            'name' => trim((string) ($payload['name'] ?? 'عميل جديد')),
            'phone' => $phone,
            'email' => trim((string) ($payload['email'] ?? '')) ?: null,
            'notes' => trim((string) ($payload['notes'] ?? '')) ?: null,
        ]);

        return [
            'customer_id' => $customer->id,
            'created' => true,
            'message' => 'تم إنشاء عميل جديد بنجاح.',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function updateCustomerAction(array $payload): array
    {
        $customer = Customer::query()->whereKey((int) ($payload['customer_id'] ?? 0))->firstOrFail();
        $customer->update([
            'name' => trim((string) ($payload['name'] ?? $customer->name)),
            'phone' => trim((string) ($payload['phone'] ?? $customer->phone)),
            'email' => trim((string) ($payload['email'] ?? $customer->email)) ?: null,
            'notes' => trim((string) ($payload['notes'] ?? $customer->notes)) ?: null,
        ]);

        return [
            'customer_id' => $customer->id,
            'updated' => true,
            'message' => 'تم تحديث بيانات العميل.',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function getAppointmentAction(array $payload): array
    {
        $query = AppointmentBooking::query()->with(['service', 'staff', 'invoice', 'order']);
        if (! empty($payload['booking_id'])) {
            $query->whereKey((int) $payload['booking_id']);
        } elseif (! empty($payload['booking_number'])) {
            $query->where('booking_number', trim((string) $payload['booking_number']));
        } else {
            throw new RuntimeException('booking_id أو booking_number مطلوب.');
        }

        $booking = $query->firstOrFail();

        return [
            'booking' => [
                'id' => $booking->id,
                'booking_number' => $booking->booking_number,
                'customer_name' => $booking->customer_name,
                'appointment_status' => $booking->appointment_status,
                'payment_status' => $booking->payment_status,
                'starts_at' => $booking->starts_at?->toDateTimeString(),
                'ends_at' => $booking->ends_at?->toDateTimeString(),
                'payment_link' => $booking->payment_link,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function requestRescheduleAction(
        Workspace $workspace,
        array $payload,
        ?int $actorId,
        string $automationMode,
        ?Conversation $conversation
    ): array {
        $booking = AppointmentBooking::query()->whereKey((int) ($payload['booking_id'] ?? 0))->firstOrFail();
        $request = $this->appointmentRequestService->createRequest($workspace, [
            'request_type' => 'reschedule',
            'target_booking_id' => $booking->id,
            'customer_id' => $booking->customer_id,
            'customer_name' => $booking->customer_name,
            'customer_phone' => $booking->customer_phone,
            'customer_email' => $booking->customer_email,
            'requested_service_id' => $booking->service_id,
            'requested_staff_id' => $booking->staff_id,
            'requested_date' => $payload['requested_date'] ?? null,
            'requested_time' => $payload['requested_time'] ?? null,
            'requested_time_end' => $payload['requested_time_end'] ?? null,
            'source' => $payload['source'] ?? 'ai_chat',
            'conversation_id' => $payload['conversation_id'] ?? $conversation?->id,
            'automation_mode' => $automationMode,
            'notes' => $payload['notes'] ?? 'طلب إعادة جدولة',
            'ai_generated' => true,
        ], $actorId, true);

        return [
            'request_id' => $request->id,
            'status' => $request->status,
            'message' => 'تم إنشاء طلب إعادة الجدولة.',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function requestCancellationAction(
        Workspace $workspace,
        array $payload,
        ?int $actorId,
        string $automationMode,
        ?Conversation $conversation
    ): array {
        $booking = AppointmentBooking::query()->whereKey((int) ($payload['booking_id'] ?? 0))->firstOrFail();
        $request = $this->appointmentRequestService->createRequest($workspace, [
            'request_type' => 'cancellation',
            'target_booking_id' => $booking->id,
            'customer_id' => $booking->customer_id,
            'customer_name' => $booking->customer_name,
            'customer_phone' => $booking->customer_phone,
            'customer_email' => $booking->customer_email,
            'requested_service_id' => $booking->service_id,
            'requested_staff_id' => $booking->staff_id,
            'source' => $payload['source'] ?? 'ai_chat',
            'conversation_id' => $payload['conversation_id'] ?? $conversation?->id,
            'automation_mode' => $automationMode,
            'notes' => $payload['notes'] ?? 'طلب إلغاء الموعد',
            'ai_generated' => true,
        ], $actorId, true);

        return [
            'request_id' => $request->id,
            'status' => $request->status,
            'message' => 'تم إنشاء طلب الإلغاء بنجاح.',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function sendPaymentLinkAction(array $payload, ?int $actorId): array
    {
        $booking = AppointmentBooking::query()->whereKey((int) ($payload['booking_id'] ?? 0))->firstOrFail();
        $booking = $this->appointmentBillingService->createInvoiceAndPaymentLink($booking, $actorId);

        return [
            'booking_id' => $booking->id,
            'payment_status' => $booking->payment_status,
            'payment_link' => $booking->payment_link,
            'message' => 'تم تجهيز رابط الدفع للعميل.',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function sendConfirmationAction(array $payload, string $automationMode): array
    {
        if ($automationMode !== 'AUTO') {
            throw new RuntimeException('تأكيد الموعد من AI غير مسموح إلا في وضع AUTO.');
        }

        $booking = AppointmentBooking::query()->whereKey((int) ($payload['booking_id'] ?? 0))->firstOrFail();
        $this->appointmentService->updateBookingStatus($booking, [
            'appointment_status' => 'confirmed',
        ]);

        return [
            'booking_id' => $booking->id,
            'appointment_status' => 'confirmed',
            'message' => 'تم تأكيد الموعد حسب إعدادات AUTO.',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function sendReminderAction(array $payload): array
    {
        $booking = AppointmentBooking::query()->whereKey((int) ($payload['booking_id'] ?? 0))->firstOrFail();
        $minutesBefore = max(5, (int) ($payload['minutes_before'] ?? 120));
        $channel = (string) ($payload['channel'] ?? 'in_app');
        $sendAt = $booking->starts_at?->copy()->subMinutes($minutesBefore) ?? now()->addMinutes(1);

        $existing = AppointmentReminder::query()
            ->where('workspace_id', $booking->workspace_id)
            ->where('booking_id', $booking->id)
            ->where('channel', $channel)
            ->where('send_at', $sendAt)
            ->first();
        if ($existing) {
            return [
                'reminder_id' => $existing->id,
                'status' => $existing->status,
                'send_at' => $existing->send_at?->toDateTimeString(),
                'message' => 'التذكير موجود مسبقًا.',
            ];
        }

        $reminder = AppointmentReminder::query()->create([
            'workspace_id' => $booking->workspace_id,
            'booking_id' => $booking->id,
            'channel' => $channel,
            'status' => 'queued',
            'send_at' => $sendAt,
            'metadata' => [
                'requested_by' => 'ai_action',
                'custom_message' => $payload['message'] ?? null,
            ],
        ]);

        return [
            'reminder_id' => $reminder->id,
            'status' => $reminder->status,
            'send_at' => $reminder->send_at?->toDateTimeString(),
            'message' => 'تمت جدولة التذكير بنجاح.',
        ];
    }

    private function assertAutomationModeForAction(string $mode, string $action): void
    {
        $restrictedInManual = [
            'approve_request',
            'reject_request',
            'select_slot',
            'create_booking',
            'send_confirmation',
        ];

        $restrictedInApproval = [
            'approve_request',
            'select_slot',
            'create_booking',
            'send_confirmation',
        ];

        if ($mode === 'MANUAL' && in_array($action, $restrictedInManual, true)) {
            throw new RuntimeException('هذا الإجراء غير مسموح للـAI في وضع MANUAL.');
        }

        if ($mode === 'APPROVAL' && in_array($action, $restrictedInApproval, true)) {
            throw new RuntimeException('هذا الإجراء غير مسموح للـAI في وضع APPROVAL بدون موافقة بشرية.');
        }
    }
}
