<?php

namespace App\Http\Controllers\Workspace\Pos;

use App\Http\Requests\Pos\StorePosOrderRequest;
use App\Models\Customer;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\PosItemCategory;
use App\Models\PosMenuItem;
use App\Services\Pos\PosOrderService;
use App\Services\Pos\PosOrderStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class CashierController extends PosBaseController
{
    public function __construct(
        private readonly PosOrderService $posOrderService,
        private readonly PosOrderStatsService $posOrderStatsService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizePos($request, 'orders.manage');

        $workspace = $this->currentWorkspace();
        $taxRate = (float) data_get($workspace->settings ?? [], 'pos.tax_rate', 0);
        $soundEnabled = (bool) data_get($workspace->settings ?? [], 'pos.new_order_sound', true);

        $items = PosMenuItem::query()
            ->with('category:id,name')
            ->where('is_active', true)
            ->when($request->integer('category_id'), fn ($query, $categoryId) => $query->where('pos_item_category_id', $categoryId))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get([
                'id',
                'pos_item_category_id',
                'name',
                'sku',
                'barcode',
                'price',
                'currency',
                'item_type',
                'size_label',
                'image_path',
            ]);

        return view('workspace.pos.cashier.index', [
            'items' => $items,
            'categories' => PosItemCategory::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'customers' => Customer::query()->orderBy('name')->limit(200)->get(['id', 'name', 'phone']),
            'tables' => DiningTable::query()->orderBy('name')->get(['id', 'name', 'status']),
            'storeOrderUrl' => route('workspace.pos.orders.store'),
            'recentMenuOrdersUrl' => route('workspace.pos.orders.recent-menu'),
            'orderStatsUrl' => route('workspace.pos.orders.channel-stats'),
            'orderChannelStats' => $this->posOrderStatsService->channelCounts(),
            'taxRate' => $taxRate,
            'soundEnabled' => $soundEnabled,
            'workspaceId' => $workspace->id,
        ]);
    }

    public function storeOrder(StorePosOrderRequest $request): JsonResponse|RedirectResponse
    {
        $this->authorizePos($request, 'orders.manage');

        try {
            $order = $this->posOrderService->createPosOrder(
                workspace: $this->currentWorkspace(),
                payload: $request->validated(),
                actor: $request->user()
            );
        } catch (RuntimeException $exception) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
            }

            return back()->withInput()->with('error', $exception->getMessage());
        }

        // Invoice is independent of order creation — print uses the order receipt.
        $printUrl = route('workspace.pos.orders.print', $order);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء الطلب بنجاح',
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'order_type' => $order->order_type,
                'invoice_id' => null,
                'print_url' => $printUrl,
                'invoice_error' => null,
            ], 201);
        }

        return back()
            ->with('success', 'تم إنشاء الطلب بنجاح.'.($order->order_number ? ' رقم الطلب: #'.$order->order_number : ''))
            ->with('print_url', $printUrl)
            ->with('order_number', $order->order_number);
    }

    /**
     * Polling fallback for new QR menu orders when realtime is unavailable.
     */
    public function recentMenuOrders(Request $request): JsonResponse
    {
        $this->authorizePos($request, 'orders.manage');

        $afterId = max(0, (int) $request->query('after_id', 0));

        $orders = Order::query()
            ->with(['table:id,name', 'items:id,order_id,product_name,quantity,total_amount'])
            ->where('source', 'qr_menu')
            ->when($afterId > 0, fn ($query) => $query->where('id', '>', $afterId))
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'table_name' => $order->table?->name,
                'dining_table_id' => $order->dining_table_id,
                'notes' => $order->notes,
                'total_amount' => (float) $order->total_amount,
                'currency' => $order->currency,
                'placed_at' => optional($order->placed_at)?->toIso8601String(),
                'items' => $order->items->map(fn ($item) => [
                    'name' => $item->product_name,
                    'quantity' => (int) $item->quantity,
                    'total_amount' => (float) $item->total_amount,
                ])->values(),
            ])
            ->values();

        return response()->json([
            'orders' => $orders,
            'latest_id' => (int) ($orders->max('id') ?? $afterId),
        ]);
    }

    public function channelStats(Request $request): JsonResponse
    {
        $this->authorizePos($request, 'orders.manage');

        return response()->json([
            'stats' => $this->posOrderStatsService->channelCounts(),
        ]);
    }
}
