<?php

namespace App\Models\Appointment;

use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Customer;
use App\Models\Finance\FinanceInvoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Models\WorkspaceScopedModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'workspace_id',
    'booking_number',
    'request_id',
    'service_id',
    'staff_id',
    'customer_id',
    'customer_name',
    'customer_phone',
    'customer_email',
    'customer_age',
    'starts_at',
    'ends_at',
    'status',
    'source',
    'source_channel',
    'appointment_status',
    'payment_status',
    'finance_invoice_id',
    'order_id',
    'latest_payment_id',
    'notes',
    'cancel_reason',
    'public_token',
    'payment_link',
    'confirmed_at',
    'checked_in_at',
    'in_progress_at',
    'completed_at',
    'cancelled_at',
    'booked_by',
    'metadata',
])]
class AppointmentBooking extends WorkspaceScopedModel
{
    use BelongsToWorkspace, SoftDeletes;

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'customer_age' => 'integer',
            'confirmed_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'in_progress_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(AppointmentRequest::class, 'request_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(AppointmentService::class, 'service_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(AppointmentStaff::class, 'staff_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(FinanceInvoice::class, 'finance_invoice_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function latestPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'latest_payment_id');
    }

    public function booker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'booked_by');
    }

    public function resources(): BelongsToMany
    {
        return $this->belongsToMany(AppointmentResource::class, 'appointment_booking_resources', 'booking_id', 'resource_id')
            ->withPivot(['workspace_id'])
            ->withTimestamps();
    }
}
