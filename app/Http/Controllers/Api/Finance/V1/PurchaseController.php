<?php

namespace App\Http\Controllers\Api\Finance\V1;

use App\Http\Controllers\Api\Finance\Concerns\HandlesFinanceClient;
use App\Http\Controllers\Api\Finance\FinanceApiController;
use App\Models\Finance\FinanceInvoice;
use App\Models\Finance\FinanceSupplier;
use App\Services\Finance\Api\FinanceClientPresenter;
use App\Services\Finance\FinanceBootstrapService;
use App\Services\Finance\InvoiceInboxService;
use App\Services\Finance\InvoiceService;
use App\Services\Finance\LedgerReportService;
use App\Support\Tenancy\WorkspaceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PurchaseController extends FinanceApiController
{
    use HandlesFinanceClient;

    public function __construct(
        private readonly WorkspaceContext $workspaceContext,
        private readonly InvoiceService $invoiceService,
        private readonly InvoiceInboxService $invoiceInboxService,
        private readonly LedgerReportService $ledgerReportService,
        private readonly FinanceBootstrapService $financeBootstrapService,
        private readonly FinanceClientPresenter $presenter,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $workspace = $this->clientWorkspace($this->workspaceContext);
        $this->clientActor($request, $workspace, 'purchases.view');
        $this->financeBootstrapService->ensureWorkspaceFinanceSetup($workspace);
        $request->merge(['type' => 'purchase']);

        $validated = $request->validate(['per_page' => ['sometimes', 'integer', 'min:1', 'max:100']]);
        $query = $this->invoiceInboxService->applyRequestFilters(
            FinanceInvoice::query()->with(['supplier', 'customer']),
            $request
        )->where('type', 'purchase');

        $page = $query->latest('id')->paginate((int) ($validated['per_page'] ?? 25));

        return $this->ok(
            $page->getCollection()->map(fn (FinanceInvoice $invoice) => $this->presenter->invoiceSummary($invoice))->values()->all(),
            meta: $this->pageMeta($page),
        );
    }

    public function show(Request $request, FinanceInvoice $invoice): JsonResponse
    {
        $workspace = $this->clientWorkspace($this->workspaceContext);
        $this->clientActor($request, $workspace, 'purchases.view');
        abort_unless((string) $invoice->type === 'purchase', 404);
        $invoice = $this->invoiceService->syncPaymentStatus($invoice);
        $invoice->load(['supplier', 'items', 'payments.receipt']);

        return $this->ok($this->presenter->invoiceDetail($invoice));
    }

    public function store(Request $request): JsonResponse
    {
        $workspace = $this->clientWorkspace($this->workspaceContext);
        $this->clientActor($request, $workspace, 'purchases.manage');
        $this->financeBootstrapService->ensureWorkspaceFinanceSetup($workspace);
        $payload = $this->purchasePayload($request, (int) $workspace->id);

        $invoice = $this->runFinanceDomain(
            fn () => $this->invoiceService->create($workspace, $payload, (int) $request->user()?->id)
        );

        return $this->ok(
            $this->presenter->invoiceDetail($invoice->load(['supplier', 'items'])),
            message: 'تم إنشاء فاتورة الشراء.',
            status: 201,
        );
    }

    public function update(Request $request, FinanceInvoice $invoice): JsonResponse
    {
        $workspace = $this->clientWorkspace($this->workspaceContext);
        $this->clientActor($request, $workspace, 'purchases.manage');
        abort_unless((string) $invoice->type === 'purchase', 404);
        $payload = $this->purchasePayload($request, (int) $workspace->id);
        $updated = $this->runFinanceDomain(
            fn () => $this->invoiceService->updateDraft($invoice, $payload, (int) $request->user()?->id)
        );

        return $this->ok($this->presenter->invoiceDetail($updated->load(['supplier', 'items'])), message: 'تم تحديث مسودة فاتورة الشراء.');
    }

    public function aging(Request $request): JsonResponse
    {
        $workspace = $this->clientWorkspace($this->workspaceContext);
        $this->clientActor($request, $workspace, 'purchases.view');
        $to = $request->validate(['to' => ['nullable', 'date']])['to'] ?? now()->toDateString();

        return $this->ok($this->ledgerReportService->aging((int) $workspace->id, 'purchase', $to));
    }

    public function suppliers(Request $request): JsonResponse
    {
        $workspace = $this->clientWorkspace($this->workspaceContext);
        $this->clientActor($request, $workspace, 'purchases.view');

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $page = FinanceSupplier::query()
            ->when($validated['search'] ?? null, function ($query, $search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->latest('id')
            ->paginate((int) ($validated['per_page'] ?? 25));

        return $this->ok(
            $page->getCollection()->map(fn (FinanceSupplier $supplier) => $this->presenter->supplier($supplier))->values()->all(),
            meta: $this->pageMeta($page),
        );
    }

    public function storeSupplier(Request $request): JsonResponse
    {
        $workspace = $this->clientWorkspace($this->workspaceContext);
        $this->clientActor($request, $workspace, 'purchases.manage');
        $payload = $this->supplierPayload($request);
        $supplier = FinanceSupplier::query()->create([
            ...$payload,
            'opening_balance' => $payload['opening_balance'] ?? 0,
            'status' => $payload['status'] ?? 'active',
        ]);

        return $this->ok($this->presenter->supplier($supplier), message: 'تم إنشاء المورد.', status: 201);
    }

    public function updateSupplier(Request $request, FinanceSupplier $supplier): JsonResponse
    {
        $workspace = $this->clientWorkspace($this->workspaceContext);
        $this->clientActor($request, $workspace, 'purchases.manage');
        $payload = $this->supplierPayload($request);
        $supplier->update([
            ...$payload,
            'opening_balance' => $payload['opening_balance'] ?? $supplier->opening_balance,
            'status' => $payload['status'] ?? $supplier->status,
        ]);

        return $this->ok($this->presenter->supplier($supplier->fresh()), message: 'تم تحديث المورد.');
    }

    /**
     * @return array<string, mixed>
     */
    private function purchasePayload(Request $request, int $workspaceId): array
    {
        $validated = $request->validate([
            'supplier_id' => [
                'required',
                'integer',
                Rule::exists('finance_suppliers', 'id')->where(fn ($query) => $query->where('workspace_id', $workspaceId)),
            ],
            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'currency' => ['nullable', 'string', 'size:3'],
            'invoice_status' => ['nullable', 'in:draft,issued'],
            'notes' => ['nullable', 'string'],
            'tax_profile_type' => ['nullable', 'in:standard,zero_rated,exempt,out_of_scope'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tax_price_mode' => ['nullable', 'in:exclusive,inclusive'],
        ]);

        if (empty($validated['supplier_id'])) {
            throw ValidationException::withMessages(['supplier_id' => 'يرجى اختيار المورد لفاتورة الشراء.']);
        }

        $payload = Arr::except($validated, []);
        $payload['type'] = 'purchase';
        $payload['items'] = $this->documentItemsFromRequest($request);

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function supplierPayload(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'arabic_name' => ['nullable', 'string', 'max:255'],
            'vat_number' => ['nullable', 'string', 'max:255'],
            'commercial_registration' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:255'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
            'payment_terms' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);
    }
}
