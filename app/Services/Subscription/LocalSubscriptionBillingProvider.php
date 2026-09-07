<?php

namespace App\Services\Subscription;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Workspace;
use App\Services\Subscription\Contracts\SubscriptionBillingProviderInterface;

/**
 * Local billing provider — no Stripe/HyperPay charge.
 * Activates through SubscriptionService::activatePlan.
 *
 * integration_mode = "local"
 */
class LocalSubscriptionBillingProvider implements SubscriptionBillingProviderInterface
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
    ) {}

    public function activatePlan(Workspace $workspace, Plan $plan, array $options = []): Subscription
    {
        $subscription = $this->subscriptions->activatePlan($workspace, $plan);

        $metadata = is_array($subscription->metadata) ? $subscription->metadata : [];
        $metadata['integration_mode'] = $this->integrationMode();
        $metadata['activated_via'] = 'local_subscription_billing_provider';
        if ($options !== []) {
            $metadata['options'] = $options;
        }

        $subscription->forceFill([
            'provider' => $subscription->provider ?: 'local',
            'metadata' => $metadata,
        ])->save();

        return $subscription->refresh();
    }

    public function integrationMode(): string
    {
        return 'local';
    }
}
