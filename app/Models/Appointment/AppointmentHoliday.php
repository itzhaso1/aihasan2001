<?php

namespace App\Models\Appointment;

use App\Models\Concerns\BelongsToWorkspace;
use App\Models\WorkspaceScopedModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'workspace_id',
    'staff_id',
    'holiday_date',
    'start_time',
    'end_time',
    'is_recurring',
    'reason',
    'metadata',
])]
class AppointmentHoliday extends WorkspaceScopedModel
{
    use BelongsToWorkspace, SoftDeletes;

    protected function casts(): array
    {
        return [
            'holiday_date' => 'date',
            'is_recurring' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(AppointmentStaff::class, 'staff_id');
    }
}
