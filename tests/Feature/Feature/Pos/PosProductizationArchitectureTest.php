<?php

namespace Tests\Feature\Feature\Pos;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PosMenuItem;
use App\Models\PosOrderReturn;
use App\Models\Product;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Domain\SslService;
use App\Services\Product\DigitalDownloadService;
use App\Models\Website\WebsiteDomain;
use Database\Seeders\FoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PosProductizationArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_pos_cart_checkout_creates_order_via_session_cart(): void
    {
        $this->seed(FoundationSeeder::class);
        [$owner, $workspace] = $this->createWorkspaceOwner('store');

        $item = PosMenuItem::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Espresso',
            'item_type' => 'مشروبات',
            'price' => 3.50,
            'currency' => 'USD',
            'is_active' => true,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->postJson(route('workspace.pos.cart.items.store'), [
                'pos_menu_item_id' => $item->id,
                'quantity' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('cart.item_count', 1)
            ->assertJsonPath('cart.subtotal', 7);

        $response = $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->postJson(route('workspace.pos.cart.checkout'), [
                'discount_amount' => 0.5,
            ])
            ->assertCreated();

        $orderId = $response->json('order_id');
        $this->assertNotNull($orderId);

        $order = Order::withoutGlobalScopes()->findOrFail($orderId);
        $this->assertSame('pos', $order->source);
        $this->assertSame(7.0, (float) $order->subtotal);
        $this->assertSame(0.5, (float) $order->discount_amount);
        $this->assertSame(6.5, (float) $order->total_amount);
        $this->assertDatabaseCount('order_items', 1);
    }

    public function test_pos_return_create_and_mark_refunded_updates_payment_metadata(): void
    {
        $this->seed(FoundationSeeder::class);
        [$owner, $workspace] = $this->createWorkspaceOwner('store');

        $item = PosMenuItem::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Tea',
            'price' => 2.00,
            'currency' => 'USD',
            'is_active' => true,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('workspace.pos.orders.store'), [
                'items' => [
                    ['pos_menu_item_id' => $item->id, 'quantity' => 2],
                ],
            ])
            ->assertRedirect();

        $order = Order::query()->where('source', 'pos')->latest('id')->firstOrFail();
        $order->update(['payment_status' => 'paid']);
        $orderItem = OrderItem::query()->where('order_id', $order->id)->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('workspace.pos.orders.returns.store', $order), [
                'reason' => 'إرجاع عميل',
                'mark_refunded' => 1,
                'items' => [
                    [
                        'order_item_id' => $orderItem->id,
                        'qty' => 2,
                    ],
                ],
            ])
            ->assertRedirect();

        $return = PosOrderReturn::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame('refunded', $return->status);
        $this->assertSame(4.0, (float) $return->total);

        $order->refresh();
        $this->assertSame('refunded', $order->payment_status);
        $this->assertNotEmpty(data_get($order->metadata, 'pos_refunds'));
    }

    public function test_digital_signed_download_url_requires_valid_signature(): void
    {
        $this->seed(FoundationSeeder::class);
        [$owner, $workspace] = $this->createWorkspaceOwner('store');

        Storage::fake('local');
        Storage::disk('local')->put('digital/manual.pdf', 'pdf-bytes');

        $product = Product::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Digital Manual',
            'slug' => 'digital-manual',
            'sku' => 'DIG-001',
            'price' => 10,
            'currency' => 'USD',
            'stock' => 0,
            'status' => 'active',
            'product_kind' => 'digital',
            'digital_type' => 'pdf',
            'download_limit' => 5,
            'digital_asset_disk' => 'local',
            'digital_asset_path' => 'digital/manual.pdf',
        ]);

        $order = Order::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'order_number' => 'DIG-0001',
            'source' => 'manual',
            'status' => 'completed',
            'payment_status' => 'paid',
            'fulfillment_status' => 'fulfilled',
            'shipping_status' => 'not_shipped',
            'currency' => 'USD',
            'subtotal' => 10,
            'discount_amount' => 0,
            'shipping_amount' => 0,
            'total_amount' => 10,
            'metadata' => [],
            'placed_at' => now(),
        ]);

        $orderItem = OrderItem::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 10,
            'discount_amount' => 0,
            'total_amount' => 10,
        ]);

        $unsigned = route('downloads.digital', $orderItem);
        $this->get($unsigned)->assertForbidden();

        $signed = app(DigitalDownloadService::class)->issueSignedDownloadUrl($orderItem);
        $this->assertStringContainsString('signature=', $signed);

        $this->get($signed)->assertOk();
    }

    public function test_ssl_renew_and_sync_is_callable_when_certbot_missing(): void
    {
        config()->set('website.ssl.enabled', true);
        config()->set('website.ssl.certbot_bin', '/usr/bin/certbot-does-not-exist-'.uniqid());
        config()->set('website.ssl.live_path', storage_path('framework/testing/letsencrypt/live'));

        $domain = WebsiteDomain::withoutGlobalScopes()->make([
            'workspace_id' => 1,
            'website_id' => 1,
            'domain' => 'renew-test.example',
            'normalized_domain' => 'renew-test.example',
            'status' => 'active',
            'ssl_status' => 'active',
            'ssl_expires_at' => now()->addDays(5),
            'metadata' => [],
        ]);

        $service = new class extends SslService {
            public function syncFromFilesystem(WebsiteDomain $domain): array
            {
                return [
                    'status' => $domain->ssl_status,
                    'certificate_verified' => false,
                    'inspection' => ['valid' => false, 'reason' => 'test_stub'],
                ];
            }
        };

        $result = $service->renewAndSync($domain);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('renew_successful', $result);
        $this->assertArrayHasKey('sync', $result);
        $this->assertFalse($result['renew_successful']);
    }

    /**
     * @return array{0: User, 1: Workspace}
     */
    private function createWorkspaceOwner(string $workspaceType): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create([
            'owner_user_id' => $user->id,
            'type' => $workspaceType,
        ]);

        $workspace->users()->attach($user->id, [
            'membership_role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        foreach (['pos', 'qr_menu', 'products', 'orders'] as $feature) {
            \App\Models\WorkspaceFeatureFlag::withoutGlobalScopes()->updateOrCreate(
                ['workspace_id' => $workspace->id, 'feature_key' => $feature],
                ['workspace_id' => $workspace->id, 'feature_key' => $feature, 'enabled' => true, 'source' => 'manual']
            );
        }

        $plan = \App\Models\Plan::query()
            ->where('workspace_type', $workspaceType)
            ->where('code', $workspaceType.'_pro')
            ->first()
            ?? \App\Models\Plan::query()
                ->where('workspace_type', $workspaceType)
                ->where('is_active', true)
                ->orderByDesc('price')
                ->first();

        if ($plan) {
            \App\Models\Subscription::withoutGlobalScopes()->create([
                'workspace_id' => $workspace->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'starts_at' => now(),
                'current_period_start' => now(),
                'current_period_end' => now()->addMonth(),
            ]);
        }

        return [$user, $workspace];
    }
}
