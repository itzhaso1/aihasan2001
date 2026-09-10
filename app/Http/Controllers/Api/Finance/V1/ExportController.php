<?php

namespace App\Http\Controllers\Api\Finance\V1;

use App\Http\Controllers\Api\Finance\Concerns\HandlesFinanceClient;
use App\Http\Controllers\Api\Finance\FinanceApiController;
use App\Services\Finance\FinanceBootstrapService;
use App\Services\Finance\FinanceExportService;
use App\Support\Tenancy\WorkspaceContext;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends FinanceApiController
{
    use HandlesFinanceClient;

    public function __construct(
        private readonly WorkspaceContext $workspaceContext,
        private readonly FinanceBootstrapService $financeBootstrapService,
        private readonly FinanceExportService $financeExportService,
    ) {}

    public function download(Request $request, string $dataset): StreamedResponse
    {
        $workspace = $this->clientWorkspace($this->workspaceContext);
        $this->financeBootstrapService->ensureWorkspaceFinanceSetup($workspace);

        $permission = match ($dataset) {
            'invoices', 'customers' => 'invoices.view',
            'payments' => 'payments.view',
            'expenses' => 'expenses.view',
            'quotes' => 'quotes.view',
            default => abort(404),
        };
        $this->clientActor($request, $workspace, $permission);

        return match ($dataset) {
            'invoices' => $this->financeExportService->invoices(
                $workspace,
                in_array($request->string('type')->toString(), ['sales', 'purchase'], true)
                    ? $request->string('type')->toString()
                    : null
            ),
            'payments' => $this->financeExportService->payments($workspace),
            'customers' => $this->financeExportService->customers($workspace),
            'expenses' => $this->financeExportService->expenses($workspace),
            'quotes' => $this->financeExportService->quotes($workspace),
            default => abort(404),
        };
    }
}
