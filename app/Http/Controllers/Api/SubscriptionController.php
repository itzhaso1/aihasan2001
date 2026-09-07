<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Workspace;
use App\Services\Subscription\SubscriptionService;
use App\Support\Tenancy\WorkspaceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService,
        private readonly WorkspaceContext $workspaceContext,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $workspace = $this->workspaceContext->workspace();
        abort_unless($workspace, 422, 'Workspace not resolved.');

        return response()->json([
            'data' => [
                'current' => $this->subscriptionService->current($workspace),
                'plans' => $this->subscriptionService->availablePlans($workspace->type),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $workspace = $this->workspaceContext->workspace();
        abort_unless($workspace, 422, 'Workspace not resolved.');

        $validated = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
        ]);

        $plan = Plan::query()->findOrFail($validated['plan_id']);
        $subscription = $this->subscriptionService->activatePlan($workspace, $plan);

        return response()->json(['data' => $subscription], 201);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json(status: 405);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        return response()->json(status: 405);
    }

    public function destroy(string $id): JsonResponse
    {
        return response()->json(status: 405);
    }
}
