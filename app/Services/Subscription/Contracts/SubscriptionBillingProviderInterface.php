<?php

namespace App\Services\Subscription\Contracts;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Workspace;

/**
 * Abstraction for commercial subscription activation / billing.
 *
 * integration_mode:
 * - "local" — activates plans via SubscriptionService without charging a card (dev / manual).
 * - "stripe" (future) — paid Stripe Billing; not implemented here on purpose.
 */
interface SubscriptionBillingProviderInterface
{
    /**
     * Activate (or switch to) a plan for the workspace.
     *
     * @param  array<string, mixed>  $options
     */
    public function activatePlan(Workspace $workspace, Plan $plan, array $options = []): Subscription;

    /**
     * Human-readable mode for checkout / admin UI (e.g. "local").
     */
    public function integrationMode(): string;
}
