<?php

namespace App\Models\Finance;

use App\Enums\Finance\QuoteStatus;
use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Customer;
use App\Models\User;
use App\Models\WorkspaceScopedModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use RuntimeException;

#[Fillable([
    'workspace_id',
    'customer_id',
    'quote_number',
    'status',
    'issue_date',
    'expiry_date',
    'currency',
    'subtotal',
    'discount',
    'taxable_amount',
    'tax_amount',
    'total',
    'tax_profile_type',
    'tax_rate',
    'tax_price_mode',
    'tax_breakdown',
    'notes',
    'terms',
    'company_snapshot',
    'recipient_snapshot',
    'pdf_snapshot',
    'created_by',
    'issued_by',
    'issued_at',
    'cancelled_at',
])]
class FinanceQuote extends WorkspaceScopedModel
{
    use BelongsToWorkspace, SoftDeletes;

    /**
     * Non-financial fields that may change after issue/cancel.
     *
     * @var array<int, string>
     */
    private const MUTABLE_WHEN_LOCKED = [
        'status',
        'cancelled_at',
        'notes',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'expiry_date' => 'date',
            'issued_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'taxable_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_breakdown' => 'array',
            'company_snapshot' => 'array',
            'recipient_snapshot' => 'array',
            'pdf_snapshot' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(FinanceQuoteItem::class, 'quote_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    protected static function booted(): void
    {
        parent::booted();

        static::updating(function (FinanceQuote $quote): void {
            $quote->assertFinancialContentNotMutated();
        });

        static::deleting(function (FinanceQuote $quote): void {
            if ($quote->isLocked()) {
                throw new RuntimeException('لا يمكن حذف عرض سعر صادر أو ملغى. استخدم الإلغاء للصادر.');
            }
        });
    }

    public function quoteStatus(): QuoteStatus
    {
        return QuoteStatus::tryFrom((string) $this->status) ?? QuoteStatus::Draft;
    }

    public function isDraft(): bool
    {
        return $this->quoteStatus() === QuoteStatus::Draft;
    }

    public function isIssued(): bool
    {
        return $this->quoteStatus() === QuoteStatus::Issued;
    }

    public function isCancelled(): bool
    {
        return $this->quoteStatus() === QuoteStatus::Cancelled;
    }

    public function isLocked(): bool
    {
        return $this->quoteStatus()->isLocked();
    }

    public function snapshotsAreAuthoritative(): bool
    {
        return $this->isLocked();
    }

    private function assertFinancialContentNotMutated(): void
    {
        $original = QuoteStatus::tryFrom((string) ($this->getOriginal('status') ?? 'draft')) ?? QuoteStatus::Draft;
        if (! $original->isLocked()) {
            return;
        }

        $dirty = $this->getDirty();
        if (array_key_exists('status', $dirty)) {
            $next = QuoteStatus::tryFrom((string) $dirty['status']);
            if (! ($original === QuoteStatus::Issued && $next === QuoteStatus::Cancelled)) {
                throw new RuntimeException('لا يمكن تغيير حالة عرض السعر الصادر إلا بالإلغاء.');
            }
            unset($dirty['status']);
        }

        $blocked = array_diff(array_keys($dirty), self::MUTABLE_WHEN_LOCKED);
        if ($blocked !== []) {
            throw new RuntimeException('عرض السعر الصادر أو الملغى وثيقة ثابتة ولا يمكن تعديل بياناتها المالية.');
        }
    }
}
