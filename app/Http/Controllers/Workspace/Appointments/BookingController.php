<?php

namespace App\Http\Controllers\Workspace\Appointments;

use App\Models\Appointment\AppointmentBooking;
use App\Services\Appointments\AppointmentBillingService;
use App\Services\Appointments\AppointmentReminderService;
use App\Services\Appointments\AppointmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class BookingController extends AppointmentsBaseController
{
    public function __construct(
        private readonly AppointmentService $appointmentService,
        private readonly AppointmentBillingService $appointmentBillingService,
        private readonly AppointmentReminderService $appointmentReminderService,
    ) {}

    public function createPaymentLink(Request $request, AppointmentBooking $booking): RedirectResponse
    {
        $this->authorizeAppointments($request, 'appointments.billing.manage');
        try {
            $this->appointmentBillingService->createInvoiceAndPaymentLink($booking, (int) $request->user()?->id);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'تم إنشاء رابط الدفع لهذا الموعد.');
    }

    public function reschedule(Request $request, AppointmentBooking $booking): RedirectResponse
    {
        $this->authorizeAppointments($request, 'appointments.manage');
        $validated = $request->validate([
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date'],
            'allow_custom_duration' => ['nullable', 'boolean'],
            'staff_id' => ['nullable', 'integer'],
            'resource_ids' => ['nullable', 'array'],
            'resource_ids.*' => ['integer'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->appointmentService->rescheduleBooking(
                booking: $booking,
                payload: [
                    ...$validated,
                    'allow_custom_duration' => $request->boolean('allow_custom_duration'),
                ],
                actorUserId: (int) $request->user()?->id
            );
        } catch (RuntimeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'تمت إعادة جدولة الموعد بنجاح.');
    }

    public function calendarEvents(Request $request): JsonResponse
    {
        $this->authorizeAppointments($request, 'appointments.calendar.view');
        $validated = $request->validate([
            'view' => ['nullable', 'in:day,week,month'],
            'date' => ['nullable', 'date'],
            'staff_id' => ['nullable', 'integer'],
        ]);

        $workspace = $this->currentWorkspace();
        $filters = $validated + ['timezone' => $this->appointmentService->workspaceTimezone($workspace->id)];
        if ($this->isStaffScoped($request)) {
            $filters['staff_user_id'] = (int) $request->user()?->id;
        }

        $events = $this->appointmentService->calendarEvents($filters);

        return response()->json(['data' => $events]);
    }

    public function sendReminder(Request $request, AppointmentBooking $booking): RedirectResponse
    {
        $this->authorizeAppointments($request, 'appointments.manage');
        $validated = $request->validate([
            'channel' => ['nullable', 'in:in_app,email,whatsapp,sms'],
            'minutes_before' => ['nullable', 'integer', 'min:1', 'max:43200'],
        ]);

        if (in_array($booking->appointment_status, ['cancelled', 'completed', 'no_show'], true)) {
            return back()->with('error', 'لا يمكن إرسال تذكير لموعد مغلق أو ملغي.');
        }

        $channel = (string) ($validated['channel'] ?? 'in_app');
        $minutesBefore = (int) ($validated['minutes_before'] ?? 5);
        $scheduled = $this->appointmentReminderService->scheduleForBooking(
            booking: $booking,
            channels: [$channel],
            offsets: [$minutesBefore]
        );

        // تشغيل التسليم الفوري للتذكيرات التي حان وقتها بالفعل.
        $this->appointmentReminderService->dispatchDueReminders(50);
        if ($scheduled <= 0) {
            return back()->with('success', 'لا توجد تذكيرات جديدة للجدولة (قد تكون موجودة مسبقًا).');
        }

        return back()->with('success', 'تم إرسال التذكير بنجاح.');
    }
}
