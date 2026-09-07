<?php

namespace App\Policies;

use App\Models\Appointment\AppointmentBooking;
use App\Models\User;
use App\Policies\Concerns\ChecksWorkspaceMembership;

class AppointmentBookingPolicy
{
    use ChecksWorkspaceMembership;

    public function view(User $user, AppointmentBooking $booking): bool
    {
        return $this->hasMembership($user, $booking->workspace);
    }

    public function update(User $user, AppointmentBooking $booking): bool
    {
        return $this->hasMembership($user, $booking->workspace);
    }
}
