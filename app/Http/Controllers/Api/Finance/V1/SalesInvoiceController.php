<?php

namespace App\Http\Controllers\Api\Finance\V1;

use App\Exceptions\Api\ApiErrorCode;
use App\Http\Controllers\Api\Finance\Concerns\HandlesFinanceClient;
use App\Http\Controllers\Api\Finance\FinanceApiController;
use App\Models\AuditLog;
use App\Models\Finance\FinanceInvoice;
use App\Models\Finance\FinanceInvoicePayment;
use App\Services\Finance\Api\FinanceClientPresenter;
use App\Services\Finance\FinanceBootstrapService;
use App\Services\Finance\InvoiceCheckoutService;
use App\Services\Finance\InvoiceEmailService;
use App\Services\Finance\InvoiceInboxService;
use App\Services\Finance\InvoicePaymentService;
use App\Services\Finance\InvoiceReminderEmailService;
use App\Services\Finance\InvoiceService;
use App\Services\Finance\PdfInvoiceService;
use App\Support\Tenancy\WorkspaceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SalesInvoiceController extends FinanceApiController
{
    use HandlesFinanceClient;

    public function __construct(
        private readonly WorkspaceContext $workspaceContext,
        private readonly InvoiceService $invoiceService,
        private readonly InvoicePaymentService $invoicePaymentService,
        private readonly InvoiceEmailService $invoiceEmailService,
        private readonly InvoiceReminderEmailService $invoiceReminderEmailService,
        private readonly InvoiceCheckoutService $invoiceCheckoutService,
        private readonly InvoiceInboxService $invoiceInboxService,
        private readonly PdfInvoiceService $pdfInvoiceService,
        private readonly FinanceBootstrapService $financeBootstrapService,
        private readonly FinanceClientPresenter $presenter,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $workspace = $this->clientWorkspace($this->workspaceContext);
        $this->clientActor($request, $workspace, 'invoices.view');
        $this->financeBootstrapService->ensureWorkspaceFinanceSetup($workspace);

        $request->merge(['type' => $request->input('type', 'sales')]);
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'type' => ['nullable', 'in:sales'],
        ]);

        $query = $this->invoiceInboxService->applyRequestFilters(
            FinanceInvoice::query()->with(['customer', 'deliveries']),
            $request
        )->where('type', 'sales');

        $page = $query->latest('id')->paginate((int) ($validated['per_page'] ?? 25));

        return $this->ok(
            $page->getCollection()->map(fn (FinanceInvoice $invoice) => $this->presenter->invoiceSummary($invoice))->values()->all(),
            meta: $this->pageMeta($page),
        );
    }

    public function show(Request $request, FinanceInvoice $invoice): JsonResponse
    {
        $workspace = $this->clientWorkspace($this->workspaceContext);
        $this->clientActor($request, $workspace, 'invoices.view');
        abort_unless((string) $invoice->type === 'sales', 404);

        $invoice = $this->invoiceService->syncPaymentStatus($invoice);
        $invoice->load([
            'customer',
            'items',
            'payments.receipt',
            'payments.invoice.customer',
            'payments.treasuryAccount',
            'receipts.customer',
            'creditNotes.customer',
            'creditNotes.invoice',
            'deliveries.sender',
        ]);

        $checkout = $this->invoiceCheckoutService->availability($invoice);

        $payload = $this->presenter->invoiceDetail($invoice, $checkout);
        $payload['audit'] = AuditLog::query()
            ->with('user')
            ->where('workspace_id', $invoice->workspace_id)
            ->where('entity_type', FinanceInvoice::class)
            ->where('entity_id', $invoice->id)
            ->latest('id')
            ->limit(30)
            ->get()
            ->map(fn (AuditLog $log) => $this->presenter->audit($log))
            ->values()
            ->all();

        return $this->ok($payload);
    }

    public function store(Request $request): JsonResponse
    {
        $workspace = $this->clientWorkspace($this->workspaceContext);
        $this->clientActor($request, $workspace, 'invoices.create');
        $this->financeBootstrapService->ensureWorkspaceFinanceSetup($workspace);
        $payload = $this->invoicePayload($request, (int) $workspace->id, 'sales');
        if (((string) ($payload['invoice_status'] ?? 'draft')) === 'issued') {
            $this->clientActor($request, $workspace, 'invoices.issue');
        }

        $invoice = $this->runFinanceDomain(
            fn () => $this->invoiceService->create($workspace, $payload, (int) $request->user()?->id)
        );

        return $this->ok(
            $this->presenter->invoiceDetail($invoice->load(['customer', 'items', 'payments.receipt', 'deliveries'])),
            message: 'تم إنشاء الفاتورة.',
            status: 201,
        );
    }

    public function update(Request $request, FinanceInvoice $invoice): JsonResponse
    {
        $workspace = $this->clientWorkspace($this->workspaceContext);
        $this->clientActor($request, $workspace, 'invoices.edit');
        abort_unless((string) $invoice->type === 'sales', 404);
        $payload = $this->invoicePayload($request, (int) $workspace->id, 'sales');

        $updated = $this->runFinanceDomain(
            fn () => $this->invoiceService->updateDraft($invoice, $payload, (int) $request->user()?->id)
        );

        return $this->ok(
            $this->presenter->invoiceDetail($updated->load(['customer', 'items', 'payments.receipt', 'deliveries'])),
            message: 'تم تحديث مسودة الفاتورة.',
        );
    }

    public function destroy(Request $request, FinanceInvoice $invoice): JsonResponse
    {
        $workspace = $this->clientWorkspace($this->workspaceContext);
        $this->clientActor($request, $workspace, 'invoices.delete');
        abort_unless((string) $invoice->type === 'sales', 404);
        $this->runFinanceDomain(fn () => $this->invoiceService->deleteDraft($invoice));

        return $this->ok(message: 'تم حذف مسودة الفاتورة.');
    }

    public function issue(Request $request, FinanceInvoice $invoice): JsonResponse
    {
        $workspace = $this->clientWorkspace($this->workspaceContext);
        $user = $this->clientActor($request, $workspace, 'invoices.issue');
        abort_unless((string) $invoice->type === 'sales', 404);
        $issued = $this->runFinanceDomain(fn () => $this->invoiceService->issue($invoice, (int) $user->id));

        return $this->ok($this->detail($issued), message: 'تم إصدار الفاتورة.');
    }

    public function cancel(Request $request, FinanceInvoice $invoice): JsonResponse
    {
        $workspace = $this->clientWorkspace($this->workspaceContext);
        $user = $this->clientActor($request, $workspace, 'invoices.cancel');
        abort_unless((string) $invoice->type === 'sales', 404);
        $cancelled = $this->runFinanceDomain(fn () => $this->invoiceService->cancel($invoice, (int) $user->id));

        return $this->ok($this->detail($cancelled), message: 'تم إلغاء الفاتورة.');
    }

    public function send(Request $request, FinanceInvoice $invoice): JsonResponse
    {
        $workspace = $this->clientWorkspace($this->workspaceContext);
        $user = $this->clientActor($request, $workspace, 'invoices.send');
        abort_unless((string) $invoice->type === 'sales', 404);
        $fields = $this->emailFields($request);
        $delivery = $this->runFinanceDomain(fn () => $this->invoiceEmailService->send($invoice, $fields, (int) $user->id));

        return $this->ok([
            'invoice' => $this->detail($invoice->fresh()),
            'delivery' => $this->presenter->delivery($delivery),
        ], message: 'تم إرسال الفاتورة إلى '.$delivery->recipient);
    }

    public function remind(Request $request, FinanceInvoice $invoice): JsonResponse
    {
        $workspace = $this->clientWorkspace($this->workspaceContext);
        $user = $this->clientActor($request, $workspace, 'invoices.remind');
        abort_unless((string) $invoice->type === 'sales', 404);
        $fields = $this->emailFields($request);
        $fields['source'] = 'manual';
        $delivery = $this->runFinanceDomain(
            fn () => $this->invoiceReminderEmailService->send($invoice, $fields, (int) $user->id)
        );

        return $this->ok([
            'invoice' => $this->detail($invoice->fresh()),
            'delivery' => $this->presenter->delivery($delivery),
        ], message: 'تم إرسال تذكير إلى '.$delivery->recipient);
    }

    public function checkoutAvailability(Request $request, FinanceInvoice $invoice): JsonResponse
    {
        $workspace = $this->clientWorkspace($this->workspaceContext);
        $this->clientActor($request, $workspace, 'payments.view');
        abort_unless((string) $invoice->type === 'sales', 404);
        $invoice = $this->invoiceService->syncPaymentStatus($invoice);

        return $this->ok($this->presenter->checkout($this->invoiceCheckoutService->availability($invoice)));
    }

    public function checkout(Request $request, FinanceInvoice $invoice): JsonResponse
    {
        $workspace = $this->clientWorkspace($this->workspaceContext);
        $this->clientActor($request, $workspace, 'payments.manage');
        abort_unless((string) $invoice->type === 'sales', 404);

        $result = $this->invoiceCheckoutService->createCheckout($invoice);
        $fresh = $invoice->fresh();
        if ((float) $fresh->amount_due <= 0.009 || (string) $fresh->payment_status === 'paid') {
            return $this->fail(
                'إنشاء رابط الدفع لا يجوز أن يغيّر حالة الفاتورة إلى مدفوعة.',
                ApiErrorCode::ValidationFailed,
                422,
            );
        }

        if (! $result->hasCheckoutUrl()) {
            return $this->fail(
                $result->message !== '' ? $result->message : 'تعذر إنشاء رابط الدفع الإلكتروني.',
                ApiErrorCode::ValidationFailed,
                422,
            );
        }

        return $this->ok([
            'checkout' => $this->presenter->checkout($result),
            'invoice' => $this->detail($fresh),
        ], message: 'تم إنشاء رابط الدفع الإلكتروني. التأكيد يتم عبر بوابة الدفع المشتركة وليس من إنشاء الرابط.');
    }

    public function storePayment(Request $request, FinanceInvoice $invoice): JsonResponse
    {
        $workspace = $this->clientWorkspace($this->workspaceContext);
        $this->clientActor($request, $workspace, 'payments.manage');
        abort_unless((string) $invoice->type === 'sales', 404);

        $validated = $request->validate([
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', 'in:cash,bank_transfer,card,other'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'treasury_account_id' => [
                'nullable',
                'integer',
                Rule::exists('finance_treasury_accounts', 'id')->where(
                    fn ($query) => $query->where('workspace_id', $invoice->workspace_id)
                ),
            ],
        ]);

        $payment = $this->runFinanceDomain(
            fn () => $this->invoicePaymentService->recordPayment($invoice, $validated, (int) $request->user()?->id)
        );
        $payment->load(['invoice.customer', 'receipt']);

        return $this->ok([
            'payment' => $this->presenter->payment($payment),
            'invoice' => $this->detail($invoice->fresh()),
        ], message: 'تم تسجيل الدفعة.');
    }

    public function reversePayment(Request $request, FinanceInvoice $invoice, FinanceInvoicePayment $payment): JsonResponse
    {
        $workspace = $this->clientWorkspace($this->workspaceContext);
        $this->clientActor($request, $workspace, 'invoices.reverse_payment');
        abort_unless((string) $invoice->type === 'sales', 404);
        abort_unless((int) $payment->invoice_id === (int) $invoice->id, 404);

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

        return $this->ok([
            'payment' => $this->presenter->payment($reversed),
            'invoice' => $this->detail($invoice->fresh()),
        ], message: 'تم عكس الدفعة.');
    }

    public function pdf(Request $request, FinanceInvoice $invoice): mixed
    {
        $workspace = $this->clientWorkspace($this->workspaceContext);
        $this->clientActor($request, $workspace, 'invoices.view');
        abort_unless((string) $invoice->type === 'sales', 404);
        $invoice = $this->invoiceService->syncPaymentStatus($invoice);

        return $this->runFinanceDomain(fn () => $this->pdfInvoiceService->download($invoice));
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(FinanceInvoice $invoice): array
    {
        $invoice = $this->invoiceService->syncPaymentStatus($invoice->fresh() ?? $invoice);
        $invoice->load([
            'customer',
            'items',
            'payments.receipt',
            'payments.invoice.customer',
            'payments.treasuryAccount',
            'receipts.customer',
            'creditNotes.customer',
            'creditNotes.invoice',
            'deliveries.sender',
        ]);

        return $this->presenter->invoiceDetail($invoice, $this->invoiceCheckoutService->availability($invoice));
    }

    /**
     * @return array<string, mixed>
     */
    private function invoicePayload(Request $request, int $workspaceId, string $type): array
    {
        $validated = $request->validate([
            'type' => ['nullable', 'in:sales,purchase'],
            'customer_id' => [
                'nullable',
                'integer',
                Rule::exists('customers', 'id')->where(fn ($query) => $query->where('workspace_id', $workspaceId)),
            ],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'supplier_id' => [
                'nullable',
                'integer',
                Rule::exists('finance_suppliers', 'id')->where(fn ($query) => $query->where('workspace_id', $workspaceId)),
            ],
            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'currency' => ['nullable', 'string', 'size:3'],
            'invoice_status' => ['nullable', 'in:draft,issued'],
            'payment_terms' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'tax_profile_type' => ['nullable', 'in:standard,zero_rated,exempt,out_of_scope'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tax_price_mode' => ['nullable', 'in:exclusive,inclusive'],
            'items' => ['nullable', 'array'],
            'items_json' => ['nullable', 'string'],
        ]);

        $validated['type'] = $type;

        if (
            $type === 'sales'
            && empty($validated['customer_id'])
            && trim((string) ($validated['customer_name'] ?? '')) === ''
        ) {
            throw ValidationException::withMessages([
                'customer_id' => 'يرجى اختيار عميل مسجل أو إدخال اسم عميل نقدي.',
            ]);
        }

        if ($type === 'purchase' && empty($validated['supplier_id'])) {
            throw ValidationException::withMessages([
                'supplier_id' => 'يرجى اختيار المورد لفاتورة الشراء.',
            ]);
        }

        $payload = Arr::except($validated, ['items', 'items_json']);
        $payload['items'] = $this->documentItemsFromRequest($request);

        return $payload;
    }
}
