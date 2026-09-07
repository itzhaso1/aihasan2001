<?php

namespace App\Http\Controllers\Workspace\Pos;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PosKitchenController extends PosBaseController
{
    public function index(Request $request): View
    {
        $this->authorizePos($request, 'orders.manage');

        $orders = Order::query()
            ->with(['table:id,name', 'items'])
            ->whereIn('source', ['pos', 'qr_menu'])
            ->whereNotNull('dining_table_id')
            ->whereIn('pos_status', ['new', 'accepted', 'preparing', 'ready'])
            ->whereNull('pos_cashier_invoice_id')
            ->orderBy('dining_table_id')
            ->orderBy('id')
            ->get();

        return view('workspace.pos.kitchen.index', [
            'orders' => $orders,
            'posStatuses' => [
                'new' => 'جديد',
                'accepted' => 'تم القبول',
                'preparing' => 'قيد التجهيز',
                'ready' => 'جاهز',
            ],
        ]);
    }
}
