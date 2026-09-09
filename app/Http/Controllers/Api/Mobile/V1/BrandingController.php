<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Api\Mobile\Concerns\ResolvesMobileWorkspace;
use App\Http\Controllers\Api\Mobile\MobileController;
use App\Services\Platform\PlatformBranding;
use App\Support\Tenancy\WorkspaceContext;
use Illuminate\Http\JsonResponse;

class BrandingController extends MobileController
{
    use ResolvesMobileWorkspace;

    public function __construct(
        private readonly WorkspaceContext $workspaceContext,
        private readonly PlatformBranding $platformBranding,
    ) {}

    public function show(): JsonResponse
    {
        $workspace = $this->requireWorkspace($this->workspaceContext);

        return $this->ok([
            'platform' => [
                'name' => $this->platformBranding->siteName(),
                'primary_color' => '#06C2A4',
                'logo_url' => $this->platformBranding->logoUrl(),
            ],
            'workspace' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'type' => $workspace->type,
            ],
        ]);
    }
}
