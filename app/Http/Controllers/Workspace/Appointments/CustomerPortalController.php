<?php

namespace App\Http\Controllers\Workspace\Appointments;

use App\Http\Controllers\Controller;
use App\Models\Appointment\AppointmentBooking;
use App\Services\Appointments\AppointmentRequestService;
use App\Services\Appointments\AppointmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Illuminate\View\View;

class CustomerPortalController extends Controller
{
    public function __construct(
        private readonly AppointmentService $appointmentService,
        private readonly AppointmentRequestService $appointmentRequestService,
    ) {}

    public function show(string $token): View
    {
        $booking = AppointmentBooking::withoutGlobalScopes()
            ->with(['service', 'staff', 'invoice.payments', 'request', 'workspace'])
            ->where('public_token', $token)
            ->firstOrFail();

        $timezone = $this->appointmentService->workspaceTimezone((int) $booking->workspace_id);
        $workspaceSettings = is_array($booking->workspace?->settings) ? $booking->workspace->settings : [];
        $contactPhone = $booking->staff?->phone ?: ($workspaceSettings['phone'] ?? null);
        $contactWhatsapp = $workspaceSettings['whatsapp'] ?? $contactPhone;

        return view('workspace.appointments.portal', [
            'booking' => $booking,
            'timezone' => $timezone,
            'contactPhone' => $contactPhone,
            'contactWhatsapp' => $contactWhatsapp,
        ]);
    }

    public function confirmAttendance(Request $request, string $token): RedirectResponse
    {
        $booking = AppointmentBooking::withoutGlobalScopes()
            ->where('public_token', $token)
            ->firstOrFail();

        if (in_array($booking->appointment_status, ['cancelled', 'completed', 'no_show'], true)) {
            return back()->with('error', 'لا يمكن تأكيد حضور موعد مغلق.');
        }

        $this->appointmentService->updateBookingStatus($booking, [
            'appointment_status' => 'confirmed',
        ]);

        return back()->with('success', 'تم تأكيد حضورك بنجاح.');
    }

    public function requestReschedule(Request $request, string $token): RedirectResponse
    {
        $booking = AppointmentBooking::withoutGlobalScopes()
            ->where('public_token', $token)
            ->firstOrFail();

        $validated = $request->validate([
            'requested_date' => ['required', 'date'],
            'requested_time' => ['nullable', 'date_format:H:i'],
            'requested_time_end' => ['nullable', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->appointmentRequestService->createRequest($booking->workspace, [
                'request_type' => 'reschedule',
                'target_booking_id' => $booking->id,
                'customer_id' => $booking->customer_id,
                'customer_name' => $booking->customer_name,
                'customer_phone' => $booking->customer_phone,
                'customer_email' => $booking->customer_email,
                'requested_service_id' => $booking->service_id,
                'requested_staff_id' => $booking->staff_id,
                'requested_date' => $validated['requested_date'],
                'requested_time' => $validated['requested_time'] ?? null,
                'requested_time_end' => $validated['requested_time_end'] ?? null,
                'source' => 'website',
                'notes' => $validated['notes'] ?? 'طلب إعادة جدولة من رابط العميل',
            ], null);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'تم إرسال طلب إعادة الجدولة لفريق العمل.');
    }

    public function requestCancellation(Request $request, string $token): RedirectResponse
    {
        $booking = AppointmentBooking::withoutGlobalScopes()
            ->where('public_token', $token)
            ->firstOrFail();

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->appointmentRequestService->createRequest($booking->workspace, [
                'request_type' => 'cancellation',
                'target_booking_id' => $booking->id,
                'customer_id' => $booking->customer_id,
                'customer_name' => $booking->customer_name,
                'customer_phone' => $booking->customer_phone,
                'customer_email' => $booking->customer_email,
                'requested_service_id' => $booking->service_id,
                'requested_staff_id' => $booking->staff_id,
                'source' => 'website',
                'notes' => $validated['notes'] ?? 'طلب إلغاء من رابط العميل',
            ], null);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'تم إرسال طلب الإلغاء لفريق العمل.');
    }
}
