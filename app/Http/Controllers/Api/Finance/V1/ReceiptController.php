<?php

namespace App\Http\Controllers\Api\Finance\V1;

use App\Http\Controllers\Api\Finance\Concerns\HandlesFinanceClient;
use App\Http\Controllers\Api\Finance\FinanceApiController;
use App\Models\Finance\FinanceReceipt;
use App\Services\Finance\Api\FinanceClientPresenter;
use App\Services\Finance\FinanceBootstrapService;
use App\Services\Finance\PdfReceiptService;
use App\Services\Finance\ReceiptEmailService;
use App\Support\Tenancy\WorkspaceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReceiptController extends FinanceApiController
{
    use HandlesFinanceClient;

    public function __construct(
        private readonly WorkspaceContext $workspaceContext,
        private readonly PdfReceiptService $pdfReceiptService,
        private readonly ReceiptEmailService $receiptEmailService,
        private readonly FinanceBootstrapService $financeBootstrapService,
        private readonly FinanceClientPresenter $presenter,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $workspace = $this->clientWorkspace($this->workspaceContext);
        $this->clientActor($request, $workspace, 'receipts.view');
        $this->financeBootstrapService->ensureWorkspaceFinanceSetup($workspace);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:posted,voided'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $page = FinanceReceipt::query()
            ->with(['customer', 'invoice', 'payment'])
            ->when($validated['search'] ?? null, function ($query, $search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('receipt_number', 'like', '%'.$search.'%')
                        ->orWhere('reference', 'like', '%'.$search.'%');
                });
            })
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest('id')
            ->paginate((int) ($validated['per_page'] ?? 25));

        return $this->ok(
            $page->getCollection()->map(fn (FinanceReceipt $receipt) => $this->presenter->receiptSummary($receipt))->values()->all(),
            meta: $this->pageMeta($page),
        );
    }

    public function show(Request $request, FinanceReceipt $receipt): JsonResponse
    {
        $workspace = $this->clientWorkspace($this->workspaceContext);
        $this->clientActor($request, $workspace, 'receipts.view');
        $receipt->load(['payment.invoice.customer', 'invoice.customer', 'customer', 'deliveries.sender']);

        return $this->ok($this->presenter->receiptDetail($receipt));
    }

    public function send(Request $request, FinanceReceipt $receipt): JsonResponse
    {
        $workspace = $this->clientWorkspace($this->workspaceContext);
        $user = $this->clientActor($request, $workspace, 'receipts.send');
        $fields = $this->emailFields($request);
        $delivery = $this->runFinanceDomain(
            fn () => $this->receiptEmailService->send($receipt, $fields, (int) $user->id)
        );
        $receipt->load(['payment', 'invoice.customer', 'customer', 'deliveries.sender']);

        return $this->ok([
            'receipt' => $this->presenter->receiptDetail($receipt),
            'delivery' => $this->presenter->delivery($delivery),
        ], message: 'تم إرسال الإيصال إلى '.$delivery->recipient);
    }

    public function pdf(Request $request, FinanceReceipt $receipt): mixed
    {
        $workspace = $this->clientWorkspace($this->workspaceContext);
        $this->clientActor($request, $workspace, 'receipts.view');

        return $this->runFinanceDomain(fn () => $this->pdfReceiptService->download($receipt));
    }
}
