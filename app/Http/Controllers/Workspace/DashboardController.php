<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Workspace\Concerns\InteractsWithWorkspace;
use App\Models\Subscription;
use App\Models\SubscriptionCheckoutSession;
use App\Services\Feature\FeatureAccessService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use InteractsWithWorkspace;

    public function __construct(private readonly FeatureAccessService $featureAccessService) {}

    public function index(): View
    {
        $workspace = $this->currentWorkspace();
        $currentSubscription = Subscription::query()
            ->with('plan')
            ->whereIn('status', ['active', 'trialing', 'past_due', 'expired'])
            ->latest('id')
            ->first();

        return view('workspace.dashboard', [
            'workspace' => $workspace,
            'currentSubscription' => $currentSubscription,
            'entitlements' => $this->featureAccessService->entitlementsSnapshot($workspace),
            'checkoutSessions' => SubscriptionCheckoutSession::query()
                ->with('plan')
                ->latest('id')
                ->limit(5)
                ->get(),
            'subscriptionHistory' => Subscription::query()
                ->with('plan')
                ->latest('id')
                ->limit(5)
                ->get(),
        ]);
    }
}
