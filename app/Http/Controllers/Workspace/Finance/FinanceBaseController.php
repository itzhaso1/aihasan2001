<?php

namespace App\Http\Controllers\Workspace\Finance;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Workspace\Concerns\InteractsWithWorkspace;
use App\Models\User;
use Illuminate\Http\Request;

abstract class FinanceBaseController extends Controller
{
    use InteractsWithWorkspace;

    protected function authorizeFinance(Request $request, string $permission = 'finance.view'): void
    {
        /** @var User|null $user */
        $user = $request->user();
        abort_unless($user, 403);

        if ($user->can($permission)) {
            return;
        }

        $workspace = $this->currentWorkspace();
        $isElevatedMember = $workspace->users()
            ->where('users.id', $user->id)
            ->wherePivot('status', 'active')
            ->wherePivotIn('membership_role', ['owner', 'admin', 'manager'])
            ->exists();

        abort_unless($isElevatedMember, 403, 'You are not allowed to access financial module.');
    }
}
