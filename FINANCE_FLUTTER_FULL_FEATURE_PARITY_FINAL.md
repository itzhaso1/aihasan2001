# Finance Flutter — full feature parity final matrix

Audit cycle: Web reconnaissance → API (additive) → Flutter screens/forms/actions/nav/polish → tests → re-audit.

Laravel is the financial authority. Flutter is `apps/hasim_finance` on `/api/finance/v1`. Sales invoices: `/sales-invoices`.

Statuses: **COMPLETE** | **PARTIAL** | **MISSING** | **WEB-ONLY** | **NOT-FINANCE**

| Module | Web | API | Flutter Model | Flutter Screen | Create | Edit | View | Actions | Filters | Export | Permission | Status |
|--------|-----|-----|---------------|----------------|--------|------|------|---------|---------|--------|------------|--------|
| Decision dashboard | Yes | GET `/dashboard` + `analytics` | DashboardData | `/dashboard` | n/a | n/a | Yes | Refresh, period apply | from/to | n/a | finance.view | COMPLETE |
| Billing hub | Yes | GET `/billing` | map | `/billing` | n/a | n/a | Yes | Refresh | from/to on API | n/a | invoices.view | COMPLETE |
| Sales hub | Yes | GET `/sales` | map | `/sales` | n/a | n/a | Yes | Refresh | from/to | n/a | finance.view | COMPLETE |
| Customers | List + forms | `/customers` | CustomerRecord | list/detail/form | Yes | Yes | Yes | Open related docs | search, pagination | n/a | customers.* | COMPLETE |
| Customer statements | Yes | GET `/statements` | StatementRecord | `/statements` | n/a | n/a | Yes | PDF, CSV | customer picker, from/to | PDF/CSV | statements.view | COMPLETE |
| Quotes | Full lifecycle | `/quotes` | QuoteRecord | list/detail/form | Yes | Draft | Yes | issue, send, accept, reject, convert, cancel, delete draft, PDF | status, outcome, search | PDF | quotes.* | COMPLETE |
| Sales invoices | Full builder | `/sales-invoices` | InvoiceRecord | list/detail/form | Yes | Draft | Yes | issue, send, remind, cancel, delete draft, payment, reverse, checkout, notes, attachments, PDF, audit | lifecycle chips, search | PDF | invoices.* | COMPLETE |
| Invoice lines | Web builder | items[] | LineItem / LineDraft | line editor | Yes | Draft | Yes | add/remove, product picker | n/a | n/a | invoices.edit | COMPLETE |
| Credit/debit notes | Nested under invoice | `/credit-notes` | NoteRecord | `/notes` | Yes | n/a | Yes | issue, cancel, PDF | search | PDF | notes.* | COMPLETE |
| Payments | Index | `/payments` | PaymentRecord | list/detail | via invoice | n/a | Yes | reverse | search | n/a | payments.* | COMPLETE |
| Receipts | Index/show | `/receipts` | ReceiptRecord | list/detail | via payment | n/a | Yes | send, PDF | search | PDF | receipts.* | COMPLETE |
| Reminders | Invoice action | POST remind | n/a | invoice detail | n/a | n/a | n/a | remind | n/a | n/a | invoices.remind | COMPLETE |
| Checkout | Invoice action | GET/POST checkout | CheckoutInfo | invoice detail | n/a | n/a | URL | copy/open/refresh; **no client paid** | n/a | n/a | payments.manage | COMPLETE |
| Contracts | Full | `/contracts` | ContractRecord | list/detail/form | Yes | Draft | Yes | activate, close, cancel, PDF, add schedule, generate | status, search | PDF | contracts.* | PARTIAL — contract **file** attachments (Web download/destroy) have no client attachment resource. PDF/schedules/invoices are implemented. Required fix: additive attachment endpoints (not done; listed in WEB-ONLY for upload, not as fake completeness). |
| Billing schedules | Yes | schedule routes | BillingScheduleRecord | contract detail | Yes | n/a | Yes | activate, pause, cancel, generate | n/a | n/a | contracts.manage | COMPLETE |
| Expenses | Yes | `/expenses` | ExpenseRecord | list/detail/form | Yes | Draft | Yes | attachment, delete draft | status, search | attachment | expenses.* | COMPLETE |
| Purchases (AP) | Yes | `/purchases` | InvoiceRecord | list/detail/form | Yes | Draft | Yes | issue, cancel, PDF | lifecycle, search, supplier_id API | PDF | purchases.* | COMPLETE |
| Purchase orders | Yes | `/purchase-orders` | PurchaseOrderRecord | list/detail/form | Yes | n/a | Yes | submit, receive, bill | status, search | n/a | purchases.* | COMPLETE |
| Suppliers | Index forms | `/suppliers` | SupplierRecord | list/detail/form | Yes | Yes | Yes | n/a | search, pagination | n/a | purchases.view | COMPLETE |
| Products (Finance read) | List + sold totals | `/products` | ProductRecord | list/detail | No | No | Yes | n/a | search | n/a | finance.view | COMPLETE for Finance view |
| Product CMS | Products product | n/a | n/a | n/a | n/a | n/a | n/a | n/a | n/a | n/a | products | NOT-FINANCE |
| Inventory movements | List | `/inventory` | InventoryMovementRecord | `/inventory` | n/a | n/a | Yes | n/a | search | n/a | finance.view | COMPLETE for Finance list |
| Inventory ops / POS stock | POS/Products | n/a | n/a | n/a | n/a | n/a | n/a | n/a | n/a | n/a | POS | NOT-FINANCE |
| Projects | Index/create | `/projects` | ProjectRecord | list/detail/form | Yes | n/a | Yes | n/a | search | n/a | finance.view | COMPLETE |
| Price lists | Full | `/price-lists` | PriceListRecord | list/detail/form | Yes | Yes | Yes | items, approve, draft, cancel | search | n/a | finance.price_lists.* | COMPLETE |
| Leads | Index/convert | `/leads` | LeadRecord | list/detail/form | Yes | n/a | Yes | convert, lost | status, search | n/a | finance.view | COMPLETE |
| Reports P&L | Yes | `/reports/profit-loss` | report JSON | `/reports` | n/a | n/a | Yes | run | from/to | CSV | reports.view | COMPLETE |
| Trial balance | Yes | `/reports/trial-balance` | report JSON | `/reports` | n/a | n/a | Yes | run | from/to | CSV | reports.view | COMPLETE |
| Cash flow | Yes | `/reports/cash-flow` | report JSON | `/reports` | n/a | n/a | Yes | run | from/to | CSV | reports.view | COMPLETE |
| Balance sheet | Yes | `/reports/balance-sheet` | report JSON | `/reports` | n/a | n/a | Yes | run | from/to | CSV | reports.view | COMPLETE |
| General ledger | Yes | `/reports/general-ledger` | report JSON | `/reports` | n/a | n/a | Yes | run | from/to, account | CSV | reports.view | COMPLETE |
| AR aging | Yes | `/reports/ar-aging` | report JSON | `/reports` | n/a | n/a | Yes | run | as API | CSV | reports.view | COMPLETE |
| AP aging | Yes | `/reports/ap-aging` + `/purchases/aging` | report JSON | `/reports` | n/a | n/a | Yes | run | as API | CSV | reports.view | COMPLETE |
| Inventory valuation | Yes | `/reports/inventory-valuation` | report JSON | `/reports` | n/a | n/a | Yes | run | as API | CSV | reports.view | COMPLETE |
| VAT hub | Yes | GET `/vat` | map | `/vat` | n/a | n/a | Yes | Refresh | n/a | n/a | accounting.view | COMPLETE |
| Accounting hub (read) | Yes | GET `/accounting` | map | `/accounting` | n/a | n/a | Yes | Refresh | n/a | n/a | accounting.view | COMPLETE |
| GL journal create / COA edit | Web lists + implied future editors | none for posting | n/a | n/a | No | No | Read only | n/a | n/a | n/a | accounting.manage | WEB-ONLY |
| Fiscal years | Yes | `/fiscal-years` | FiscalYearRecord | list/detail | Yes | Yes | Yes | open/close year, periods, period status | n/a | n/a | finance.fiscal_years.* | COMPLETE |
| CSV exports | Yes | `/exports` | map | `/exports` | n/a | n/a | Yes | download datasets | n/a | CSV | finance.view | COMPLETE |
| Alerts | Yes | GET `/alerts` | list | `/alerts` | n/a | n/a | Yes | Refresh | n/a | n/a | finance.view | COMPLETE |
| Copilot | Yes | POST `/copilot/ask` | map | `/copilot` | n/a | n/a | Yes | ask | n/a | n/a | finance.view | COMPLETE |
| Banks | Yes | GET `/banks` | TreasuryAccountRecord | `/banks` | via settings | n/a | Yes | n/a | pagination | n/a | finance.view | COMPLETE |
| Treasury transfers | Yes | GET/POST treasury | map | `/treasury` | transfer | n/a | Yes | transfer | n/a | n/a | finance.view | COMPLETE |
| Bank statement matching | Yes | none on v1 client | n/a | n/a | No | No | No | n/a | n/a | n/a | finance.manage | WEB-ONLY |
| Settings company | Yes | GET/PUT `/settings` | map | `/settings` | n/a | Yes | Yes | save, create tax rate, treasury account | n/a | n/a | finance.settings | COMPLETE except logo file |
| Settings logo upload | Yes | not on v1 | n/a | n/a | No | No | No | n/a | n/a | n/a | finance.settings | WEB-ONLY |
| ZATCA secrets / keys / stamp UI | Server | stripped | n/a | read-only mode | No | No | Mode | n/a | n/a | n/a | server | WEB-ONLY |
| Search | Yes | GET `/search` | buckets | `/search` | n/a | n/a | Yes | navigate | q | n/a | finance.view | COMPLETE |
| Audit (invoice) | On invoice show | presenter `audit` | maps | invoice detail | n/a | n/a | Yes | n/a | n/a | n/a | invoices.view | COMPLETE |
| Auth / workspaces | Yes | auth + `/workspaces` | AuthState | login, Google, forgot, picker | n/a | n/a | Yes | switch, logout | n/a | n/a | Sanctum | COMPLETE |
| Navigation | Sidebar groups | n/a | `_navGroups` | FinanceShell | n/a | n/a | Yes | grouped RTL | n/a | n/a | per module | COMPLETE (payroll omitted) |
| PDF invoice/quote/receipt/note/contract/purchase/statement | Yes | `*/pdf` | bytes | details | n/a | n/a | Yes | open/share | n/a | PDF | matching view | COMPLETE |
| Payroll block | Yes in Web sidebar | none for client | n/a | n/a | No | No | No | n/a | n/a | n/a | payroll.* | NOT-FINANCE |
| POS / Booking / Inbox | Other products | n/a | n/a | n/a | No | No | No | n/a | n/a | n/a | other | NOT-FINANCE |
| Phase 10 `/invoices` | Compliance API | exists, unused by Flutter | n/a | n/a | n/a | n/a | n/a | n/a | n/a | n/a | e-invoice | WEB-ONLY / separate contract |
| Cashbox placeholder | Placeholder | none | n/a | n/a | n/a | n/a | n/a | n/a | n/a | n/a | n/a | WEB-ONLY (not a real feature) |
| Quote create attachments | Web multipart | quote JSON save | n/a | compose JSON | No file | No | n/a | n/a | n/a | n/a | quotes.edit | PARTIAL — missing create-time file picker. Why: quote client save is JSON; invoice/expense attachments exist. Required fix: additive quote attachment endpoints. Not labeled WEB-ONLY as a finished product choice except as documented optional later work. **Not implemented this cycle for quotes; invoices cover the attachment pattern.** |
| Recurring expense engine | Flag only | `is_recurring` | flag | form switch | flag | flag | Yes | n/a | n/a | n/a | expenses | COMPLETE for backend (flag). Scheduler does not exist. |
| Dashboard Blade charts | Decorative | analytics series JSON | analytics.series | lists/KPIs | n/a | n/a | Yes | n/a | period | n/a | finance.view | COMPLETE for numbers; chart widgets WEB-ONLY presentation |

