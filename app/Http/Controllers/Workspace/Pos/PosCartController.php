<?php

namespace App\Http\Controllers\Workspace\Pos;

use App\Services\Pos\PosCartService;
use App\Services\Pos\PosOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PosCartController extends PosBaseController
{
    public function __construct(
        private readonly PosCartService $posCartService,
        private readonly PosOrderService $posOrderService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $this->authorizePos($request, 'orders.manage');

        return response()->json([
            'cart' => $this->posCartService->summary($this->currentWorkspace()),
        ]);
    }

    public function addItem(Request $request): JsonResponse
    {
        $this->authorizePos($request, 'orders.manage');

        $data = $request->validate([
            'pos_menu_item_id' => ['required', 'integer'],
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $summary = $this->posCartService->addItem(
                $this->currentWorkspace(),
                (int) $data['pos_menu_item_id'],
                (int) ($data['quantity'] ?? 1)
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['cart' => $summary]);
    }

    public function updateItem(Request $request, string $key): JsonResponse
    {
        $this->authorizePos($request, 'orders.manage');

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:0'],
        ]);

        try {
            $summary = $this->posCartService->updateQty(
                $this->currentWorkspace(),
                $key,
                (int) $data['quantity']
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['cart' => $summary]);
    }

    public function removeItem(Request $request, string $key): JsonResponse
    {
        $this->authorizePos($request, 'orders.manage');

        $summary = $this->posCartService->removeItem($this->currentWorkspace(), $key);

        return response()->json(['cart' => $summary]);
    }

    public function updateMeta(Request $request): JsonResponse
    {
        $this->authorizePos($request, 'orders.manage');

        $data = $request->validate([
            'customer_id' => ['nullable', 'integer'],
            'dining_table_id' => ['nullable', 'integer'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $summary = $this->posCartService->setMeta($this->currentWorkspace(), $data);

        return response()->json(['cart' => $summary]);
    }

    public function clear(Request $request): JsonResponse
    {
        $this->authorizePos($request, 'orders.manage');
        $this->posCartService->clear($this->currentWorkspace());

        return response()->json([
            'cart' => $this->posCartService->summary($this->currentWorkspace()),
        ]);
    }

    public function checkout(Request $request): JsonResponse|RedirectResponse
    {
        $this->authorizePos($request, 'orders.manage');

        $data = $request->validate([
            'customer_id' => ['nullable', 'integer'],
            'dining_table_id' => ['nullable', 'integer'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($data !== []) {
            $this->posCartService->setMeta($this->currentWorkspace(), $data);
        }

        try {
            $order = $this->posCartService->checkout($this->currentWorkspace(), $request->user());
        } catch (RuntimeException $exception) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], 422);
            }

            return back()->withInput()->with('error', $exception->getMessage());
        }

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
                'redirect' => null,
            ], 201);
        }

        return back()
            ->with('success', 'تم إنشاء الطلب بنجاح.'.($order->order_number ? ' رقم الطلب: #'.$order->order_number : ''))
            ->with('print_url', $printUrl)
            ->with('order_number', $order->order_number);
    }
}
