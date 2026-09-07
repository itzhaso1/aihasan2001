<?php

namespace App\Services\Pos;

use App\Exceptions\PosSyncOperationException;
use App\Models\Customer;
use App\Models\DiningTable;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\PosDevice;
use App\Models\PosSyncChange;
use App\Models\PosSyncOperation;
use App\Models\TableSession;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Feature\FeatureAccessService;
use App\Services\Inventory\InventoryService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Batch POS push — idempotent by (workspace_id, operation_uuid).
 *
 * Source of Truth:
 *   catalog / prices / categories / users / settings → Laravel
 *   orders / payments / table sessions / invoices     → POS then Laravel
 *   stock                                             → Laravel via movements (sale stock is applied by order.created)
 */
class PosSyncPushService
{
    public const MAX_OPERATIONS = 50;

    public function __construct(
        private readonly PosOrderService $orders,
        private readonly InventoryService $inventory,
        private readonly FeatureAccessService $features,
        private readonly PosDeviceRegistry $devices,
        private readonly PosSyncPullService $pull,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $operations
     * @return array{
     *     success: bool,
     *     accepted: list<array<string, mixed>>,
     *     failed: list<array<string, mixed>>,
     *     server_cursor: int,
     *     device_id: string
     * }
     */
    public function push(Workspace $workspace, User $user, string $deviceId, array $operations): array
    {
        if (! $this->features->workspaceHasFeature($workspace, 'pos')) {
            throw new HttpException(403, 'الكاشير غير متاح في باقتك الحالية');
        }

        $device = $this->devices->requireRegistered($workspace, $deviceId);
        $this->bindOriginDevice($deviceId);

        $accepted = [];
        $failed = [];

        foreach ($operations as $operation) {
            $uuid = trim((string) ($operation['id'] ?? ''));
            $type = trim((string) ($operation['type'] ?? ''));
            if ($uuid === '' || $type === '') {
                $failed[] = [
                    'id' => $uuid === '' ? null : $uuid,
                    'type' => $type === '' ? null : $type,
                    'error' => 'كل عملية تحتاج id و type.',
                    'retryable' => false,
                ];

                continue;
            }

            try {
                $accepted[] = $this->processOne($workspace, $user, $device, $operation);
            } catch (PosSyncOperationException $e) {
                $this->rememberFailure($workspace, $deviceId, $operation, $e->getMessage());
                $failed[] = [
                    'id' => $uuid,
                    'type' => $type,
                    'error' => $e->getMessage(),
                    'retryable' => $e->retryable,
                ];
            } catch (RuntimeException $e) {
                $this->rememberFailure($workspace, $deviceId, $operation, $e->getMessage());
                $failed[] = [
                    'id' => $uuid,
                    'type' => $type,
                    'error' => $e->getMessage(),
                    'retryable' => false,
                ];
            } catch (Throwable $e) {
                Log::warning('pos.sync.push_operation_failed', [
                    'workspace_id' => $workspace->id,
                    'device_id' => $deviceId,
                    'operation_uuid' => $uuid,
                    'error' => $e->getMessage(),
                ]);
                $this->rememberFailure($workspace, $deviceId, $operation, $e->getMessage());
                $failed[] = [
                    'id' => $uuid,
                    'type' => $type,
                    'error' => 'تعذّر معالجة العملية مؤقتاً.',
                    'retryable' => true,
                ];
            }
        }

        $cursor = $this->serverCursor($workspace);
        $error = $failed === [] ? null : (string) ($failed[0]['error'] ?? null);
        $this->devices->touch($device, $cursor, $error);

        return [
            'success' => $failed === [],
            'accepted' => $accepted,
            'failed' => $failed,
            'server_cursor' => $cursor,
            'device_id' => $device->device_id,
        ];
    }

    /**
     * @return array{cursor: int, server_cursor: int, has_more: bool, changes: list<array<string, mixed>>, device_id: string}
     */
    public function pull(Workspace $workspace, string $deviceId, int $cursor, int $limit = 200): array
    {
        if (! $this->features->workspaceHasFeature($workspace, 'pos')) {
            throw new HttpException(403, 'الكاشير غير متاح في باقتك الحالية');
        }

        $device = $this->devices->requireRegistered($workspace, $deviceId);
        $payload = $this->pull->changes($workspace, $cursor, $limit);
        $this->devices->touch($device, (int) $payload['server_cursor']);
        $payload['device_id'] = $device->device_id;

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    private function processOne(Workspace $workspace, User $user, PosDevice $device, array $operation): array
    {
        $uuid = trim((string) $operation['id']);
        $type = trim((string) $operation['type']);
        $data = is_array($operation['data'] ?? null) ? $operation['data'] : [];

        $existing = PosSyncOperation::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->where('operation_uuid', $uuid)
            ->first();

        if ($existing && $existing->status === PosSyncOperation::STATUS_ACCEPTED) {
            return $this->ack($existing, 'duplicate');
        }

        $row = $existing ?? $this->claim($workspace, $device, $uuid, $type, $operation);
        if ($row->status === PosSyncOperation::STATUS_ACCEPTED) {
            return $this->ack($row, 'duplicate');
        }

        $handled = $this->dispatch($workspace, $user, $type, $data, $uuid);
        $row->fill([
            'status' => PosSyncOperation::STATUS_ACCEPTED,
            'entity_type' => $handled['entity_type'] ?? null,
            'entity_id' => $handled['entity_id'] ?? null,
            'result_payload' => $handled['result'] ?? [],
            'last_error' => null,
            'attempts' => (int) $row->attempts + ($existing ? 1 : 0),
            'processed_at' => now(),
        ]);
        $row->save();

        return $this->ack($row, $existing ? 'duplicate' : 'applied');
    }

    /**
     * @param  array<string, mixed>  $operation
     */
    private function claim(Workspace $workspace, PosDevice $device, string $uuid, string $type, array $operation): PosSyncOperation
    {
        try {
            return PosSyncOperation::withoutGlobalScopes()->create([
                'workspace_id' => $workspace->id,
                'device_id' => $device->device_id,
                'operation_uuid' => $uuid,
                'type' => $type,
                'status' => PosSyncOperation::STATUS_SYNCING,
                'request_payload' => $operation,
                'attempts' => 1,
            ]);
        } catch (QueryException $e) {
            $existing = PosSyncOperation::withoutGlobalScopes()
                ->where('workspace_id', $workspace->id)
                ->where('operation_uuid', $uuid)
                ->first();
            if ($existing) {
                return $existing;
            }
            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{entity_type: ?string, entity_id: ?int, result: array<string, mixed>}
     */
    private function dispatch(Workspace $workspace, User $user, string $type, array $data, string $uuid): array
    {
        return match ($type) {
            'order.created' => $this->orderCreated($workspace, $user, $data, $uuid),
            'order.updated' => $this->orderUpdated($workspace, $data),
            'order.deleted' => $this->orderDeleted($workspace, $user, $data),
            'customer.created' => $this->customerCreated($workspace, $data, $uuid),
            'table_session.open' => $this->sessionOpen($workspace, $data),
            'table_session.close' => $this->sessionClose($workspace, $user, $data),
            'table_session.cancel' => $this->sessionCancel($workspace, $user, $data),
            'table_session.note' => $this->sessionNote($workspace, $data),
            'table_session.discount' => $this->sessionDiscount($workspace, $data),
            'table_session.transfer' => $this->sessionTransfer($workspace, $data),
            'table_session.merge' => $this->sessionMerge($workspace, $data),
            'table_session.split' => $this->sessionSplit($workspace, $user, $data),
            'invoice.created' => $this->invoiceCreated($workspace, $user, $data),
            'stock.movement' => $this->stockMovement($workspace, $user, $data, $uuid),
            default => throw PosSyncOperationException::permanent('نوع عملية غير مدعوم: '.$type),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{entity_type: string, entity_id: int, result: array<string, mixed>}
     */
    private function orderCreated(Workspace $workspace, User $user, array $data, string $uuid): array
    {
        if (empty($data['client_reference'])) {
            $data['client_reference'] = $uuid;
        }
        if (empty($data['dining_table_id']) && ! empty($data['table_server_id'])) {
            $data['dining_table_id'] = $data['table_server_id'];
        }
        if (isset($data['items']) && is_array($data['items'])) {
            $data['items'] = array_values(array_map(static function ($item): array {
                $row = is_array($item) ? $item : [];

                return [
                    'pos_menu_item_id' => $row['pos_menu_item_id'] ?? null,
                    'quantity' => $row['quantity'] ?? 1,
                ];
            }, $data['items']));
        }

        $order = $this->orders->createPosOrder($workspace, $data, $user);
        $order->load(['items', 'table', 'customer']);

        return [
            'entity_type' => 'order',
            'entity_id' => (int) $order->id,
            'result' => $this->orderResult($order),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{entity_type: string, entity_id: int, result: array<string, mixed>}
     */
    private function orderUpdated(Workspace $workspace, array $data): array
    {
        $order = $this->resolveOrder($workspace, $data);
        $updated = $this->orders->updateOrderItems($order, $data);
        $updated->load(['items', 'table', 'customer']);

        return [
            'entity_type' => 'order',
            'entity_id' => (int) $updated->id,
            'result' => $this->orderResult($updated),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{entity_type: string, entity_id: int, result: array<string, mixed>}
     */
    private function orderDeleted(Workspace $workspace, User $user, array $data): array
    {
        $order = $this->resolveOrder($workspace, $data);
        $updated = $this->orders->deletePosOrder($order, $user);

        return [
            'entity_type' => 'order',
            'entity_id' => (int) $updated->id,
            'result' => [
                'id' => $updated->id,
                'pos_status' => $updated->pos_status,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{entity_type: string, entity_id: int, result: array<string, mixed>}
     */
    private function customerCreated(Workspace $workspace, array $data, string $uuid): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $phone = trim((string) ($data['phone'] ?? ''));
        $clientRef = trim((string) ($data['client_reference'] ?? $uuid));
        if ($name === '' || $phone === '') {
            throw PosSyncOperationException::permanent('اسم ورقم هاتف العميل مطلوبان.');
        }

        if ($clientRef !== '') {
            $existing = Customer::withoutGlobalScopes()
                ->where('workspace_id', $workspace->id)
                ->where('client_reference', $clientRef)
                ->first();
            if ($existing) {
                return [
                    'entity_type' => 'customer',
                    'entity_id' => (int) $existing->id,
                    'result' => $this->customerResult($existing),
                ];
            }
        }

        $byPhone = Customer::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->where('phone', $phone)
            ->first();
        if ($byPhone) {
            if ($clientRef !== '' && empty($byPhone->client_reference)) {
                $byPhone->client_reference = $clientRef;
                $byPhone->save();
            }

            return [
                'entity_type' => 'customer',
                'entity_id' => (int) $byPhone->id,
                'result' => $this->customerResult($byPhone),
            ];
        }

        $customer = Customer::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => $name,
            'phone' => $phone,
            'client_reference' => $clientRef !== '' ? $clientRef : null,
            'orders_count' => 0,
            'total_purchases' => 0,
        ]);

        return [
            'entity_type' => 'customer',
            'entity_id' => (int) $customer->id,
            'result' => $this->customerResult($customer),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{entity_type: string, entity_id: int, result: array<string, mixed>}
     */
    private function sessionOpen(Workspace $workspace, array $data): array
    {
        $table = $this->resolveTable($workspace, $data);
        $session = $this->orders->openSession($table);

        return [
            'entity_type' => 'table_session',
            'entity_id' => (int) $session->id,
            'result' => [
                'session_id' => $session->id,
                'table_id' => $table->id,
                'status' => $session->status,
                'opened_at' => optional($session->opened_at)?->toIso8601String(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{entity_type: string, entity_id: ?int, result: array<string, mixed>}
     */
    private function sessionClose(Workspace $workspace, User $user, array $data): array
    {
        $session = $this->resolveSession($workspace, $data, required: false);
        if (! $session) {
            return [
                'entity_type' => 'table_session',
                'entity_id' => null,
                'result' => ['invoice' => null, 'already_closed' => true],
            ];
        }

        $invoice = $this->orders->closeSession(
            $session,
            (int) $user->id,
            isset($data['payment_method']) ? (string) $data['payment_method'] : null,
        );

        return [
            'entity_type' => 'table_session',
            'entity_id' => (int) $session->id,
            'result' => [
                'session_id' => $session->id,
                'invoice' => $invoice ? [
                    'id' => $invoice->id,
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'total_amount' => (float) $invoice->total_amount,
                    'currency' => $invoice->currency,
                ] : null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{entity_type: string, entity_id: int, result: array<string, mixed>}
     */
    private function sessionCancel(Workspace $workspace, User $user, array $data): array
    {
        $session = $this->resolveSession($workspace, $data, required: true);
        $this->orders->cancelSession($session, $user);

        return [
            'entity_type' => 'table_session',
            'entity_id' => (int) $session->id,
            'result' => ['session_id' => $session->id, 'status' => 'cancelled'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{entity_type: string, entity_id: int, result: array<string, mixed>}
     */
    private function sessionNote(Workspace $workspace, array $data): array
    {
        $session = $this->resolveSession($workspace, $data, required: true);
        $this->orders->applySessionNote($session, (string) ($data['notes'] ?? ''));

        return [
            'entity_type' => 'table_session',
            'entity_id' => (int) $session->id,
            'result' => ['session_id' => $session->id],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{entity_type: string, entity_id: int, result: array<string, mixed>}
     */
    private function sessionDiscount(Workspace $workspace, array $data): array
    {
        $session = $this->resolveSession($workspace, $data, required: true);
        $this->orders->applySessionDiscount($session, (float) ($data['discount_amount'] ?? 0));

        return [
            'entity_type' => 'table_session',
            'entity_id' => (int) $session->id,
            'result' => ['session_id' => $session->id],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{entity_type: string, entity_id: int, result: array<string, mixed>}
     */
    private function sessionTransfer(Workspace $workspace, array $data): array
    {
        $session = $this->resolveSession($workspace, $data, required: true);
        $targetId = (int) ($data['target_table_id'] ?? 0);
        $target = DiningTable::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->whereKey($targetId)
            ->first();
        if (! $target) {
            throw PosSyncOperationException::permanent('طاولة النقل غير موجودة.');
        }
        $this->orders->transferSession($session, $target);

        return [
            'entity_type' => 'table_session',
            'entity_id' => (int) $session->id,
            'result' => ['session_id' => $session->id, 'target_table_id' => $target->id],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{entity_type: string, entity_id: int, result: array<string, mixed>}
     */
    private function sessionMerge(Workspace $workspace, array $data): array
    {
        $session = $this->resolveSession($workspace, $data, required: true);
        $targetId = (int) ($data['target_table_id'] ?? 0);
        $target = DiningTable::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->whereKey($targetId)
            ->first();
        if (! $target) {
            throw PosSyncOperationException::permanent('طاولة الدمج غير موجودة.');
        }
        $this->orders->mergeSessions($session, $target);

        return [
            'entity_type' => 'table_session',
            'entity_id' => (int) $session->id,
            'result' => ['session_id' => $session->id, 'target_table_id' => $target->id],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{entity_type: string, entity_id: ?int, result: array<string, mixed>}
     */
    private function sessionSplit(Workspace $workspace, User $user, array $data): array
    {
        $session = $this->resolveSession($workspace, $data, required: false);
        $groups = $data['remote_groups']['groups'] ?? $data['groups'] ?? [];
        if (! $session || ! is_array($groups) || count($groups) < 2) {
            // Local split already created orders; remote split is best-effort.
            return [
                'entity_type' => 'table_session',
                'entity_id' => $session?->id,
                'result' => ['skipped' => true],
            ];
        }

        $orders = $this->orders->splitSessionByItems($session, $groups, $user);

        return [
            'entity_type' => 'table_session',
            'entity_id' => (int) $session->id,
            'result' => ['order_ids' => $orders->pluck('id')->all()],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{entity_type: string, entity_id: int, result: array<string, mixed>}
     */
    private function invoiceCreated(Workspace $workspace, User $user, array $data): array
    {
        $order = $this->resolveOrder($workspace, [
            'server_order_id' => $data['order_server_id'] ?? $data['server_order_id'] ?? null,
            'client_reference' => $data['order_local_id'] ?? $data['client_reference'] ?? null,
        ]);
        $invoice = $this->orders->createInvoiceFromOrder($order, (int) $user->id);

        return [
            'entity_type' => 'invoice',
            'entity_id' => (int) $invoice->id,
            'result' => [
                'invoice_id' => $invoice->id,
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'total_amount' => (float) $invoice->total_amount,
                'currency' => $invoice->currency,
            ],
        ];
    }

    /**
     * Standalone stock ledger. Sale deductions from order.created are skipped
     * so inventory is not double-applied.
     *
     * @param  array<string, mixed>  $data
     * @return array{entity_type: string, entity_id: ?int, result: array<string, mixed>}
     */
    private function stockMovement(Workspace $workspace, User $user, array $data, string $uuid): array
    {
        $kind = strtolower(trim((string) ($data['kind'] ?? $data['type'] ?? '')));
        $productId = (int) ($data['product_id'] ?? 0);
        $quantity = (int) ($data['quantity'] ?? 0);
        if ($productId <= 0 || $quantity <= 0 || $kind === '') {
            throw PosSyncOperationException::permanent('حركة المخزون تحتاج product_id و kind و quantity.');
        }

        if (in_array($kind, ['sale', 'remove'], true)) {
            return [
                'entity_type' => 'stock',
                'entity_id' => null,
                'result' => [
                    'skipped' => true,
                    'reason' => 'sale_applied_by_order',
                ],
            ];
        }

        $inventoryType = match ($kind) {
            'purchase', 'add' => 'add',
            'return' => 'return',
            'adjustment' => 'adjustment',
            'transfer', 'release' => 'release',
            default => throw PosSyncOperationException::permanent('نوع حركة مخزون غير مدعوم: '.$kind),
        };

        $movement = $this->inventory->adjustStock(
            productId: $productId,
            variantId: isset($data['variant_id']) ? (int) $data['variant_id'] : null,
            type: $inventoryType,
            quantity: $quantity,
            actor: $user,
            referenceType: isset($data['reference_type']) ? (string) $data['reference_type'] : InventoryMovement::class,
            referenceId: isset($data['reference_id']) ? (int) $data['reference_id'] : null,
            notes: (string) ($data['notes'] ?? 'POS sync '.$uuid),
        );

        return [
            'entity_type' => 'stock',
            'entity_id' => (int) $movement->id,
            'result' => [
                'id' => $movement->id,
                'product_id' => $movement->product_id,
                'type' => $movement->type,
                'quantity' => $movement->quantity,
                'before_quantity' => $movement->before_quantity,
                'after_quantity' => $movement->after_quantity,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveOrder(Workspace $workspace, array $data): Order
    {
        $id = (int) ($data['server_order_id'] ?? $data['order_server_id'] ?? $data['id'] ?? 0);
        if ($id > 0) {
            $order = Order::withoutGlobalScopes()
                ->where('workspace_id', $workspace->id)
                ->whereKey($id)
                ->first();
            if ($order) {
                return $order;
            }
        }

        $ref = trim((string) ($data['client_reference'] ?? $data['order_local_id'] ?? ''));
        if ($ref !== '') {
            $order = Order::withoutGlobalScopes()
                ->where('workspace_id', $workspace->id)
                ->where('client_reference', $ref)
                ->first();
            if ($order) {
                return $order;
            }
        }

        throw PosSyncOperationException::permanent('الطلب غير موجود.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveTable(Workspace $workspace, array $data): DiningTable
    {
        $id = (int) ($data['table_server_id'] ?? $data['dining_table_id'] ?? $data['table_id'] ?? 0);
        $table = DiningTable::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->whereKey($id)
            ->first();
        if (! $table) {
            throw PosSyncOperationException::permanent('الطاولة غير موجودة.');
        }

        return $table;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveSession(Workspace $workspace, array $data, bool $required): ?TableSession
    {
        $sessionId = (int) ($data['session_server_id'] ?? $data['session_id'] ?? 0);
        if ($sessionId > 0) {
            $session = TableSession::withoutGlobalScopes()
                ->where('workspace_id', $workspace->id)
                ->whereKey($sessionId)
                ->first();
            if ($session) {
                return $session;
            }
        }

        try {
            $table = $this->resolveTable($workspace, $data);
        } catch (PosSyncOperationException $e) {
            if ($required) {
                throw $e;
            }

            return null;
        }

        $open = TableSession::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->where('dining_table_id', $table->id)
            ->where('status', 'open')
            ->latest('id')
            ->first();

        if ($open) {
            return $open;
        }

        if ($required) {
            throw PosSyncOperationException::permanent('لا توجد جلسة مفتوحة لهذه الطاولة.');
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function orderResult(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'client_reference' => $order->client_reference,
            'pos_status' => $order->pos_status,
            'total_amount' => (float) $order->total_amount,
            'dining_table_id' => $order->dining_table_id,
            'table_session_id' => $order->table_session_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function customerResult(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'client_reference' => $customer->client_reference,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ack(PosSyncOperation $row, string $status): array
    {
        $result = is_array($row->result_payload) ? $row->result_payload : [];

        return [
            'id' => $row->operation_uuid,
            'status' => $status,
            'type' => $row->type,
            'entity_id' => $row->entity_id,
            'result' => $result,
        ];
    }

    /**
     * @param  array<string, mixed>  $operation
     */
    private function rememberFailure(Workspace $workspace, string $deviceId, array $operation, string $error): void
    {
        $uuid = trim((string) ($operation['id'] ?? ''));
        if ($uuid === '') {
            return;
        }

        $row = PosSyncOperation::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->where('operation_uuid', $uuid)
            ->first();
        if (! $row) {
            return;
        }
        if ($row->status === PosSyncOperation::STATUS_ACCEPTED) {
            return;
        }

        $row->fill([
            'status' => PosSyncOperation::STATUS_FAILED,
            'last_error' => $error,
            'device_id' => $deviceId,
        ]);
        $row->save();
    }

    private function serverCursor(Workspace $workspace): int
    {
        return (int) (PosSyncChange::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->max('id') ?? 0);
    }

    private function bindOriginDevice(string $deviceId): void
    {
        try {
            request()?->headers->set('X-Device-Id', $deviceId);
        } catch (Throwable) {
            // Observer origin is best-effort.
        }
    }
}