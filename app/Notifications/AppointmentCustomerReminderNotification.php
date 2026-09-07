<?php

namespace App\Notifications;

use App\Contracts\Email\CentralEmailNotification;
use App\Models\Appointment\AppointmentBooking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class AppointmentCustomerReminderNotification extends Notification implements ShouldQueue, CentralEmailNotification
{
    use Queueable;

    public function __construct(public readonly AppointmentBooking $booking) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['central_mail'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toCentralEmail(object $notifiable): array
    {
        return [
            'template' => 'general_notification',
            'subject' => 'تذكير بموعدك القادم',
            'workspace_id' => $this->booking->workspace_id,
            'data' => [
                'headline' => 'تذكير بموعدك القادم',
                'intro' => 'هذا تذكير بموعدك القادم معنا.',
                'lines' => [
                    'رقم الموعد: '.$this->booking->booking_number,
                    'الخدمة: '.($this->booking->service?->name ?? '—'),
                    'الوقت: '.$this->booking->starts_at?->toDateTimeString(),
                    'يمكنك استخدام رابط العميل لتأكيد الحضور أو طلب التعديل عند الحاجة.',
                ],
            ],
        ];
    }
}
