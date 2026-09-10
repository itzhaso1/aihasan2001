class FinancePermissions {
  const FinancePermissions(this._raw);

  final Map<String, bool> _raw;

  bool can(String key) => _raw[key] == true;

  bool get customersView => can('customers.view');
  bool get customersCreate => can('customers.create') || can('customers.edit');
  bool get quotesView => can('quotes.view');
  bool get quotesCreate => can('quotes.create');
  bool get invoicesView => can('invoices.view');
  bool get invoicesCreate => can('invoices.create');
  bool get paymentsView => can('payments.view');
  bool get paymentsManage => can('payments.manage');
  bool get receiptsView => can('receipts.view');
  bool get statementsView => can('statements.view') || can('invoices.view');
  bool get notesView => can('notes.view') || can('invoices.view');
  bool get contractsView => can('contracts.view');
  bool get expensesView => can('expenses.view');
  bool get purchasesView => can('purchases.view');
  bool get reportsView => can('reports.view');
  bool get settings => can('finance.settings');
  bool get financeView => can('finance.view');

  FinancePermissions copyWith(Map<String, bool> next) => FinancePermissions(next);
}