## PARTIAL items — exact gaps

### Contracts file attachments
- **Missing:** upload/download/delete of contract files that Web exposes on `contracts.attachments.*`.
- **Why:** Finance v1 has contract PDF and schedule APIs, not a contract attachment resource.
- **Required fix:** additive `POST/GET/DELETE /contracts/{id}/attachments` reusing `ContractService`.
- **Fixed this cycle?** No. Not labeled COMPLETE. See WEB-ONLY table as later additive work.

### Quote create-time attachments
- **Missing:** picking files while creating a quote (Web `enctype=multipart` on quote store).
- **Why:** Flutter quote save is JSON; issued quotes lock like Web.
- **Required fix:** additive quote attachment endpoints (same pattern as sales invoices).
- **Fixed this cycle?** No. Invoice and expense attachments **were** implemented.

These two PARTIAL rows are the only unfinished Finance **user** attachment surfaces. They are not hidden as WEB-ONLY completeness.

## Tests / visual QA

| Check | Result |
|---|---|
| `flutter analyze` (`apps/hasim_finance`) | No issues |
| `flutter test` | 64 passed |
| `FinanceFlutterFeatureParityTest` + data parity + client API | passed |
| Additional Finance + checkout PHPUnit | 51 passed |
| Pint | dirty files formatted |
| Visual QA | Widget tests at 1400×2400 covering dashboard (including analytics hero), sales hub, invoice list/detail/compose, quotes, statements, reports, products, suppliers, alerts, copilot, permission gate. Flutter web desktop was not launched in this environment (no interactive Finance session against a live workspace). Remaining visual risk: live browser Blob download and Google Sign-In on a real device. |

## Remaining limitations

1. Contract file attachments (not PDF) — PARTIAL, see above.
2. Quote create-time file attachments — PARTIAL, see above.
3. Company logo file upload — WEB-ONLY.
4. Bank statement matching — WEB-ONLY.
5. Journal/COA editors — WEB-ONLY.
6. Payroll — NOT-FINANCE.
7. Recurring expenses remain a boolean (Laravel has no scheduler).
8. Dashboard analytics in Flutter exposes **from/to** and a searchable **customer** filter. Product/project/lifecycle/payment-method query params exist on `GET /dashboard` and can be wired as extra selectors later without new backend work.
9. No live browser session against a production-like workspace in this agent environment.

## Product boundaries respected

- No POS/Cashier, Booking, Inbox, WhatsApp chat, Instagram, Messenger, or generic AI shell.
- No dummy Orders.
- No second tax/accounting/auth engine.
- No local ZATCA key management.
- No client-side payment settlement.
