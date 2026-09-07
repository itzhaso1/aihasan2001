<?php

namespace App\Listeners;

use App\Events\PaymentConfirmed;
use App\Services\Appointments\AppointmentBillingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SyncAppointmentPaymentStatus implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        private readonly AppointmentBillingService $appointmentBillingService,
    ) {}

    public function handle(PaymentConfirmed $event): void
    {
        $this->appointmentBillingService->syncAfterPaymentConfirmed($event->payment);
    }
}
