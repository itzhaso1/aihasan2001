<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\FeatureNotAvailableException;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Services\Appointments\AppointmentAiActionService;
use App\Services\Feature\FeatureAccessService;
use App\Services\Workspace\WorkspaceResolverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AppointmentAiActionController extends Controller
{
    public function __construct(
        private readonly AppointmentAiActionService $appointmentAiActionService,
        private readonly WorkspaceResolverService $workspaceResolverService,
    ) {}

    public function execute(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'string', Rule::in(AppointmentAiActionService::ALLOWED_ACTIONS)],
            'payload' => ['nullable', 'array'],
            'conversation_id' => ['nullable', 'integer'],
        ]);

        $workspace = $this->workspaceResolverService->resolveFromRequest($request, $request->user());
        abort_unless($workspace, 422, 'لا يمكن تحديد مساحة العمل.');

        $user = $request->user();
        abort_unless($user, 403);
        if (! app(FeatureAccessService::class)->hasFeature($user, $workspace, 'appointments')) {
            throw new FeatureNotAvailableException(
                feature: 'appointments',
                requiredPlan: app(FeatureAccessService::class)->suggestedPlanForFeature('appointments'),
            );
        }

        $this->assertActionPermission($request, (string) $validated['action']);

        $conversation = null;
        if (! empty($validated['conversation_id'])) {
            $conversation = Conversation::query()
                ->whereKey((int) $validated['conversation_id'])
                ->where('workspace_id', $workspace->id)
                ->first();
        }

        $result = $this->appointmentAiActionService->execute(
            workspace: $workspace,
            action: (string) $validated['action'],
            payload: is_array($validated['payload'] ?? null) ? $validated['payload'] : [],
            actor: $request->user(),
            conversation: $conversation,
        );

        return response()->json(['data' => $result]);
    }

    private function assertActionPermission(Request $request, string $action): void
    {
        $user = $request->user();
        abort_unless($user, 403);

        $permissionMap = [
            'create_appointment_request' => ['appointments.requests.manage', 'appointments.manage'],
            'get_appointment_request' => ['appointments.requests.view', 'appointments.view'],
            'suggest_slots' => ['appointments.requests.manage'],
            'select_slot' => ['appointments.requests.manage'],
            'approve_request' => ['appointments.requests.manage'],
            'reject_request' => ['appointments.requests.manage'],
            'create_booking' => ['appointments.manage'],
            'get_booking' => ['appointments.view'],
            'get_appointment' => ['appointments.view'],
            'request_reschedule' => ['appointments.requests.manage'],
            'request_cancellation' => ['appointments.requests.manage'],
            'send_payment_link' => ['appointments.billing.manage'],
            'send_confirmation' => ['appointments.manage'],
            'send_reminder' => ['appointments.manage'],
            'create_customer' => ['appointments.manage', 'appointments.requests.manage'],
            'get_customer' => ['appointments.view', 'appointments.requests.view'],
            'update_customer' => ['appointments.manage', 'appointments.requests.manage'],
        ];

        $allowedPermissions = $permissionMap[$action] ?? ['appointments.manage'];
        $allowed = $user->can('workspace.manage');
        foreach ($allowedPermissions as $permission) {
            if ($user->can($permission)) {
                $allowed = true;
                break;
            }
        }

        abort_unless($allowed, 403, 'ليس لديك صلاحية تنفيذ هذا الإجراء.');
    }
}
