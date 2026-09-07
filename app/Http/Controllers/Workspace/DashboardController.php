<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Workspace\Concerns\InteractsWithWorkspace;
use App\Models\AiLog;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Subscription;
use App\Services\Feature\FeatureAccessService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use InteractsWithWorkspace;

    public function __construct(private readonly FeatureAccessService $featureAccessService) {}

    public function index(): View
    {
        $workspace = $this->currentWorkspace();
        $isCommercial = in_array($workspace->type, ['company', 'store'], true);
        $currentSubscription = Subscription::query()
            ->with('plan')
            ->whereIn('status', ['active', 'trialing', 'past_due', 'expired'])
            ->latest('id')
            ->first();
        $subscriptionStatus = $currentSubscription?->status ?? 'inactive';

        $usageWindowStart = $currentSubscription?->current_period_start
            ?? $currentSubscription?->starts_at
            ?? now()->startOfMonth();
        $usageWindowEnd = $currentSubscription?->current_period_end && $currentSubscription->current_period_end->lt(now())
            ? $currentSubscription->current_period_end
            : now();

        $messagesUsed = Message::query()
            ->whereBetween('created_at', [$usageWindowStart, $usageWindowEnd])
            ->count();
        $messageLimit = data_get($currentSubscription?->plan?->limits, 'messages');
        if (! is_numeric($messageLimit)) {
            $messageLimit = data_get($currentSubscription?->plan?->limits, 'conversations');
        }
        $messageLimitValue = is_numeric($messageLimit) ? max(0, (int) $messageLimit) : null;
        $usagePercent = ($messageLimitValue && $messageLimitValue > 0)
            ? round(($messagesUsed / $messageLimitValue) * 100, 1)
            : null;

        $stats = [
            'conversations' => Conversation::query()->count(),
            'subscription_status' => $subscriptionStatus,
            'ai_tokens_30d' => (int) AiLog::query()
                ->whereDate('created_at', '>=', now()->subDays(30)->toDateString())
                ->sum('tokens_used'),
        ];

        if ($isCommercial) {
            $stats = array_merge($stats, [
                'orders_today' => Order::query()->whereDate('created_at', today())->count(),
                'paid_orders' => Order::query()->where('payment_status', 'paid')->count(),
                'sales_total' => (float) Order::query()
                    ->whereIn('status', ['confirmed', 'completed'])
                    ->sum('total_amount'),
                'customers' => Customer::query()->count(),
                'products' => Product::query()->count(),
                'paid_payments' => Payment::query()->where('status', 'paid')->count(),
            ]);
        }

        return view('workspace.dashboard', [
            'workspace' => $workspace,
            'isCommercial' => $isCommercial,
            'stats' => $stats,
            'entitlements' => $this->featureAccessService->entitlementsSnapshot($workspace),
            'subscriptionUsage' => [
                'plan_name' => $currentSubscription?->plan?->name,
                'status' => $subscriptionStatus,
                'expires_at' => $currentSubscription?->current_period_end
                    ?? $currentSubscription?->trial_ends_at
                    ?? $currentSubscription?->ends_at,
                'messages_used' => $messagesUsed,
                'messages_limit' => $messageLimitValue,
                'usage_percent' => $usagePercent,
                'is_near_limit' => $usagePercent !== null && $usagePercent >= 80 && $usagePercent <= 100,
                'is_over_limit' => $messageLimitValue !== null && $messageLimitValue > 0 && $messagesUsed > $messageLimitValue,
            ],
        ]);
    }
}
