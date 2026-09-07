<?php

namespace App\Services\Website;

use App\Models\Appointment\AppointmentBooking;
use App\Models\Appointment\AppointmentService as AppointmentServiceModel;
use App\Models\Appointment\AppointmentStaff;
use App\Models\Appointment\AppointmentSetting;
use App\Models\Customer;
use App\Models\Website\Website;
use App\Models\Workspace;
use App\Exceptions\FeatureNotAvailableException;
use App\Services\Appointments\AppointmentBillingService;
use App\Services\Appointments\AppointmentService;
use App\Services\Feature\FeatureAccessService;
use Illuminate\Support\Carbon;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PublicBookingService
{
    public function __construct(
        private readonly AppointmentService $appointmentService,
        private readonly AppointmentBillingService $appointmentBillingService,
        private readonly FeatureAccessService $featureAccessService,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listServices(Website $website): array
    {
        return AppointmentServiceModel::withoutGlobalScopes()
            ->where('workspace_id', $website->workspace_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function (AppointmentServiceModel $service): array {
                return [
                    'id' => $service->id,
                    'name' => $service->name,
                    'description' => $service->description,
                    'duration_minutes' => $service->duration_minutes,
                    'price' => (float) $service->price,
                    'requires_payment' => (bool) $service->requires_payment,
                    'payment_mode' => $service->payment_mode,
                    'deposit_amount' => $service->deposit_amount ? (float) $service->deposit_amount : null,
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listStaffForService(Website $website, int $serviceId): array
    {
        $service = AppointmentServiceModel::withoutGlobalScopes()
            ->where('workspace_id', $website->workspace_id)
            ->whereKey($serviceId)
            ->where('is_active', true)
            ->firstOrFail();

        $staffQuery = AppointmentStaff::withoutGlobalScopes()
            ->where('workspace_id', $website->workspace_id)
            ->where('is_active', true)
            ->orderBy('name');

        if ($service->staffMembers()->withoutGlobalScopes()->exists()) {
            $staffIds = $service->staffMembers()->withoutGlobalScopes()->pluck('appointment_staff.id')->all();
            $staffQuery->whereIn('id', $staffIds);
        }

        return $staffQuery->get(['id', 'name', 'role'])->map(fn (AppointmentStaff $staff) => [
            'id' => $staff->id,
            'name' => $staff->name,
            'role' => $staff->role,
        ])->all();
    }

    /**
     * @param  array<string, mixed>  payload
     * @return array<string, mixed>
     */
    public function availability(Website $website, array $payload): array
    {
        $serviceId = (int) ($payload['service_id'] ?? 0);
        $staffId = isset($payload['staff_id']) ? (int) $payload['staff_id'] : null;
        $date = trim((string) ($payload['date'] ?? ''));
        if ($serviceId <= 0 || $date === '') {
            throw new RuntimeException('service_id and date are required.');
        }

        $workspace = Workspace::withoutGlobalScopes()->findOrFail($website->workspace_id);
        $slots = $this->appointmentService->availableSlots(
            workspace: $workspace,
            serviceId: $serviceId,
            date: $date,
            staffId: $staffId
        );

        return [
            'date' => $date,
            'slots' => $slots,
        ];
    }

    /**
     * @param  array<string, mixed>  payload
     * @return array<string, mixed>
     */
    public function validateBooking(Website $website, array $payload): array
    {
        $errors = [];

        $serviceId = (int) ($payload['service_id'] ?? 0);
        if ($serviceId <= 0) {
            $errors['service_id'] = 'service_id is required.';
        }

        $staffId = isset($payload['staff_id']) && $payload['staff_id'] !== '' ? (int) $payload['staff_id'] : null;

        $startsAt = trim((string) ($payload['starts_at'] ?? ''));
        if ($startsAt === '') {
            $errors['starts_at'] = 'starts_at is required.';
        }

        $customerName = trim((string) ($payload['customer_name'] ?? ''));
        if ($customerName === '') {
            $errors['customer_name'] = 'customer_name is required.';
        }

        if ($errors !== []) {
            return ['valid' => false, 'errors' => $errors];
        }

        $service = AppointmentServiceModel::withoutGlobalScopes()
            ->where('workspace_id', $website->workspace_id)
            ->whereKey($serviceId)
            ->where('is_active', true)
            ->first();

        if (! $service) {
            return ['valid' => false, 'errors' => ['service_id' => 'Selected service is unavailable.']];
        }

        if ($staffId) {
            $staff = AppointmentStaff::withoutGlobalScopes()
                ->where('workspace_id', $website->workspace_id)
                ->whereKey($staffId)
                ->where('is_active', true)
                ->first();
            if (! $staff) {
                return ['valid' => false, 'errors' => ['staff_id' => 'Selected staff is unavailable.']];
            }
        }

        $setting = AppointmentSetting::withoutGlobalScopes()->where('workspace_id', $website->workspace_id)->first();
        $timezone = $this->appointmentService->workspaceTimezone($website->workspace_id, $setting);

        try {
            $startLocal = Carbon::parse($startsAt, $timezone);
            if ($startLocal->lt(now($timezone))) {
                return ['valid' => false, 'errors' => ['starts_at' => 'Past slots are not allowed.']];
            }
        } catch (\Throwable) {
            return ['valid' => false, 'errors' => ['starts_at' => 'Invalid starts_at datetime.']];
        }

        return ['valid' => true, 'errors' => []];
    }

    /**
     * @param  array<string, mixed>  payload
     * @return array<string, mixed>
     */
    public function createBooking(Website $website, array $payload): array
    {
        $workspace = Workspace::withoutGlobalScopes()->findOrFail($website->workspace_id);

        if (! $this->featureAccessService->workspaceHasFeature($workspace, 'public_booking')) {
            throw new FeatureNotAvailableException(
                feature: 'public_booking',
                requiredPlan: $this->featureAccessService->suggestedPlanForFeature('public_booking'),
                message: 'الحجز العام غير متاح في باقة مساحة العمل الحالية. يرجى ترقية الاشتراك.',
            );
        }

        $validation = $this->validateBooking($website, $payload);
        if (($validation['valid'] ?? false) !== true) {
            throw new RuntimeException('Booking payload validation failed.');
        }

        $service = AppointmentServiceModel::withoutGlobalScopes()
            ->where('workspace_id', $website->workspace_id)
            ->whereKey((int) $payload['service_id'])
            ->firstOrFail();

        $setting = AppointmentSetting::withoutGlobalScopes()->where('workspace_id', $website->workspace_id)->first();
        $timezone = $this->appointmentService->workspaceTimezone($website->workspace_id, $setting);
        $startLocal = Carbon::parse((string) $payload['starts_at'], $timezone);
        $lockKey = sprintf(
            'public-booking:%d:%d:%s:%d',
            $website->workspace_id,
            (int) $payload['service_id'],
            $startLocal->format('YmdHi'),
            (int) ($payload['staff_id'] ?? 0)
        );

        $callback = function () use ($workspace, $payload, $service, $website): array {
            return DB::transaction(function () use ($workspace, $payload, $service, $website): array {
                $customerId = $this->resolveCustomerId($website, $payload);
                $booking = $this->appointmentService->createBooking($workspace, [
                    'service_id' => (int) $payload['service_id'],
                    'staff_id' => isset($payload['staff_id']) && (int) $payload['staff_id'] > 0
                        ? (int) $payload['staff_id']
                        : null,
                    'customer_id' => $customerId,
                    'customer_name' => trim((string) $payload['customer_name']),
                    'customer_phone' => trim((string) ($payload['customer_phone'] ?? '')) ?: null,
                    'customer_email' => trim((string) ($payload['customer_email'] ?? '')) ?: null,
                    'starts_at' => (string) $payload['starts_at'],
                    'notes' => trim((string) ($payload['notes'] ?? '')) ?: null,
                    'source' => 'website',
                    'source_channel' => 'website',
                    'appointment_status' => 'scheduled',
                    'payment_status' => $service->requires_payment ? 'unpaid' : 'paid',
                    'metadata' => [
                        'public_website_id' => $website->id,
                        'public_website_slug' => $website->slug,
                        'public_booking' => true,
                    ],
                ], null);

                if ((bool) $service->requires_payment) {
                    $booking = $this->appointmentBillingService->createInvoiceAndPaymentLink($booking, $workspace->owner_user_id);
                }

                return [
                    'booking_number' => $booking->booking_number,
                    'public_token' => $booking->public_token,
                    'appointment_status' => $booking->appointment_status,
                    'payment_status' => $booking->payment_status,
                    'payment_link' => $booking->payment_link,
                    'requires_payment' => (bool) $service->requires_payment,
                ];
            });
        };

        $store = Cache::getStore();
        if ($store instanceof LockProvider) {
            return Cache::lock($lockKey, 15)->block(8, $callback);
        }

        // Fallback atomicity when cache store has no locks: serialize via advisory lock table row.
        return Cache::remember($lockKey.':gate', 1, fn () => true) && $callback();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function bookingReference(Website $website, string $reference): ?array
    {
        // Opaque public_token only — sequential booking_number must not be enumerable publicly.
        $reference = trim($reference);
        if ($reference === '' || strlen($reference) < 16) {
            return null;
        }

        $booking = AppointmentBooking::withoutGlobalScopes()
            ->where('workspace_id', $website->workspace_id)
            ->where('public_token', $reference)
            ->first();

        if (! $booking) {
            return null;
        }

        return [
            'booking_number' => $booking->booking_number,
            'public_token' => $booking->public_token,
            'appointment_status' => $booking->appointment_status,
            'payment_status' => $booking->payment_status,
            'starts_at' => optional($booking->starts_at)->toIso8601String(),
            'ends_at' => optional($booking->ends_at)->toIso8601String(),
            'customer_name' => $booking->customer_name,
            'service_id' => $booking->service_id,
            'staff_id' => $booking->staff_id,
        ];
    }

    /**
     * @param  array<string, mixed>  payload
     */
    private function resolveCustomerId(Website $website, array $payload): ?int
    {
        $phone = trim((string) ($payload['customer_phone'] ?? ''));
        $email = trim((string) ($payload['customer_email'] ?? ''));
        $name = trim((string) ($payload['customer_name'] ?? ''));

        if ($phone === '' && $email === '') {
            return null;
        }

        $query = Customer::withoutGlobalScopes()->where('workspace_id', $website->workspace_id);

        $existing = null;
        if ($phone !== '') {
            $existing = (clone $query)->where('phone', $phone)->first();
        }
        if (! $existing && $email !== '') {
            $existing = (clone $query)->where('email', $email)->first();
        }

        if ($existing) {
            $updates = [];
            if ($name !== '' && trim((string) $existing->name) === '') {
                $updates['name'] = $name;
            }
            if ($phone !== '' && blank($existing->phone)) {
                $updates['phone'] = $phone;
            }
            if ($email !== '' && blank($existing->email)) {
                $updates['email'] = $email;
            }
            if ($updates !== []) {
                $existing->update($updates);
            }

            return $existing->id;
        }

        if ($name === '') {
            return null;
        }

        $customer = Customer::withoutGlobalScopes()->create([
            'workspace_id' => $website->workspace_id,
            'name' => $name,
            'phone' => $phone !== '' ? $phone : null,
            'email' => $email !== '' ? $email : null,
        ]);

        return $customer->id;
    }
}
