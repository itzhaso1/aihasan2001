<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Api\Mobile\Concerns\ResolvesMobileWorkspace;
use App\Http\Controllers\Api\Mobile\MobileController;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\AI\AIService;
use App\Services\Feature\FeatureAccessService;
use App\Support\Tenancy\WorkspaceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AiController extends MobileController
{
    use ResolvesMobileWorkspace;

    public function __construct(
        private readonly AIService $aiService,
        private readonly FeatureAccessService $featureAccessService,
        private readonly WorkspaceContext $workspaceContext,
    ) {}

    public function suggestReply(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'conversation_id' => ['required', 'integer'],
            'content' => ['nullable', 'string'],
            'persist' => ['nullable', 'boolean'],
        ]);

        $conversation = Conversation::query()->findOrFail($validated['conversation_id']);
        $this->authorize('view', $conversation);

        $workspace = $this->requireWorkspace($this->workspaceContext);
        $user = $request->user();
        if (! $user || ! $this->featureAccessService->hasFeature($user, $workspace, 'ai')) {
            return $this->fail('ميزة الذكاء الاصطناعي غير متاحة لباقتك.', 403);
        }

        if ($request->boolean('persist')) {
            return $this->fail('حفظ الرد الآلي غير مدعوم من هذا المسار.', 422);
        }

        $latestInbound = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('direction', 'inbound')
            ->latest('id')
            ->first();

        $inbound = new Message([
            'content' => $validated['content'] ?? $latestInbound?->content ?? '',
            'direction' => 'inbound',
            'conversation_id' => $conversation->id,
            'workspace_id' => $conversation->workspace_id,
        ]);
        $inbound->setRelation('conversation', $conversation);

        try {
            $suggestion = $this->aiService->generateReply($conversation, $inbound);
        } catch (\Throwable) {
            return $this->fail('تعذر توليد اقتراح الرد حالياً.', 503);
        }

        return $this->ok([
            'suggestion' => $suggestion,
            'meta' => ['persisted' => false],
        ]);
    }

    public function summarizeConversation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'conversation_id' => ['required', 'integer'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $conversation = Conversation::query()->findOrFail($validated['conversation_id']);
        $this->authorize('view', $conversation);

        $limit = max(1, min(50, (int) ($validated['limit'] ?? 20)));

        $messages = Message::query()
            ->where('conversation_id', $conversation->id)
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'content', 'direction', 'created_at'])
            ->reverse()
            ->values();

        $transcript = $messages
            ->map(fn (Message $message): string => trim((string) $message->content))
            ->filter()
            ->implode("\n");

        if ($transcript === '') {
            return $this->ok([
                'summary' => 'لا توجد رسائل كافية للتلخيص.',
                'meta' => ['source' => 'local', 'message_count' => 0],
            ]);
        }

        $workspace = $this->requireWorkspace($this->workspaceContext);
        $user = $request->user();

        if ($user && $this->featureAccessService->hasFeature($user, $workspace, 'ai')) {
            $prompt = "لخص المحادثة التالية بين العميل والفريق بشكل مختصر وواضح بالعربية:\n\n".$transcript;
            $synthetic = new Message([
                'content' => $prompt,
                'direction' => 'inbound',
                'conversation_id' => $conversation->id,
                'workspace_id' => $conversation->workspace_id,
            ]);
            $synthetic->setRelation('conversation', $conversation);

            try {
                $summary = $this->aiService->generateReply($conversation, $synthetic);

                return $this->ok([
                    'summary' => $summary,
                    'meta' => [
                        'source' => 'ai',
                        'message_count' => $messages->count(),
                    ],
                ]);
            } catch (\Throwable) {
                // fall through to local summary
            }
        }

        return $this->ok([
            'summary' => Str::limit($transcript, 500),
            'meta' => [
                'source' => 'local',
                'message_count' => $messages->count(),
            ],
        ]);
    }
}
