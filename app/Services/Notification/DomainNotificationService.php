<?php

namespace App\Services\Notification;

use App\Models\Appointment\AppointmentBooking;
use App\Models\Appointment\AppointmentRequest;
use App\Models\Contract\Contract;
use App\Models\Finance\FinanceInvoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\AppointmentCustomerReminderNotification;
use App\Notifications\AppointmentLifecycleNotification;
use App\Notifications\AppointmentRequestNotification;
use App\Notifications\ContractLifecycleNotification;
use App\Notifications\FinanceInvoiceNotification;
use App\Notifications\LowStockNotification;
use App\Notifications\NewOrderNotification;
use App\Notifications\PaymentConfirmedNotification;

class DomainNotificationService
{
    public function notifyOrderCreated(Order $order): void
    {
        $recipients = $order->workspace->users()
            ->wherePivotIn('membership_role', ['owner', 'admin', 'manager'])
            ->get();

        /** @var User $recipient */
        foreach ($recipients as $recipient) {
            $recipient->notify(new NewOrderNotification($order));
        }
    }

    public function notifyPaymentConfirmed(Payment $payment): void
    {
        $recipients = $payment->workspace->users()
            ->wherePivotIn('membership_role', ['owner', 'admin', 'manager'])
            ->get();

        /** @var User $recipient */
        foreach ($recipients as $recipient) {
            $recipient->notify(new PaymentConfirmedNotification($payment));
        }
    }

    public function notifyLowStock(User $recipient, string $productName, int $stock): void
    {
        $recipient->notify(new LowStockNotification($productName, $stock));
    }

    public function notifyAppointmentRequestCreated(AppointmentRequest $request): void
    {
        $recipients = $request->workspace->users()
            ->wherePivotIn('membership_role', ['owner', 'admin', 'manager', 'agent', 'receptionist', 'staff_doctor'])
            ->wherePivot('status', 'active')
            ->get();

        /** @var User $recipient */
        foreach ($recipients as $recipient) {
            $recipient->notify(new AppointmentRequestNotification($request));
        }
    }

    public function notifyAppointmentRequestStatusChanged(AppointmentRequest $request, string $title, string $message): void
    {
        $recipients = $request->workspace->users()
            ->wherePivotIn('membership_role', ['owner', 'admin', 'manager', 'agent', 'receptionist', 'staff_doctor'])
            ->wherePivot('status', 'active')
            ->get();

        /** @var User $recipient */
        foreach ($recipients as $recipient) {
            $recipient->notify(new AppointmentRequestNotification($request, $title, $message));
        }
    }

    public function notifyAppointmentBookingStatusChanged(AppointmentBooking $booking, string $title, string $message): void
    {
        $recipients = $booking->workspace->users()
            ->wherePivotIn('membership_role', ['owner', 'admin', 'manager', 'agent', 'receptionist', 'staff_doctor', 'accountant'])
            ->wherePivot('status', 'active')
            ->get();

        /** @var User $recipient */
        foreach ($recipients as $recipient) {
            $recipient->notify(new AppointmentLifecycleNotification($booking, $title, $message));
        }
    }

    public function notifyAppointmentReminderDue(AppointmentBooking $booking, string $title): void
    {
        $this->notifyAppointmentBookingStatusChanged(
            booking: $booking,
            title: $title,
            message: 'الموعد قريب. الرجاء المتابعة مع العميل وتجهيز الخدمة.'
        );
    }

    public function notifyAppointmentReminderByEmail(AppointmentBooking $booking): void
    {
        $booking->loadMissing('service');
        if (! filled($booking->customer_email)) {
            return;
        }

        \Illuminate\Support\Facades\Notification::route('central_mail', (string) $booking->customer_email)
            ->notify(new AppointmentCustomerReminderNotification($booking));
    }

    public function notifyFinanceInvoiceEvent(FinanceInvoice $invoice, string $title, string $message, string $eventType = 'invoice_event'): void
    {
        $invoice->loadMissing('workspace');
        $workspace = $invoice->workspace;
        if (! $workspace instanceof Workspace) {
            return;
        }

        $recipients = $workspace->users()
            ->wherePivotIn('membership_role', ['owner', 'admin', 'manager', 'accountant'])
            ->wherePivot('status', 'active')
            ->get();

        /** @var User $recipient */
        foreach ($recipients as $recipient) {
            try {
                $recipient->notify(new FinanceInvoiceNotification($invoice, $title, $message, $eventType));
            } catch (\Throwable $exception) {
                \Illuminate\Support\Facades\Log::warning('finance-invoice-notification-failed', [
                    'invoice_id' => $invoice->id,
                    'user_id' => $recipient->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    public function notifyContractEvent(Contract $contract, string $title, string $message): void
    {
        $contract->loadMissing('workspace');
        $workspace = $contract->workspace;
        if (! $workspace instanceof Workspace) {
            return;
        }

        $recipients = $workspace->users()
            ->wherePivotIn('membership_role', ['owner', 'admin', 'manager', 'accountant'])
            ->wherePivot('status', 'active')
            ->get();

        /** @var User $recipient */
        foreach ($recipients as $recipient) {
            try {
                $recipient->notify(new ContractLifecycleNotification($contract, $title, $message));
            } catch (\Throwable $exception) {
                \Illuminate\Support\Facades\Log::warning('contract-notification-failed', [
                    'contract_id' => $contract->id,
                    'user_id' => $recipient->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }
}
