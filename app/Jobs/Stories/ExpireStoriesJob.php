<?php

namespace App\Jobs\Stories;

use App\Services\Stories\StoryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExpireStoriesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function handle(StoryService $storyService): void
    {
        $storyService->expireOldStories();
    }
}
