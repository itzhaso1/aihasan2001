<?php

namespace App\Http\Controllers\Workspace\Finance;

use App\Models\Finance\FinanceAccount;
use App\Services\Finance\FinanceAnalyticsService;
use App\Services\Finance\LedgerReportService;
use App\Services\Finance\PeriodComparisonService;
use App\Services\Finance\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ReportController extends FinanceBaseController
{
    public function __construct(
        private readonly ReportService $reportService,
        private readonly LedgerReportService $ledgerReportService,
        private readonly PeriodComparisonService $periodComparisonService,
        private readonly FinanceAnalyticsService $financeAnalyticsService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeFinance($request, 'reports.view');

        $from = $this->resolveDate($request->input('from'), now()->startOfMonth())->toDateString();
        $to = $this->resolveDate($request->input('to'), now()->endOfMonth())->toDateString();
        $summary = $this->reportService->summary($from, $to);
        $workspaceId = (int) $this->currentWorkspace()->id;

        return view('workspace.finance.reports.index', [
            'from' => $from,
            'to' => $to,
            'profitAndLoss' => $this->ledgerReportService->profitAndLoss($workspaceId, $from, $to),
            'trialBalance' => $this->ledgerReportService->trialBalance($workspaceId, $to),
            'cashFlow' => $this->ledgerReportService->cashFlow($workspaceId, $from, $to),
            'periods' => $this->periodComparisonService->compare($workspaceId),
            'analytics' => $this->financeAnalyticsService->dashboard($workspaceId, [
                'from' => $from,
                'to' => $to,
            ]),
            ...$summary,
        ]);
    }

    public function show(Request $request, string $report): View
    {
        $this->authorizeFinance($request, 'reports.view');
        $workspaceId = (int) $this->currentWorkspace()->id;
        $from = $this->resolveDate($request->input('from'), now()->startOfMonth())->toDateString();
        $to = $this->resolveDate($request->input('to'), now()->endOfMonth())->toDateString();

        $data = match ($report) {
            'profit-loss' => ['profitAndLoss' => $this->ledgerReportService->profitAndLoss($workspaceId, $from, $to)],
            'balance-sheet' => ['balanceSheet' => $this->ledgerReportService->balanceSheet($workspaceId, $to)],
            'trial-balance' => ['trialBalance' => $this->ledgerReportService->trialBalance($workspaceId, $to)],
            'general-ledger' => [
                'generalLedger' => $this->ledgerReportService->generalLedger(
                    $workspaceId,
                    $request->integer('account_id') ?: null,
                    $from,
                    $to
                ),
                'accounts' => FinanceAccount::query()->orderBy('code')->get(['id', 'code', 'name']),
            ],
            'ar-aging' => ['aging' => $this->ledgerReportService->aging($workspaceId, 'sales', $to)],
            'ap-aging' => ['aging' => $this->ledgerReportService->aging($workspaceId, 'purchase', $to)],
            'cash-flow' => ['cashFlow' => $this->ledgerReportService->cashFlow($workspaceId, $from, $to)],
            'inventory-valuation' => ['inventoryValuation' => $this->ledgerReportService->inventoryValuation($workspaceId)],
            default => abort(404),
        };

        return view('workspace.finance.reports.show', [
            'report' => $report,
            'from' => $from,
            'to' => $to,
            ...$data,
        ]);
    }

    private function resolveDate(?string $value, Carbon $fallback): Carbon
    {
        if (! $value) {
            return $fallback;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return $fallback;
        }
    }
}
