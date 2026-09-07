<?php

namespace App\Http\Controllers\Workspace\Appointments;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Workspace\Concerns\InteractsWithWorkspace;
use App\Models\Appointment\AppointmentStaff;
use App\Models\User;
use Illuminate\Http\Request;

abstract class AppointmentsBaseController extends Controller
{
    use InteractsWithWorkspace;

    protected function authorizeAppointments(Request $request, string $permission = 'appointments.view'): void
    {
        /** @var User|null $user */
        $user = $request->user();
        abort_unless($user, 403);

        if ($user->can($permission)) {
            return;
        }

        $workspace = $this->currentWorkspace();
        $isElevatedMember = $workspace->users()
            ->where('users.id', $user->id)
            ->wherePivot('status', 'active')
            ->wherePivotIn('membership_role', ['owner', 'admin', 'manager'])
            ->exists();

        abort_unless($isElevatedMember, 403, 'You are not allowed to access appointments module.');
    }

    protected function activeMembershipRole(Request $request): ?string
    {
        /** @var User|null $user */
        $user = $request->user();
        if (! $user) {
            return null;
        }

        $workspace = $this->currentWorkspace();
        $membership = $workspace->users()
            ->where('users.id', $user->id)
            ->wherePivot('status', 'active')
            ->first();

        return $membership?->pivot?->membership_role;
    }

    protected function isStaffScoped(Request $request): bool
    {
        /** @var User|null $user */
        $user = $request->user();
        if (! $user) {
            return false;
        }

        if ($user->can('appointments.manage') || $user->can('workspace.manage')) {
            return false;
        }

        return $this->activeMembershipRole($request) === 'staff_doctor';
    }

    protected function currentStaffId(Request $request): ?int
    {
        /** @var User|null $user */
        $user = $request->user();
        if (! $user) {
            return null;
        }

        $staff = AppointmentStaff::query()
            ->where('user_id', $user->id)
            ->first();

        return $staff?->id;
    }
}
