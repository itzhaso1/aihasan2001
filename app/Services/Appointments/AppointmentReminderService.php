<?php

namespace App\Services\Appointments;

use App\Models\Appointment\AppointmentBooking;
use App\Models\Appointment\AppointmentReminder;
use App\Models\Appointment\AppointmentSetting;
use App\Services\Notification\DomainNotificationService;
use Illuminate\Support\Facades\DB;

class AppointmentReminderService
{
    public function __construct(
        private readonly DomainNotificationService $domainNotificationService,
    ) {}

    /**
     * @param array<int, string>|null $channels
     * @param array<int, int>|null $offsets
     */
    public function scheduleForBooking(AppointmentBooking $booking, ?array $channels = null, ?array $offsets = null): int
    {
        if (in_array($booking->appointment_status, ['cancelled', 'completed', 'no_show'], true)) {
            return 0;
        }

        $setting = AppointmentSetting::withoutGlobalScopes()
            ->where('workspace_id', $booking->workspace_id)
            ->first();

        $rawOffsets = $offsets ?? ($setting?->reminder_offsets ?? [1440, 120]);
        if (is_string($rawOffsets)) {
            $decoded = json_decode($rawOffsets, true);
            $rawOffsets = is_array($decoded) ? $decoded : explode(',', $rawOffsets);
        }

        $offsetValues = collect(is_array($rawOffsets) ? $rawOffsets : [1440, 120])
            ->map(fn ($value) => max(1, (int) $value))
            ->filter(fn (int $value): bool => $value > 0)
            ->unique()
            ->values()
            ->all();
        $settingMetadata = is_array($setting?->metadata) ? $setting->metadata : [];
        $configuredChannels = is_array($settingMetadata['reminder_channels'] ?? null)
            ? $settingMetadata['reminder_channels']
            : ['in_app'];

        $channelValues = collect($channels ?? $configuredChannels)
            ->map(fn ($channel) => trim((string) $channel))
            ->filter(fn (string $channel): bool => in_array($channel, ['in_app', 'email', 'whatsapp', 'sms'], true))
            ->unique()
            ->values()
            ->all();

        if ($offsetValues === [] || $channelValues === []) {
            return 0;
        }

        $scheduledCount = 0;
        foreach ($channelValues as $channel) {
            foreach ($offsetValues as $offset) {
                $sendAt = $booking->starts_at?->copy()->subMinutes($offset);
                if (! $sendAt) {
                    continue;
                }

                $exists = AppointmentReminder::withoutGlobalScopes()
                    ->where('workspace_id', $booking->workspace_id)
                    ->where('booking_id', $booking->id)
                    ->where('channel', $channel)
                    ->where('send_at', $sendAt)
                    ->exists();

                if ($exists) {
                    continue;
                }

                AppointmentReminder::withoutGlobalScopes()->create([
                    'workspace_id' => $booking->workspace_id,
                    'booking_id' => $booking->id,
                    'channel' => $channel,
                    'status' => 'queued',
                    'send_at' => $sendAt,
                    'metadata' => [
                        'offset_minutes' => $offset,
                        'scheduled_by' => 'auto',
                    ],
                ]);
                $scheduledCount++;
            }
        }

        return $scheduledCount;
    }

    public function cancelPendingReminders(AppointmentBooking $booking, string $reason = 'booking_updated'): int
    {
        return AppointmentReminder::withoutGlobalScopes()
            ->where('workspace_id', $booking->workspace_id)
            ->where('booking_id', $booking->id)
            ->where('status', 'queued')
            ->update([
                'status' => 'cancelled',
                'error_message' => $reason,
                'updated_at' => now(),
            ]);
    }

    public function scheduleUpcomingReminders(int $windowDays = 14): int
    {
        $from = now('UTC');
        $to = now('UTC')->addDays(max(1, $windowDays));

        $bookings = AppointmentBooking::query()
            ->whereBetween('starts_at', [$from, $to])
            ->whereIn('appointment_status', ['scheduled', 'confirmed', 'checked_in', 'in_progress'])
            ->get(['id', 'workspace_id', 'starts_at', 'appointment_status']);

        $created = 0;
        foreach ($bookings as $booking) {
            $created += $this->scheduleForBooking($booking);
        }

        return $created;
    }

    public function dispatchDueReminders(int $limit = 200): int
    {
        $count = 0;
        $due = AppointmentReminder::withoutGlobalScopes()
            ->with(['booking.workspace', 'booking.service', 'booking.staff'])
            ->where('status', 'queued')
            ->where('send_at', '<=', now())
            ->orderBy('send_at')
            ->limit(max(1, $limit))
            ->get();

        foreach ($due as $reminder) {
            DB::transaction(function () use ($reminder, &$count): void {
                $locked = AppointmentReminder::withoutGlobalScopes()
                    ->whereKey($reminder->id)
                    ->lockForUpdate()
                    ->first();
                if (! $locked || $locked->status !== 'queued') {
                    return;
                }

                $booking = $locked->booking;
                if (! $booking || in_array($booking->appointment_status, ['cancelled', 'completed', 'no_show'], true)) {
                    $locked->update([
                        'status' => 'cancelled',
                        'error_message' => 'booking_closed',
                        'updated_at' => now(),
                    ]);

                    return;
                }

                $result = $this->deliver($locked);
                $locked->update([
                    'status' => $result['status'],
                    'sent_at' => $result['status'] === 'sent' ? now() : null,
                    'error_message' => $result['error'],
                    'metadata' => array_merge(
                        is_array($locked->metadata) ? $locked->metadata : [],
                        ['delivered_at' => now()->toDateTimeString()]
                    ),
                ]);
                $count++;
            });
        }

        return $count;
    }

    /**
     * @return array{status: string, error: string|null}
     */
    private function deliver(AppointmentReminder $reminder): array
    {
        $booking = $reminder->booking;
        if (! $booking) {
            return ['status' => 'failed', 'error' => 'booking_not_found'];
        }

        if ($reminder->channel === 'in_app') {
            $this->domainNotificationService->notifyAppointmentReminderDue($booking, 'تذكير بموعد قريب');

            return ['status' => 'sent', 'error' => null];
        }

        if ($reminder->channel === 'email') {
            if (! filled($booking->customer_email)) {
                return ['status' => 'failed', 'error' => 'customer_email_missing'];
            }

            $this->domainNotificationService->notifyAppointmentReminderByEmail($booking);

            return ['status' => 'sent', 'error' => null];
        }

        // لا نفترض مزود WhatsApp/SMS إذا لم يكن متوفرًا.
        return ['status' => 'failed', 'error' => 'provider_not_configured'];
    }
}
