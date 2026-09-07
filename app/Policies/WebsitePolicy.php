<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Website\Website;
use App\Policies\Concerns\ChecksWorkspaceMembership;

class WebsitePolicy
{
    use ChecksWorkspaceMembership;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Website $website): bool
    {
        return $this->hasMembership($user, $website->workspace);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Website $website): bool
    {
        return $this->hasAnyWorkspaceRole($user, $website->workspace, ['owner', 'admin', 'manager', 'receptionist']);
    }

    public function delete(User $user, Website $website): bool
    {
        return $this->hasAnyWorkspaceRole($user, $website->workspace, ['owner', 'admin']);
    }

    public function publish(User $user, Website $website): bool
    {
        return $this->hasAnyWorkspaceRole($user, $website->workspace, ['owner', 'admin', 'manager']);
    }

    public function manageDomains(User $user, Website $website): bool
    {
        return $this->hasAnyWorkspaceRole($user, $website->workspace, ['owner', 'admin', 'manager']);
    }
}
