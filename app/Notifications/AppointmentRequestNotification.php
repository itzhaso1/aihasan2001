<?php

namespace App\Notifications;

use App\Contracts\Email\CentralEmailNotification;
use App\Models\Appointment\AppointmentRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class AppointmentRequestNotification extends Notification implements ShouldQueue, CentralEmailNotification
{
    use Queueable;

    public function __construct(
        public readonly AppointmentRequest $appointmentRequest,
        public readonly ?string $title = null,
        public readonly ?string $message = null,
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
            'subject' => $this->title ?: 'تحديث طلب موعد',
            'workspace_id' => $this->appointmentRequest->workspace_id,
            'data' => [
                'headline' => $this->title ?: 'تحديث طلب موعد',
                'intro' => $this->message ?: 'تم إنشاء/تحديث طلب موعد للعميل: '.$this->appointmentRequest->customer_name,
                'lines' => [
                    'القناة: '.$this->appointmentRequest->source,
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
            'type' => $this->title ? 'appointment_request_update' : 'appointment_request_created',
            'title' => $this->title ?: 'تحديث طلب موعد',
            'message' => $this->message ?: 'تم تحديث حالة طلب الموعد',
            'request_id' => $this->appointmentRequest->id,
            'workspace_id' => $this->appointmentRequest->workspace_id,
            'customer_name' => $this->appointmentRequest->customer_name,
            'source' => $this->appointmentRequest->source,
            'status' => $this->appointmentRequest->status,
        ];
    }
}
