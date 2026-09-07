<?php

namespace App\Http\Controllers\Workspace\Finance;

use App\Models\Finance\FinanceCreditNote;
use App\Models\Finance\FinanceInvoice;
use App\Services\Finance\CreditNoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class CreditNoteController extends FinanceBaseController
{
    public function __construct(
        private readonly CreditNoteService $creditNoteService,
    ) {}

    public function create(Request $request, FinanceInvoice $invoice): View
    {
        $this->authorizeFinance($request, 'invoices.create');
        $this->assertSameWorkspace($invoice->workspace_id);

        return view('workspace.finance.credit-notes.create', [
            'invoice' => $invoice->load('items'),
            'type' => $request->string('type')->toString() === 'debit' ? 'debit' : 'credit',
        ]);
    }

    public function store(Request $request, FinanceInvoice $invoice): RedirectResponse
    {
        $this->authorizeFinance($request, 'invoices.create');
        $this->assertSameWorkspace($invoice->workspace_id);

        $validated = $request->validate([
            'type' => ['required', 'in:credit,debit'],
            'reason' => ['required', 'string', 'max:255'],
            'issue_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'status' => ['nullable', 'in:draft,issued'],
            'items_json' => ['required', 'string'],
        ]);

        $items = json_decode($validated['items_json'], true);
        if (! is_array($items) || $items === []) {
            return back()->withInput()->withErrors(['items_json' => 'أضف بنداً واحداً على الأقل.']);
        }

        try {
            $note = $this->creditNoteService->create(
                $this->currentWorkspace(),
                $invoice,
                [
                    ...$validated,
                    'items' => $items,
                    'tax_profile_type' => $invoice->tax_profile_type,
                    'tax_rate' => $invoice->tax_rate,
                ],
                (int) $request->user()?->id
            );
        } catch (RuntimeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->route('workspace.finance.invoices.show', $invoice)
            ->with('success', 'تم إنشاء الإشعار '.$note->note_number.'.');
    }

    public function issue(Request $request, FinanceInvoice $invoice, FinanceCreditNote $creditNote): RedirectResponse
    {
        $this->authorizeFinance($request, 'invoices.create');
        $this->assertSameWorkspace($invoice->workspace_id);
        abort_unless((int) $creditNote->invoice_id === (int) $invoice->id, 404);

        try {
            $this->creditNoteService->issue($creditNote, (int) $request->user()?->id);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('workspace.finance.invoices.show', $invoice)->with('success', 'تم إصدار الإشعار وترحيله محاسبياً.');
    }

    public function cancel(Request $request, FinanceInvoice $invoice, FinanceCreditNote $creditNote): RedirectResponse
    {
        $this->authorizeFinance($request, 'invoices.cancel');
        $this->assertSameWorkspace($invoice->workspace_id);
        abort_unless((int) $creditNote->invoice_id === (int) $invoice->id, 404);

        try {
            $this->creditNoteService->cancel($creditNote, (int) $request->user()?->id);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('workspace.finance.invoices.show', $invoice)->with('success', 'تم إلغاء الإشعار.');
    }
}
