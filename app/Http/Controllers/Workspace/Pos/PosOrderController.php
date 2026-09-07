<?php

namespace App\Http\Controllers\Workspace\Pos;

use App\Http\Requests\Pos\UpdatePosOrderStatusRequest;
use App\Http\Requests\Pos\UpdateTableOrderRequest;
use App\Models\Order;
use App\Services\Pos\PosOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class PosOrderController extends PosBaseController
{
    public function __construct(
        private readonly PosOrderService $posOrderService,
    ) {}

    public function running(Request $request): View
    {
        $this->authorizePos($request, 'orders.manage');

        $orders = Order::query()
            ->with(['table:id,name', 'tableSession:id,dining_table_id,opened_at,status', 'items'])
            ->whereIn('source', ['pos', 'qr_menu'])
            ->where(function ($query): void {
                $query->whereIn('pos_status', ['new', 'accepted', 'preparing', 'ready', 'delivered'])
                    ->orWhere(function ($inner): void {
                        $inner->whereNull('table_session_id')
                            ->where('pos_status', 'completed');
                    });
            })
            ->whereNull('pos_cashier_invoice_id')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('workspace.pos.orders.running', [
            'orders' => $orders,
            'posStatuses' => $this->posStatusLabels(),
        ]);
    }

    public function updateStatus(UpdatePosOrderStatusRequest $request, Order $order): RedirectResponse
    {
        $this->authorizePos($request, 'orders.manage');
        $this->authorize('update', $order);
        $this->ensurePosOrder($order);

        try {
            $this->posOrderService->updatePosStatus($order, $request->string('pos_status')->toString(), $request->user());
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'تم تحديث حالة الطلب.');
    }

    public function updateItems(UpdateTableOrderRequest $request, Order $order): RedirectResponse
    {
        $this->authorizePos($request, 'orders.manage');
        $this->authorize('update', $order);
        $this->ensurePosOrder($order);

        try {
            $this->posOrderService->updateOrderItems($order, $request->validated());
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'تم تعديل الفاتورة داخل الجلسة.');
    }

    public function createInvoice(Request $request, Order $order): RedirectResponse
    {
        $this->authorizePos($request, 'orders.manage');
        $this->authorize('update', $order);
        $this->ensurePosOrder($order);

        try {
            $invoice = $this->posOrderService->createInvoiceFromOrder($order, (int) $request->user()?->id);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('workspace.pos.invoices.show', $invoice)->with('success', 'تم إنشاء فاتورة كاشير بنجاح.');
    }

    public function createPaymentLink(Request $request, Order $order): RedirectResponse
    {
        $this->authorizePos($request, 'orders.manage');
        $this->authorize('update', $order);
        $this->ensurePosOrder($order);

        try {
            $payment = $this->posOrderService->createPaymentLinkForOrder($order);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'تم إنشاء رابط الدفع.')->with('payment_link', $payment->payment_link);
    }

    public function printOrder(Request $request, Order $order): View
    {
        $this->authorizePos($request, 'orders.manage');
        $this->authorize('view', $order);
        $this->ensurePosOrder($order);

        return view('workspace.pos.orders.print-order', [
            'order' => $order->load(['items', 'table', 'tableSession', 'customer']),
        ]);
    }

    private function ensurePosOrder(Order $order): void
    {
        abort_unless(in_array($order->source, ['pos', 'qr_menu'], true), 404);
    }

    /**
     * @return array<string,string>
     */
    private function posStatusLabels(): array
    {
        return [
            'new' => 'جديد',
            'accepted' => 'مقبول',
            'preparing' => 'قيد التحضير',
            'ready' => 'جاهز',
            'delivered' => 'تم التسليم',
            'completed' => 'مكتمل',
            'cancelled' => 'ملغي',
        ];
    }
}
