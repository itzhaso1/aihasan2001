<?php

namespace App\Models\Appointment;

use App\Models\Concerns\BelongsToWorkspace;
use App\Models\WorkspaceScopedModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'workspace_id',
    'name',
    'description',
    'duration_minutes',
    'price',
    'color',
    'is_active',
    'requires_confirmation',
    'requires_payment',
    'payment_mode',
    'deposit_amount',
    'approval_required',
    'metadata',
])]
class AppointmentService extends WorkspaceScopedModel
{
    use BelongsToWorkspace, SoftDeletes;

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'price' => 'decimal:2',
            'is_active' => 'boolean',
            'requires_confirmation' => 'boolean',
            'requires_payment' => 'boolean',
            'deposit_amount' => 'decimal:2',
            'approval_required' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(AppointmentBooking::class, 'service_id');
    }

    public function staffMembers(): BelongsToMany
    {
        return $this->belongsToMany(AppointmentStaff::class, 'appointment_service_staff', 'service_id', 'staff_id')
            ->withPivot(['workspace_id', 'is_primary'])
            ->withTimestamps();
    }
}
