<?php

namespace Tests;

use App\Models\Workspace;
use App\Models\WorkspaceFeatureFlag;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Carbon;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    protected function enableWorkspaceFeature(Workspace $workspace, string $feature, bool $enabled = true): void
    {
        WorkspaceFeatureFlag::withoutGlobalScopes()->updateOrCreate(
            ['workspace_id' => $workspace->id, 'feature_key' => $feature],
            ['workspace_id' => $workspace->id, 'feature_key' => $feature, 'enabled' => $enabled, 'source' => 'manual']
        );
    }

    protected function nextOpenAppointmentSlot(string $timezone = 'Asia/Riyadh', int $hour = 10, int $minute = 0): Carbon
    {
        return Carbon::now($timezone)->next(Carbon::MONDAY)->setTime($hour, $minute);
    }
}
