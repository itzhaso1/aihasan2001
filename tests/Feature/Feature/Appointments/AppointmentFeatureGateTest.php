<?php

namespace Tests\Feature\Feature\Appointments;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentFeatureGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_appointments_require_feature_flag(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create([
            'owner_user_id' => $user->id,
            'type' => 'company',
        ]);
        $workspace->users()->attach($user->id, [
            'membership_role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('workspace.appointments.dashboard'))
            ->assertRedirect(route('workspace.subscriptions.index'));

        $this->enableWorkspaceFeature($workspace, 'appointments');

        $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('workspace.appointments.dashboard'))
            ->assertOk();
    }

    public function test_mobile_appointment_routes_require_feature_flag(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create([
            'owner_user_id' => $user->id,
            'type' => 'company',
        ]);
        $workspace->users()->attach($user->id, [
            'membership_role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $token = $user->createToken('mobile');
        $token->accessToken->forceFill(['workspace_id' => $workspace->id])->save();

        $this->withToken($token->plainTextToken)
            ->withHeader('X-Workspace-Id', (string) $workspace->id)
            ->getJson('/api/mobile/v1/appointments/today')
            ->assertStatus(402);

        $this->enableWorkspaceFeature($workspace, 'appointments');

        $this->withToken($token->plainTextToken)
            ->withHeader('X-Workspace-Id', (string) $workspace->id)
            ->getJson('/api/mobile/v1/appointments/today')
            ->assertOk();
    }
}
