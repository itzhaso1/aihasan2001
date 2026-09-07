<?php

namespace App\Http\Controllers\Workspace\Appointments;

use App\Models\Appointment\AppointmentHoliday;
use App\Models\Appointment\AppointmentStaff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HolidayController extends AppointmentsBaseController
{
    public function index(Request $request): View
    {
        $this->authorizeAppointments($request, 'appointments.settings.manage');

        $holidays = AppointmentHoliday::query()
            ->with(['staff:id,name'])
            ->orderByDesc('holiday_date')
            ->orderByDesc('id')
            ->paginate(25);

        $staff = AppointmentStaff::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('workspace.appointments.holidays.index', [
            'holidays' => $holidays,
            'staff' => $staff,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAppointments($request, 'appointments.settings.manage');

        $validated = $request->validate([
            'holiday_date' => ['required', 'date'],
            'staff_id' => ['nullable', 'integer', 'exists:appointment_staff,id'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'is_recurring' => ['nullable', 'boolean'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        if (! empty($validated['staff_id'])) {
            $staffBelongs = AppointmentStaff::query()
                ->whereKey((int) $validated['staff_id'])
                ->exists();
            abort_unless($staffBelongs, 422);
        }

        AppointmentHoliday::query()->create([
            'holiday_date' => $validated['holiday_date'],
            'staff_id' => $validated['staff_id'] ?? null,
            'start_time' => $validated['start_time'] ?? null,
            'end_time' => $validated['end_time'] ?? null,
            'is_recurring' => (bool) ($validated['is_recurring'] ?? false),
            'reason' => $validated['reason'] ?? null,
        ]);

        return redirect()
            ->route('workspace.appointments.holidays.index')
            ->with('success', 'تم إضافة الإجازة بنجاح.');
    }

    public function destroy(Request $request, AppointmentHoliday $holiday): RedirectResponse
    {
        $this->authorizeAppointments($request, 'appointments.settings.manage');

        $holiday->delete();

        return redirect()
            ->route('workspace.appointments.holidays.index')
            ->with('success', 'تم حذف الإجازة.');
    }
}
