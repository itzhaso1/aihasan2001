<?php

namespace App\Services\Finance;

use App\Models\Finance\FinanceExpense;
use App\Models\Finance\FinanceInvoice;

class ReportService
{
    /**
     * @return array<string,mixed>
     */
    public function summary(string $from, string $to): array
    {
        $salesSummary = FinanceInvoice::query()
            ->where('type', 'sales')
            ->whereIssued()
            ->whereBetween('issue_date', [$from, $to])
            ->selectRaw('COUNT(*) as invoices_count')
            ->selectRaw('COALESCE(SUM(total), 0) as total_sales')
            ->selectRaw('COALESCE(SUM(amount_paid), 0) as total_paid')
            ->selectRaw('COALESCE(SUM(amount_due), 0) as total_due')
            ->first();

        $purchaseSummary = FinanceInvoice::query()
            ->where('type', 'purchase')
            ->whereIssued()
            ->whereBetween('issue_date', [$from, $to])
            ->selectRaw('COUNT(*) as invoices_count')
            ->selectRaw('COALESCE(SUM(total), 0) as total_purchases')
            ->selectRaw('COALESCE(SUM(amount_due), 0) as total_due')
            ->first();

        $expenseSummary = FinanceExpense::query()
            ->whereBetween('expense_date', [$from, $to])
            ->selectRaw('COUNT(*) as expenses_count')
            ->selectRaw('COALESCE(SUM(total), 0) as total_expenses')
            ->selectRaw('COALESCE(SUM(tax_amount), 0) as total_vat')
            ->first();

        $salesByCustomer = FinanceInvoice::query()
            ->where('type', 'sales')
            ->whereIssued()
            ->whereBetween('issue_date', [$from, $to])
            ->leftJoin('customers', function ($join): void {
                $join->on('customers.id', '=', 'finance_invoices.customer_id')
                    ->on('customers.workspace_id', '=', 'finance_invoices.workspace_id');
            })
            ->groupBy('customers.id', 'customers.name', 'finance_invoices.customer_name')
            ->selectRaw("COALESCE(customers.name, finance_invoices.customer_name, 'عميل غير محدد') as customer_name")
            ->selectRaw('COALESCE(SUM(finance_invoices.total), 0) as total')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $expensesByCategory = FinanceExpense::query()
            ->whereBetween('expense_date', [$from, $to])
            ->leftJoin('finance_expense_categories', function ($join): void {
                $join->on('finance_expense_categories.id', '=', 'finance_expenses.category_id')
                    ->on('finance_expense_categories.workspace_id', '=', 'finance_expenses.workspace_id');
            })
            ->groupBy('finance_expense_categories.id', 'finance_expense_categories.name')
            ->selectRaw("COALESCE(finance_expense_categories.name, 'غير مصنف') as category_name")
            ->selectRaw('COALESCE(SUM(finance_expenses.total), 0) as total')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $outputVat = (float) FinanceInvoice::query()
            ->where('type', 'sales')
            ->whereIssued()
            ->whereBetween('issue_date', [$from, $to])
            ->sum('tax_amount');
        $inputVatPurchase = (float) FinanceInvoice::query()
            ->where('type', 'purchase')
            ->whereIssued()
            ->whereBetween('issue_date', [$from, $to])
            ->sum('tax_amount');
        $inputVatExpense = (float) FinanceExpense::query()
            ->whereBetween('expense_date', [$from, $to])
            ->sum('tax_amount');
        $inputVat = $inputVatPurchase + $inputVatExpense;

        return [
            'salesSummary' => $salesSummary,
            'purchaseSummary' => $purchaseSummary,
            'expenseSummary' => $expenseSummary,
            'salesByCustomer' => $salesByCustomer,
            'expensesByCategory' => $expensesByCategory,
            'vat' => [
                'output' => round($outputVat, 2),
                'input' => round($inputVat, 2),
                'net' => round($outputVat - $inputVat, 2),
            ],
        ];
    }
}
