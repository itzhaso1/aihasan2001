<?php

namespace App\Http\Controllers\Api\Finance\V1;

use App\Http\Controllers\Api\Finance\Concerns\HandlesFinanceClient;
use App\Http\Controllers\Api\Finance\FinanceApiController;
use App\Models\Finance\FinanceInvoicePayment;
use App\Services\Finance\Api\FinanceClientPresenter;
use App\Services\Finance\FinanceBootstrapService;
use App\Services\Finance\InvoicePaymentService;
use App\Support\Tenancy\WorkspaceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends FinanceApiController
{
    use HandlesFinanceClient;

    public function __construct(
        private readonly WorkspaceContext $workspaceContext,
        private readonly InvoicePaymentService $invoicePaymentService,
        private readonly FinanceBootstrapService $financeBootstrapService,
        private readonly FinanceClientPresenter $presenter,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $workspace = $this->clientWorkspace($this->workspaceContext);
        $this->clientActor($request, $workspace, 'payments.view');
        $this->financeBootstrapService->ensureWorkspaceFinanceSetup($workspace);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:posted,reversed'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $page = FinanceInvoicePayment::query()
            ->with(['invoice.customer', 'receipt'])
            ->when($validated['search'] ?? null, function ($query, $search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('reference', 'like', '%'.$search.'%')
                        ->orWhereHas('invoice', fn ($invoice) => $invoice->where('invoice_number', 'like', '%'.$search.'%'));
                });
            })
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest('id')
            ->paginate((int) ($validated['per_page'] ?? 25));

        return $this->ok(
            $page->getCollection()->map(fn (FinanceInvoicePayment $payment) => $this->presenter->payment($payment))->values()->all(),
            meta: $this->pageMeta($page),
        );
    }

    public function show(Request $request, FinanceInvoicePayment $payment): JsonResponse
    {
        $workspace = $this->clientWorkspace($this->workspaceContext);
        $this->clientActor($request, $workspace, 'payments.view');
        $payment->load(['invoice.customer', 'receipt']);

        return $this->ok($this->presenter->payment($payment));
    }

    public function reverse(Request $request, FinanceInvoicePayment $payment): JsonResponse
    {
        $workspace = $this->clientWorkspace($this->workspaceContext);
        $this->clientActor($request, $workspace, 'invoices.reverse_payment');
        $validated = $request->validate([
            'reversal_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $reversed = $this->runFinanceDomain(
            fn () => $this->invoicePaymentService->reversePayment(
                $payment,
                (int) $request->user()?->id,
                $validated['reversal_reason'] ?? null
            )
        );
        $reversed->load(['invoice.customer', 'receipt']);

        return $this->ok($this->presenter->payment($reversed), message: 'تم عكس الدفعة.');
    }
}
