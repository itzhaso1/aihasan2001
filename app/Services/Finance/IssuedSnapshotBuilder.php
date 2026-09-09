<?php

namespace App\Services\Finance;

use App\Models\Finance\FinanceCreditNote;
use App\Models\Finance\FinanceInvoice;
use App\Models\Finance\FinanceInvoiceItem;
use App\Models\Finance\FinanceSetting;
use App\Models\Finance\IssuedDocumentSnapshot;
use App\Support\Money\Money;
use App\Support\Tenancy\WorkspaceContext;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class IssuedSnapshotBuilder
{
    public function captureInvoice(FinanceInvoice $invoice): IssuedDocumentSnapshot
    {
        $this->assertSameWorkspace((int) $invoice->workspace_id);

        if (! $invoice->isIssued()) {
            throw new RuntimeException('لا يمكن إنشاء لقطة إلا لمستند صادر.');
        }

        return $this->persist(
            workspaceId: (int) $invoice->workspace_id,
            sourceType: IssuedDocumentSnapshot::SOURCE_FINANCE_INVOICE,
            sourceId: (int) $invoice->id,
            documentNumber: (string) $invoice->invoice_number,
            issueDate: $invoice->issue_date?->toDateString() ?? now(config('app.timezone'))->toDateString(),
            issuedAt: $invoice->issued_at,
            currency: (string) ($invoice->currency ?: 'SAR'),
            payload: $this->invoicePayload($invoice),
        );
    }

    public function captureCreditNote(FinanceCreditNote $note): IssuedDocumentSnapshot
    {
        $this->assertSameWorkspace((int) $note->workspace_id);

        if ($note->status !== FinanceCreditNote::STATUS_ISSUED) {
            throw new RuntimeException('لا يمكن إنشاء لقطة إلا لمستند صادر.');
        }

        $sourceType = $note->isCredit()
            ? IssuedDocumentSnapshot::SOURCE_FINANCE_CREDIT_NOTE
            : IssuedDocumentSnapshot::SOURCE_FINANCE_DEBIT_NOTE;

        return $this->persist(
            workspaceId: (int) $note->workspace_id,
            sourceType: $sourceType,
            sourceId: (int) $note->id,
            documentNumber: (string) $note->note_number,
            issueDate: $note->issue_date?->toDateString() ?? now(config('app.timezone'))->toDateString(),
            issuedAt: $note->issued_at,
            currency: (string) ($note->currency ?: 'SAR'),
            payload: $this->creditNotePayload($note),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function persist(
        int $workspaceId,
        string $sourceType,
        int $sourceId,
        string $documentNumber,
        string $issueDate,
        mixed $issuedAt,
        string $currency,
        array $payload,
    ): IssuedDocumentSnapshot {
        $existing = $this->findExisting($workspaceId, $sourceType, $sourceId);
        if ($existing) {
            return $existing;
        }

        try {
            return DB::transaction(function () use (
                $workspaceId,
                $sourceType,
                $sourceId,
                $documentNumber,
                $issueDate,
                $issuedAt,
                $currency,
                $payload
            ): IssuedDocumentSnapshot {
                $existing = $this->findExisting($workspaceId, $sourceType, $sourceId);
                if ($existing) {
                    return $existing;
                }

                return IssuedDocumentSnapshot::withoutGlobalScopes()->create([
                    'workspace_id' => $workspaceId,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                    'document_number' => $documentNumber,
                    'issue_date' => $issueDate,
                    'issued_at' => $issuedAt,
                    'currency' => $currency,
                    'payload' => $payload,
                ]);
            });
        } catch (UniqueConstraintViolationException $exception) {
            $existing = $this->findExisting($workspaceId, $sourceType, $sourceId);
            if ($existing) {
                return $existing;
            }

            Log::warning('issued_snapshot.duplicate_unresolved', [
                'workspace_id' => $workspaceId,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
            ]);

            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('issued_snapshot.persist_failed', [
                'workspace_id' => $workspaceId,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'operation' => 'persist',
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function findExisting(int $workspaceId, string $sourceType, int $sourceId): ?IssuedDocumentSnapshot
    {
        return IssuedDocumentSnapshot::withoutGlobalScopes()
            ->where('workspace_id', $workspaceId)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function invoicePayload(FinanceInvoice $invoice): array
    {
        $invoice->loadMissing(['items', 'contract', 'customer', 'supplier']);

        $seller = is_array($invoice->company_snapshot) ? $invoice->company_snapshot : [];
        $buyer = is_array($invoice->recipient_snapshot) ? $invoice->recipient_snapshot : [];
        $pdf = is_array($invoice->pdf_snapshot) ? $invoice->pdf_snapshot : [];

        return [
            'document' => [
                'number' => $invoice->invoice_number,
                'issue_date' => $invoice->issue_date?->toDateString(),
                'issued_at' => $invoice->issued_at?->toIso8601String(),
                'due_date' => $invoice->due_date?->toDateString(),
                'status' => $invoice->resolvedInvoiceStatus(),
                'type' => $invoice->type,
                'tax_document_subtype' => $invoice->tax_document_subtype ?: 'standard',
                'currency' => $invoice->currency,
                'payment_terms' => $invoice->payment_terms,
                'notes' => $invoice->notes,
            ],
            'seller' => $seller,
            'buyer' => $buyer,
            'contract' => $this->contractSection($invoice),
            'reference' => null,
            'lines' => $invoice->items->map(fn (FinanceInvoiceItem $item): array => $this->invoiceLine($item))->values()->all(),
            'tax' => [
                'profile_type' => $invoice->tax_profile_type,
                'rate' => $this->money($invoice->tax_rate),
                'price_mode' => $invoice->tax_price_mode,
                'amount' => $this->money($invoice->tax_amount),
                'breakdown' => is_array($invoice->tax_breakdown) ? $invoice->tax_breakdown : null,
            ],
            'totals' => [
                'subtotal' => $this->money($invoice->subtotal),
                'discount' => $this->money($invoice->discount),
                'taxable_amount' => $this->money($invoice->taxable_amount),
                'tax_amount' => $this->money($invoice->tax_amount),
                'total' => $this->money($invoice->total),
                'amount_paid' => $this->money($invoice->amount_paid),
                'amount_due' => $this->money($invoice->amount_due),
                'amount_credited' => $this->money($invoice->amount_credited ?? 0),
                'amount_debited' => $this->money($invoice->amount_debited ?? 0),
            ],
            'payment' => [
                'payment_status' => $invoice->payment_status,
                'amount_paid' => $this->money($invoice->amount_paid),
                'amount_due' => $this->money($invoice->amount_due),
            ],
            'metadata' => [
                'schema_version' => IssuedDocumentSnapshot::SCHEMA_VERSION,
                'source_type' => IssuedDocumentSnapshot::SOURCE_FINANCE_INVOICE,
                'source_id' => (int) $invoice->id,
                'workspace_id' => (int) $invoice->workspace_id,
                'pdf' => $pdf,
                'legacy_invoice_snapshots' => true,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function creditNotePayload(FinanceCreditNote $note): array
    {
        $note->loadMissing(['items', 'invoice', 'customer']);
        $invoice = $note->invoice;
        if ($invoice && (int) $invoice->workspace_id !== (int) $note->workspace_id) {
            throw new RuntimeException('Cross-workspace credit note reference is not allowed.');
        }

        $seller = is_array($invoice?->company_snapshot) && $invoice->company_snapshot !== []
            ? $invoice->company_snapshot
            : $this->sellerFromSettings((int) $note->workspace_id);
        $buyer = is_array($invoice?->recipient_snapshot) && $invoice->recipient_snapshot !== []
            ? $invoice->recipient_snapshot
            : [
                'kind' => 'customer',
                'name' => $note->customer?->name,
                'vat_number' => $note->customer?->vat_number,
                'commercial_registration' => $note->customer?->commercial_registration,
                'address' => $note->customer?->address,
                'phone' => $note->customer?->phone,
                'email' => $note->customer?->email,
            ];

        return [
            'document' => [
                'number' => $note->note_number,
                'issue_date' => $note->issue_date?->toDateString(),
                'issued_at' => $note->issued_at?->toIso8601String(),
                'status' => $note->status,
                'type' => $note->type,
                'reason' => $note->reason,
                'currency' => $note->currency,
                'notes' => $note->notes,
            ],
            'seller' => $seller,
            'buyer' => $buyer,
            'contract' => $invoice ? $this->contractSection($invoice) : null,
            'reference' => [
                'invoice_id' => $invoice?->id,
                'invoice_number' => $invoice?->invoice_number,
                'invoice_issue_date' => $invoice?->issue_date?->toDateString(),
                'invoice_type' => $invoice?->type,
            ],
            'lines' => $note->items->map(fn ($item): array => [
                'description' => $item->description ?: $item->product_name,
                'product_name' => $item->product_name,
                'quantity' => $this->quantity($item->quantity),
                'unit_price' => $this->money($item->unit_price),
                'discount' => $this->money($item->discount),
                'taxable_amount' => $this->money($item->taxable_amount),
                'tax_profile_type' => $item->tax_profile_type,
                'tax_rate' => $this->money($item->tax_rate),
                'tax_amount' => $this->money($item->tax_amount),
                'total' => $this->money($item->total),
                'exemption_reason' => $item->exemption_reason ?? null,
                'exemption_code' => $item->exemption_code ?? null,
            ])->values()->all(),
            'tax' => [
                'profile_type' => $note->tax_profile_type,
                'rate' => $this->money($note->tax_rate),
                'price_mode' => $note->tax_price_mode,
                'amount' => $this->money($note->tax_amount),
                'breakdown' => is_array($note->tax_breakdown) ? $note->tax_breakdown : null,
            ],
            'totals' => [
                'subtotal' => $this->money($note->subtotal),
                'discount' => $this->money($note->discount),
                'taxable_amount' => $this->money($note->taxable_amount),
                'tax_amount' => $this->money($note->tax_amount),
                'total' => $this->money($note->total),
            ],
            'payment' => null,
            'metadata' => [
                'schema_version' => IssuedDocumentSnapshot::SCHEMA_VERSION,
                'source_type' => $note->isCredit()
                    ? IssuedDocumentSnapshot::SOURCE_FINANCE_CREDIT_NOTE
                    : IssuedDocumentSnapshot::SOURCE_FINANCE_DEBIT_NOTE,
                'source_id' => (int) $note->id,
                'workspace_id' => (int) $note->workspace_id,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function invoiceLine(FinanceInvoiceItem $item): array
    {
        return [
            'description' => $item->description ?: $item->product_name,
            'product_id' => $item->product_id,
            'product_name' => $item->product_name,
            'quantity' => $this->quantity($item->quantity),
            'unit_price' => $this->money($item->unit_price),
            'discount' => $this->money($item->discount),
            'taxable_amount' => $this->money($item->taxable_amount),
            'tax_profile_type' => $item->tax_profile_type,
            'tax_rate' => $this->money($item->tax_rate),
            'tax_amount' => $this->money($item->tax_amount),
            'total' => $this->money($item->total),
            'exemption_reason' => $item->exemption_reason ?? null,
            'exemption_code' => $item->exemption_code ?? null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function contractSection(FinanceInvoice $invoice): ?array
    {
        $contract = $invoice->contract;
        if (! $contract) {
            return $invoice->contract_id ? ['id' => (int) $invoice->contract_id] : null;
        }

        return [
            'id' => (int) $contract->id,
            'contract_number' => $contract->contract_number,
            'title' => $contract->title,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sellerFromSettings(int $workspaceId): array
    {
        $setting = FinanceSetting::forWorkspaceId($workspaceId);

        return [
            'company_name' => $setting?->company_name,
            'company_name_ar' => $setting?->company_name_ar,
            'vat_number' => $setting?->vat_number,
            'commercial_registration' => $setting?->commercial_registration,
            'address_line' => $setting?->address_line,
            'building_number' => $setting?->building_number,
            'street' => $setting?->street,
            'district' => $setting?->district,
            'city' => $setting?->city,
            'postal_code' => $setting?->postal_code,
            'country_code' => $setting?->country_code,
            'phone' => $setting?->phone,
            'email' => $setting?->email,
            'website' => $setting?->website,
            'currency' => $setting?->currency,
            'logo_path' => $setting?->logo_path,
        ];
    }

    private function assertSameWorkspace(int $documentWorkspaceId): void
    {
        $contextId = app(WorkspaceContext::class)->workspaceId();
        if ($contextId !== null && (int) $contextId !== $documentWorkspaceId) {
            throw new RuntimeException('Cross-workspace snapshot creation is not allowed.');
        }
    }

    private function money(mixed $value): string
    {
        return Money::of($value ?? 0);
    }

    private function quantity(mixed $value): string
    {
        return number_format((float) $value, 3, '.', '');
    }
}
