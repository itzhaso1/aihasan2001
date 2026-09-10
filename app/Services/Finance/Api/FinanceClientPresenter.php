<?php

namespace App\Services\Finance\Api;

use App\Models\AuditLog;
use App\Models\Contract\Contract;
use App\Models\Customer;
use App\Models\Finance\FinanceCreditNote;
use App\Models\Finance\FinanceDocumentDelivery;
use App\Models\Finance\FinanceExpense;
use App\Models\Finance\FinanceInvoice;
use App\Models\Finance\FinanceInvoiceItem;
use App\Models\Finance\FinanceInvoicePayment;
use App\Models\Finance\FinanceQuote;
use App\Models\Finance\FinanceQuoteItem;
use App\Models\Finance\FinanceReceipt;
use App\Models\Finance\FinanceSetting;
use App\Models\Finance\FinanceSupplier;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Payment\Contracts\BillableCheckoutResult;
use App\Support\Money\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class FinanceClientPresenter
{
    public function money(mixed $value): string
    {
        return Money::of($value ?? 0);
    }

    /**
     * @param  array<string, mixed>|null  $extra
     * @return array<string, mixed>
     */
    public function customer(Customer $customer, ?float $outstanding = null, ?array $extra = null): array
    {
        $payload = [
            'id' => (int) $customer->id,
            'name' => $customer->name,
            'party_type' => $customer->partyType(),
            'email' => $customer->email,
            'phone' => $customer->phone,
            'whatsapp' => $customer->whatsapp,
            'vat_number' => $customer->vat_number,
            'commercial_registration' => $customer->commercial_registration,
            'address' => $customer->address,
            'building_number' => $customer->building_number,
            'street' => $customer->street,
            'district' => $customer->district,
            'city' => $customer->city,
            'postal_code' => $customer->postal_code,
            'country_code' => $customer->country_code,
            'additional_number' => $customer->additional_number,
            'payment_terms' => $customer->payment_terms,
            'notes' => $customer->notes,
            'outstanding_balance' => $this->money($outstanding ?? 0),
        ];

        if ($extra !== null) {
            $payload = array_merge($payload, $extra);
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function invoiceSummary(FinanceInvoice $invoice): array
    {
        return [
            'id' => (int) $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'type' => $invoice->type,
            'customer_id' => $invoice->customer_id ? (int) $invoice->customer_id : null,
            'customer_name' => $invoice->customer?->name ?? $invoice->customer_name,
            'supplier_id' => $invoice->supplier_id ? (int) $invoice->supplier_id : null,
            'supplier_name' => $invoice->supplier?->name,
            'issue_date' => $this->date($invoice->issue_date),
            'due_date' => $this->date($invoice->due_date),
            'currency' => $invoice->currency ?: 'SAR',
            'subtotal' => $this->money($invoice->subtotal),
            'discount' => $this->money($invoice->discount),
            'taxable_amount' => $this->money($invoice->taxable_amount),
            'tax_amount' => $this->money($invoice->tax_amount),
            'total' => $this->money($invoice->total),
            'amount_paid' => $this->money($invoice->amount_paid),
            'amount_due' => $this->money($invoice->amount_due),
            'amount_credited' => $this->money($invoice->amount_credited ?? 0),
            'amount_debited' => $this->money($invoice->amount_debited ?? 0),
            'document_status' => $invoice->invoice_status ?: $invoice->status,
            'payment_status' => $invoice->payment_status,
            'delivery_status' => $this->latestDeliveryStatus($invoice->relationLoaded('deliveries') ? $invoice->deliveries : null),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function invoiceDetail(FinanceInvoice $invoice, ?BillableCheckoutResult $checkout = null): array
    {
        $payload = $this->invoiceSummary($invoice);
        $payload['notes'] = $invoice->notes;
        $payload['payment_terms'] = $invoice->payment_terms;
        $payload['tax_profile_type'] = $invoice->tax_profile_type;
        $payload['tax_rate'] = $this->money($invoice->tax_rate ?? 0);
        $payload['tax_price_mode'] = $invoice->tax_price_mode;
        $payload['contract_id'] = $invoice->contract_id ? (int) $invoice->contract_id : null;
        $payload['project_id'] = $invoice->project_id ? (int) $invoice->project_id : null;
        $payload['company_snapshot'] = is_array($invoice->company_snapshot) ? $invoice->company_snapshot : null;
        $payload['recipient_snapshot'] = is_array($invoice->recipient_snapshot) ? $invoice->recipient_snapshot : null;
        $payload['lines'] = $invoice->items?->map(fn ($item) => $this->invoiceItem($item))->values()->all() ?? [];
        $payload['payments'] = $invoice->payments?->map(fn ($payment) => $this->payment($payment))->values()->all() ?? [];
        $payload['receipts'] = $invoice->receipts?->map(fn ($receipt) => $this->receiptSummary($receipt))->values()->all() ?? [];
        $payload['credit_notes'] = $invoice->creditNotes?->map(fn ($note) => $this->noteSummary($note))->values()->all() ?? [];
        $payload['deliveries'] = $invoice->deliveries?->map(fn ($delivery) => $this->delivery($delivery))->values()->all() ?? [];
        $payload['checkout'] = $checkout ? $this->checkout($checkout) : null;
        $payload['zatca'] = [
            'requirement' => $invoice->zatca_requirement,
            'tax_document_subtype' => $invoice->tax_document_subtype,
            'has_qr' => filled($invoice->zatca_qr_code),
        ];

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function invoiceItem(FinanceInvoiceItem|FinanceQuoteItem $item): array
    {
        return [
            'id' => (int) $item->id,
            'product_id' => $item->product_id ? (int) $item->product_id : null,
            'product_name' => $item->product_name,
            'description' => $item->description,
            'unit' => $item->unit,
            'quantity' => (string) $item->quantity,
            'unit_price' => $this->money($item->unit_price),
            'discount' => $this->money($item->discount),
            'tax_rate' => $this->money($item->tax_rate ?? 0),
            'tax_amount' => $this->money($item->tax_amount),
            'taxable_amount' => $this->money($item->taxable_amount),
            'total' => $this->money($item->total),
            'tax_profile_type' => $item->tax_profile_type,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function quoteSummary(FinanceQuote $quote): array
    {
        return [
            'id' => (int) $quote->id,
            'quote_number' => $quote->quote_number,
            'customer_id' => $quote->customer_id ? (int) $quote->customer_id : null,
            'customer_name' => $quote->customer?->name,
            'issue_date' => $this->date($quote->issue_date),
            'expiry_date' => $this->date($quote->expiry_date),
            'currency' => $quote->currency ?: 'SAR',
            'subtotal' => $this->money($quote->subtotal),
            'discount' => $this->money($quote->discount),
            'taxable_amount' => $this->money($quote->taxable_amount),
            'tax_amount' => $this->money($quote->tax_amount),
            'total' => $this->money($quote->total),
            'document_status' => $quote->status,
            'outcome' => $quote->outcome,
            'delivery_status' => $this->latestDeliveryStatus($quote->relationLoaded('deliveries') ? $quote->deliveries : null),
            'converted_invoice_id' => $quote->converted_invoice_id ? (int) $quote->converted_invoice_id : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function quoteDetail(FinanceQuote $quote): array
    {
        $payload = $this->quoteSummary($quote);
        $payload['notes'] = $quote->notes;
        $payload['terms'] = $quote->terms;
        $payload['tax_profile_type'] = $quote->tax_profile_type;
        $payload['tax_rate'] = $this->money($quote->tax_rate ?? 0);
        $payload['tax_price_mode'] = $quote->tax_price_mode;
        $payload['rejection_reason'] = $quote->rejection_reason;
        $payload['converted_invoice_number'] = $quote->convertedInvoice?->invoice_number;
        $payload['lines'] = $quote->items?->map(fn ($item) => $this->invoiceItem($item))->values()->all() ?? [];
        $payload['deliveries'] = $quote->deliveries?->map(fn ($delivery) => $this->delivery($delivery))->values()->all() ?? [];

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function payment(FinanceInvoicePayment $payment): array
    {
        return [
            'id' => (int) $payment->id,
            'invoice_id' => $payment->invoice_id ? (int) $payment->invoice_id : null,
            'invoice_number' => $payment->invoice?->invoice_number,
            'customer_id' => $payment->invoice?->customer_id ? (int) $payment->invoice->customer_id : null,
            'customer_name' => $payment->invoice?->customer?->name ?? $payment->invoice?->customer_name,
            'payment_date' => $this->date($payment->payment_date),
            'amount' => $this->money($payment->amount),
            'method' => $payment->method,
            'reference' => $payment->reference,
            'status' => $payment->status ?: FinanceInvoicePayment::STATUS_POSTED,
            'notes' => $payment->notes,
            'reversed_at' => $payment->reversed_at?->toIso8601String(),
            'reversal_reason' => $payment->reversal_reason,
            'receipt_id' => $payment->receipt?->id,
            'receipt_number' => $payment->receipt?->receipt_number,
            'receipt_status' => $payment->receipt?->status,
            'treasury_account_id' => $payment->treasury_account_id ? (int) $payment->treasury_account_id : null,
            'treasury_account_name' => $payment->treasuryAccount?->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function receiptSummary(FinanceReceipt $receipt): array
    {
        return [
            'id' => (int) $receipt->id,
            'receipt_number' => $receipt->receipt_number,
            'invoice_id' => $receipt->invoice_id ? (int) $receipt->invoice_id : null,
            'invoice_number' => $receipt->invoice?->invoice_number,
            'customer_id' => $receipt->customer_id ? (int) $receipt->customer_id : null,
            'customer_name' => $receipt->customer?->name,
            'payment_id' => $receipt->payment_id ? (int) $receipt->payment_id : null,
            'payment_date' => $this->date($receipt->payment_date),
            'amount' => $this->money($receipt->amount),
            'method' => $receipt->method,
            'reference' => $receipt->reference,
            'currency' => $receipt->currency ?: 'SAR',
            'status' => $receipt->status ?: FinanceReceipt::STATUS_POSTED,
            'voided_at' => $receipt->voided_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function receiptDetail(FinanceReceipt $receipt): array
    {
        $payload = $this->receiptSummary($receipt);
        $payload['payment'] = $receipt->payment ? $this->payment($receipt->payment) : null;
        $payload['deliveries'] = $receipt->deliveries?->map(fn ($delivery) => $this->delivery($delivery))->values()->all() ?? [];

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function noteSummary(FinanceCreditNote $note): array
    {
        return [
            'id' => (int) $note->id,
            'note_number' => $note->note_number,
            'type' => $note->type,
            'status' => $note->status,
            'invoice_id' => $note->invoice_id ? (int) $note->invoice_id : null,
            'invoice_number' => $note->invoice?->invoice_number,
            'customer_id' => $note->customer_id ? (int) $note->customer_id : null,
            'customer_name' => $note->customer?->name,
            'issue_date' => $this->date($note->issue_date),
            'reason' => $note->reason,
            'currency' => $note->currency ?: 'SAR',
            'tax_amount' => $this->money($note->tax_amount),
            'total' => $this->money($note->total),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function noteDetail(FinanceCreditNote $note): array
    {
        $payload = $this->noteSummary($note);
        $payload['notes'] = $note->notes;
        $payload['subtotal'] = $this->money($note->subtotal);
        $payload['lines'] = $note->items?->map(function ($item): array {
            return [
                'id' => (int) $item->id,
                'product_id' => $item->product_id ? (int) $item->product_id : null,
                'product_name' => $item->product_name ?? $item->description,
                'description' => $item->description ?? $item->product_name,
                'unit' => $item->unit ?? $item->unit_code,
                'quantity' => (string) $item->quantity,
                'unit_price' => $this->money($item->unit_price),
                'discount' => $this->money($item->discount ?? 0),
                'tax_rate' => $this->money($item->tax_rate ?? 0),
                'tax_amount' => $this->money($item->tax_amount ?? 0),
                'taxable_amount' => $this->money($item->taxable_amount ?? 0),
                'total' => $this->money($item->total),
                'tax_profile_type' => $item->tax_profile_type,
            ];
        })->values()->all() ?? [];

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function expense(FinanceExpense $expense): array
    {
        return [
            'id' => (int) $expense->id,
            'expense_number' => $expense->expense_number,
            'expense_date' => $this->date($expense->expense_date),
            'description' => $expense->description,
            'category_id' => $expense->category_id ? (int) $expense->category_id : null,
            'category_name' => $expense->category?->name,
            'supplier_id' => $expense->supplier_id ? (int) $expense->supplier_id : null,
            'supplier_name' => $expense->supplier?->name,
            'amount' => $this->money($expense->amount),
            'tax_rate' => $this->money($expense->tax_rate ?? 0),
            'tax_amount' => $this->money($expense->tax_amount),
            'total' => $this->money($expense->total),
            'currency' => $expense->currency ?: 'SAR',
            'payment_method' => $expense->payment_method,
            'status' => $expense->status,
            'has_attachment' => filled($expense->attachment_path),
            'treasury_account_id' => $expense->treasury_account_id ? (int) $expense->treasury_account_id : null,
            'treasury_account_name' => $expense->treasuryAccount?->name,
            'is_recurring' => (bool) $expense->is_recurring,
            'tax_profile_type' => $expense->tax_profile_type,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function supplier(FinanceSupplier $supplier): array
    {
        return [
            'id' => (int) $supplier->id,
            'name' => $supplier->name,
            'arabic_name' => $supplier->arabic_name,
            'vat_number' => $supplier->vat_number,
            'commercial_registration' => $supplier->commercial_registration,
            'address' => $supplier->address,
            'phone' => $supplier->phone,
            'email' => $supplier->email,
            'payment_terms' => $supplier->payment_terms,
            'status' => $supplier->status,
            'opening_balance' => $this->money($supplier->opening_balance ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function contract(Contract $contract): array
    {
        return [
            'id' => (int) $contract->id,
            'contract_number' => $contract->contract_number,
            'title' => $contract->title,
            'status' => $contract->status,
            'customer_id' => $contract->customer_id ? (int) $contract->customer_id : null,
            'customer_name' => $contract->customer?->name,
            'start_date' => $this->date($contract->start_date),
            'end_date' => $this->date($contract->end_date),
            'value' => $this->money($contract->value ?? 0),
            'currency' => $contract->currency ?: 'SAR',
            'notes' => $contract->notes,
            'terms' => $contract->terms,
            'items' => $contract->relationLoaded('items')
                ? $contract->items->map(fn ($item) => [
                    'id' => (int) $item->id,
                    'title' => $item->title,
                    'description' => $item->description,
                    'quantity' => (string) $item->quantity,
                    'unit_price' => $this->money($item->unit_price),
                    'total' => $this->money($item->total),
                ])->values()->all()
                : [],
            'billing_schedules' => $contract->relationLoaded('billingSchedules')
                ? $contract->billingSchedules->map(fn ($schedule) => [
                    'id' => (int) $schedule->id,
                    'title' => $schedule->title,
                    'frequency' => $schedule->frequency,
                    'status' => $schedule->status,
                    'start_date' => $this->date($schedule->start_date),
                    'end_date' => $this->date($schedule->end_date),
                    'next_run_on' => $this->date($schedule->next_run_on),
                    'interval_count' => $schedule->interval_count,
                    'total_occurrences' => $schedule->total_occurrences,
                    'generated_count' => $schedule->generated_count,
                    'amount' => $this->money($schedule->amount ?? 0),
                    'currency' => $schedule->currency ?: ($contract->currency ?: 'SAR'),
                    'auto_issue' => (bool) $schedule->auto_issue,
                    'notes' => $schedule->notes,
                ])->values()->all()
                : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function delivery(FinanceDocumentDelivery $delivery): array
    {
        return [
            'id' => (int) $delivery->id,
            'document_type' => $delivery->document_type,
            'channel' => $delivery->channel,
            'recipient' => $delivery->recipient,
            'status' => $delivery->status,
            'subject' => $delivery->subject,
            'error' => $delivery->error,
            'sent_at' => $delivery->sent_at?->toIso8601String(),
            'sender_name' => $delivery->sender?->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function checkout(BillableCheckoutResult $result): array
    {
        return [
            'supported' => $result->supported,
            'checkout_url' => $result->checkoutUrl,
            'code' => $result->code,
            'message' => $result->message,
        ];
    }

    /**
     * @param  array<string, mixed>  $statement
     * @return array<string, mixed>
     */
    public function statement(array $statement): array
    {
        /** @var Customer $customer */
        $customer = $statement['customer'];

        return [
            'customer' => $this->customer($customer),
            'from' => $statement['from'],
            'to' => $statement['to'],
            'opening_balance' => $this->money($statement['opening_balance'] ?? 0),
            'closing_balance' => $this->money($statement['closing_balance'] ?? 0),
            'invoices_total' => $this->money($statement['invoices_total'] ?? 0),
            'payments_total' => $this->money($statement['payments_total'] ?? 0),
            'credits_total' => $this->money($statement['credits_total'] ?? 0),
            'debits_total' => $this->money($statement['debits_total'] ?? 0),
            'lines' => collect($statement['lines'] ?? [])->map(function (array $line): array {
                return [
                    'date' => $line['date'] ?? null,
                    'kind' => $line['kind'] ?? null,
                    'reference' => $line['reference'] ?? null,
                    'description' => $line['description'] ?? null,
                    'debit' => $this->money($line['debit'] ?? 0),
                    'credit' => $this->money($line['credit'] ?? 0),
                    'balance' => $this->money($line['balance'] ?? 0),
                    'invoice_id' => isset($line['invoice_id']) ? (int) $line['invoice_id'] : null,
                ];
            })->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @return array<string, mixed>
     */
    public function dashboard(array $metrics, string $paidThisPeriod): array
    {
        $cards = $metrics['cards'] ?? [];

        return [
            'cards' => [
                'outstanding_customer_balance' => $this->money($cards['receivables_total'] ?? 0),
                'invoices_due' => (int) ($cards['unpaid_invoices'] ?? 0),
                'overdue_invoices' => (int) ($cards['overdue_invoices'] ?? 0),
                'paid_this_period' => $paidThisPeriod,
                'sales' => $this->money($cards['sales_total'] ?? 0),
                'purchases' => $this->money($cards['purchases_total'] ?? 0),
                'expenses' => $this->money($cards['expenses_total'] ?? 0),
                'receivables' => $this->money($cards['receivables_total'] ?? 0),
                'payables' => $this->money($cards['payables_total'] ?? 0),
                'net_profit' => $this->money($cards['net_profit'] ?? 0),
                'output_vat' => $this->money($cards['output_vat'] ?? 0),
                'input_vat' => $this->money($cards['input_vat'] ?? 0),
                'net_vat' => $this->money($cards['net_vat'] ?? 0),
                'cash_balance' => $this->money($cards['cash_balance'] ?? 0),
                'bank_balance' => $this->money($cards['bank_balance'] ?? 0),
                'active_contracts_count' => (int) ($cards['active_contracts_count'] ?? 0),
            ],
            'recent_invoices' => collect($metrics['latest']['invoices'] ?? [])
                ->map(fn ($invoice) => $this->invoiceSummary($invoice))
                ->values()
                ->all(),
            'recent_payments' => collect($metrics['latest']['payments'] ?? [])
                ->map(fn ($payment) => $this->payment($payment))
                ->values()
                ->all(),
            'recent_expenses' => collect($metrics['latest']['expenses'] ?? [])
                ->map(fn ($expense) => $this->expense($expense))
                ->values()
                ->all(),
            'overdue_invoices' => collect($metrics['latest']['overdue_invoices'] ?? [])
                ->map(fn ($invoice) => $this->invoiceSummary($invoice))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function settings(FinanceSetting $setting): array
    {
        return [
            'company_name' => $setting->company_name,
            'company_name_ar' => $setting->company_name_ar,
            'vat_number' => $setting->vat_number,
            'commercial_registration' => $setting->commercial_registration,
            'address_line' => $setting->address_line,
            'building_number' => $setting->building_number,
            'street' => $setting->street,
            'district' => $setting->district,
            'city' => $setting->city,
            'postal_code' => $setting->postal_code,
            'country_code' => $setting->country_code,
            'phone' => $setting->phone,
            'email' => $setting->email,
            'website' => $setting->website,
            'currency' => $setting->currency ?: 'SAR',
            'invoice_prefix' => $setting->invoice_prefix,
            'default_vat_rate' => $this->money($setting->default_vat_rate ?? 0),
            'default_payment_terms' => $setting->default_payment_terms,
            'allow_manual_invoice_numbers' => (bool) $setting->allow_manual_invoice_numbers,
            'zatca_integration_mode' => $setting->zatca_integration_mode,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function audit(AuditLog $log): array
    {
        return [
            'id' => (int) $log->id,
            'action' => $log->action,
            'created_at' => $log->occurred_at?->toIso8601String(),
            'actor_name' => $log->user?->name,
        ];
    }

    /**
     * @param  Collection<int, Workspace>  $workspaces
     * @return list<array<string, mixed>>
     */
    public function workspaces($workspaces, callable $financeEnabled): array
    {
        return $workspaces
            ->map(function ($workspace) use ($financeEnabled): array {
                return [
                    'id' => $workspace->id,
                    'name' => $workspace->name,
                    'slug' => $workspace->slug ?? null,
                    'type' => $workspace->type,
                    'finance_enabled' => (bool) $financeEnabled($workspace),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function user(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'locale' => $user->locale,
        ];
    }

    private function date(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof Carbon) {
            return $value->toDateString();
        }

        return (string) $value;
    }

    private function latestDeliveryStatus(mixed $deliveries): ?string
    {
        if ($deliveries === null) {
            return null;
        }

        $first = collect($deliveries)->first();

        return $first?->status;
    }
}
