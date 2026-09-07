<?php

namespace App\Support\Tenancy;

use App\Models\Workspace;

class WorkspaceContext
{
    private ?Workspace $workspace = null;

    public function set(Workspace $workspace): void
    {
        $this->workspace = $workspace;
    }

    public function clear(): void
    {
        $this->workspace = null;
    }

    public function workspace(): ?Workspace
    {
        return $this->workspace;
    }

    public function workspaceId(): ?int
    {
        return $this->workspace?->id;
    }

    public function workspaceType(): ?string
    {
        return $this->workspace?->type;
    }

    public function isResolved(): bool
    {
        return $this->workspace !== null;
    }
}
