<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuthIdentity;
use App\Models\User;
use App\Services\Workspace\WorkspaceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialLoginController extends Controller
{
    public function __construct(
        private readonly WorkspaceService $workspaceService,
    ) {}

    public function redirect(string $provider)
    {
        abort_unless(in_array($provider, ['google', 'facebook'], true), 404);

        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider)
    {
        abort_unless(in_array($provider, ['google', 'facebook'], true), 404);

        $socialUser = Socialite::driver($provider)->user();

        $identity = AuthIdentity::query()
            ->where('provider', $provider)
            ->where('provider_user_id', $socialUser->getId())
            ->first();

        if ($identity) {
            $user = $identity->user;
        } else {
            $email = $socialUser->getEmail() ?: 'social-'.Str::lower(Str::random(12)).'@local.invalid';

            $user = User::query()->firstOrCreate(
                ['email' => $email],
                [
                    'name' => $socialUser->getName() ?: 'Social User',
                    'password' => Str::password(24),
                    'email_verified_at' => now(),
                ]
            );

            AuthIdentity::query()->create([
                'user_id' => $user->id,
                'provider' => $provider,
                'provider_user_id' => $socialUser->getId(),
                'provider_email' => $socialUser->getEmail(),
                'provider_data' => [
                    'name' => $socialUser->getName(),
                    'nickname' => $socialUser->getNickname(),
                    'avatar' => $socialUser->getAvatar(),
                ],
            ]);
        }

        $workspace = $user->workspaces()->wherePivot('status', 'active')->first()
            ?? $this->workspaceService->createForUser($user, 'individual');

        Auth::login($user);
        request()->session()->put('current_workspace_id', $workspace->id);

        return redirect()->route('workspace.choose');
    }
}
