<?php

namespace App\Services\Subscription;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionCheckoutSession;
use App\Models\Workspace;
use App\Services\Subscription\Contracts\SubscriptionBillingProviderInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SubscriptionService
{
    public function current(Workspace $workspace): ?Subscription
    {
        return Subscription::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->whereIn('status', [
                Subscription::STATUS_ACTIVE,
                Subscription::STATUS_TRIALING,
                Subscription::STATUS_PAST_DUE,
            ])
            ->latest('id')
            ->first();
    }

    public function activatePlan(Workspace $workspace, Plan $plan): Subscription
    {
        return DB::transaction(function () use ($workspace, $plan): Subscription {
            $this->cancelRunningSubscriptions($workspace);

            return Subscription::withoutGlobalScopes()->create([
                'workspace_id' => $workspace->id,
                'plan_id' => $plan->id,
                'status' => Subscription::STATUS_ACTIVE,
                'starts_at' => now(),
                'current_period_start' => now(),
                'current_period_end' => $this->nextPeriodEnd($plan->billing_period),
                'metadata' => [
                    'proration' => 'none',
                    'note' => 'Plan activated without proration.',
                ],
            ]);
        });
    }

    /**
     * Switch to a higher plan. Cancels the running subscription and activates the new one.
     * No proration is calculated — documented in subscription metadata.
     */
    public function upgrade(Workspace $workspace, Plan $plan): Subscription
    {
        return $this->switchPlan($workspace, $plan, 'upgrade');
    }

    /**
     * Switch to a lower plan. Cancels the running subscription and activates the new one.
     * No proration / credit is issued — documented in subscription metadata.
     */
    public function downgrade(Workspace $workspace, Plan $plan): Subscription
    {
        return $this->switchPlan($workspace, $plan, 'downgrade');
    }

    public function markPastDue(Subscription $subscription, ?\DateTimeInterface $graceEndsAt = null): Subscription
    {
        $subscription->forceFill([
            'status' => Subscription::STATUS_PAST_DUE,
            'grace_ends_at' => $graceEndsAt ?? now()->addDays(3),
            'failed_payment_count' => (int) $subscription->failed_payment_count + 1,
            'metadata' => array_merge(
                is_array($subscription->metadata) ? $subscription->metadata : [],
                ['marked_past_due_at' => now()->toIso8601String()]
            ),
        ])->save();

        return $subscription->refresh();
    }

    public function pause(Subscription $subscription, ?\DateTimeInterface $graceEndsAt = null): Subscription
    {
        $subscription->forceFill([
            'status' => Subscription::STATUS_PAUSED,
            'paused_at' => now(),
            'grace_ends_at' => $graceEndsAt,
            'metadata' => array_merge(
                is_array($subscription->metadata) ? $subscription->metadata : [],
                ['paused_via' => 'subscription_service']
            ),
        ])->save();

        return $subscription->refresh();
    }

    public function resume(Subscription $subscription): Subscription
    {
        $subscription->forceFill([
            'status' => Subscription::STATUS_ACTIVE,
            'paused_at' => null,
            'grace_ends_at' => null,
            'metadata' => array_merge(
                is_array($subscription->metadata) ? $subscription->metadata : [],
                ['resumed_at' => now()->toIso8601String()]
            ),
        ])->save();

        return $subscription->refresh();
    }

    /**
     * Reactivate a cancelled / expired / paused subscription onto its current plan period.
     */
    public function reactivate(Subscription $subscription): Subscription
    {
        $plan = $subscription->plan;
        $periodEnd = $plan
            ? $this->nextPeriodEnd($plan->billing_period)
            : now()->addMonth();

        $subscription->forceFill([
            'status' => Subscription::STATUS_ACTIVE,
            'starts_at' => $subscription->starts_at ?? now(),
            'cancelled_at' => null,
            'paused_at' => null,
            'ends_at' => null,
            'grace_ends_at' => null,
            'current_period_start' => now(),
            'current_period_end' => $periodEnd,
            'metadata' => array_merge(
                is_array($subscription->metadata) ? $subscription->metadata : [],
                [
                    'reactivated_at' => now()->toIso8601String(),
                    'proration' => 'none',
                ]
            ),
        ])->save();

        return $subscription->refresh();
    }

    public function createCheckoutSession(Workspace $workspace, Plan $plan, string $paymentProvider = 'hyperpay'): SubscriptionCheckoutSession
    {
        return SubscriptionCheckoutSession::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'plan_id' => $plan->id,
            'checkout_status' => 'awaiting_payment',
            'payment_status' => 'pending',
            'subscription_status' => 'pending_activation',
            'payment_provider' => $paymentProvider,
            'provider_checkout_id' => 'subchk_'.Str::lower(Str::random(24)),
            'amount' => $plan->price,
            'currency' => $plan->currency,
            'expires_at' => now()->addHours(12),
            'metadata' => [
                'integration_mode' => 'frontend-placeholder',
                'note' => 'Ready for HyperPay API checkout integration',
                'proration' => 'none',
            ],
        ]);
    }

    public function completeCheckoutAndActivate(
        Workspace $workspace,
        SubscriptionCheckoutSession $checkoutSession,
        ?string $paymentReference = null
    ): Subscription {
        return DB::transaction(function () use ($workspace, $checkoutSession, $paymentReference): Subscription {
            /** @var SubscriptionCheckoutSession|null $fresh */
            $fresh = SubscriptionCheckoutSession::withoutGlobalScopes()
                ->where('id', $checkoutSession->id)
                ->lockForUpdate()
                ->first();

            if (! $fresh) {
                throw new \RuntimeException('Checkout session was not found.');
            }

            if (
                $fresh->payment_status === 'paid'
                && $fresh->subscription_status === 'activated'
                && $fresh->activated_subscription_id
            ) {
                return Subscription::withoutGlobalScopes()->findOrFail($fresh->activated_subscription_id);
            }

            if ($fresh->checkout_status === 'cancelled' || $fresh->checkout_status === 'expired') {
                throw new \RuntimeException('Checkout session is not payable anymore.');
            }

            $this->cancelRunningSubscriptions($workspace);

            $subscription = Subscription::withoutGlobalScopes()->create([
                'workspace_id' => $workspace->id,
                'plan_id' => $fresh->plan_id,
                'status' => Subscription::STATUS_ACTIVE,
                'starts_at' => now(),
                'current_period_start' => now(),
                'current_period_end' => $this->nextPeriodEnd($fresh->plan->billing_period),
                'provider' => $fresh->payment_provider,
                'provider_subscription_id' => 'sub_'.Str::lower(Str::random(24)),
                'metadata' => [
                    'checkout_session_id' => $fresh->id,
                    'payment_reference' => $paymentReference,
                    'activated_via' => 'frontend-confirmation-flow',
                    'proration' => 'none',
                ],
            ]);

            $fresh->update([
                'checkout_status' => 'completed',
                'payment_status' => 'paid',
                'subscription_status' => 'activated',
                'paid_at' => now(),
                'payment_reference' => $paymentReference ?: 'pay_'.Str::lower(Str::random(12)),
                'activated_subscription_id' => $subscription->id,
            ]);

            return $subscription;
        });
    }

    public function availablePlans(?string $workspaceType = null): Collection
    {
        $officialCodes = ['starter', 'pro', 'business', 'enterprise'];

        $query = Plan::query()->where('is_active', true);

        if ($this->plansHaveColumn('is_public')) {
            $query->where('is_public', true);
        }

        if ($this->plansHaveColumn('is_official')) {
            $query->where(function ($q) use ($officialCodes): void {
                $q->where('is_official', true)
                    ->orWhereIn('code', $officialCodes);
            });
        } else {
            $query->whereIn('code', $officialCodes);
        }

        // Official catalog is shared across all workspace types — ignore workspace_type filter.
        if ($this->plansHaveColumn('sort_order')) {
            $query->orderBy('sort_order');
        }

        return $query->orderBy('price')->get();
    }

    /**
     * Cancel running + activate new plan. No fake proration.
     */
    private function switchPlan(Workspace $workspace, Plan $plan, string $direction): Subscription
    {
        if (app()->bound(SubscriptionBillingProviderInterface::class)) {
            $provider = app(SubscriptionBillingProviderInterface::class);
            $subscription = $provider->activatePlan($workspace, $plan, [
                'direction' => $direction,
                'proration' => 'none',
            ]);

            $metadata = is_array($subscription->metadata) ? $subscription->metadata : [];
            $metadata['switch_direction'] = $direction;
            $metadata['proration'] = 'none';
            $metadata['proration_note'] = 'No proration: previous subscription cancelled and new plan activated immediately.';
            $subscription->forceFill(['metadata' => $metadata])->save();

            return $subscription->refresh();
        }

        return DB::transaction(function () use ($workspace, $plan, $direction): Subscription {
            $this->cancelRunningSubscriptions($workspace);

            return Subscription::withoutGlobalScopes()->create([
                'workspace_id' => $workspace->id,
                'plan_id' => $plan->id,
                'status' => Subscription::STATUS_ACTIVE,
                'starts_at' => now(),
                'current_period_start' => now(),
                'current_period_end' => $this->nextPeriodEnd($plan->billing_period),
                'metadata' => [
                    'switch_direction' => $direction,
                    'proration' => 'none',
                    'proration_note' => 'No proration: previous subscription cancelled and new plan activated immediately.',
                ],
            ]);
        });
    }

    private function cancelRunningSubscriptions(Workspace $workspace): void
    {
        Subscription::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->whereIn('status', [
                Subscription::STATUS_TRIALING,
                Subscription::STATUS_ACTIVE,
                Subscription::STATUS_PAST_DUE,
                Subscription::STATUS_PAUSED,
            ])
            ->update([
                'status' => Subscription::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ]);
    }

    private function nextPeriodEnd(string $billingPeriod): \Illuminate\Support\Carbon
    {
        return match ($billingPeriod) {
            'yearly' => now()->addYear(),
            'lifetime' => now()->addYears(100),
            default => now()->addMonth(),
        };
    }

    private function plansHaveColumn(string $column): bool
    {
        try {
            return Schema::hasColumn('plans', $column);
        } catch (\Throwable) {
            return false;
        }
    }
}
