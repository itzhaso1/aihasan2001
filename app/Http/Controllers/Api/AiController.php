<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\GenerateReplyRequest;
use App\Models\AiSetting;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\AI\AIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiController extends Controller
{
    public function __construct(
        private readonly AIService $aiService,
    ) {}

    public function settings(): JsonResponse
    {
        $settings = AiSetting::query()->first();

        return response()->json(['data' => $settings]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'instructions' => ['nullable', 'string'],
            'tone' => ['nullable', 'string', 'max:255'],
            'reply_style' => ['nullable', 'string', 'max:255'],
            'rules' => ['nullable', 'array'],
            'business_information' => ['nullable', 'array'],
            'provider' => ['nullable', 'string', 'max:64'],
            'model' => ['nullable', 'string', 'max:255'],
            'max_tokens' => ['nullable', 'integer', 'min:64', 'max:4096'],
            'temperature' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $settings = AiSetting::query()->firstOrCreate([], [
            'name' => 'AI Assistant',
            'provider' => config('ai.default_provider', 'google_ai_studio'),
            'model' => config('ai.google_ai_studio.model', 'gemini-2.5-flash'),
            'temperature' => config('ai.google_ai_studio.temperature', 0.3),
            'max_tokens' => config('ai.google_ai_studio.max_tokens', 1024),
        ]);
        $settings->update($validated);

        return response()->json(['data' => $settings->refresh()]);
    }

    public function generateReply(GenerateReplyRequest $request): JsonResponse
    {
        $conversation = Conversation::query()->findOrFail($request->integer('conversation_id'));
        $message = Message::query()->findOrFail($request->integer('message_id'));

        $reply = $this->aiService->generateReply($conversation, $message);

        $outbound = Message::query()->create([
            'workspace_id' => $conversation->workspace_id,
            'conversation_id' => $conversation->id,
            'customer_id' => $conversation->customer_id,
            'direction' => 'outbound',
            'message_type' => 'text',
            'content' => $reply,
            'ai_generated' => true,
        ]);

        return response()->json([
            'data' => [
                'reply' => $reply,
                'message' => $outbound,
            ],
        ]);
    }
}
