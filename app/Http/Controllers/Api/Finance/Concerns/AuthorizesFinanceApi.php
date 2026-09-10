<?php

namespace App\Http\Controllers\Api\Finance\Concerns;

use App\Exceptions\Api\ApiErrorCode;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Tenancy\WorkspaceContext;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

trait AuthorizesFinanceApi
{
    protected function requireWorkspace(WorkspaceContext $workspaceContext): Workspace
    {
        $workspace = $workspaceContext->workspace();
        if (! $workspace) {
            throw new HttpResponseException($this->fail(
                'Workspace context is required.',
                ApiErrorCode::Forbidden,
                403,
            ));
        }

        return $workspace;
    }

    protected function authorizeFinanceApi(Request $request, Workspace $workspace, string $permission): User
    {
        /** @var User|null $user */
        $user = $request->user();
        if (! $user) {
            throw new HttpResponseException($this->fail(
                'Unauthenticated.',
                ApiErrorCode::Unauthorized,
                401,
            ));
        }

        if ($user->can($permission) || $user->can('workspace.manage')) {
            return $user;
        }

        $isElevatedMember = $workspace->users()
            ->where('users.id', $user->id)
            ->wherePivot('status', 'active')
            ->wherePivotIn('membership_role', ['owner', 'admin', 'manager'])
            ->exists();

        if (! $isElevatedMember) {
            throw new HttpResponseException($this->fail(
                'You are not allowed to access this financial resource.',
                ApiErrorCode::Forbidden,
                403,
            ));
        }

        return $user;
    }
}
