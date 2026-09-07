<?php

namespace App\Models\Appointment;

use App\Models\Concerns\BelongsToWorkspace;
use App\Models\User;
use App\Models\WorkspaceScopedModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'workspace_id',
    'user_id',
    'name',
    'role',
    'phone',
    'color',
    'is_active',
    'working_days',
    'working_hours',
    'vacation_periods',
    'staff_permissions',
    'metadata',
])]
class AppointmentStaff extends WorkspaceScopedModel
{
    use BelongsToWorkspace, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'working_days' => 'array',
            'working_hours' => 'array',
            'vacation_periods' => 'array',
            'staff_permissions' => 'array',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(AppointmentBooking::class, 'staff_id');
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(AppointmentService::class, 'appointment_service_staff', 'staff_id', 'service_id')
            ->withPivot(['workspace_id', 'is_primary'])
            ->withTimestamps();
    }

    public function holidays(): HasMany
    {
        return $this->hasMany(AppointmentHoliday::class, 'staff_id');
    }
}
