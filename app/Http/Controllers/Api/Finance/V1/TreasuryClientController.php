<?php

namespace App\Http\Controllers\Api\Finance\V1;

use App\Http\Controllers\Api\Finance\Concerns\HandlesFinanceClient;
use App\Http\Controllers\Api\Finance\FinanceApiController;
use App\Models\Finance\FinanceTreasuryAccount;
use App\Models\Finance\FinanceTreasuryTransfer;
use App\Services\Finance\Api\FinanceClientPresenter;
use App\Services\Finance\TreasuryTransferService;
use App\Support\Tenancy\WorkspaceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TreasuryClientController extends FinanceApiController
{
    use HandlesFinanceClient;

    public function __construct(
        private readonly WorkspaceContext $workspaceContext,
        private readonly TreasuryTransferService $treasuryTransferService,
        private readonly FinanceClientPresenter $presenter,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $workspace = $this->clientWorkspace($this->workspaceContext);
        $this->clientActor($request, $workspace, 'finance.view');

        $accounts = FinanceTreasuryAccount::query()->orderBy('type')->orderBy('name')->get();
        $transfers = FinanceTreasuryTransfer::query()
            ->with(['fromAccount', 'toAccount'])
            ->latest('id')
            ->limit(20)
            ->get();

        return $this->ok([
            'accounts' => $accounts->map(fn (FinanceTreasuryAccount $account) => $this->presenter->treasuryAccount($account))->values()->all(),
            'transfers' => $transfers->map(fn (FinanceTreasuryTransfer $transfer) => $this->presenter->treasuryTransfer($transfer))->values()->all(),
        ]);
    }

    public function transfer(Request $request): JsonResponse
    {
        $workspace = $this->clientWorkspace($this->workspaceContext);
        $this->clientActor($request, $workspace, 'accounting.manage');
        $validated = $request->validate([
            'from_treasury_account_id' => [
                'required',
                'integer',
                Rule::exists('finance_treasury_accounts', 'id')->where(fn ($query) => $query->where('workspace_id', $workspace->id)),
            ],
            'to_treasury_account_id' => [
                'required',
                'integer',
                'different:from_treasury_account_id',
                Rule::exists('finance_treasury_accounts', 'id')->where(fn ($query) => $query->where('workspace_id', $workspace->id)),
            ],
            'amount' => ['required', 'numeric', 'gt:0'],
            'transfer_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $transfer = $this->runFinanceDomain(
            fn () => $this->treasuryTransferService->transfer((int) $workspace->id, $validated, (int) $request->user()?->id)
        );

        return $this->ok(
            $this->presenter->treasuryTransfer($transfer->load(['fromAccount', 'toAccount'])),
            message: 'تم تنفيذ التحويل وترحيله محاسبيًا.',
            status: 201,
        );
    }
}
