<?php

namespace App\Http\Controllers\Workspace\Appointments;

use App\Models\Appointment\AppointmentRequest;
use App\Models\Customer;
use App\Models\EmailMessage;
use App\Models\Finance\FinanceInvoice;
use App\Models\Finance\FinanceInvoicePayment;
use App\Models\Conversation;
use App\Models\Appointment\AppointmentBooking;
use App\Services\Appointments\AppointmentService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerProfileController extends AppointmentsBaseController
{
    public function __construct(private readonly AppointmentService $appointmentService) {}

    public function show(Request $request, Customer $customer): View
    {
        $this->authorizeAppointments($request, 'appointments.view');
        $timezone = $this->appointmentService->workspaceTimezone($customer->workspace_id);
        $nowUtc = now('UTC');

        $upcomingBookings = AppointmentBooking::query()
            ->where('customer_id', $customer->id)
            ->where('starts_at', '>=', $nowUtc)
            ->orderBy('starts_at')
            ->limit(10)
            ->get();

        $pastBookings = AppointmentBooking::query()
            ->where('customer_id', $customer->id)
            ->where('starts_at', '<', $nowUtc)
            ->orderByDesc('starts_at')
            ->limit(15)
            ->get();

        $appointmentRequests = AppointmentRequest::query()
            ->where('customer_id', $customer->id)
            ->latest('id')
            ->limit(20)
            ->get();

        $invoices = FinanceInvoice::query()
            ->where('customer_id', $customer->id)
            ->latest('id')
            ->limit(20)
            ->get();

        $payments = FinanceInvoicePayment::query()
            ->whereHas('invoice', fn ($query) => $query->where('customer_id', $customer->id))
            ->latest('id')
            ->limit(20)
            ->get();

        $conversations = Conversation::query()
            ->with(['messages' => fn ($query) => $query->latest()->limit(1)])
            ->where('customer_id', $customer->id)
            ->latest('last_message_at')
            ->limit(20)
            ->get();

        $emailQuery = EmailMessage::query()->latest('id')->limit(20);
        if ($customer->email) {
            $email = mb_strtolower($customer->email);
            $emailQuery->where(function ($query) use ($email): void {
                $query->whereRaw('LOWER(sender) LIKE ?', ['%'.$email.'%'])
                    ->orWhereRaw('LOWER(recipient) LIKE ?', ['%'.$email.'%']);
            });
        } else {
            $emailQuery->whereRaw('1 = 0');
        }

        return view('workspace.appointments.customer-profile', [
            'customer' => $customer,
            'timezone' => $timezone,
            'upcomingBookings' => $upcomingBookings,
            'pastBookings' => $pastBookings,
            'appointmentRequests' => $appointmentRequests,
            'invoices' => $invoices,
            'payments' => $payments,
            'conversations' => $conversations,
            'emails' => $emailQuery->get(),
        ]);
    }
}
