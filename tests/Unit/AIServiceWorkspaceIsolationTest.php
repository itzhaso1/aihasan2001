<?php

namespace Tests\Unit;

use App\Models\AiLog;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\Workspace;
use App\Services\AI\AIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AIServiceWorkspaceIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_service_builds_product_context_from_same_workspace_only(): void
    {
        $workspaceA = Workspace::factory()->create(['type' => 'company']);
        $workspaceB = Workspace::factory()->create(['type' => 'company']);

        $plan = Plan::query()->create([
            'code' => 'company_ai_isolation',
            'name' => 'AI Isolation',
            'workspace_type' => 'company',
            'billing_period' => 'monthly',
            'currency' => 'USD',
            'price' => 0,
            'is_active' => true,
            'features' => ['ai', 'conversations', 'smart_replies'],
            'limits' => ['ai_usage' => 10000, 'ai_tokens' => 1000000],
        ]);

        Subscription::query()->create([
            'workspace_id' => $workspaceA->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'current_period_start' => now()->startOfMonth(),
            'current_period_end' => now()->endOfMonth(),
        ]);

        $customer = Customer::query()->create([
            'workspace_id' => $workspaceA->id,
            'name' => 'عميل تجريبي',
            'phone' => '+966500000000',
            'whatsapp' => null,
            'email' => 'customer-a@example.com',
            'orders_count' => 0,
            'total_purchases' => 0,
            'notes' => null,
            'metadata' => [],
        ]);

        $conversation = Conversation::query()->create([
            'workspace_id' => $workspaceA->id,
            'customer_id' => $customer->id,
            'channel' => 'web',
            'external_id' => 'conv-a',
            'status' => 'open',
            'ai_enabled' => true,
            'metadata' => [],
        ]);

        $message = Message::query()->create([
            'workspace_id' => $workspaceA->id,
            'conversation_id' => $conversation->id,
            'customer_id' => $customer->id,
            'direction' => 'inbound',
            'message_type' => 'text',
            'content' => 'هل يوجد منتج معين؟',
            'ai_generated' => false,
            'metadata' => [],
        ]);

        Product::factory()->create([
            'workspace_id' => $workspaceA->id,
            'name' => 'حذاء أسود',
            'sku' => 'A-BLACK-42',
            'slug' => 'black-shoe-a',
            'status' => 'active',
        ]);

        Product::factory()->create([
            'workspace_id' => $workspaceB->id,
            'name' => 'منتج من مساحة أخرى',
            'sku' => 'B-OTHER-1',
            'slug' => 'other-workspace-product',
            'status' => 'active',
        ]);

        app(AIService::class)->generateReply($conversation, $message);

        $log = AiLog::query()->latest('id')->firstOrFail();
        $systemPrompt = (string) data_get($log->input_payload, 'messages.0.content', '');

        $this->assertStringContainsString('حذاء أسود', $systemPrompt);
        $this->assertStringNotContainsString('منتج من مساحة أخرى', $systemPrompt);
    }
}
