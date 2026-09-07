<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'workspace_id',
    'order_id',
    'payment_gateway_id',
    'provider',
    'provider_payment_id',
    'idempotency_key',
    'status',
    'amount',
    'currency',
    'payment_link',
    'provider_payload',
    'payment_context',
    'money_bucket',
    'paid_at',
    'failed_at',
    'failure_reason',
])]
class Payment extends WorkspaceScopedModel
{
    /** @use HasFactory<\Database\Factories\PaymentFactory> */
    use BelongsToWorkspace, HasFactory;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'provider_payload' => 'array',
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function gateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class, 'payment_gateway_id');
    }
}
