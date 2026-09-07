@php
    $workspace = request()->attributes->get('workspace');
    $navLinks = [
        ['label' => 'الكاشير', 'route' => 'workspace.pos.cashier.index', 'active' => ['workspace.pos.cashier.*']],
        ['label' => 'الطاولات', 'route' => 'workspace.pos.tables.index', 'active' => ['workspace.pos.dashboard', 'workspace.pos.tables.*']],
        ['label' => 'Menu', 'route' => 'workspace.pos.menu.index', 'active' => 'workspace.pos.menu.*'],
        ['label' => 'إدارة الأصناف', 'route' => 'workspace.pos.items.index', 'active' => 'workspace.pos.items.*'],
        ['label' => 'المطبخ', 'route' => 'workspace.pos.kitchen.index', 'active' => 'workspace.pos.kitchen.*'],
        ['label' => 'الفواتير', 'route' => 'workspace.pos.invoices.index', 'active' => 'workspace.pos.invoices.*'],
        ['label' => 'التقارير اليومية', 'route' => 'workspace.pos.reports.daily', 'active' => 'workspace.pos.reports.*'],
    ];

    if ($workspace) {
        $navLinks[] = [
            'label' => 'فتح المنيو العام ↗',
            'url' => route('menu.general', ['workspace' => $workspace->slug]),
            'target' => '_blank',
            'active' => 'menu.general',
        ];
    }
@endphp

<nav class="border-b border-slate-200 bg-white px-2 sm:px-6">
    <div class="mx-auto flex max-w-[1500px] items-center gap-1.5 overflow-x-auto py-2">
        @foreach($navLinks as $link)
            @php
                $patterns = is_array($link['active']) ? $link['active'] : [$link['active']];
                $isActive = collect($patterns)->contains(fn (string $pattern): bool => request()->routeIs($pattern));
            @endphp
            <a
                href="{{ $link['url'] ?? route($link['route']) }}"
                @if(($link['target'] ?? null) === '_blank')
                    target="_blank" rel="noopener"
                @endif
                class="{{ $isActive ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }} whitespace-nowrap rounded-lg px-2.5 py-1.5 text-xs font-semibold transition"
            >
                {{ $link['label'] }}
            </a>
        @endforeach
    </div>
</nav>
