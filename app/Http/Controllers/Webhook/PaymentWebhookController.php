<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessPaymentWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentWebhookController extends Controller
{
    public function handle(Request $request, string $provider): JsonResponse
    {
        ProcessPaymentWebhook::dispatch(
            provider: $provider,
            headers: collect($request->headers->all())->map(fn ($value) => is_array($value) ? ($value[0] ?? '') : $value)->all(),
            payload: $request->all(),
            rawBody: $request->getContent(),
        );

        return response()->json(['received' => true], 202);
    }
}
