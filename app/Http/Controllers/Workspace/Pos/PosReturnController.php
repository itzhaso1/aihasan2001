<?php

namespace App\Http\Controllers\Workspace\Pos;

use App\Models\Order;
use App\Models\PosOrderReturn;
use App\Services\Pos\PosReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class PosReturnController extends PosBaseController
{
    public function __construct(
        private readonly PosReturnService $posReturnService,
    ) {}

    public function create(Request $request, Order $order): View
    {
        $this->authorizePos($request, 'orders.manage');
        $this->authorize('view', $order);
        abort_unless(in_array($order->source, ['pos', 'qr_menu'], true), 404);

        return view('workspace.pos.returns.create', [
            'order' => $order->load(['items', 'table', 'customer']),
        ]);
    }

    public function store(Request $request, Order $order): RedirectResponse
    {
        $this->authorizePos($request, 'orders.manage');
        $this->authorize('update', $order);
        abort_unless(in_array($order->source, ['pos', 'qr_menu'], true), 404);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.amount' => ['nullable', 'numeric', 'min:0'],
            'mark_refunded' => ['nullable', 'boolean'],
        ]);

        try {
            $return = $this->posReturnService->createReturn(
                $this->currentWorkspace(),
                $order,
                $data,
                $request->user()
            );

            if ($request->boolean('mark_refunded')) {
                $this->posReturnService->markRefunded($return, $request->user());
            }
        } catch (RuntimeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('workspace.pos.orders.print', $order)
            ->with('success', 'تم تسجيل مرتجع POS.');
    }

    public function markRefunded(Request $request, PosOrderReturn $return): RedirectResponse
    {
        $this->authorizePos($request, 'orders.manage');
        abort_unless((int) $return->workspace_id === (int) $this->currentWorkspace()->id, 404);

        try {
            $this->posReturnService->markRefunded($return, $request->user());
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'تم تحديث حالة المرتجع إلى مسترجع.');
    }
}
