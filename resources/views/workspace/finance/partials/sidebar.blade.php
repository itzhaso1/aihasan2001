@php
    $sections = [
        'لوحة التحكم' => [
            ['label' => 'لوحة القرار', 'route' => 'workspace.finance.dashboard', 'active' => 'workspace.finance.dashboard'],
            ['label' => 'لوحة الفوترة', 'route' => 'workspace.finance.billing.dashboard', 'active' => 'workspace.finance.billing.*'],
        ],
        'المبيعات' => [
            ['label' => 'المبيعات', 'route' => 'workspace.finance.sales.index', 'active' => 'workspace.finance.sales.*'],
            ['label' => 'الفواتير', 'route' => 'workspace.finance.invoices.index', 'active' => 'workspace.finance.invoices.*'],
            ['label' => 'كشف حساب العميل', 'route' => 'workspace.finance.statements.index', 'active' => 'workspace.finance.statements.*'],
            ['label' => 'العملاء المحتملون', 'route' => 'workspace.finance.leads.index', 'active' => 'workspace.finance.leads.*'],
            ['label' => 'العقود', 'route' => 'workspace.finance.contracts.index', 'active' => 'workspace.finance.contracts.*'],
            ['label' => 'العملاء', 'route' => 'workspace.finance.customers.index', 'active' => 'workspace.finance.customers.*'],
            ['label' => 'قوائم الأسعار', 'route' => 'workspace.finance.price-lists.index', 'active' => 'workspace.finance.price-lists.*'],
        ],
        'المشتريات والموردون' => [
            ['label' => 'فواتير الشراء', 'route' => 'workspace.finance.invoices.index', 'params' => ['type' => 'purchase'], 'active' => 'workspace.finance.invoices.*'],
            ['label' => 'أوامر الشراء', 'route' => 'workspace.finance.purchase-orders.index', 'active' => 'workspace.finance.purchase-orders.*'],
            ['label' => 'الموردون', 'route' => 'workspace.finance.suppliers.index', 'active' => 'workspace.finance.suppliers.*'],
        ],
        'المصروفات والمخزون' => [
            ['label' => 'المصروفات', 'route' => 'workspace.finance.expenses.index', 'active' => 'workspace.finance.expenses.*'],
            ['label' => 'المنتجات', 'route' => 'workspace.finance.products.index', 'active' => 'workspace.finance.products.*'],
            ['label' => 'المخزون', 'route' => 'workspace.finance.inventory.index', 'active' => 'workspace.finance.inventory.*'],
            ['label' => 'المشاريع', 'route' => 'workspace.finance.projects.index', 'active' => 'workspace.finance.projects.*'],
        ],
        'المحاسبة والضرائب' => [
            ['label' => 'لوحة المحاسبة', 'route' => 'workspace.finance.accounting.dashboard', 'active' => 'workspace.finance.accounting.*'],
            ['label' => 'السنوات والفترات', 'route' => 'workspace.finance.fiscal-years.index', 'active' => 'workspace.finance.fiscal-years.*'],
            ['label' => 'VAT', 'route' => 'workspace.finance.vat.index', 'active' => 'workspace.finance.vat.*'],
            ['label' => 'التقارير', 'route' => 'workspace.finance.reports.index', 'active' => 'workspace.finance.reports.*'],
            ['label' => 'التنبيهات', 'route' => 'workspace.finance.alerts.index', 'active' => 'workspace.finance.alerts.*'],
            ['label' => 'المساعد المالي', 'route' => 'workspace.finance.copilot.index', 'active' => 'workspace.finance.copilot.*'],
        ],
        'الرواتب والبنوك' => [
            ['label' => 'الرواتب', 'route' => 'workspace.finance.payroll.index', 'active' => 'workspace.finance.payroll.*'],
            ['label' => 'البدلات', 'route' => 'workspace.finance.allowances.index', 'active' => 'workspace.finance.allowances.*'],
            ['label' => 'الخصومات', 'route' => 'workspace.finance.deductions.index', 'active' => 'workspace.finance.deductions.*'],
            ['label' => 'المكافآت', 'route' => 'workspace.finance.bonuses.index', 'active' => 'workspace.finance.bonuses.*'],
            ['label' => 'السلف', 'route' => 'workspace.finance.salary-advances.index', 'active' => 'workspace.finance.salary-advances.*'],
            ['label' => 'موظفو المالية', 'route' => 'workspace.finance.employees.index', 'active' => 'workspace.finance.employees.*'],
            ['label' => 'الحسابات البنكية', 'route' => 'workspace.finance.banks.index', 'active' => 'workspace.finance.banks.*'],
            ['label' => 'الخزينة والتسويات', 'route' => 'workspace.finance.treasury.index', 'active' => 'workspace.finance.treasury.*'],
        ],
        'الإعدادات' => [
            ['label' => 'إعدادات الفوترة', 'route' => 'workspace.finance.settings.index', 'active' => 'workspace.finance.settings.*'],
        ],
    ];

    $sectionStates = [];
    foreach ($sections as $sectionTitle => $links) {
        $sectionKey = \Illuminate\Support\Str::slug($sectionTitle, '-');
        $hasActiveLink = false;

        foreach ($links as $link) {
            if (request()->routeIs($link['active'] ?? $link['route'])) {
                $hasActiveLink = true;
                break;
            }
        }

        $sectionStates[$sectionKey] = $hasActiveLink;
    }
@endphp

<div x-data='{ openSections: @json($sectionStates) }' class="h-full overflow-y-auto px-4 py-5">
    <div class="mb-5 border-b border-slate-200 pb-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">HASem</p>
        <h2 class="mt-1 text-xl font-extrabold text-slate-900">المالية</h2>
        <p class="mt-1 text-xs text-slate-500">وحدة فوترة مستقرة للإنتاج</p>
    </div>

    <nav class="space-y-4">
        @foreach($sections as $sectionTitle => $links)
            @php
                $sectionKey = \Illuminate\Support\Str::slug($sectionTitle, '-');
            @endphp
            <div>
                <button
                    type="button"
                    @click="openSections['{{ $sectionKey }}'] = !openSections['{{ $sectionKey }}']"
                    class="mb-2 flex w-full items-center justify-between rounded-lg px-2 py-1 text-[11px] font-bold tracking-wider text-slate-500 transition hover:bg-slate-100"
                >
                    <span>{{ $sectionTitle }}</span>
                    <svg class="h-4 w-4 transition-transform" :class="openSections['{{ $sectionKey }}'] ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div x-cloak x-show="openSections['{{ $sectionKey }}']" x-transition class="space-y-1">
                    @foreach($links as $link)
                        @php
                            $isActive = request()->routeIs($link['active'] ?? $link['route']);
                        @endphp
                        <a href="{{ route($link['route'], $link['params'] ?? []) }}"
                           class="{{ $isActive ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-700 hover:bg-slate-100' }} flex items-center justify-between rounded-lg px-3 py-2 text-sm font-medium transition">
                            <span>{{ $link['label'] }}</span>
                            @if($isActive)
                                <span class="h-1.5 w-1.5 rounded-full bg-white"></span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
    </nav>
</div>
