<?php

namespace App\Http\Controllers\Workspace\Finance;

use App\Models\Finance\FinanceInvoicePayment;
use App\Services\Finance\FinanceBootstrapService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends FinanceBaseController
{
    public function __construct(
        private readonly FinanceBootstrapService $financeBootstrapService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeFinance($request, 'payments.view');
        $this->financeBootstrapService->ensureWorkspaceFinanceSetup($this->currentWorkspace());

        $payments = FinanceInvoicePayment::query()
            ->with(['invoice.customer', 'receipt', 'treasuryAccount'])
            ->when($request->string('search')->toString(), function ($query, $search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('reference', 'like', '%'.$search.'%')
                        ->orWhereHas('invoice', fn ($invoice) => $invoice->where('invoice_number', 'like', '%'.$search.'%'));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('workspace.finance.payments.index', [
            'payments' => $payments,
        ]);
    }
}
