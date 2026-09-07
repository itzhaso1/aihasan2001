<?php

namespace App\Models\Appointment;

use App\Models\Concerns\BelongsToWorkspace;
use App\Models\WorkspaceScopedModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'workspace_id',
    'name',
    'resource_type',
    'is_active',
    'metadata',
])]
class AppointmentResource extends WorkspaceScopedModel
{
    use BelongsToWorkspace, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function bookings(): BelongsToMany
    {
        return $this->belongsToMany(AppointmentBooking::class, 'appointment_booking_resources', 'resource_id', 'booking_id')
            ->withPivot(['workspace_id'])
            ->withTimestamps();
    }
}
