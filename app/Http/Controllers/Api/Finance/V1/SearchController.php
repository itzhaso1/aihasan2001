<?php

namespace App\Http\Controllers\Api\Finance\V1;

use App\Http\Controllers\Api\Finance\Concerns\HandlesFinanceClient;
use App\Http\Controllers\Api\Finance\FinanceApiController;
use App\Models\Customer;
use App\Models\Finance\FinanceInvoice;
use App\Models\Finance\FinanceInvoicePayment;
use App\Models\Finance\FinanceQuote;
use App\Models\Finance\FinanceReceipt;
use App\Support\Tenancy\WorkspaceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends FinanceApiController
{
    use HandlesFinanceClient;

    public function __construct(
        private readonly WorkspaceContext $workspaceContext,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $workspace = $this->clientWorkspace($this->workspaceContext);
        $this->clientActor($request, $workspace, 'finance.view');

        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:120'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:20'],
        ]);

        $term = trim($validated['q']);
        $limit = (int) ($validated['limit'] ?? 8);
        $like = '%'.$term.'%';

        return $this->ok([
            'customers' => Customer::query()
                ->where(function ($query) use ($like): void {
                    $query->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('phone', 'like', $like);
                })
                ->limit($limit)
                ->get(['id', 'name', 'email', 'phone'])
                ->map(fn (Customer $row): array => [
                    'id' => $row->id,
                    'title' => $row->name,
                    'subtitle' => $row->email ?: $row->phone,
                    'type' => 'customer',
                ])->all(),
            'invoices' => FinanceInvoice::query()
                ->where('type', 'sales')
                ->where(function ($query) use ($like): void {
                    $query->where('invoice_number', 'like', $like)
                        ->orWhere('customer_name', 'like', $like);
                })
                ->limit($limit)
                ->get(['id', 'invoice_number', 'total', 'invoice_status'])
                ->map(fn (FinanceInvoice $row): array => [
                    'id' => $row->id,
                    'title' => $row->invoice_number,
                    'subtitle' => (string) $row->invoice_status,
                    'type' => 'invoice',
                ])->all(),
            'quotes' => FinanceQuote::query()
                ->where('quote_number', 'like', $like)
                ->limit($limit)
                ->get(['id', 'quote_number', 'status', 'outcome'])
                ->map(fn (FinanceQuote $row): array => [
                    'id' => $row->id,
                    'title' => $row->quote_number,
                    'subtitle' => $row->status.' / '.$row->outcome,
                    'type' => 'quote',
                ])->all(),
            'receipts' => FinanceReceipt::query()
                ->where('receipt_number', 'like', $like)
                ->limit($limit)
                ->get(['id', 'receipt_number', 'status'])
                ->map(fn (FinanceReceipt $row): array => [
                    'id' => $row->id,
                    'title' => $row->receipt_number,
                    'subtitle' => $row->status,
                    'type' => 'receipt',
                ])->all(),
            'payments' => FinanceInvoicePayment::query()
                ->where('reference', 'like', $like)
                ->limit($limit)
                ->get(['id', 'reference', 'amount', 'status'])
                ->map(fn (FinanceInvoicePayment $row): array => [
                    'id' => $row->id,
                    'title' => $row->reference ?: ('#'.$row->id),
                    'subtitle' => $row->status,
                    'type' => 'payment',
                ])->all(),
        ]);
    }
}
