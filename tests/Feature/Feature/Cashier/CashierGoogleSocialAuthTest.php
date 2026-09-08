<?php

namespace Tests\Feature\Feature\Cashier;

use App\Models\AuthIdentity;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\FoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class CashierGoogleSocialAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_social_login_returns_the_same_cashier_envelope(): void
    {
        $this->seed(FoundationSeeder::class);
        [$owner, $workspace] = $this->createWorkspaceOwner('store');
        $this->fakeGoogleUser($owner->email, $owner->name, 'google-user-1');

        $login = $this->postJson('/api/cashier/v1/auth/social', [
            'provider' => 'google',
            'access_token' => 'ya29.google-token',
            'device_name' => 'كاشير حاسم',
            'device_type' => 'cashier',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.id', $owner->id)
            ->assertJsonPath('data.user.email', $owner->email)
            ->assertJsonPath('data.workspace.id', $workspace->id)
            ->assertJsonPath('data.workspaces.0.id', $workspace->id)
            ->assertJsonPath('data.workspaces.0.pos_enabled', true)
            ->assertJsonPath('data.pos_enabled', true);

        $this->assertNotEmpty($login->json('data.token'));
        $this->assertSame(1, AuthIdentity::query()->where('provider', 'google')->count());
    }

    public function test_google_social_login_rejects_missing_token(): void
    {
        $this->seed(FoundationSeeder::class);

        $this->postJson('/api/cashier/v1/auth/social', [
            'provider' => 'google',
            'device_name' => 'كاشير حاسم',
        ])
            ->assertStatus(422);
    }

    public function test_google_social_login_rejects_invalid_provider(): void
    {
        $this->seed(FoundationSeeder::class);

        $this->postJson('/api/cashier/v1/auth/social', [
            'provider' => 'twitter',
            'access_token' => 'token',
        ])
            ->assertStatus(422);
    }

    /**
     * @return array{0: User, 1: Workspace}
     */
    private function createWorkspaceOwner(string $workspaceType): array
    {
        $user = User::factory()->create([
            'password' => bcrypt('password'),
        ]);
        $workspace = Workspace::factory()->create([
            'owner_user_id' => $user->id,
            'type' => $workspaceType,
        ]);

        $workspace->users()->attach($user->id, [
            'membership_role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        foreach (['pos', 'qr_menu', 'products', 'orders'] as $feature) {
            \App\Models\WorkspaceFeatureFlag::withoutGlobalScopes()->updateOrCreate(
                ['workspace_id' => $workspace->id, 'feature_key' => $feature],
                ['workspace_id' => $workspace->id, 'feature_key' => $feature, 'enabled' => true, 'source' => 'manual']
            );
        }

        $plan = \App\Models\Plan::query()
            ->where('workspace_type', $workspaceType)
            ->where('is_active', true)
            ->orderByDesc('price')
            ->first();

        if ($plan) {
            \App\Models\Subscription::withoutGlobalScopes()->updateOrCreate(
                ['workspace_id' => $workspace->id],
                [
                    'workspace_id' => $workspace->id,
                    'plan_id' => $plan->id,
                    'status' => 'active',
                    'starts_at' => now()->subDay(),
                    'ends_at' => now()->addMonth(),
                ]
            );
        }

        return [$user, $workspace];
    }

    private function fakeGoogleUser(string $email, string $name, string $googleId): void
    {
        $socialUser = Mockery::mock(SocialiteUser::class);
        $socialUser->shouldReceive('getId')->andReturn($googleId);
        $socialUser->shouldReceive('getEmail')->andReturn($email);
        $socialUser->shouldReceive('getName')->andReturn($name);
        $socialUser->shouldReceive('getNickname')->andReturnNull();
        $socialUser->shouldReceive('getAvatar')->andReturnNull();

        $driver = Mockery::mock();
        $driver->shouldReceive('stateless')->andReturnSelf();
        $driver->shouldReceive('userFromToken')->with('ya29.google-token')->andReturn($socialUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);
    }
}
