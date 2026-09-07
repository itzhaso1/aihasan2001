<?php

namespace Tests\Feature\Feature\Appointments;

use App\Models\User;
use App\Models\Website\Website;
use App\Models\Workspace;
use App\Models\WorkspaceFeatureFlag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsiteTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_cannot_access_other_workspace_website_customizer(): void
    {
        [$ownerA, $workspaceA] = $this->createWorkspaceOwner('company');
        [, $workspaceB] = $this->createWorkspaceOwner('company');
        $this->enableWebsiteFeatures($workspaceA);
        $this->enableWebsiteFeatures($workspaceB);

        $websiteB = Website::withoutGlobalScopes()->create([
            'workspace_id' => $workspaceB->id,
            'name' => 'Workspace B Site',
            'slug' => 'workspace-b-site',
            'status' => 'draft',
            'preview_token' => 'ws-b-preview',
            'settings' => [],
            'theme' => [],
            'metadata' => [],
        ]);

        $this->actingAs($ownerA)
            ->withSession(['current_workspace_id' => $workspaceA->id])
            ->get(route('workspace.appointments.website.customize', $websiteB))
            ->assertNotFound();
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
            'status' => 'active',
        ]);

        $workspace->users()->attach($user->id, [
            'membership_role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        return [$user, $workspace];
    }

    private function enableWebsiteFeatures(Workspace $workspace): void
    {
        foreach (['website_builder', 'custom_domains', 'public_booking', 'appointments'] as $feature) {
            WorkspaceFeatureFlag::withoutGlobalScopes()->updateOrCreate(
                ['workspace_id' => $workspace->id, 'feature_key' => $feature],
                ['workspace_id' => $workspace->id, 'feature_key' => $feature, 'enabled' => true, 'source' => 'manual']
            );
        }
    }
}
