<?php

namespace Tests;

use App\Models\Workspace;
use App\Models\WorkspaceFeatureFlag;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Carbon;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /**
     * Operational POS sales go through the domain service, not Web POS.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function placePosOrder(\App\Models\Workspace $workspace, \App\Models\User $actor, array $payload): \App\Models\Order
    {
        $order = app(\App\Services\Pos\PosOrderService::class)->createPosOrder($workspace, $payload, $actor);
        $isTable = ($payload['order_type'] ?? '') === 'table' || ! empty($payload['dining_table_id']);
        if (! $isTable) {
            try {
                app(\App\Services\Pos\PosOrderService::class)->issueWebPosDirectInvoice($order, (int) $actor->id);
            } catch (\RuntimeException) {
            }
        }

        return $order->fresh(['items', 'table', 'tableSession']) ?? $order;
    }

    protected function enableWorkspaceFeature(Workspace $workspace, string $feature, bool $enabled = true): void
    {
        WorkspaceFeatureFlag::withoutGlobalScopes()->updateOrCreate(
            ['workspace_id' => $workspace->id, 'feature_key' => $feature],
            ['workspace_id' => $workspace->id, 'feature_key' => $feature, 'enabled' => $enabled, 'source' => 'manual']
        );
    }

    protected function nextOpenAppointmentSlot(string $timezone = 'Asia/Riyadh', int $hour = 10, int $minute = 0): Carbon
    {
        return Carbon::now($timezone)->next(Carbon::MONDAY)->setTime($hour, $minute);
    }
}
