<?php

namespace App\Notifications;

use App\Contracts\Email\CentralEmailNotification;
use App\Models\Appointment\AppointmentBooking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class AppointmentLifecycleNotification extends Notification implements ShouldQueue, CentralEmailNotification
{
    use Queueable;

    public function __construct(
        public readonly AppointmentBooking $booking,
        public readonly string $title,
        public readonly string $message,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'central_mail'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toCentralEmail(object $notifiable): array
    {
        return [
            'template' => 'general_notification',
            'subject' => $this->title,
            'workspace_id' => $this->booking->workspace_id,
            'data' => [
                'headline' => $this->title,
                'intro' => $this->message,
                'lines' => [
                    'رقم الموعد: '.$this->booking->booking_number,
                    'حالة الموعد: '.$this->booking->appointment_status,
                    'حالة الدفع: '.$this->booking->payment_status,
                ],
                'action_text' => 'فتح لوحة المواعيد',
                'action_url' => url('/workspace/appointments'),
                'footer' => 'HASEM Appointments',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'appointment_lifecycle',
            'title' => $this->title,
            'message' => $this->message,
            'booking_id' => $this->booking->id,
            'booking_number' => $this->booking->booking_number,
            'workspace_id' => $this->booking->workspace_id,
            'appointment_status' => $this->booking->appointment_status,
            'payment_status' => $this->booking->payment_status,
        ];
    }
}
