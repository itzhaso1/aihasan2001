<?php

namespace App\Models\Appointment;

use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\User;
use App\Models\WorkspaceScopedModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'workspace_id',
    'request_type',
    'target_booking_id',
    'customer_id',
    'conversation_id',
    'requested_service_id',
    'requested_staff_id',
    'customer_name',
    'customer_phone',
    'customer_email',
    'customer_age',
    'requested_date',
    'requested_time',
    'requested_time_end',
    'status',
    'appointment_status',
    'payment_status',
    'source',
    'automation_mode',
    'notes',
    'ai_generated',
    'ai_payload',
    'last_customer_response_at',
    'expires_at',
    'approved_by',
    'approved_at',
    'rejected_by',
    'rejected_at',
    'cancelled_at',
])]
class AppointmentRequest extends WorkspaceScopedModel
{
    use BelongsToWorkspace, SoftDeletes;

    protected function casts(): array
    {
        return [
            'customer_age' => 'integer',
            'requested_date' => 'date',
            'ai_generated' => 'boolean',
            'ai_payload' => 'array',
            'last_customer_response_at' => 'datetime',
            'expires_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function targetBooking(): BelongsTo
    {
        return $this->belongsTo(AppointmentBooking::class, 'target_booking_id');
    }

    public function booking(): HasOne
    {
        return $this->hasOne(AppointmentBooking::class, 'request_id')->latestOfMany();
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(AppointmentBooking::class, 'request_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(AppointmentService::class, 'requested_service_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(AppointmentStaff::class, 'requested_staff_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function slots(): HasMany
    {
        return $this->hasMany(AppointmentRequestSlot::class, 'request_id');
    }
}
