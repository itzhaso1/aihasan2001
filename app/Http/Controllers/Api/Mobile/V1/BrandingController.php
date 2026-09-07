<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Api\Mobile\Concerns\ResolvesMobileWorkspace;
use App\Http\Controllers\Api\Mobile\MobileController;
use App\Support\Tenancy\WorkspaceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;

class BrandingController extends MobileController
{
    use ResolvesMobileWorkspace;

    public function __construct(
        private readonly WorkspaceContext $workspaceContext,
    ) {}

    public function show(): JsonResponse
    {
        $workspace = $this->requireWorkspace($this->workspaceContext);

        $logoPath = public_path('images/hasim-logo.png');
        $logoUrl = File::exists($logoPath) ? asset('images/hasim-logo.png') : null;

        return $this->ok([
            'platform' => [
                'name' => 'حاسم',
                'primary_color' => '#06C2A4',
                'logo_url' => $logoUrl,
            ],
            'workspace' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'type' => $workspace->type,
            ],
        ]);
    }
}
