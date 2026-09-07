<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'workspace_id',
    'dining_table_id',
    'table_session_id',
    'closed_by_user_id',
    'invoice_number',
    'status',
    'currency',
    'subtotal',
    'discount_amount',
    'total_amount',
    'closed_at',
    'metadata',
])]
class PosCashierInvoice extends WorkspaceScopedModel
{
    /** @use HasFactory<\Database\Factories\PosCashierInvoiceFactory> */
    use BelongsToWorkspace, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'closed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'dining_table_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(TableSession::class, 'table_session_id');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PosCashierInvoiceItem::class, 'pos_cashier_invoice_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'pos_cashier_invoice_id');
    }
}
