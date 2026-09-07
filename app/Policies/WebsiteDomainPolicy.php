<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Website\WebsiteDomain;
use App\Policies\Concerns\ChecksWorkspaceMembership;

class WebsiteDomainPolicy
{
    use ChecksWorkspaceMembership;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, WebsiteDomain $domain): bool
    {
        return $this->hasMembership($user, $domain->workspace);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, WebsiteDomain $domain): bool
    {
        return $this->hasAnyWorkspaceRole($user, $domain->workspace, ['owner', 'admin', 'manager']);
    }

    public function delete(User $user, WebsiteDomain $domain): bool
    {
        return $this->hasAnyWorkspaceRole($user, $domain->workspace, ['owner', 'admin']);
    }

    public function purchase(User $user, WebsiteDomain $domain): bool
    {
        return $this->update($user, $domain);
    }

    public function renew(User $user, WebsiteDomain $domain): bool
    {
        return $this->update($user, $domain);
    }

    public function setPrimary(User $user, WebsiteDomain $domain): bool
    {
        return $this->update($user, $domain);
    }
}
