<?php

namespace App\Http\Controllers\Api\Finance\Concerns;

use App\Exceptions\Api\ApiErrorCode;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Tenancy\WorkspaceContext;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

trait AuthorizesFinanceApi
{
    protected function requireWorkspace(WorkspaceContext $workspaceContext): Workspace
    {
        $workspace = $workspaceContext->workspace();
        if (! $workspace) {
            throw new HttpResponseException($this->fail(
                'Workspace context is required.',
                ApiErrorCode::Forbidden,
                403,
            ));
        }

        return $workspace;
    }

    protected function authorizeFinanceApi(Request $request, Workspace $workspace, string $permission): User
    {
        /** @var User|null $user */
        $user = $request->user();
        if (! $user) {
            throw new HttpResponseException($this->fail(
                'Unauthenticated.',
                ApiErrorCode::Unauthorized,
                401,
            ));
        }

        if ($user->can($permission) || $user->can('workspace.manage')) {
            return $user;
        }

        if (! $this->isElevatedFinanceMember($workspace, $user)) {
            throw new HttpResponseException($this->fail(
                'You are not allowed to access this financial resource.',
                ApiErrorCode::Forbidden,
                403,
            ));
        }

        return $user;
    }

    /**
     * Matches Web FinanceBaseController::authorizeFinance.
     *
     * Do not tighten this to Spatie keys only: workspace owners/admins/managers
     * operate Finance without every permission row assigned. Removing elevation
     * would lock legitimate owners out of billing. Cashiers are not elevated.
     */
    protected function isElevatedFinanceMember(Workspace $workspace, User $user): bool
    {
        return $workspace->users()
            ->where('users.id', $user->id)
            ->wherePivot('status', 'active')
            ->wherePivotIn('membership_role', ['owner', 'admin', 'manager'])
            ->exists();
    }

    /**
     * Matches financePermissionMap['accounting.view'] so invoice journals
     * are not hidden from workspace owners who operate Finance without a
     * dedicated accounting.view Spatie row.
     */
    protected function mayViewAccounting(?User $user, Workspace $workspace): bool
    {
        if (! $user) {
            return false;
        }

        return $user->can('accounting.view')
            || $user->can('workspace.manage')
            || $this->isElevatedFinanceMember($workspace, $user);
    }

    /**
     * UI gating map. Laravel still authorizes every mutation.
     * Owner/admin/manager match Web FinanceBaseController (not cashier agent elevation).
     *
     * @return array<string, bool>
     */
    protected function financePermissionMap(User $user, Workspace $workspace): array
    {
        $elevated = $this->isElevatedFinanceMember($workspace, $user);
        $can = fn (string $permission): bool => $user->can($permission)
            || $user->can('workspace.manage')
            || $elevated;

        return [
            'finance.view' => $can('finance.view'),
            'finance.settings' => $can('finance.settings'),
            'customers.view' => $can('finance.view') || $can('customers.manage') || $can('invoices.view'),
            'customers.create' => $can('customers.manage'),
            'customers.edit' => $can('customers.manage') || $can('invoices.create') || $can('finance.manage'),
            'customers.delete' => $can('customers.manage'),
            'quotes.view' => $can('quotes.view'),
            'quotes.create' => $can('quotes.create'),
            'quotes.edit' => $can('quotes.edit'),
            'quotes.issue' => $can('quotes.issue'),
            'quotes.send' => $can('quotes.send'),
            'quotes.accept' => $can('quotes.accept'),
            'quotes.reject' => $can('quotes.reject'),
            'quotes.convert' => $can('quotes.convert'),
            'quotes.cancel' => $can('quotes.cancel'),
            'quotes.delete' => $can('quotes.delete'),
            'invoices.view' => $can('invoices.view'),
            'invoices.create' => $can('invoices.create'),
            'invoices.edit' => $can('invoices.edit'),
            'invoices.issue' => $can('invoices.issue'),
            'invoices.send' => $can('invoices.send'),
            'invoices.remind' => $can('invoices.remind'),
            'invoices.cancel' => $can('invoices.cancel'),
            'invoices.delete' => $can('invoices.delete'),
            'invoices.credit' => $can('invoices.credit'),
            'invoices.reverse_payment' => $can('invoices.reverse_payment'),
            'payments.view' => $can('payments.view'),
            'payments.manage' => $can('payments.manage'),
            'receipts.view' => $can('receipts.view'),
            'receipts.send' => $can('receipts.send'),
            'statements.view' => $can('invoices.view'),
            'notes.view' => $can('invoices.view'),
            'notes.create' => $can('invoices.credit'),
            'notes.issue' => $can('invoices.credit'),
            'notes.cancel' => $can('invoices.cancel'),
            'contracts.view' => $can('contracts.view'),
            'contracts.create' => $can('contracts.manage'),
            'contracts.edit' => $can('contracts.manage'),
            'expenses.view' => $can('expenses.view'),
            'expenses.create' => $can('expenses.create'),
            'expenses.edit' => $can('expenses.edit'),
            'expenses.delete' => $can('expenses.edit'),
            'purchases.view' => $can('purchases.view'),
            'purchases.create' => $can('purchases.manage'),
            'purchases.edit' => $can('purchases.manage'),
            'reports.view' => $can('reports.view'),
            'exports.invoices' => $can('invoices.view'),
            'exports.payments' => $can('payments.view'),
            'exports.customers' => $can('invoices.view'),
            'exports.expenses' => $can('expenses.view'),
            'exports.quotes' => $can('quotes.view'),
            'accounting.view' => $can('accounting.view'),
            'accounting.manage' => $can('accounting.manage'),
            'finance.manage' => $can('finance.manage'),
            'finance.price_lists.view' => $can('finance.price_lists.view'),
            'finance.price_lists.manage' => $can('finance.price_lists.manage'),
            'finance.fiscal_years.view' => $can('finance.fiscal_years.view'),
            'finance.fiscal_years.manage' => $can('finance.fiscal_years.manage'),
            'payroll.view' => $can('payroll.view'),
            'payroll.manage' => $can('payroll.manage'),
            'finance.adjustments.view' => $can('finance.adjustments.view'),
            'finance.adjustments.manage' => $can('finance.adjustments.manage'),
            'finance.salary_advances.view' => $can('finance.salary_advances.view'),
            'finance.salary_advances.manage' => $can('finance.salary_advances.manage'),
        ];
    }
}
