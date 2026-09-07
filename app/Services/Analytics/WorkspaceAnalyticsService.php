<?php

namespace App\Services\Analytics;

use App\Models\Appointment\AppointmentBooking;
use App\Models\Appointment\AppointmentService as AppointmentServiceModel;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Workspace;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class WorkspaceAnalyticsService
{
    /**
     * @return array{
     *     from:string,
     *     to:string,
     *     revenue:float,
     *     bookings_count:int,
     *     orders_count:int,
     *     customers_new:int,
     *     top_services:array<int, array{id:int|null,name:string,bookings:int}>,
     *     top_products:array<int, array{name:string,quantity:float,revenue:float}>,
     *     payment_status_breakdown:array<string, int>
     * }
     */
    public function summary(Workspace $workspace, CarbonInterface|string $from, CarbonInterface|string $to): array
    {
        $fromAt = Carbon::parse($from)->startOfDay();
        $toAt = Carbon::parse($to)->endOfDay();
        $workspaceId = (int) $workspace->id;

        $revenue = (float) Payment::withoutGlobalScopes()
            ->where('workspace_id', $workspaceId)
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$fromAt, $toAt])
            ->sum('amount');

        $bookingsCount = (int) AppointmentBooking::withoutGlobalScopes()
            ->where('workspace_id', $workspaceId)
            ->whereBetween('starts_at', [$fromAt, $toAt])
            ->whereNull('deleted_at')
            ->count();

        $ordersCount = (int) Order::withoutGlobalScopes()
            ->where('workspace_id', $workspaceId)
            ->whereBetween('created_at', [$fromAt, $toAt])
            ->whereNull('deleted_at')
            ->count();

        $customersNew = (int) Customer::withoutGlobalScopes()
            ->where('workspace_id', $workspaceId)
            ->whereBetween('created_at', [$fromAt, $toAt])
            ->whereNull('deleted_at')
            ->count();

        $topServices = $this->topServices($workspaceId, $fromAt, $toAt);
        $topProducts = $this->topProducts($workspaceId, $fromAt, $toAt);
        $paymentBreakdown = $this->paymentStatusBreakdown($workspaceId, $fromAt, $toAt);

        return [
            'from' => $fromAt->toDateString(),
            'to' => $toAt->toDateString(),
            'revenue' => round($revenue, 2),
            'bookings_count' => $bookingsCount,
            'orders_count' => $ordersCount,
            'customers_new' => $customersNew,
            'top_services' => $topServices,
            'top_products' => $topProducts,
            'payment_status_breakdown' => $paymentBreakdown,
        ];
    }

    /**
     * @return array<int, array{id:int|null,name:string,bookings:int}>
     */
    private function topServices(int $workspaceId, CarbonInterface $fromAt, CarbonInterface $toAt): array
    {
        $rows = AppointmentBooking::withoutGlobalScopes()
            ->where('workspace_id', $workspaceId)
            ->whereBetween('starts_at', [$fromAt, $toAt])
            ->whereNull('deleted_at')
            ->selectRaw('service_id, COUNT(*) as bookings')
            ->groupBy('service_id')
            ->orderByDesc('bookings')
            ->limit(5)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $serviceNames = AppointmentServiceModel::withoutGlobalScopes()
            ->where('workspace_id', $workspaceId)
            ->whereIn('id', $rows->pluck('service_id')->filter()->all())
            ->pluck('name', 'id');

        return $rows->map(function ($row) use ($serviceNames): array {
            $serviceId = $row->service_id ? (int) $row->service_id : null;

            return [
                'id' => $serviceId,
                'name' => $serviceId ? (string) ($serviceNames[$serviceId] ?? 'خدمة محذوفة') : 'بدون خدمة',
                'bookings' => (int) $row->bookings,
            ];
        })->values()->all();
    }

    /**
     * @return array<int, array{name:string,quantity:float,revenue:float}>
     */
    private function topProducts(int $workspaceId, CarbonInterface $fromAt, CarbonInterface $toAt): array
    {
        $items = OrderItem::withoutGlobalScopes()
            ->where('workspace_id', $workspaceId)
            ->whereHas('order', function ($query) use ($workspaceId, $fromAt, $toAt): void {
                $query->withoutGlobalScopes()
                    ->where('workspace_id', $workspaceId)
                    ->whereBetween('created_at', [$fromAt, $toAt])
                    ->whereNull('deleted_at');
            })
            ->get(['product_name', 'sku', 'quantity', 'total_amount']);

        return $items
            ->groupBy(function (OrderItem $item): string {
                $name = trim((string) $item->product_name);
                if ($name !== '') {
                    return $name;
                }

                $sku = trim((string) $item->sku);

                return $sku !== '' ? $sku : 'منتج بدون اسم';
            })
            ->map(fn (Collection $group, string $name): array => [
                'name' => $name,
                'quantity' => (float) $group->sum('quantity'),
                'revenue' => round((float) $group->sum('total_amount'), 2),
            ])
            ->sortByDesc('quantity')
            ->take(5)
            ->values()
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function paymentStatusBreakdown(int $workspaceId, CarbonInterface $fromAt, CarbonInterface $toAt): array
    {
        $paymentRows = Payment::withoutGlobalScopes()
            ->where('workspace_id', $workspaceId)
            ->where(function ($query) use ($fromAt, $toAt): void {
                $query->whereBetween('created_at', [$fromAt, $toAt])
                    ->orWhereBetween('paid_at', [$fromAt, $toAt]);
            })
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $bookingRows = AppointmentBooking::withoutGlobalScopes()
            ->where('workspace_id', $workspaceId)
            ->whereBetween('starts_at', [$fromAt, $toAt])
            ->whereNull('deleted_at')
            ->selectRaw('payment_status, COUNT(*) as total')
            ->groupBy('payment_status')
            ->pluck('total', 'payment_status');

        $merged = [];
        foreach ($paymentRows as $status => $total) {
            $key = 'payment:'.(string) $status;
            $merged[$key] = (int) $total;
        }
        foreach ($bookingRows as $status => $total) {
            $key = 'booking:'.(string) ($status ?: 'unknown');
            $merged[$key] = (int) $total;
        }

        ksort($merged);

        return $merged;
    }
}
