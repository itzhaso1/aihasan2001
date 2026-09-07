<?php

namespace Tests\Feature\Feature\Appointments;

use App\Models\Appointment\AppointmentHoliday;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentHolidaysTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_list_create_and_delete_holidays(): void
    {
        [$owner, $workspace] = $this->createWorkspaceOwner('company');

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('workspace.appointments.holidays.index'))
            ->assertOk()
            ->assertSee('الإجازات والعطل');

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('workspace.appointments.holidays.store'), [
                'holiday_date' => '2026-09-23',
                'reason' => 'عطلة وطنية',
                'is_recurring' => 1,
            ])
            ->assertRedirect(route('workspace.appointments.holidays.index'));

        $holiday = AppointmentHoliday::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->first();

        $this->assertNotNull($holiday);
        $this->assertSame('عطلة وطنية', $holiday->reason);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->delete(route('workspace.appointments.holidays.destroy', $holiday))
            ->assertRedirect(route('workspace.appointments.holidays.index'));

        $this->assertSoftDeleted('appointment_holidays', ['id' => $holiday->id]);
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

        $this->enableWorkspaceFeature($workspace, 'appointments');

        return [$user, $workspace];
    }
}
