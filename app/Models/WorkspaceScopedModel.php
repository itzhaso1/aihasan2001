<?php

namespace App\Models;

use App\Services\Workspace\WorkspaceResolverService;
use App\Support\Tenancy\WorkspaceContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

abstract class WorkspaceScopedModel extends Model
{
    public function resolveRouteBinding($value, $field = null)
    {
        $this->ensureWorkspaceContextForBinding();

        $workspaceId = app(WorkspaceContext::class)->workspaceId();
        $user = $this->bindingUser();

        if ($user && $workspaceId === null) {
            return null;
        }

        return parent::resolveRouteBinding($value, $field);
    }

    public function resolveChildRouteBinding($childType, $value, $field)
    {
        $this->ensureWorkspaceContextForBinding();

        $workspaceId = app(WorkspaceContext::class)->workspaceId();
        $user = $this->bindingUser();

        if ($user && $workspaceId === null) {
            return null;
        }

        return parent::resolveChildRouteBinding($childType, $value, $field);
    }

    private function ensureWorkspaceContextForBinding(): void
    {
        $context = app(WorkspaceContext::class);
        if ($context->workspaceId() !== null) {
            return;
        }

        $request = request();
        $user = $this->bindingUser();
        if (! $user) {
            return;
        }

        $workspace = app(WorkspaceResolverService::class)->resolveFromRequest($request, $user);
        if (! $workspace) {
            return;
        }

        $context->set($workspace);
        app(PermissionRegistrar::class)->setPermissionsTeamId($workspace->id);
    }

    private function bindingUser(): mixed
    {
        $request = request();

        return $request->user()
            ?? Auth::guard('web')->user()
            ?? Auth::guard('sanctum')->user();
    }

    protected static function booted(): void
    {
        static::addGlobalScope('workspace', function (Builder $builder): void {
            $workspaceId = app(WorkspaceContext::class)->workspaceId();

            if ($workspaceId !== null) {
                $builder->where($builder->getModel()->qualifyColumn('workspace_id'), $workspaceId);
            }
        });

        static::creating(function (Model $model): void {
            $workspaceId = app(WorkspaceContext::class)->workspaceId();
            $isPlatformAdmin = auth('platform_admin')->check();
            $incomingWorkspaceId = $model->getAttribute('workspace_id');

            if ($isPlatformAdmin) {
                if (! $incomingWorkspaceId) {
                    throw new RuntimeException('workspace_id is required for platform admin writes.');
                }

                return;
            }

            if ($workspaceId === null) {
                if (! $incomingWorkspaceId) {
                    // audit_logs.workspace_id is intentionally nullable for system/global entities
                    // (e.g. website templates) that are still audit-worthy.
                    if ($model instanceof AuditLog) {
                        return;
                    }

                    throw new RuntimeException('Workspace context is required for creating workspace scoped records.');
                }

                return;
            }

            if ($incomingWorkspaceId !== null && (int) $incomingWorkspaceId !== (int) $workspaceId) {
                throw new RuntimeException('Cross-workspace write attempt detected.');
            }

            $model->setAttribute('workspace_id', $workspaceId);
        });

        static::updating(function (Model $model): void {
            $workspaceId = app(WorkspaceContext::class)->workspaceId();
            if (auth('platform_admin')->check()) {
                return;
            }
            if ($workspaceId === null) {
                return;
            }

            $incomingWorkspaceId = $model->getAttribute('workspace_id');
            if ($incomingWorkspaceId !== null && (int) $incomingWorkspaceId !== (int) $workspaceId) {
                throw new RuntimeException('Workspace_id cannot be changed outside current workspace context.');
            }
        });
    }
}
