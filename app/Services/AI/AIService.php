<?php

namespace App\Services\AI;

use App\Exceptions\FeatureNotAvailableException;
use App\Jobs\UpdateAIUsage;
use App\Models\AiLog;
use App\Models\AiSetting;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Workspace;
use App\Services\Feature\FeatureAccessService;

class AIService
{
    public function __construct(
        private readonly AiProviderManager $providerManager,
        private readonly FeatureAccessService $featureAccess,
    ) {}

    public function generateReply(Conversation $conversation, Message $message): string
    {
        $workspace = Workspace::query()->find($conversation->workspace_id)
            ?? Workspace::withoutGlobalScopes()->find($conversation->workspace_id);

        if ($workspace) {
            $this->assertAiFeature($workspace);
            // Soft pre-check: block when already over hard limit (estimate 1 unit / ~256 tokens).
            $this->featureAccess->assertWithinLimit($workspace, 'ai_usage', 1);
            $this->featureAccess->assertWithinLimit($workspace, 'ai_tokens', 256);
        }

        $setting = AiSetting::query()
            ->where('workspace_id', $conversation->workspace_id)
            ->first() ?? new AiSetting([
            'name' => 'AI Assistant',
            'instructions' => 'كن مساعدًا مهذبًا ومختصرًا.',
            'provider' => config('ai.default_provider', 'google_ai_studio'),
            'model' => $this->defaultModel(config('ai.default_provider', 'google_ai_studio')),
            'temperature' => $this->defaultTemperature(config('ai.default_provider', 'google_ai_studio')),
            'max_tokens' => $this->defaultMaxTokens(config('ai.default_provider', 'google_ai_studio')),
        ]);

        $providerName = $this->providerManager->normalize((string) ($setting->provider ?: config('ai.default_provider', 'google_ai_studio')));
        $provider = $this->providerManager->resolve($providerName);

        $products = Product::query()
            ->where('workspace_id', $conversation->workspace_id)
            ->where('status', 'active')
            ->with(['variants' => fn ($query) => $query
                ->where('workspace_id', $conversation->workspace_id)
                ->where('status', 'active')])
            ->limit(20)
            ->get(['id', 'name', 'description', 'sku', 'price', 'sale_price', 'stock', 'currency', 'brand'])
            ->map(fn (Product $product): array => [
                'name' => $product->name,
                'description' => $product->description,
                'sku' => $product->sku,
                'price' => $product->sale_price ?: $product->price,
                'stock' => $product->stock,
                'currency' => $product->currency,
                'brand' => $product->brand,
                'variants' => $product->variants
                    ->map(fn (ProductVariant $variant): array => [
                        'name' => $variant->name,
                        'sku' => $variant->sku,
                        'attributes' => $variant->attributes ?? [],
                        'price' => $variant->sale_price ?: $variant->price,
                        'stock' => $variant->stock,
                    ])->values()->all(),
            ])->all();

        $messages = [
            [
                'role' => 'system',
                'content' => trim(($setting->instructions ?? '')."\n\n".
                    'قواعد مهمة: استخدم فقط بيانات نفس workspace الحالية ولا تختلق بيانات غير موجودة. '.
                    "إذا كان المنتج غير متوفر أخبر العميل بذلك بوضوح.\n\n".
                    "المنتجات المتاحة داخل نفس مساحة العمل:\n".json_encode($products, JSON_UNESCAPED_UNICODE)),
            ],
            [
                'role' => 'user',
                'content' => $message->content ?? '',
            ],
        ];

        try {
            $result = $provider->generate(
                messages: $messages,
                model: (string) ($setting->model ?: $this->defaultModel($providerName)),
                temperature: (float) ($setting->temperature ?: $this->defaultTemperature($providerName)),
                maxTokens: (int) ($setting->max_tokens ?: $this->defaultMaxTokens($providerName)),
            );

            $tokensUsed = (int) ($result['tokens_used'] ?? 0);

            AiLog::query()->create([
                'workspace_id' => $conversation->workspace_id,
                'conversation_id' => $conversation->id,
                'message_id' => $message->id,
                'type' => 'reply',
                'input_payload' => ['messages' => $messages],
                'output_payload' => $result['raw'],
                'tokens_used' => $tokensUsed,
                'status' => 'success',
            ]);

            if ($workspace) {
                $this->featureAccess->consumeUsage($workspace, 'ai_usage', 1, enforce: true);
                if ($tokensUsed > 0) {
                    $this->featureAccess->consumeUsage($workspace, 'ai_tokens', $tokensUsed, enforce: true);
                }
                // Cache sync only — usage already consumed above. Pass 0 tokens to avoid double-count.
                UpdateAIUsage::dispatch($workspace->id, 0);
            }

            return $result['content'] ?: 'تعذر إنشاء رد مناسب حالياً.';
        } catch (FeatureNotAvailableException|\App\Exceptions\UsageLimitExceededException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            AiLog::query()->create([
                'workspace_id' => $conversation->workspace_id,
                'conversation_id' => $conversation->id,
                'message_id' => $message->id,
                'type' => 'reply',
                'input_payload' => ['messages' => $messages],
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);

            return 'تعذر إنشاء الرد الآن. حاول مرة أخرى لاحقًا.';
        }
    }

    private function assertAiFeature(Workspace $workspace): void
    {
        $user = auth()->user();

        if ($user) {
            $this->featureAccess->assertFeature($user, $workspace, 'ai');

            return;
        }

        if (! $this->featureAccess->workspaceHasFeature($workspace, 'ai')) {
            throw new FeatureNotAvailableException(
                feature: 'ai',
                requiredPlan: $this->featureAccess->suggestedPlanForFeature('ai'),
                message: __('ميزة الذكاء الاصطناعي غير متاحة في باقتك الحالية.'),
            );
        }
    }

    private function defaultModel(string $provider): string
    {
        if ($provider === 'google_ai_studio') {
            return (string) config('ai.google_ai_studio.model', config('services.google_ai_studio.model', 'gemini-2.5-flash'));
        }

        return (string) config('ai.'.$provider.'.model', config('ai.openai.model'));
    }

    private function defaultTemperature(string $provider): float
    {
        return (float) config('ai.'.$provider.'.temperature', 0.4);
    }

    private function defaultMaxTokens(string $provider): int
    {
        return (int) config('ai.'.$provider.'.max_tokens', 512);
    }
}
