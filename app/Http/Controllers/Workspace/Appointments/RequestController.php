<?php

namespace App\Http\Controllers\Workspace\Appointments;

use App\Models\Appointment\AppointmentRequest;
use App\Models\Appointment\AppointmentRequestSlot;
use App\Services\Appointments\AppointmentRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class RequestController extends AppointmentsBaseController
{
    public function __construct(
        private readonly AppointmentRequestService $appointmentRequestService,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAppointments($request, 'appointments.requests.manage');
        $workspace = $this->currentWorkspace();
        $validated = $request->validate([
            'request_type' => ['nullable', Rule::in(['new', 'reschedule', 'cancellation', 'information'])],
            'target_booking_id' => ['nullable', 'integer'],
            'customer_id' => ['nullable', 'integer'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_age' => ['nullable', 'integer', 'min:1', 'max:120'],
            'requested_service_id' => ['nullable', 'integer'],
            'requested_staff_id' => ['nullable', 'integer'],
            'requested_date' => ['nullable', 'date'],
            'requested_time' => ['nullable', 'date_format:H:i'],
            'requested_time_end' => ['nullable', 'date_format:H:i'],
            'source' => ['nullable', Rule::in(['ai_chat', 'whatsapp', 'website', 'phone', 'dashboard', 'walk_in', 'email', 'api'])],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $this->appointmentRequestService->createRequest(
                $workspace,
                $validated,
                (int) $request->user()?->id,
                false
            );
        } catch (RuntimeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'تم تسجيل طلب الموعد بنجاح.');
    }

    public function approve(Request $request, AppointmentRequest $appointmentRequest): RedirectResponse
    {
        $this->authorizeAppointments($request, 'appointments.requests.manage');
        $validated = $request->validate([
            'service_id' => ['nullable', 'integer'],
            'staff_id' => ['nullable', 'integer'],
            'slot_id' => ['nullable', 'integer'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'appointment_status' => ['nullable', Rule::in(['scheduled', 'confirmed'])],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $this->appointmentRequestService->approveRequest(
                $appointmentRequest,
                $validated,
                (int) $request->user()?->id
            );
        } catch (RuntimeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'تم اعتماد الطلب وإنشاء الموعد.');
    }

    public function reject(Request $request, AppointmentRequest $appointmentRequest): RedirectResponse
    {
        $this->authorizeAppointments($request, 'appointments.requests.manage');
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);
        try {
            $this->appointmentRequestService->rejectRequest(
                $appointmentRequest,
                (int) $request->user()?->id,
                $validated['reason'] ?? null
            );
        } catch (RuntimeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'تم رفض طلب الموعد.');
    }

    public function markAwaitingCustomer(Request $request, AppointmentRequest $appointmentRequest): RedirectResponse
    {
        $this->authorizeAppointments($request, 'appointments.requests.manage');
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);
        try {
            $this->appointmentRequestService->markAwaitingCustomer($appointmentRequest, $validated['message']);
        } catch (RuntimeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'تم تحويل الطلب إلى بانتظار رد العميل.');
    }

    public function cancel(Request $request, AppointmentRequest $appointmentRequest): RedirectResponse
    {
        $this->authorizeAppointments($request, 'appointments.requests.manage');
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);
        try {
            $this->appointmentRequestService->cancelRequest($appointmentRequest, $validated['reason'] ?? null);
        } catch (RuntimeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'تم إلغاء طلب الموعد.');
    }

    public function proposeSlots(Request $request, AppointmentRequest $appointmentRequest): RedirectResponse
    {
        $this->authorizeAppointments($request, 'appointments.requests.manage');
        $slots = collect($request->input('slots', []))
            ->filter(fn ($slot) => filled($slot['starts_at'] ?? null) && filled($slot['ends_at'] ?? null))
            ->values()
            ->all();

        $validated = validator(['slots' => $slots], [
            'slots' => ['required', 'array', 'min:1'],
            'slots.*.service_id' => ['nullable', 'integer'],
            'slots.*.staff_id' => ['nullable', 'integer'],
            'slots.*.starts_at' => ['required', 'date'],
            'slots.*.ends_at' => ['required', 'date'],
            'slots.*.expires_at' => ['nullable', 'date'],
        ])->validate();

        try {
            $this->appointmentRequestService->proposeSlots(
                $appointmentRequest,
                $validated['slots'],
                (int) $request->user()?->id
            );
        } catch (RuntimeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'تم اقتراح مواعيد بديلة للعميل.');
    }

    public function selectSlot(Request $request, AppointmentRequest $appointmentRequest, AppointmentRequestSlot $slot): RedirectResponse
    {
        $this->authorizeAppointments($request, 'appointments.requests.manage');
        try {
            $this->appointmentRequestService->selectSlot(
                $appointmentRequest,
                $slot,
                (int) $request->user()?->id
            );
        } catch (RuntimeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'تم اختيار الموعد المقترح واعتماده.');
    }
}
