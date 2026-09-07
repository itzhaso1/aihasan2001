<?php

namespace App\Jobs;

use App\Models\AiLog;
use App\Models\Workspace;
use App\Services\Feature\FeatureAccessService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class UpdateAIUsage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $workspaceId,
        public readonly int|float $tokens = 0,
    ) {}

    public function handle(FeatureAccessService $featureAccess): void
    {
        $usage = AiLog::withoutGlobalScopes()
            ->where('workspace_id', $this->workspaceId)
            ->sum('tokens_used');

        Cache::put('workspace:'.$this->workspaceId.':ai_usage_tokens', $usage, now()->addHours(2));

        $workspace = Workspace::withoutGlobalScopes()->find($this->workspaceId);
        if (! $workspace) {
            return;
        }

        // Incremental consume from this generation (AIService already consumed when tokens known;
        // this path covers callers that only dispatch the job). Use enforce:false to avoid
        // double-throw when AIService already recorded usage under hard limits.
        if ($this->tokens > 0) {
            $featureAccess->consumeUsage($workspace, 'ai_tokens', $this->tokens, enforce: false);
            $featureAccess->consumeUsage($workspace, 'ai_usage', 1, enforce: false);
        }
    }
}
