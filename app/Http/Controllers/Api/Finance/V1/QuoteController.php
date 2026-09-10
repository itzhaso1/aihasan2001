<?php

namespace App\Http\Controllers\Api\Finance\V1;

use App\Http\Controllers\Api\Finance\Concerns\HandlesFinanceClient;
use App\Http\Controllers\Api\Finance\FinanceApiController;
use App\Models\Finance\FinanceQuote;
use App\Services\Finance\Api\FinanceClientPresenter;
use App\Services\Finance\FinanceBootstrapService;
use App\Services\Finance\PdfQuoteService;
use App\Services\Finance\QuoteEmailService;
use App\Services\Finance\QuoteService;
use App\Support\Tenancy\WorkspaceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class QuoteController extends FinanceApiController
{
    use HandlesFinanceClient;

    public function __construct(
        private readonly WorkspaceContext $workspaceContext,
        private readonly QuoteService $quoteService,
        private readonly QuoteEmailService $quoteEmailService,
        private readonly PdfQuoteService $pdfQuoteService,
        private readonly FinanceBootstrapService $financeBootstrapService,
        private readonly FinanceClientPresenter $presenter,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $workspace = $this->clientWorkspace($this->workspaceContext);
        $this->clientActor($request, $workspace, 'quotes.view');
        $this->financeBootstrapService->ensureWorkspaceFinanceSetup($workspace);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:draft,issued,cancelled'],
            'outcome' => ['nullable', 'in:pending,accepted,rejected,expired,converted'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $page = FinanceQuote::query()
            ->with(['customer', 'deliveries'])
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($validated['outcome'] ?? null, fn ($query, $outcome) => $query->where('outcome', $outcome))
            ->when($validated['search'] ?? null, function ($query, $search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('quote_number', 'like', '%'.$search.'%')
                        ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->latest('id')
            ->paginate((int) ($validated['per_page'] ?? 25));

        return $this->ok(
            $page->getCollection()->map(fn (FinanceQuote $quote) => $this->presenter->quoteSummary($quote))->values()->all(),
            meta: $this->pageMeta($page),
        );
    }

    public function show(Request $request, FinanceQuote $quote): JsonResponse
    {
        $workspace = $this->clientWorkspace($this->workspaceContext);
        $this->clientActor($request, $workspace, 'quotes.view');

        $quote->load(['customer', 'items', 'deliveries.sender', 'convertedInvoice']);

        return $this->ok($this->presenter->quoteDetail($quote));
    }

    public function store(Request $request): JsonResponse
    {
        $workspace = $this->clientWorkspace($this->workspaceContext);
        $this->clientActor($request, $workspace, 'quotes.create');
        $this->financeBootstrapService->ensureWorkspaceFinanceSetup($workspace);
        $payload = $this->quotePayload($request, (int) $workspace->id);
        if (((string) ($payload['status'] ?? 'draft')) === 'issued') {
            $this->clientActor($request, $workspace, 'quotes.issue');
        }

        $quote = $this->runFinanceDomain(
            fn () => $this->quoteService->create($workspace, $payload, (int) $request->user()?->id)
        );

        return $this->ok($this->presenter->quoteDetail($quote->load(['customer', 'items', 'deliveries'])), message: 'تم حفظ عرض السعر.', status: 201);
    }

    public function update(Request $request, FinanceQuote $quote): JsonResponse
    {
        $workspace = $this->clientWorkspace($this->workspaceContext);
        $this->clientActor($request, $workspace, 'quotes.edit');
        $payload = $this->quotePayload($request, (int) $workspace->id);

        $updated = $this->runFinanceDomain(
            fn () => $this->quoteService->updateDraft($quote, $payload, (int) $request->user()?->id)
        );

        return $this->ok($this->presenter->quoteDetail($updated->load(['customer', 'items', 'deliveries'])), message: 'تم تحديث مسودة عرض السعر.');
    }

    public function destroy(Request $request, FinanceQuote $quote): JsonResponse
    {
        $workspace = $this->clientWorkspace($this->workspaceContext);
        $this->clientActor($request, $workspace, 'quotes.delete');
        $this->runFinanceDomain(fn () => $this->quoteService->deleteDraft($quote));

        return $this->ok(message: 'تم حذف مسودة عرض السعر.');
    }

    public function issue(Request $request, FinanceQuote $quote): JsonResponse
    {
        $workspace = $this->clientWorkspace($this->workspaceContext);
        $user = $this->clientActor($request, $workspace, 'quotes.issue');
        $issued = $this->runFinanceDomain(fn () => $this->quoteService->issue($quote, (int) $user->id));

        return $this->ok($this->presenter->quoteDetail($issued->load(['customer', 'items', 'deliveries'])), message: 'تم إصدار عرض السعر.');
    }

    public function cancel(Request $request, FinanceQuote $quote): JsonResponse
    {
        $workspace = $this->clientWorkspace($this->workspaceContext);
        $this->clientActor($request, $workspace, 'quotes.cancel');
        $cancelled = $this->runFinanceDomain(fn () => $this->quoteService->cancel($quote));

        return $this->ok($this->presenter->quoteDetail($cancelled->load(['customer', 'items', 'deliveries'])), message: 'تم إلغاء عرض السعر.');
    }

    public function accept(Request $request, FinanceQuote $quote): JsonResponse
    {
        $workspace = $this->clientWorkspace($this->workspaceContext);
        $user = $this->clientActor($request, $workspace, 'quotes.accept');
        $accepted = $this->runFinanceDomain(fn () => $this->quoteService->accept($quote, (int) $user->id));

        return $this->ok($this->presenter->quoteDetail($accepted->load(['customer', 'items', 'deliveries', 'convertedInvoice'])), message: 'تم قبول عرض السعر.');
    }

    public function reject(Request $request, FinanceQuote $quote): JsonResponse
    {
        $workspace = $this->clientWorkspace($this->workspaceContext);
        $user = $this->clientActor($request, $workspace, 'quotes.reject');
        $validated = $request->validate(['rejection_reason' => ['nullable', 'string', 'max:2000']]);
        $rejected = $this->runFinanceDomain(
            fn () => $this->quoteService->reject($quote, (int) $user->id, $validated['rejection_reason'] ?? null)
        );

        return $this->ok($this->presenter->quoteDetail($rejected->load(['customer', 'items', 'deliveries'])), message: 'تم رفض عرض السعر.');
    }

    public function convert(Request $request, FinanceQuote $quote): JsonResponse
    {
        $workspace = $this->clientWorkspace($this->workspaceContext);
        $user = $this->clientActor($request, $workspace, 'quotes.convert');
        $converted = $this->runFinanceDomain(fn () => $this->quoteService->convert($quote, (int) $user->id));
        $converted->load(['customer', 'items', 'deliveries', 'convertedInvoice']);

        return $this->ok($this->presenter->quoteDetail($converted), message: 'تم تحويل عرض السعر إلى فاتورة مسودة.');
    }

    public function send(Request $request, FinanceQuote $quote): JsonResponse
    {
        $workspace = $this->clientWorkspace($this->workspaceContext);
        $user = $this->clientActor($request, $workspace, 'quotes.send');
        $fields = $this->emailFields($request);
        $delivery = $this->runFinanceDomain(fn () => $this->quoteEmailService->send($quote, $fields, (int) $user->id));
        $quote->load(['customer', 'items', 'deliveries.sender', 'convertedInvoice']);

        return $this->ok([
            'quote' => $this->presenter->quoteDetail($quote),
            'delivery' => $this->presenter->delivery($delivery),
        ], message: 'تم إرسال عرض السعر إلى '.$delivery->recipient);
    }

    public function pdf(Request $request, FinanceQuote $quote): mixed
    {
        $workspace = $this->clientWorkspace($this->workspaceContext);
        $this->clientActor($request, $workspace, 'quotes.view');

        return $this->runFinanceDomain(fn () => $this->pdfQuoteService->download($quote));
    }

    /**
     * @return array<string, mixed>
     */
    private function quotePayload(Request $request, int $workspaceId): array
    {
        $validated = $request->validate([
            'customer_id' => [
                'required',
                'integer',
                Rule::exists('customers', 'id')->where(fn ($query) => $query->where('workspace_id', $workspaceId)),
            ],
            'issue_date' => ['required', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'currency' => ['nullable', 'string', 'size:3'],
            'status' => ['nullable', 'in:draft,issued'],
            'notes' => ['nullable', 'string'],
            'terms' => ['nullable', 'string'],
            'tax_profile_type' => ['nullable', 'in:standard,zero_rated,exempt,out_of_scope'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tax_price_mode' => ['nullable', 'in:exclusive,inclusive'],
            'items' => ['nullable', 'array'],
            'items_json' => ['nullable', 'string'],
        ]);

        $payload = Arr::except($validated, ['items', 'items_json']);
        $payload['items'] = $this->documentItemsFromRequest($request);

        return $payload;
    }
}
